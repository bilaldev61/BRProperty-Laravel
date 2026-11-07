<?php

namespace App\Services\AutoReply;

/**
 * Checks an AI-written email against the data it was given, before a client
 * ever sees it.
 *
 * The model is told to use only the facts it is handed, but a prompt is a
 * request, not a guarantee. This is the guarantee: every figure a client could
 * act on - a price, a size, a room count, a phone number, an email address, a
 * link - is pulled out of the reply and must appear in the grounding text (the
 * enquiry, the listing records and every tool result). One unverifiable claim
 * and the whole reply is refused; the client gets the template email instead.
 *
 * It errs towards refusing. A good reply thrown away costs nothing - the
 * template still goes out. A wrong price in the agent's name costs a client.
 */
class ReplyCheck
{
    private const MIN_LENGTH = 80;

    private const MAX_LENGTH = 3000;

    /** Amounts: "AED 625,000", "$625,000", "$1.2 million", "$450k", "PKR 4.25 Crore", "Rs. 85 lakh", "42,500,000 rupees". */
    private const MONEY = '/(?:\b(?:pkr|rs|aed)\.?\s*)(\d[\d,]*(?:\.\d+)?)(?:\s*(crore|lakh|million))?|\$\s*(\d[\d,]*(?:\.\d+)?)(?:\s*(k|thousand|million))?|\b(\d[\d,]*(?:\.\d+)?)\s*(crore|lakh|million|thousand|k)\b/i';

    private const MULTIPLIERS = ['crore' => 10_000_000, 'lakh' => 100_000, 'million' => 1_000_000, 'thousand' => 1_000, 'k' => 1_000];

    /** @param list<string> $allowedPhones $allowedEmails $allowedUrlPrefixes */
    public function __construct(
        private readonly array $allowedPhones,
        private readonly array $allowedEmails,
        private readonly array $allowedUrlPrefixes,
    ) {}

    /** @return string|null why the reply fails, or null when it passes */
    public function problem(string $reply, string $grounding): ?string
    {
        $length = mb_strlen(trim($reply));
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return "length {$length} is outside " . self::MIN_LENGTH . '-' . self::MAX_LENGTH;
        }

        if (preg_match('/\[[^\]]*\]|\{\{|\}\}|<[a-z\/][^>]*>/i', $reply)) {
            return 'contains a placeholder, template syntax or HTML';
        }

