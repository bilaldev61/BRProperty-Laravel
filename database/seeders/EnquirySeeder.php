<?php

namespace Database\Seeders;

use App\Models\Enquiry;
use App\Models\Property;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Demo enquiry history. Spread across the last twelve weeks so the dashboard
 * charts have a realistic shape rather than three lonely rows.
 */
class EnquirySeeder extends Seeder
{
    public function run(): void
    {
        if (Enquiry::exists()) {
            return;
        }

        $names = [
            'Tyler Bennett',
            'Madison Cole',
            'Brandon Hayes',
            'Aaliyah Brooks',
            'Caleb Morgan',
            'Hannah Rivera',
            'Marcus Reed',
            'Sofia Ramirez',
            'Nathan Cooper',
            'Chloe Jenkins',
            'Derek Malone',
            'Isabel Torres',
            'Kyle Sanders',
            'Nina Patel',
            'Aaron Blake',
            'Jasmine Hughes',
            'Wesley Grant',
            'Shelby Adams',
            'Devon Marshall',
            'Maya Sullivan',
            'Ryan Whitaker',
            'Avery Duncan',
            'Omar Farouk',
            'Leah Fontaine',
            'Trent Crawford',
        ];

        $propertyMessages = [
            'Is the car park space included in the advertised size?',
            'Are all service charges current on this unit, and what is the chiller situation?',
            'Can we arrange a viewing this Saturday afternoon?',
            'Is the price negotiable, and has the title deed been issued?',
            'Is the building chiller free, or is that billed separately?',
            'How old is the building, and how much snagging is still open?',
            'Is the property vacant on transfer or is there a tenant in place?',
            'What is the exact built-up area, and does it include the balcony?',
        ];

        $appraisalMessages = [
            'Thinking of selling in the next few months, would like a realistic number.',
            'Need a valuation for a bank refinance, no rush to sell.',
            'What would my villa fetch in the current market?',
            'Considering selling and moving to a bigger community. What is mine worth?',
            null,
        ];

        $contactMessages = [
            'Do you handle commercial property as well, or residential only?',
            'What is your commission on a sale?',
            'Do you manage short lets as well as annual rentals?',
            'Can you help with a transfer where the seller is outside the UAE?',
            'Looking for a 3-bedroom in Dubai Hills under AED 3M. Anything coming up?',
        ];

        $areas = [
            'Business Bay',
            'Downtown Dubai',
            'Dubai Marina',
            'Jumeirah Lake Towers',
            'Arabian Ranches',
            'Dubai Hills Estate',
            'Jumeirah Village Circle',
        ];
        $timeframes = ['ASAP', '1-3 months', '3-6 months', '6-12 months', 'Just researching'];

        $listings = Property::published()->forSale()->pluck('id')->all();
        $featured = Property::published()->forSale()->where('is_featured', true)->pluck('id')->all();

        $rows = [];
        $seed = 20260829;
        mt_srand($seed);

        // Twelve weeks of history, busier in recent weeks and quieter mid-week.
        for ($week = 11; $week >= 0; $week--) {
            $volume = match (true) {
                $week >= 9 => mt_rand(2, 4),
                $week >= 5 => mt_rand(3, 6),
                default    => mt_rand(4, 8),
            };

            for ($i = 0; $i < $volume; $i++) {
                $when = now()
                    ->subWeeks($week)
                    ->startOfWeek()
                    ->addDays(mt_rand(0, 6))
                    ->setTime(mt_rand(9, 21), Arr::random([0, 12, 24, 36, 48]));

                if ($when->isFuture()) {
                    continue;
                }

                $type = Arr::random(['property', 'property', 'property', 'appraisal', 'contact']);
                $name = Arr::random($names);
                $slug = strtolower(str_replace(' ', '.', $name));

                $read = $week >= 2 ? $when->copy()->addHours(mt_rand(1, 20)) : (mt_rand(0, 2) ? $when->copy()->addHours(mt_rand(1, 20)) : null);

                $row = [
                    'type'       => $type,
                    'name'       => $name,
                    'email'      => $slug . '@example.com',
                    'phone'      => mt_rand(0, 4) ? '+971 50 ' . mt_rand(200, 999) . ' ' . mt_rand(1000, 9999) : null,
                    // Older enquiries have been dealt with; the last fortnight has unread ones.
                    'read_at'    => $read,
                    // Anything older than a fortnight has run its course. Recent
                    // ones are left mid-flight so the queue has real work in it.
                    'status'     => $week >= 2
                        ? Arr::random([Enquiry::STATUS_REPLIED, Enquiry::STATUS_REPLIED, Enquiry::STATUS_CLOSED])
                        : ($read ? Enquiry::STATUS_IN_PROGRESS : Enquiry::STATUS_NEW),
                    'created_at' => $when,
                    'updated_at' => $when,
                ];

                // A handful of open ones carry a follow-up date, some already past.
                if ($week < 3 && mt_rand(0, 2) === 0) {
                    $row['follow_up_at'] = $when->copy()->addDays(mt_rand(2, 10));
                }

                if ($type === 'property') {
                    // Featured listings pull more interest, which is what the chart should show.
                    $row['property_id'] = mt_rand(0, 1) && $featured
                        ? Arr::random($featured)
                        : Arr::random($listings);
                    $row['message'] = Arr::random($propertyMessages);
                } elseif ($type === 'appraisal') {
                    $row['message'] = Arr::random($appraisalMessages);
                    $row['details'] = [
                        'address'       => 'Villa ' . mt_rand(1, 40) . ' ' . Arr::random(['Al Reem', 'Mira', 'Al Barsha South', 'Jumeirah Park', 'Meadows 9', 'Springs 12', 'Al Furjan']),
                        'suburb'        => Arr::random($areas),
                        'property_type' => Arr::random(['house', 'house', 'apartment', 'land']),
                        'bedrooms'      => Arr::random([3, 3, 4, 4, 5]),
                        'timeframe'     => Arr::random($timeframes),
                    ];
                } else {
                    $row['message'] = Arr::random($contactMessages);
                }

                $rows[] = $row;
            }
        }

        foreach ($rows as $row) {
            Enquiry::create($row);
        }
    }
}
