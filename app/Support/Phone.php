<?php

namespace App\Support;

/**
 * Phone numbers in one comparable form, so a caller is recognised whatever
 * way their number was typed or reported.
 *
 *   "512-234-5678"      -> +15122345678   (local US)
 *   "(512) 234 5678"    -> +15122345678
 *   "1 512 234 5678"    -> +15122345678
 *   "+44 20 7123 4567"  -> +442071234567  (already international)
 *
 * US / North American numbers written locally (ten digits, or a leading 1 or
 * 0) gain +1; anything already international keeps its own country code.
 */
class Phone
{
    public const LOCAL_COUNTRY_CODE = '1';

    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $international = str_starts_with(trim($phone), '+') || str_starts_with(trim($phone), '00');
        $digits = preg_replace('/\D/', '', $phone);
        $digits = str_starts_with(trim($phone), '00') ? substr($digits, 2) : $digits;

        if (strlen($digits) < 7) {
            return null;
        }

        if (! $international) {
            // Drop a trunk-prefix 0 ("0 512 ..." is not a US form but shows up
            // from copied contacts), then add the country code to a bare number.
            $digits = ltrim($digits, '0');

            if (strlen($digits) === 10) {
                $digits = self::LOCAL_COUNTRY_CODE . $digits; // "512-234-5678"
            } elseif (! str_starts_with($digits, self::LOCAL_COUNTRY_CODE) && strlen($digits) === 11) {
                $digits = self::LOCAL_COUNTRY_CODE . $digits; // defensive: missing leading 1
            }
        }

        return '+' . $digits;
    }
}