        return $this->unsupportedMoney($reply, $grounding)
            ?? $this->unsupportedMeasure($reply, $grounding)
            ?? $this->unsupportedRooms($reply, $grounding)
            ?? $this->unknownContact($reply)
            ?? $this->unknownUrl($reply);
    }

    private function unsupportedMoney(string $reply, string $grounding): ?string
    {
        $known = $this->amounts($grounding);

        foreach ($this->amounts($reply) as $raw => $value) {
            $matches = array_filter($known, fn($k) => abs($k - $value) <= max(1, $value * 0.005));

            if ($matches === []) {
                return "price \"{$raw}\" is not in the records";
            }
        }

        return null;
    }

    /** @return array<string, float> mention as written => rupees */
    private function amounts(string $text): array
    {
        // "$300-400K" / "PKR 3-5 Crore" is two amounts sharing one unit;
        // read alone, the "3" would be three rupees.
        $text = preg_replace(
            '/(\d+(?:\.\d+)?)\s*(?:-|–|—|to)\s*(\d+(?:\.\d+)?)\s*(crore|lakh|million|thousand|k)\b/iu',
            '$1 $3 - $2 $3',
            $text,
        );

        preg_match_all(self::MONEY, $text, $found, PREG_SET_ORDER);

        $amounts = [];
        foreach ($found as $m) {
            $numbers = array_values(array_filter([$m[1] ?? '', $m[3] ?? '', $m[5] ?? ''], 'strlen'));
            $units = array_values(array_filter([$m[2] ?? '', $m[4] ?? '', $m[6] ?? ''], 'strlen'));
            $number = (float) str_replace(',', '', $numbers[0] ?? '0');
            $unit = strtolower($units[0] ?? '');
            $amounts[trim($m[0])] = $number * (self::MULTIPLIERS[$unit] ?? 1);
        }

        return $amounts;
    }

    /** Land and covered area: "10 Marla", "1 Kanal", "4,200 sq ft". */
    private function unsupportedMeasure(string $reply, string $grounding): ?string
    {
        $pattern = '/(\d[\d,]*(?:\.\d+)?)\s*(marla|kanal|sq\.?\s?ft|square\s+feet)\b/i';
        $known = $this->measures($pattern, $grounding);

        foreach ($this->measures($pattern, $reply) as $raw => $key) {
            if (! in_array($key, $known, true)) {
                return "size \"{$raw}\" is not in the records";
            }
        }

        return null;
    }

    /** @return array<string, string> mention => normalised "10|marla" */
    private function measures(string $pattern, string $text): array
    {
        preg_match_all($pattern, $text, $found, PREG_SET_ORDER);

        $out = [];
        foreach ($found as $m) {
            $unit = strtolower(preg_replace('/[^a-z]/i', '', $m[2]));
            $unit = $unit === 'squarefeet' ? 'sqft' : $unit;
            $out[trim($m[0])] = ((float) str_replace(',', '', $m[1])) . '|' . $unit;
        }

        return $out;
    }

    /** "4 bedrooms", "3-bath": the count must be one the records give. */
    private function unsupportedRooms(string $reply, string $grounding): ?string
    {
        foreach (['bed', 'bath'] as $room) {
            preg_match_all('/\b(\d+)[\s-]*' . $room . '(?:room)?s?\b/i', $reply, $claimed);

            if ($claimed[1] === []) {
                continue;
            }

            // The records say it either way round: "Bedrooms: 4." or "bedrooms":4.
            preg_match_all('/' . $room . '(?:room)?s?\W{0,4}(\d+)|\b(\d+)[\s-]*' . $room . '/i', $grounding, $known);
            $knownCounts = array_filter(array_merge($known[1], $known[2]), 'strlen');

            foreach ($claimed[1] as $count) {
                if (! in_array($count, $knownCounts, true)) {
                    return "{$count} {$room}rooms is not in the records";
                }
            }
        }

        return null;
    }

    private function unknownContact(string $reply): ?string
    {
        preg_match_all('/\+?\d[\d\s()-]{6,}\d/', $reply, $phones);
        $allowed = array_map(fn($p) => substr(preg_replace('/\D/', '', $p), -9), $this->allowedPhones);

        foreach ($phones[0] as $phone) {
            // Long digit runs that are prices ("42,500,000") are checked above.
            if (preg_match('/^\d{1,3}(,\d{2,3})+$/', trim($phone))) {
                continue;
            }
            if (! in_array(substr(preg_replace('/\D/', '', $phone), -9), $allowed, true)) {
                return "phone number \"{$phone}\" is not the agent's or the client's";
            }
        }

        preg_match_all('/[\w.+-]+@[\w-]+\.[\w.-]+/', $reply, $emails);
        $allowedEmails = array_map('strtolower', $this->allowedEmails);

        foreach ($emails[0] as $email) {
            if (! in_array(strtolower(rtrim($email, '.')), $allowedEmails, true)) {
                return "email address \"{$email}\" is not the agent's or the client's";
            }
        }

        return null;
    }

    private function unknownUrl(string $reply): ?string
    {
        preg_match_all('/\b(?:https?:\/\/|www\.)[^\s)>,]+/i', $reply, $urls);

        foreach ($urls[0] as $url) {
            $url = rtrim($url, '.');
            $ok = array_filter($this->allowedUrlPrefixes, fn($prefix) => str_starts_with($url, $prefix));

            if ($ok === []) {
                return "link \"{$url}\" is not one of ours";
            }
        }

        return null;
    }
}
