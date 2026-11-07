<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->properties() as $index => $data) {
            $images = $data['images'];
            unset($data['images']);

            // Keyed on address, not slug, so the model's own slug rule runs.
            $property = Property::updateOrCreate(
                ['address' => $data['address']],
                $data,
            );

            $property->images()->delete();

            $sort = 0;

            foreach ($images as $path => $shot) {
                $property->images()->create([
                    'path'       => $path,
                    'alt'        => $shot . ' of ' . $data['address'] . ', ' . $data['suburb'],
                    'sort_order' => $sort++,
                ]);
            }
        }
    }

    /**
     * Demo listings. Replace these with real ones through /admin.
     *
     * Photos are CC0 (public domain) stock sourced via Openverse - see
     * public/images/properties/CREDITS.md. They are illustrative only and are
     * not photographs of the addresses named below, which are also invented.
     */
    private function properties(): array
    {
        return [
            [
                'title' => 'Signature Palm villa with a private beach frontage',
                'status' => 'for_sale',
                'type' => 'house',
                'price' => 14500000,
                'price_label' => null,
                'address' => 'Villa 27, Frond K, Palm Jumeirah',
                'suburb' => 'Palm Jumeirah',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1124,
                'longitude' => 55.1390,
                'bedrooms' => 5,
                'bathrooms' => 5,
                'carspaces' => 3,
                'land_size' => 8600,
                'floor_size' => 7100,
                'is_featured' => true,
                'inspection_times' => 'Friday and Saturday, 11:00am - 5:00pm, or call for a private viewing',
                'features' => ['Direct beach access from the garden', 'Full Atlantis and sunset view', 'Refurbished kitchen in 2024', 'Staff quarters with separate entrance', 'Temperature-controlled pool', 'Title deed ready for immediate transfer'],
                'description' => 'A frond villa with its own stretch of sand - not a shared beach club, a garden that ends at the water. Five bedrooms over two floors, the master taking the whole sea-facing side, and a pool that stays shaded until late afternoon.

The current owner refurbished the kitchen and all five bathrooms in 2024 and kept the receipts and the contractor\'s snagging close-out. Service charges are on the low side for the frond and the 2025 statement goes with the pack.

Title deed is held by the owner in Dubai, so a transfer at the Land Department is straightforward. We can have the Form F issued the same week you offer.',
                'images' => [
                    'images/properties/p01-1.webp' => 'Front exterior',
                    'images/properties/p01-2.webp' => 'Living room',
                    'images/properties/p01-3.webp' => 'Kitchen',
                    'images/properties/p01-4.webp' => 'Garden and pool',
                ],
            ],
            [
                'title' => 'Downtown 2-bed with full Burj Khalifa outlook',
                'status' => 'for_sale',
                'type' => 'apartment',
                'price' => 2350000,
                'price_label' => null,
                'address' => 'Apartment 3402, Burj Views Tower C, Downtown Dubai',
                'suburb' => 'Downtown Dubai',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1926,
                'longitude' => 55.2760,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 1180,
                'is_featured' => true,
                'inspection_times' => 'Saturday 12:00pm - 4:00pm',
                'features' => ['Burj Khalifa and fountain view', 'Vacant on transfer', 'Two covered parking bays', 'Chiller free', 'Concierge and gym on the same floor', 'Walk to Dubai Mall'],
                'description' => 'Thirty-fourth floor, both bedrooms facing the Burj, and the balcony is deep enough to actually sit on rather than stand on. The apartment is empty, so you can view it without an owner\'s furniture hiding the flaws.

Chiller is free, which on a Downtown tower is a real number every month. Service charge statements for the last two years are in the pack, along with the building\'s maintenance schedule.

Dubai Mall is a seven minute walk and the metro is closer than that. For a first Dubai purchase or a rental you actually intend to use, this layout is hard to beat.',
                'images' => [
                    'images/properties/p02-1.webp' => 'Living room',
                    'images/properties/p02-2.webp' => 'Bedroom',
                    'images/properties/p02-3.webp' => 'Kitchen',
                    'images/properties/p02-4.webp' => 'Balcony view',
                ],
            ],
            [
                'title' => 'Canal-facing 1-bed in Business Bay, chiller free',
                'status' => 'for_sale',
                'type' => 'apartment',
                'price' => 1150000,
                'price_label' => null,
                'address' => 'Apartment 1206, Damac Majestine, Business Bay',
                'suburb' => 'Business Bay',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1857,
                'longitude' => 55.2760,
                'bedrooms' => 1,
                'bathrooms' => 2,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 720,
                'is_featured' => true,
                'inspection_times' => 'Sunday to Thursday, 4:00pm - 7:00pm',
                'features' => ['Direct canal view', 'Chiller free', 'Rented at AED 85,000 until March', 'Furnished to a good standard', 'Gym and pool on the podium', 'Five minutes to Business Bay metro'],
                'description' => 'A one-bedroom with a genuine canal view rather than a slice of one between two towers. Tenant in place at AED 85,000 a year until March, paying on time - the statement is in the pack if you are buying as an investment.

The building is a solid Business Bay performer: good occupancy, active facilities management, and none of the handover snagging left open. Chiller is free.

For a first rental investment this is the simplest thing on our books right now. Walking distance to the metro, ten minutes to Downtown by car outside rush hour.',
                'images' => [
                    'images/properties/p03-1.webp' => 'Living room',
                    'images/properties/p03-2.webp' => 'Kitchen',
                    'images/properties/p03-3.webp' => 'Bedroom',
                    'images/properties/p03-4.webp' => 'Canal view',
                ],
            ],
            [
                'title' => 'Marina 3-bed with a corner terrace and marina berth',
                'status' => 'for_sale',
                'type' => 'apartment',
                'price' => 3650000,
                'price_label' => null,
                'address' => 'Apartment 2104, Marina Gate 2, Dubai Marina',
                'suburb' => 'Dubai Marina',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.0805,
                'longitude' => 55.1403,
                'bedrooms' => 3,
                'bathrooms' => 4,
                'carspaces' => 2,
                'land_size' => null,
                'floor_size' => 1920,
                'is_featured' => true,
                'inspection_times' => 'Saturday and Sunday, 12:00pm - 5:00pm',
                'features' => ['Corner unit with three-sided light', 'Wrap-around terrace', 'Optional marina berth', 'Two parking bays', 'Private lift lobby', 'Walking distance to JBR beach'],
                'description' => 'A corner unit, which in Dubai Marina is the difference between a view and a sliver. Three bedrooms, a wrap-around terrace off the living room, and light from three sides all day.

The berth can come with it if you keep a boat - the seller pays the transfer, which is not the usual arrangement. Two parking bays, and the private lift lobby means no corridor traffic.

Building service charges are high, as they are across the Marina, but this tower earns it: the facilities are kept properly. Statements are in the pack.',
                'images' => [
                    'images/properties/p04-1.webp' => 'Living room',
                    'images/properties/p04-2.webp' => 'Kitchen',
                    'images/properties/p04-3.webp' => 'Terrace',
                    'images/properties/p04-4.webp' => 'Bedroom',
                    'images/properties/p04-5.webp' => 'Building exterior',
                ],
            ],
            [
                'title' => 'Dubai Hills family villa backing onto the golf course',
                'status' => 'for_sale',
                'type' => 'house',
                'price' => 7900000,
                'price_label' => null,
                'address' => 'Villa 14, Golf Grove, Dubai Hills Estate',
                'suburb' => 'Dubai Hills Estate',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1050,
                'longitude' => 55.2450,
                'bedrooms' => 4,
                'bathrooms' => 5,
                'carspaces' => 2,
                'land_size' => 6800,
                'floor_size' => 4300,
                'is_featured' => true,
                'inspection_times' => 'Saturday 1:00pm - 5:00pm',
                'features' => ['Backs onto the 9th fairway', 'Landscaped garden with mature trees', 'Upgraded kitchen and pantry', 'Maid\'s room and driver\'s room', 'Community pools and parks', 'GEMS school within the community'],
                'description' => 'Golf Grove, backing onto the fairway rather than looking across at somebody else\'s wall. Four bedrooms, all en-suite, plus a maid\'s room and a driver\'s room that are actually usable rather than a cupboard.

The owners upgraded the kitchen, added a proper pantry and landscaped the garden themselves - the difference is obvious the moment you walk in. Everything else is the standard Emaar handover build, which holds up.

Dubai Hills Mall, the park and the GEMS school are all inside the community, so most of the school runs never reach the main road.',
                'images' => [
                    'images/properties/p05-1.webp' => 'Front exterior',
                    'images/properties/p05-2.webp' => 'Living room',
                    'images/properties/p05-3.webp' => 'Kitchen',
                    'images/properties/p05-4.webp' => 'Garden',
                ],
            ],
            [
                'title' => 'JVC studio, tenanted, walk to Circle Mall',
                'status' => 'for_sale',
                'type' => 'apartment',
                'price' => 520000,
                'price_label' => null,
                'address' => 'Apartment 508, The Square Tower, Jumeirah Village Circle',
                'suburb' => 'Jumeirah Village Circle',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.0600,
                'longitude' => 55.2100,
                'bedrooms' => 0,
                'bathrooms' => 1,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 440,
                'is_featured' => false,
                'inspection_times' => 'By appointment, weekdays from 5:00pm',
                'features' => ['Tenant in place at AED 42,000 a year', 'Fitted kitchen with appliances', 'Balcony with pool outlook', 'Gym and pool', 'Walk to Circle Mall', 'Low service charge'],
                'description' => 'A straightforward studio let at AED 42,000 a year. Not glamorous, but it is the sort of unit that lets within a fortnight every time it comes free, and the numbers work at this price.

Fitted kitchen with the appliances included, a small balcony over the podium pool, and a low service charge for the area. The tenant is on a renewed contract running to the end of the year.

Circle Mall is a short walk, and JVC\'s access to Sheikh Zayed Road and Al Khail makes it a sensible base for anyone working across the city.',
                'images' => [
                    'images/properties/p06-1.webp' => 'Living area',
                    'images/properties/p06-2.webp' => 'Kitchen',
                    'images/properties/p06-3.webp' => 'Bathroom',
                    'images/properties/p06-4.webp' => 'Building exterior',
                ],
            ],
            [
                'title' => 'Arabian Ranches townhouse with a private courtyard',
                'status' => 'for_sale',
                'type' => 'townhouse',
                'price' => 4200000,
                'price_label' => null,
                'address' => 'Townhouse 32, Al Reem 2, Arabian Ranches',
                'suburb' => 'Arabian Ranches',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.0500,
                'longitude' => 55.2700,
                'bedrooms' => 3,
                'bathrooms' => 4,
                'carspaces' => 2,
                'land_size' => 3900,
                'floor_size' => 2950,
                'is_featured' => false,
                'inspection_times' => 'Friday 2:00pm - 6:00pm',
                'features' => ['Private walled courtyard', 'Upgraded bathrooms', 'Community pools and tennis courts', 'Ranches 1 schools and nursery', 'Direct access to Al Qudra road', 'Mature landscaping'],
                'description' => 'Al Reem townhouses have the largest courtyard of the Ranches layouts, and this one has been planted out properly rather than paved over. Three bedrooms, all upstairs, with the living space wrapping around the courtyard.

The bathrooms were upgraded two years ago and the kitchen re-faced rather than replaced. Mechanical systems have been serviced annually and the paperwork is in the file.

Ranches 1 is quiet by design, and the community pools and tennis courts are a short walk rather than a drive. Good school options inside the community too.',
                'images' => [
                    'images/properties/p07-1.webp' => 'Front exterior',
                    'images/properties/p07-2.webp' => 'Living room',
                    'images/properties/p07-3.webp' => 'Kitchen',
                    'images/properties/p07-4.webp' => 'Courtyard',
                ],
            ],
            [
                'title' => 'Creek Harbour 2-bed overlooking the marina promenade',
                'status' => 'for_sale',
                'type' => 'apartment',
                'price' => 1750000,
                'price_label' => null,
                'address' => 'Apartment 1809, Harbour Gate 1, Dubai Creek Harbour',
                'suburb' => 'Dubai Creek Harbour',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.2000,
                'longitude' => 55.3450,
                'bedrooms' => 2,
                'bathrooms' => 3,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 1080,
                'is_featured' => false,
                'inspection_times' => 'Saturday 11:00am - 3:00pm',
                'features' => ['Marina promenade view', 'Skyline and Creek outlook', 'Post-handover payment plan available', 'Vacant on transfer', 'Shared pool deck and gym', 'Twenty minutes to Downtown'],
                'description' => 'Harbour Gate, looking straight down the promenade rather than across at the next tower. Two bedrooms, both with their own bathroom, and a balcony that takes the evening light.

The remaining post-handover payment plan transfers with the property, which usually helps a buyer more than a small discount does. Details are in the pack - read them before you offer.

Creek Harbour is still building out, so the view in five years will be better than it is now, and the Creek Tower side of the community is the quieter half.',
                'images' => [
                    'images/properties/p08-1.webp' => 'Living room',
                    'images/properties/p08-2.webp' => 'Kitchen',
                    'images/properties/p08-3.webp' => 'Bedroom',
                    'images/properties/p08-4.webp' => 'View',
                ],
            ],
            [
                'title' => 'Sobha Hartland 3-bed with a study, high floor',
                'status' => 'for_sale',
                'type' => 'apartment',
                'price' => 2850000,
                'price_label' => null,
                'address' => 'Apartment 2206, Sobha Creek Vistas, Sobha Hartland',
                'suburb' => 'Sobha Hartland',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1830,
                'longitude' => 55.3050,
                'bedrooms' => 3,
                'bathrooms' => 4,
                'carspaces' => 2,
                'land_size' => null,
                'floor_size' => 1640,
                'is_featured' => false,
                'inspection_times' => 'Sunday to Thursday, 5:00pm - 8:00pm',
                'features' => ['Sobha in-house build quality', 'Separate study', 'Creek and skyline view', 'Two parking bays', 'Hartland International School nearby', 'Chiller free'],
                'description' => 'Sobha finishes its own buildings, and you can feel the difference in the doors and the joinery - this is one of the better built apartments in the wider Downtown ring.

Three bedrooms plus a separate study that closes, which is what most people working from home actually need. High floor, so the Creek and skyline view is unobstructed in both directions.

Chiller free, two parking bays, and the school is a five minute drive. Handover snagging was closed out in full and we have the report.',
                'images' => [
                    'images/properties/p09-1.webp' => 'Living room',
                    'images/properties/p09-2.webp' => 'Kitchen',
                    'images/properties/p09-3.webp' => 'Bedroom',
                    'images/properties/p09-4.webp' => 'View',
                ],
            ],
            [
                // On the sale list and the rental list at once - the owner will
                // take either, so the rental side is set alongside the sale one.
                'title' => 'Canal studio in Business Bay, sale or let',
                'status' => 'for_sale',
                'type' => 'apartment',
                'price' => 890000,
                'price_label' => null,
                'is_for_rent' => true,
                'rent_price' => 68000,
                'rent_period' => 'year',
                'rent_status' => 'available',
                'address' => 'Studio 807, Bay Square Building 12, Business Bay',
                'suburb' => 'Business Bay',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1830,
                'longitude' => 55.2710,
                'bedrooms' => 0,
                'bathrooms' => 1,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 510,
                'is_featured' => false,
                'inspection_times' => 'Daily, 11:00am - 6:00pm',
                'features' => ['Canal view from the balcony', 'Furnished and ready', 'Chiller free', 'One parking bay', 'Bay Square restaurants downstairs', 'Ten minutes to Downtown'],
                'description' => 'The owner is selling and letting at the same time and will take whichever comes first, so the studio is priced for both markets - AED 890,000 to buy, AED 68,000 a year to rent, fully furnished.

Bay Square is one of the few Business Bay addresses with restaurants and a supermarket at street level rather than a car park, and the canal is two minutes\' walk.

If you are buying as an investment, we can hand the tenancy to you; if you want to live in it, the furniture stays.',
                'images' => [
                    // p03 already fronts the Business Bay 1-bed and the Al Barsha
                    // rental; this studio sits beside both grids, so it borrows
                    // the sold Burj Views set instead - sold homes only appear
                    // on /recently-sold, never next to this listing.
                    'images/properties/p12-1.webp' => 'Living area',
                    'images/properties/p12-2.webp' => 'Kitchen',
                    'images/properties/p12-3.webp' => 'Balcony view',
                ],
            ],
            [
                'title' => 'Under offer - Business Bay 2-bed with Dubai Canal frontage',
                'status' => 'under_offer',
                'type' => 'apartment',
                'price' => 1980000,
                'price_label' => null,
                'address' => 'Apartment 906, Peninsula One, Business Bay',
                'suburb' => 'Business Bay',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1890,
                'longitude' => 55.2790,
                'bedrooms' => 2,
                'bathrooms' => 3,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 1120,
                'is_featured' => false,
                'inspection_times' => 'Under offer - backup viewings by appointment',
                'features' => ['Canal frontage', 'Vacant on transfer', 'Peninsula community pool deck', 'Chiller free', 'Walk to Downtown', 'One parking bay'],
                'description' => 'Under offer as of this month. We are still taking backup viewings, because offers in this building do occasionally fall over at the NOC stage.

Two bedrooms on a lower floor with direct canal frontage and a decent sized balcony. Chiller free, vacant on transfer, and the Peninsula deck has one of the better pools on this stretch.

If you want to be told when it comes back to market, or if you would like to see the two similar units we have off-market, call us.',
                'images' => [
                    'images/properties/p10-1.webp' => 'Living room',
                    'images/properties/p10-2.webp' => 'Kitchen',
                    'images/properties/p10-3.webp' => 'Balcony',
                    'images/properties/p10-4.webp' => 'Canal view',
                ],
            ],
            [
                'title' => 'Sold - Dubai Marina 2-bed with sea view',
                'status' => 'sold',
                'type' => 'apartment',
                'price' => 1725000,
                'price_label' => null,
                'sold_price' => 1725000,
                'sold_at' => '2026-09-18',
                'days_on_market' => 19,
                'address' => 'Apartment 1502, Marina Pinnacle, Dubai Marina',
                'suburb' => 'Dubai Marina',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.0850,
                'longitude' => 55.1450,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 1240,
                'is_featured' => false,
                'inspection_times' => null,
                'features' => ['Full sea view', 'Vacant on transfer', 'One parking bay', 'Chiller free', 'Pool and gym', 'Metro within walking distance'],
                'description' => 'Sold in nineteen days at the asking price, to a cash buyer who had been looking in this tower for a year. Two bedrooms, full sea view, chiller free.

We keep this on the site because it is a fair example of what the market did in this building this quarter. If you are buying or selling on the Marina, we can show you the comparable evidence behind the number.

The buyer had the valuation done before we listed it, which is unusual and made the NOC stage painless.',
                'images' => [
                    'images/properties/p11-1.webp' => 'Living room',
                    'images/properties/p11-2.webp' => 'Bedroom',
                    'images/properties/p11-3.webp' => 'Kitchen',
                    'images/properties/p11-4.webp' => 'Sea view',
                ],
            ],
            [
                'title' => 'Sold - Downtown 1-bed at Burj Views',
                'status' => 'sold',
                'type' => 'apartment',
                'price' => 1380000,
                'price_label' => null,
                'sold_price' => 1350000,
                'sold_at' => '2026-08-04',
                'days_on_market' => 32,
                'address' => 'Apartment 2108, Burj Views Tower B, Downtown Dubai',
                'suburb' => 'Downtown Dubai',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1935,
                'longitude' => 55.2755,
                'bedrooms' => 1,
                'bathrooms' => 2,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 880,
                'is_featured' => false,
                'inspection_times' => null,
                'features' => ['Burj Khalifa view', 'Chiller free', 'One parking bay', 'Concierge', 'Furnished', 'Walk to Dubai Mall'],
                'description' => 'Sold at AED 1,350,000 after thirty-two days. The seller wanted 1.4 and we told them the comparable evidence did not support it, so we listed at 1.38 and closed two percent under.

A one-bed with the Burj view, chiller free, sold furnished. Downtown one-beds at this floor count still trade quickly when the view is right and the paperwork is clean.

Both parties used the same conveyancer and the transfer was done eleven days after the offer.',
                'images' => [
                    'images/properties/p12-1.webp' => 'Living room',
                    'images/properties/p12-2.webp' => 'Bedroom',
                    'images/properties/p12-3.webp' => 'Kitchen',
                    'images/properties/p12-4.webp' => 'View',
                ],
            ],
            [
                'title' => 'Sold - The Springs 3-bed townhouse, lake side',
                'status' => 'sold',
                'type' => 'townhouse',
                'price' => 2650000,
                'price_label' => null,
                'sold_price' => 2650000,
                'sold_at' => '2026-07-22',
                'days_on_market' => 14,
                'address' => 'Townhouse 18, The Springs 7, The Springs',
                'suburb' => 'The Springs',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.0600,
                'longitude' => 55.1900,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'carspaces' => 2,
                'land_size' => 3200,
                'floor_size' => 2400,
                'is_featured' => false,
                'inspection_times' => null,
                'features' => ['Lake side plot', 'Upgraded kitchen', 'Private garden', 'Community pools', 'Two parking spaces', 'Close to Springs Souk'],
                'description' => 'Fourteen days, asking price, lake-side plot. The Springs moves fast when the plot backs onto water rather than another townhouse, and this one had an upgraded kitchen and a well-kept garden.

These are freehold, managed by Emaar, and still the most sensible family entry point in that part of the city. Service charges are reasonable and the community facilities are maintained.

We had three offers in the first week. The one that closed was the one with the cleanest funds position, not the highest number - worth remembering when you are bidding.',
                'images' => [
                    'images/properties/p01-1.webp' => 'Front exterior',
                    'images/properties/p01-2.webp' => 'Living room',
                    'images/properties/p01-3.webp' => 'Kitchen',
                    'images/properties/p01-4.webp' => 'Garden',
                ],
            ],
            [
                'title' => 'Sold - Mirdif villa with a large garden',
                'status' => 'sold',
                'type' => 'house',
                'price' => 3100000,
                'price_label' => null,
                'sold_price' => 3020000,
                'sold_at' => '2026-06-11',
                'days_on_market' => 41,
                'address' => 'Villa 6, Uptown Mirdif, Mirdif',
                'suburb' => 'Mirdif',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.2200,
                'longitude' => 55.4200,
                'bedrooms' => 5,
                'bathrooms' => 5,
                'carspaces' => 2,
                'land_size' => 7600,
                'floor_size' => 4100,
                'is_featured' => false,
                'inspection_times' => null,
                'features' => ['Large corner garden', 'Five bedrooms plus maid\'s room', 'Near Mirdif City Centre', 'Close to two international schools', 'Two parking spaces', 'Family neighbourhood'],
                'description' => 'A big family house on a corner plot, sold to a family relocating from Sharjah who wanted the schools and the garden. Forty-one days on market and ninety-eight thousand under asking.

Mirdif remains the value play for families who want space and do not need to be on Sheikh Zayed Road every morning. The airport is fifteen minutes, which matters to some buyers more than the commute.

The garden needed work and we priced for it rather than pretending otherwise. That is usually why a house like this sells inside six weeks.',
                'images' => [
                    'images/properties/p02-1.webp' => 'Front exterior',
                    'images/properties/p02-2.webp' => 'Living room',
                    'images/properties/p02-3.webp' => 'Kitchen',
                    'images/properties/p02-4.webp' => 'Garden',
                ],
            ],
            [
                'title' => 'Al Barsha 2-bed, fully furnished and ready to move in',
                'status' => 'rental',
                'type' => 'apartment',
                'price' => null,
                'price_label' => 'Rent on application',
                'is_for_rent' => true,
                'rent_price' => 95000,
                'rent_period' => 'year',
                'rent_status' => 'available',
                'address' => 'Apartment 704, Al Barsha Residence 3, Al Barsha',
                'suburb' => 'Al Barsha',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1100,
                'longitude' => 55.1950,
                'bedrooms' => 2,
                'bathrooms' => 3,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 1150,
                'is_featured' => false,
                'inspection_times' => 'Daily, 10:00am - 7:00pm',
                'features' => ['Fully furnished', 'Chiller free', 'One covered parking bay', 'Mall of the Emirates ten minutes away', 'Gym and pool', 'DEWA connected'],
                'description' => 'Furnished properly rather than with the usual hotel-leftovers, and available now. Two bedrooms, three bathrooms, chiller free, at AED 95,000 a year in four cheques.

Al Barsha is the practical choice for anyone working in Media City, Internet City or the Marina corridor - you avoid Sheikh Zayed Road at peak by going through Al Sufouh.

One parking bay, DEWA already connected in the tenant\'s name, and the building has a usable gym and pool rather than a token one.',
                'images' => [
                    'images/properties/p03-1.webp' => 'Living room',
                    'images/properties/p03-2.webp' => 'Kitchen',
                    'images/properties/p03-3.webp' => 'Bedroom',
                    'images/properties/p03-4.webp' => 'Building exterior',
                ],
            ],
            [
                'title' => 'JLT 1-bed, high floor, available immediately',
                'status' => 'rental',
                'type' => 'apartment',
                'price' => null,
                'price_label' => 'Two cheques considered',
                'is_for_rent' => true,
                'rent_price' => 75000,
                'rent_period' => 'year',
                'rent_status' => 'available',
                'address' => 'Apartment 2904, Goldcrest Views 1, Jumeirah Lake Towers',
                'suburb' => 'Jumeirah Lake Towers',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.0690,
                'longitude' => 55.1400,
                'bedrooms' => 1,
                'bathrooms' => 2,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 780,
                'is_featured' => false,
                'inspection_times' => 'Daily, 9:00am - 8:00pm',
                'features' => ['High floor with lake view', 'Chiller free', 'Metro two minutes away', 'Unfurnished', 'Gym and pool', 'One parking bay'],
                'description' => 'Twenty-ninth floor, lake rather than road, and the metro is two minutes from the lobby - for anyone commuting to Media City or the Marina this is the sensible address.

Unfurnished, so you can bring your own things. AED 75,000 a year, and the landlord will consider two cheques for the right tenant.

The building is one of the better maintained in the cluster and the chiller is free, which is worth checking carefully in JLT where it often is not.',
                'images' => [
                    'images/properties/p04-1.webp' => 'Living room',
                    'images/properties/p04-2.webp' => 'Kitchen',
                    'images/properties/p04-3.webp' => 'Bedroom',
                    'images/properties/p04-4.webp' => 'View',
                ],
            ],
            [
                'title' => 'JVC 1-bed with a balcony, pet friendly building',
                'status' => 'rental',
                'type' => 'apartment',
                'price' => null,
                'price_label' => null,
                'is_for_rent' => true,
                'rent_price' => 62000,
                'rent_period' => 'year',
                'rent_status' => 'available',
                'address' => 'Apartment 312, Bloom Towers, Jumeirah Village Circle',
                'suburb' => 'Jumeirah Village Circle',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.0630,
                'longitude' => 55.2080,
                'bedrooms' => 1,
                'bathrooms' => 2,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 690,
                'is_featured' => false,
                'inspection_times' => 'Sunday to Friday, 11:00am - 6:00pm',
                'features' => ['Pets allowed', 'Semi-furnished', 'Balcony with garden view', 'Gym and pool', 'One parking bay', 'Close to Al Khail road'],
                'description' => 'One of the few buildings in JVC that will put "pets allowed" in writing, which is why these units rarely sit empty for long.

Semi-furnished - white goods, wardrobes and beds are in, everything else is yours. Balcony looks over the internal garden rather than the car park.

AED 62,000 a year in four cheques. Al Khail is two minutes away and you can be at Mall of the Emirates in fifteen.',
                'images' => [
                    'images/properties/p05-1.webp' => 'Living room',
                    'images/properties/p05-2.webp' => 'Kitchen',
                    'images/properties/p05-3.webp' => 'Bedroom',
                    'images/properties/p05-4.webp' => 'Balcony',
                ],
            ],
            [
                'title' => 'MBR City 4-bed villa with a private pool',
                'status' => 'rental',
                'type' => 'house',
                'price' => null,
                'price_label' => null,
                'is_for_rent' => true,
                'rent_price' => 240000,
                'rent_period' => 'year',
                'rent_status' => 'available',
                'address' => 'Villa 9, District One, Mohammed Bin Rashid City',
                'suburb' => 'Mohammed Bin Rashid City',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.1600,
                'longitude' => 55.2900,
                'bedrooms' => 4,
                'bathrooms' => 5,
                'carspaces' => 2,
                'land_size' => 6200,
                'floor_size' => 4200,
                'is_featured' => false,
                'inspection_times' => 'By appointment, daily',
                'features' => ['Private pool', 'Landscaped garden', 'Unfurnished', 'Maid\'s room', 'Lagoon and park access', 'Fifteen minutes to Downtown'],
                'description' => 'District One, with the lagoon and park access that come with the community, and a private pool in the garden. Four bedrooms, all en-suite, plus a maid\'s room.

Unfurnished and available now, at AED 240,000 a year in four cheques. For a family that wants space close to Downtown without living inside it, this is the usual answer.

The landlord maintains the pool and garden as part of the rent, which is worth having in writing - it is in the contract we will show you.',
                'images' => [
                    'images/properties/p06-1.webp' => 'Front exterior',
                    'images/properties/p06-2.webp' => 'Living room',
                    'images/properties/p06-3.webp' => 'Kitchen',
                    'images/properties/p06-4.webp' => 'Pool',
                ],
            ],
            [
                'title' => 'Jumeirah 3-bed, two minutes from the beach',
                'status' => 'rental',
                'type' => 'house',
                'price' => null,
                'price_label' => 'Short lets considered',
                'is_for_rent' => true,
                'rent_price' => 180000,
                'rent_period' => 'year',
                'rent_status' => 'available',
                'address' => 'Villa 4, Jumeirah 2, Jumeirah',
                'suburb' => 'Jumeirah',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.2048,
                'longitude' => 55.2440,
                'bedrooms' => 3,
                'bathrooms' => 4,
                'carspaces' => 2,
                'land_size' => 3400,
                'floor_size' => 2800,
                'is_featured' => false,
                'inspection_times' => 'Daily, 10:00am - 6:00pm',
                'features' => ['Two minutes from Jumeirah beach', 'Private garden', 'Partly furnished', 'Independent annexe', 'Near Jumeirah schools', 'Two parking spaces'],
                'description' => 'A Jumeirah villa rather than an apartment - three bedrooms in the main house, plus an annexe that works as a guest room or a study.

Two minutes from the beach road, and within easy reach of the Jumeirah schools, which is why this pocket stays in demand all year. Partly furnished, AED 180,000 a year.

The landlord will consider a short let at a premium. Ask us for the numbers before you plan around it.',
                'images' => [
                    'images/properties/p07-1.webp' => 'Front exterior',
                    'images/properties/p07-2.webp' => 'Living room',
                    'images/properties/p07-3.webp' => 'Kitchen',
                    'images/properties/p07-4.webp' => 'Garden',
                ],
            ],
            [
                // Let and no longer available, so it stays out of the live
                // rental list while the tenancy runs.
                'title' => 'Two-bed in The Greens, currently leased',
                'status' => 'rental',
                'type' => 'apartment',
                'price' => null,
                'price_label' => 'Let until June',
                'is_for_rent' => true,
                'rent_price' => 105000,
                'rent_period' => 'year',
                'rent_status' => 'let',
                'address' => 'Apartment 402, Al Dhafra 4, The Greens',
                'suburb' => 'The Greens',
                'state' => 'Dubai',
                'postcode' => '',
                'latitude' => 25.0880,
                'longitude' => 55.1620,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'carspaces' => 1,
                'land_size' => null,
                'floor_size' => 1020,
                'is_featured' => false,
                'inspection_times' => null,
                'features' => ['Overlooks the shared gardens', 'Chiller free', 'Metro within walking distance', 'One parking bay'],
                'description' => 'Let to a tenant who moved in this spring and is staying until June, so this one is off the market for now.

We keep it on file because The Greens two-beds with a garden view come up maybe twice a year. If you want to be told before it is advertised again, ask us to put your name on it.',
                'images' => [
                    'images/properties/p05-1.webp' => 'Living room',
                    'images/properties/p05-2.webp' => 'Kitchen',
                    'images/properties/p05-3.webp' => 'Bedroom',
                    'images/properties/p05-4.webp' => 'Garden view',
                ],
            ],
        ];
    }
}
