<?php

namespace Tests\Unit;

use App\Services\AutoReply\ReplyCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReplyCheckTest extends TestCase
{
    private const GROUNDING = <<<'TXT'
    - Their name: Ayesha Khan
    - Price as advertised: PKR 3.25 Crore
    - Bedrooms: 4. Bathrooms: 3. Car spaces: 2.
    - Land size: 10 Marla
    - Covered area: 2,250 sq ft
    - Listing url: https://estate.brealstate.example/properties/10-marla-house
    {"properties":[{"title":"1 Kanal House","price":"PKR 6.5 Crore","bedrooms":5,"land":"1 Kanal"}]}
    TXT;

    private function check(): ReplyCheck
    {
        return new ReplyCheck(
            allowedPhones: ['+92 322 728 9296', '0300 1234567'],
            allowedEmails: ['agent@example.com', 'ayesha@example.com'],
            allowedUrlPrefixes: ['https://estate.brealstate.example/', 'https://wa.me/923227289296'],
        );
    }

    private static function letter(string $body): string
    {
        return "Dear Ayesha,\n\n{$body}\n\nRegards,\nBilal Raza";
    }

    public function test_a_reply_that_only_uses_the_records_passes(): void
    {
        $reply = self::letter(
            "Thank you for asking about the house. It is advertised at PKR 3.25 Crore, sits on 10 Marla with "
                . "2,250 sq ft covered, and has 4 bedrooms. I also have a 1 Kanal house with 5 bedrooms at PKR 6.5 Crore:\n"
                . "https://estate.brealstate.example/properties/10-marla-house\nI will call you on 0300 1234567."
        );

        $this->assertNull($this->check()->problem($reply, self::GROUNDING));
    }

    /** @return array<string, array{0: string, 1: string}> reply body, expected reason */
    public static function inventions(): array
    {
        return [
            'invented price'         => ['The asking price is PKR 3 Crore, which is a fair deal for the area.', 'price'],
            'invented price in lakh' => ['I could do 310 lakh for you if you move quickly on this one today.', 'price'],
            'rupees written out'     => ['It is listed at Rs 30,000,000 and the owner is keen to sell quickly.', 'price'],
            'invented size'          => ['The plot is 12 Marla, which is generous for this block of the society.', 'size'],
            'invented covered area'  => ['The covered area is 3,000 sq ft over two floors with a separate kitchen.', 'size'],
            'invented bedrooms'      => ['It has 6 bedrooms, so there is plenty of space for a large family here.', 'bed'],
            'unknown phone number'   => ['Please call my colleague on 0321 9998887 who handles this listing for me.', 'phone'],
            'unknown email'          => ['Please send your documents to deals@other-agency.com so we can start.', 'email'],
            'outside link'           => ['You can compare prices here: https://some-portal.example/listing/123 today.', 'link'],
            'placeholder left in'    => ['Thank you [NAME], the property is available and I would love to show you.', 'placeholder'],
        ];
    }

    #[DataProvider('inventions')]
    public function test_anything_not_in_the_records_is_refused(string $body, string $reason): void
    {
        $problem = $this->check()->problem(self::letter($body), self::GROUNDING);

        $this->assertNotNull($problem);
        $this->assertStringContainsString($reason, $problem);
    }

    public function test_a_reply_that_is_too_short_or_too_long_is_refused(): void
    {
        $this->assertNotNull($this->check()->problem('Thanks!', self::GROUNDING));
        $this->assertNotNull($this->check()->problem(self::letter(str_repeat('Lovely house. ', 300)), self::GROUNDING));
    }

    public function test_a_price_range_reads_as_two_amounts_in_that_unit(): void
    {
        $grounding = self::GROUNDING . "\nSearched budget: PKR 30000000\nSearched budget: PKR 50000000";

        foreach (['PKR 3–5 Crore', 'PKR 3-5 crore', '3 to 5 crore'] as $range) {
            $reply = self::letter("I searched Bahria Town for houses in a similar price range ({$range}) and nothing matches right now.");
            $this->assertNull($this->check()->problem($reply, $grounding), $range);
        }

        // A range nobody searched is still an invention.
        $reply = self::letter('I searched Bahria Town for houses in a similar price range (PKR 7–9 Crore) and found nothing.');
        $this->assertStringContainsString('price', (string) $this->check()->problem($reply, $grounding));
    }

    public function test_the_agents_whatsapp_link_is_allowed(): void
    {
        $reply = self::letter('Thank you for your enquiry about the house. You can also message me here: https://wa.me/923227289296 at any time.');

        $this->assertNull($this->check()->problem($reply, self::GROUNDING));
    }
}
