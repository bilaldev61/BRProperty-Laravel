<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;

/**
 * The market facts behind the demo listings: what is next door, what the
 * building costs to run, and the identifiers a buyer asks for before agreeing
 * to a viewing.
 *
 * Kept apart from PropertySeeder so it can be re-run on its own - the listings
 * themselves are created there, this only fills detail onto them by slug.
 */
class PropertyMarketFactsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->facts() as $slug => $values) {
            Property::where('slug', $slug)->update($values);
        }
    }

    /**
     * Landmarks are listed in the order a buyer asks about them: how they get
     * to work, where they shop, where the children go to school, then the
     * hospital and the airport. Distances are the ones quoted on the portals -
     * walk time inside walking distance, drive time beyond it.
     */
    private function facts(): array
    {
        return [
            'signature-palm-villa-with-a-private-beach-frontage' => [
                'nearby' => [
                    ['type' => 'beach', 'name' => 'Private beach frontage', 'distance' => 'on the plot'],
                    ['type' => 'metro', 'name' => 'Palm Monorail, Atlantis Aquaventure station', 'distance' => '4 min drive'],
                    ['type' => 'mall', 'name' => 'Nakheel Mall', 'distance' => '6 min drive'],
                    ['type' => 'mall', 'name' => 'Mall of the Emirates', 'distance' => '18 min drive'],
                    ['type' => 'school', 'name' => 'GEMS Wellington Academy, Al Sufouh', 'distance' => '15 min drive'],
                    ['type' => 'hospital', 'name' => 'Al Zahra Hospital Dubai', 'distance' => '16 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '28 min drive'],
                ],
                'view_type' => 'Atlantis and sunset over the Gulf',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 24,
                'payment_terms' => '5% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7145863101',
            ],

            'downtown-2-bed-with-full-burj-khalifa-outlook' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'Burj Khalifa / Dubai Mall Metro (Red Line)', 'distance' => '450 m walk'],
                    ['type' => 'mall', 'name' => 'The Dubai Mall', 'distance' => '8 min walk'],
                    ['type' => 'park', 'name' => 'Burj Park', 'distance' => '6 min walk'],
                    ['type' => 'school', 'name' => 'Dubai British School, Jumeirah', 'distance' => '15 min drive'],
                    ['type' => 'hospital', 'name' => 'Mediclinic, The Dubai Mall', 'distance' => '7 min walk'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '20 min drive'],
                ],
                'view_type' => 'Burj Khalifa and the Dubai Fountain',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 22,
                'payment_terms' => '5% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7145932740',
            ],

            'canal-facing-1-bed-in-business-bay-chiller-free' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'Business Bay Metro (Red Line)', 'distance' => '900 m walk'],
                    ['type' => 'mall', 'name' => 'The Dubai Mall', 'distance' => '10 min drive'],
                    ['type' => 'park', 'name' => 'Bay Avenue Park', 'distance' => '5 min walk'],
                    ['type' => 'school', 'name' => 'GEMS Our Own English High School', 'distance' => '12 min drive'],
                    ['type' => 'hospital', 'name' => 'Saudi German Hospital, Al Barsha', 'distance' => '18 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '22 min drive'],
                ],
                'view_type' => 'Dubai Water Canal',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 18,
                'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7146021836',
            ],

            'marina-3-bed-with-a-corner-terrace-and-marina-berth-dubai-marina' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'DMCC Metro (Red Line)', 'distance' => '600 m walk'],
                    ['type' => 'beach', 'name' => 'JBR public beach', 'distance' => '8 min walk'],
                    ['type' => 'mall', 'name' => 'Marina Mall', 'distance' => '6 min walk'],
                    ['type' => 'golf', 'name' => 'Emirates Golf Club', 'distance' => '8 min drive'],
                    ['type' => 'school', 'name' => 'GEMS Wellington International School', 'distance' => '12 min drive'],
                    ['type' => 'hospital', 'name' => 'Emirates Hospital, Jumeirah Beach Road', 'distance' => '10 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '30 min drive'],
                ],
                'view_type' => 'Marina skyline and the open sea',
                'furnishing' => 'semi_furnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 19,
                'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7145758322',
            ],

            'dubai-hills-family-villa-backing-onto-the-golf-course' => [
                'nearby' => [
                    ['type' => 'golf', 'name' => 'Dubai Hills Golf Club, 9th fairway', 'distance' => 'on the doorstep'],
                    ['type' => 'mall', 'name' => 'Dubai Hills Mall', 'distance' => '6 min drive'],
                    ['type' => 'school', 'name' => 'GEMS International School, Al Khail', 'distance' => '7 min drive'],
                    ['type' => 'hospital', 'name' => "King's College Hospital, Dubai Hills", 'distance' => '8 min drive'],
                    ['type' => 'park', 'name' => 'Dubai Hills Park', 'distance' => '5 min walk'],
                    ['type' => 'metro', 'name' => 'Equiti Metro (Red Line)', 'distance' => '15 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '22 min drive'],
                ],
                'view_type' => '9th fairway of Dubai Hills Golf Club',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 12,
                'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7146119490',
            ],

            'jvc-studio-tenanted-walk-to-circle-mall-jumeirah-village-circle' => [
                'nearby' => [
                    ['type' => 'mall', 'name' => 'Circle Mall', 'distance' => '6 min walk'],
                    ['type' => 'hospital', 'name' => 'Aster Clinic, JVC', 'distance' => '5 min walk'],
                    ['type' => 'park', 'name' => 'JVC Community Park', 'distance' => '7 min walk'],
                    ['type' => 'school', 'name' => 'JSS International School', 'distance' => '8 min drive'],
                    ['type' => 'metro', 'name' => 'Dubai Internet City Metro (Red Line)', 'distance' => '15 min drive'],
                    ['type' => 'airport', 'name' => 'Al Maktoum International (DWC)', 'distance' => '30 min drive'],
                ],
                'view_type' => 'Community courtyard',
                'furnishing' => 'furnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 14,
                'payment_terms' => '5% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7145630214',
            ],

            'arabian-ranches-townhouse-with-a-private-courtyard' => [
                'nearby' => [
                    ['type' => 'school', 'name' => 'Ranches Primary School', 'distance' => '4 min drive'],
                    ['type' => 'school', 'name' => 'Jumeirah English Speaking School (JESS)', 'distance' => '5 min drive'],
                    ['type' => 'mall', 'name' => 'Arabian Ranches Retail Centre', 'distance' => '6 min drive'],
                    ['type' => 'golf', 'name' => 'Arabian Ranches Golf Club', 'distance' => '7 min drive'],
                    ['type' => 'mall', 'name' => 'Dubai Hills Mall', 'distance' => '12 min drive'],
                    ['type' => 'metro', 'name' => 'Mall of the Emirates Metro (Red Line)', 'distance' => '20 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '25 min drive'],
                ],
                'view_type' => 'Private courtyard and community lake',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 6,
                'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7145884603',
            ],

            'creek-harbour-2-bed-overlooking-the-marina-promenade-dubai-creek-harbour' => [
                'nearby' => [
                    ['type' => 'park', 'name' => 'Creek Harbour Central Park', 'distance' => '4 min walk'],
                    ['type' => 'park', 'name' => 'Ras Al Khor Wildlife Sanctuary', 'distance' => '8 min drive'],
                    ['type' => 'mall', 'name' => 'Dubai Festival City Mall', 'distance' => '10 min drive'],
                    ['type' => 'school', 'name' => 'Dubai British School, Dubai Festival City', 'distance' => '12 min drive'],
                    ['type' => 'hospital', 'name' => 'Mediclinic Parkview Hospital', 'distance' => '15 min drive'],
                    ['type' => 'metro', 'name' => 'Creek Metro (Green Line)', 'distance' => '12 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '12 min drive'],
                ],
                'view_type' => 'Creek marina and the promenade',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 18,
                'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7146093771',
            ],

            'sobha-hartland-3-bed-with-a-study-high-floor' => [
                'nearby' => [
                    ['type' => 'school', 'name' => 'Hartland International School', 'distance' => '4 min walk'],
                    ['type' => 'park', 'name' => 'Sobha Hartland Central Park', 'distance' => '5 min walk'],
                    ['type' => 'mall', 'name' => 'The Dubai Mall', 'distance' => '12 min drive'],
                    ['type' => 'hospital', 'name' => 'Mediclinic Al Khail', 'distance' => '11 min drive'],
                    ['type' => 'metro', 'name' => 'Business Bay Metro (Red Line)', 'distance' => '12 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '18 min drive'],
                ],
                'view_type' => 'Dubai Water Canal and the skyline',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 16,
                'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7146177402',
            ],

            'under-offer-business-bay-2-bed-with-dubai-canal-frontage' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'Business Bay Metro (Red Line)', 'distance' => '750 m walk'],
                    ['type' => 'mall', 'name' => 'The Dubai Mall', 'distance' => '9 min drive'],
                    ['type' => 'park', 'name' => 'Bay Avenue Park', 'distance' => '6 min walk'],
                    ['type' => 'school', 'name' => 'GEMS Our Own English High School', 'distance' => '12 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '22 min drive'],
                ],
                'view_type' => 'Dubai Water Canal',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 17,
                'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7145910478',
            ],

            'sold-dubai-marina-2-bed-with-sea-view' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'DMCC Metro (Red Line)', 'distance' => '700 m walk'],
                    ['type' => 'mall', 'name' => 'Marina Mall', 'distance' => '8 min walk'],
                    ['type' => 'beach', 'name' => 'JBR public beach', 'distance' => '10 min walk'],
                    ['type' => 'school', 'name' => 'GEMS Wellington International School', 'distance' => '13 min drive'],
                ],
                'view_type' => 'Open sea and Palm Jumeirah',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 19,
                'payment_terms' => null,
                'permit_number' => '7145422019',
            ],

            'sold-downtown-1-bed-at-burj-views' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'Burj Khalifa / Dubai Mall Metro (Red Line)', 'distance' => '500 m walk'],
                    ['type' => 'mall', 'name' => 'The Dubai Mall', 'distance' => '9 min walk'],
                    ['type' => 'park', 'name' => 'Burj Park', 'distance' => '7 min walk'],
                ],
                'view_type' => 'Burj Khalifa',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 21,
                'payment_terms' => null,
                'permit_number' => '7145388440',
            ],

            'sold-the-springs-3-bed-townhouse-lake-side' => [
                'nearby' => [
                    ['type' => 'mall', 'name' => 'The Springs Town Centre', 'distance' => '4 min drive'],
                    ['type' => 'school', 'name' => 'Emirates International School, Meadows', 'distance' => '6 min drive'],
                    ['type' => 'golf', 'name' => 'Emirates Golf Club', 'distance' => '10 min drive'],
                    ['type' => 'mall', 'name' => 'Mall of the Emirates', 'distance' => '15 min drive'],
                    ['type' => 'metro', 'name' => 'Mall of the Emirates Metro (Red Line)', 'distance' => '16 min drive'],
                ],
                'view_type' => 'Community lake',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 5,
                'payment_terms' => null,
                'permit_number' => '7145276761',
            ],

            'sold-mirdif-villa-with-a-large-garden' => [
                'nearby' => [
                    ['type' => 'mall', 'name' => 'Mirdif City Centre', 'distance' => '5 min drive'],
                    ['type' => 'school', 'name' => 'Star International School, Mirdif', 'distance' => '7 min drive'],
                    ['type' => 'hospital', 'name' => 'Aster Hospital, Mirdif', 'distance' => '8 min drive'],
                    ['type' => 'park', 'name' => 'Mushrif Park', 'distance' => '9 min drive'],
                    ['type' => 'metro', 'name' => 'Rashidiya Metro (Red Line)', 'distance' => '12 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '14 min drive'],
                ],
                'view_type' => 'Garden and courtyard',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 4,
                'payment_terms' => null,
                'permit_number' => '7145109823',
            ],

            'al-barsha-2-bed-fully-furnished-and-ready-to-move-in' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'Mashreq Metro (Red Line)', 'distance' => '7 min walk'],
                    ['type' => 'mall', 'name' => 'Mall of the Emirates', 'distance' => '10 min walk'],
                    ['type' => 'hospital', 'name' => 'Mediclinic Al Barsha', 'distance' => '5 min drive'],
                    ['type' => 'school', 'name' => 'GEMS Al Barsha National School', 'distance' => '6 min drive'],
                    ['type' => 'beach', 'name' => 'Umm Suqeim beach', 'distance' => '12 min drive'],
                ],
                'view_type' => 'Community and street view',
                'furnishing' => 'furnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 15,
                'payment_terms' => '4 cheques · 5% deposit · 5% agency fee',
                'permit_number' => '7145993725',
            ],

            'jlt-1-bed-high-floor-available-immediately-jumeirah-lake-towers' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'DAMAC Metro (Red Line)', 'distance' => '5 min walk'],
                    ['type' => 'park', 'name' => 'JLT community parks', 'distance' => '4 min walk'],
                    ['type' => 'mall', 'name' => 'Marina Mall', 'distance' => '10 min drive'],
                    ['type' => 'school', 'name' => 'Emirates International School, Meadows', 'distance' => '12 min drive'],
                    ['type' => 'beach', 'name' => 'JBR public beach', 'distance' => '15 min drive'],
                ],
                'view_type' => 'Lake and cluster view',
                'furnishing' => 'semi_furnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 17,
                'payment_terms' => '4 cheques · 5% deposit · 5% agency fee',
                'permit_number' => '7146041226',
            ],

            'jvc-1-bed-with-a-balcony-pet-friendly-building-jumeirah-village-circle' => [
                'nearby' => [
                    ['type' => 'mall', 'name' => 'Circle Mall', 'distance' => '8 min walk'],
                    ['type' => 'hospital', 'name' => 'Aster Clinic, JVC', 'distance' => '6 min walk'],
                    ['type' => 'park', 'name' => 'JVC Community Park', 'distance' => '6 min walk'],
                    ['type' => 'school', 'name' => 'JSS International School', 'distance' => '9 min drive'],
                    ['type' => 'metro', 'name' => 'Dubai Internet City Metro (Red Line)', 'distance' => '15 min drive'],
                ],
                'view_type' => 'Community and courtyard',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 14,
                'payment_terms' => '4 cheques · 5% deposit · 5% agency fee',
                'permit_number' => '7145708992',
            ],

            'mbr-city-4-bed-villa-with-a-private-pool-mohammed-bin-rashid-city' => [
                'nearby' => [
                    ['type' => 'golf', 'name' => 'The Track, Meydan Golf', 'distance' => '9 min drive'],
                    ['type' => 'school', 'name' => 'GEMS Wellington Academy, Al Khail', 'distance' => '10 min drive'],
                    ['type' => 'mall', 'name' => 'Dubai Hills Mall', 'distance' => '12 min drive'],
                    ['type' => 'park', 'name' => 'Meydan Grandstand park', 'distance' => '8 min drive'],
                    ['type' => 'metro', 'name' => 'Business Bay Metro (Red Line)', 'distance' => '15 min drive'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '20 min drive'],
                ],
                'view_type' => 'Pool and Meydan skyline',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 8,
                'payment_terms' => '1 cheque · 5% deposit · 5% agency fee',
                'permit_number' => '7146166338',
            ],

            'jumeirah-3-bed-two-minutes-from-the-beach' => [
                'nearby' => [
                    ['type' => 'beach', 'name' => 'Jumeirah public beach', 'distance' => '2 min walk'],
                    ['type' => 'park', 'name' => 'Jumeirah Beach Park', 'distance' => '4 min walk'],
                    ['type' => 'mall', 'name' => 'Mercato Mall', 'distance' => '6 min drive'],
                    ['type' => 'hospital', 'name' => 'Emirates Hospital, Jumeirah', 'distance' => '7 min drive'],
                    ['type' => 'school', 'name' => 'Jumeirah Primary School', 'distance' => '8 min drive'],
                    ['type' => 'metro', 'name' => 'World Trade Centre Metro (Red Line)', 'distance' => '12 min drive'],
                ],
                'view_type' => 'Sea view from the upper floor',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 10,
                'payment_terms' => '1 cheque · 5% deposit · 5% agency fee',
                'permit_number' => '7145820147',
            ],

            'canal-studio-in-business-bay-sale-or-let' => [
                'nearby' => [
                    ['type' => 'metro', 'name' => 'Business Bay Metro (Red Line)', 'distance' => '850 m walk'],
                    ['type' => 'mall', 'name' => 'The Dubai Mall', 'distance' => '10 min drive'],
                    ['type' => 'park', 'name' => 'Bay Avenue Park', 'distance' => '5 min walk'],
                    ['type' => 'airport', 'name' => 'DXB Terminal 3', 'distance' => '22 min drive'],
                ],
                'view_type' => 'Dubai Water Canal',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 18,
                'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
                'permit_number' => '7145971057',
            ],

            'two-bed-in-the-greens-currently-leased' => [
                'nearby' => [
                    ['type' => 'school', 'name' => 'Regent International School', 'distance' => '5 min walk'],
                    ['type' => 'mall', 'name' => 'The Greens Village Mall', 'distance' => '6 min walk'],
                    ['type' => 'metro', 'name' => 'Dubai Internet City Metro (Red Line)', 'distance' => '8 min drive'],
                    ['type' => 'beach', 'name' => 'JBR public beach', 'distance' => '15 min drive'],
                ],
                'view_type' => 'Community and garden view',
                'furnishing' => 'unfurnished',
                'completion' => 'ready',
                'handover' => null,
                'ownership' => 'freehold',
                'service_charge' => 15,
                'payment_terms' => '4 cheques · 5% deposit · 5% agency fee',
                'permit_number' => '7145664310',
            ],
        ];
    }
}
