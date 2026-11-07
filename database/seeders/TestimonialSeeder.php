<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * PLACEHOLDER testimonials for the demo. Replace with real ones - these are
 * published under your name and should not stay invented.
 */
class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            ['Palm Jumeirah', 'Seller', 5, true, "Two agencies quoted us prices a million dirhams apart and neither could show their work. Bilal pulled the transfer evidence for six frond villas sold in the previous quarter and walked us through each one, including the two that argued against the higher number. We listed where he suggested and closed above it in three weeks.", 'Marcus & Elena Delgado'],
            ['Dubai Marina', 'Buyer', 5, true, "He told me the service charge on the first Marina apartment I liked had an unresolved levy against the building, and that it would become my problem rather than the seller's unless we made clearing it a condition. No other agent mentioned it. That one sentence saved me more than his commission.", 'Tyler Bennett'],
            ['Arabian Ranches', 'Seller', 5, true, "I was in London for the entire sale. Video walkthroughs every week, the power of attorney handled properly before it was needed, and the transfer done at the Land Department without me flying back once.", 'Priya Nair'],
            ['Jumeirah Village Circle', 'Seller', 5, false, "What I appreciated was being told not to renovate. Every other agent wanted us to spend sixty thousand on a new kitchen first. Bilal said the right buyer for this unit would want to do their own and we would never see the money back. He was right.", 'Greg Salazar'],
            ['Dubai Hills Estate', 'Buyer', 5, false, "I bought into Dubai Hills and he pulled the title deed and the developer NOC himself before I put down a dime. Turned out clean, but the point is he checked rather than telling me it was fine.", 'Amanda Foster'],
            ['Business Bay', 'Tenant', 4, false, "Answered the phone every time, which sounds like a small thing until you have dealt with agents who do not. Straightforward about what was wrong with each apartment as well as what was right.", 'Jordan Whitfield'],
            ['Emirates Hills', 'Seller', 5, false, "Sold in under a month at the price he first quoted. No drama, no last-minute renegotiation, and only pre-qualified buyers were ever brought to the house.", 'Retired Col. James Hartley'],
            ['Al Furjan', 'Buyer', 5, false, "We had lost two villas because our mortgage pre-approval was not in place. He made us get the approval sorted first and then we moved fast on the third. First home in Dubai, and it went smoothly.", 'Kevin & Rachel Osei'],
            ['The Meadows', 'Seller', 5, false, "Discreet, which mattered to us. The villa was never put on the public portals and he brought exactly four buyers, all of them serious and all with funds verified.", 'Margaret Liang'],
            ['Dubai Creek Harbour', 'Buyer', 5, false, "Bought off-plan here and the payment plan paperwork was a mess of amendments. Bilal read all of it, flagged the two clauses that would have cost me at handover, and got the developer to correct them before signature.", 'Sana Iqbal'],
            ['Jumeirah Lake Towers', 'Landlord', 5, false, "He found the tenant in nine days and then handled the deposit, the EJARI and the chiller account without me chasing once. I have three other units with agencies that need chasing monthly.", 'Chidi Okonkwo'],
            ['Downtown Dubai', 'Buyer', 4, false, "Bought a one-bed as an investment and he talked me out of the first two I liked on the grounds of service charge alone. The third has let every year since. Honest rather than eager.", 'Hannah Rees'],
        ];

        foreach ($testimonials as $i => [$location, $role, $rating, $featured, $body, $author]) {
            Testimonial::updateOrCreate(
                ['author' => $author],
                [
                    'location'     => $location,
                    'role'         => $role,
                    'rating'       => $rating,
                    'body'         => $body,
                    'is_featured'  => $featured,
                    'is_published' => true,
                    'sort_order'   => $i,
                ],
            );
        }
    }
}
