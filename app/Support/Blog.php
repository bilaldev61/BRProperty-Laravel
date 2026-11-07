<?php

namespace App\Support;

/**
 * The Dubai market journal.
 *
 * Posts live here as plain data rather than a database table: the site has no
 * editorial workflow, and a static list keeps the blog consistent with the rest
 * of the config-driven pages while still rendering real, linked article pages.
 * The agent can extend the array below (or move it to the database) whenever
 * they start publishing for real.
 */
class Blog
{
    /** @return list<array{slug:string,title:string,category:string,date:string,read:string,excerpt:string,body:list<string>}> */
    public static function posts(): array
    {
        return [
            [
                'slug'     => 'dubai-2026-mortgage-rates-and-your-budget',
                'title'    => 'Dubai 2026: what mortgage rates actually do to your budget',
                'category' => 'Buyers',
                'date'     => '2026-10-12',
                'read'     => '6 min read',
                'excerpt'  => 'A quarter-point on the mortgage moves your monthly payment more than a shift in the asking price. Here is how to size a real budget before you fall for a Palm Jumeirah view.',
                'body'     => [
                    'Most buyers anchor on the sticker price, but it is the financing that sets what you are really out each month. On a AED 2.5 million apartment with 20% down, a move from 4.0% to 4.5% adds roughly AED 630 a month and over AED 70,000 in interest across the first five years. That difference is what quietly decides whether a purchase is comfortable or a stretch.',
                    'Before you view anything, get a full pre-approval from a bank that will look at your whole position, not just salary. Residents usually need 20% down and banks will lend against salary and liabilities; non-residents can borrow too, typically up to 50-60% of value, at a slightly higher rate. A pre-approval letter also tells the seller you are real, which matters more in a building with twelve similar units on the market than it does in a street of detached homes.',
                    'Then leave room for the parts nobody puts on the list: the 4% Land Department transfer fee, the agency commission, the developer NOC fee, the trustee office charge, and for apartments, the annual service charge that can run from AED 12 to AED 30 per square foot depending on the building. We build that whole picture with you before you make an offer, so the number you sign up for is the number you actually pay.',
                ],
            ],
            [
                'slug'     => 'title-deed-noc-oqood-dubai-paperwork',
                'title'    => 'Title deed, NOC and Oqood: the Dubai paperwork that trips up buyers',
                'category' => 'Buying Process',
                'date'     => '2026-09-28',
                'read'     => '7 min read',
                'excerpt'  => 'The deal rarely breaks on price. It breaks on service charge arrears nobody mentioned, a mortgage still registered against the unit, or an Oqood that was never issued. A walkthrough of the three documents to read closely.',
                'body'     => [
                    'The title deed is not the same as a clean title. It tells you who owns the unit, and it should match the seller\'s Emirates ID exactly. What it does not tell you is whether there is a mortgage registered against the property, a levy from the developer, or service charge arrears waiting to be settled before transfer. Those come out of the NOC and the service charge statement, and both are ordered before any money moves.',
                    'For off-plan and recently handed-over property, ask for the Oqood registration. It is the interim registration at the Land Department, and without it the property cannot be transferred cleanly when the title deed eventually issues. Developers are usually prompt, but "usually" is not a document. If the Oqood has not been registered, that is a conversation to have before you pay a deposit, not after.',
                    'For anything in a managed community - and in Dubai that is most of it - read the service charge statement and the last two years of the budget, not just the arrears figure. A building with a thin reserve or a chiller contract up for renewal is a real cost you inherit the day you take possession. We flag these before you sign the Form F, because "the building is fine" is not the same as "there is an AED 40,000 chiller replacement coming."',
                ],
            ],
            [
                'slug'     => 'pricing-a-home-from-dld-transfers',
                'title'    => 'How we price a home from DLD transfers, not from what someone hopes',
                'category' => 'Sellers',
                'date'     => '2026-09-10',
                'read'     => '5 min read',
                'excerpt'  => 'Overpricing does not leave money on the table - it burns days on market and drags the final number down. Here is the comparison method we use to land a price a buyer will actually accept.',
                'body'     => [
                    'A good asking price is a range anchored to real, recent transfers in your own building or community, of a similar size and condition. Not the asking price on an active listing that has been sitting for eight months, and not what the neighbour "would like" for theirs. Those are wishes. Land Department transfers are evidence, and buyers, valuers and banks all work from them.',
                    'We adjust for the things that genuinely move value - the square footage and whether it is built-up or plot, the floor and the view, chiller free or not, a renovated kitchen versus one that will be redone, whether the unit has a balcony, and the unglamorous but real weight of service charges and which side of the building you are on. Two apartments that look identical online can sit six figures apart once those differences are counted.',
                    'The reason this matters is time on market. An overpriced home gets seen, then ignored, and every week that passes buyers start assuming something is wrong with it. By the time the price drops you have spent the momentum the first two weeks gave you. We would rather set the number once, correctly, get a Trakheesi permit and the photography right, and let the viewings do the negotiating.',
                ],
            ],
            [
                'slug'     => 'first-time-buyer-checklist-jvc-business-bay',
                'title'    => 'A first-time buyer checklist for JVC and Business Bay',
                'category' => 'Buyers',
                'date'     => '2026-08-22',
                'read'     => '6 min read',
                'excerpt'  => 'Affordable communities have a catch as well as a price. Here is what a first-time buyer should walk, measure and ask before writing an offer on a mid-market apartment.',
                'body'     => [
                    'JVC, Business Bay and the older Marina towers are where most first purchases happen, and the difference between two units in the same building is usually the landlord rather than the floor plan. Ask how many units in the building are owned by the developer and still being rented out - a building where the developer competes with you for tenants behaves very differently at renewal time.',
                    'Walk the unit at a weekday evening and again at midday. Opposite-building proximity in Business Bay can mean a neighbour\'s living room two metres from your window, and the District Cooling chiller plant can be audible from one wing and inaudible from the other. Neither shows up in a floor plan, and both are fixable only by buying a different unit.',
                    'Finally, get the service charge statement and the DEWA history before you sign the Form F. Service charges in older buildings can be double those in a new one, and the difference is a real annual number that never appears in the asking price. We pull both early so the cost you imagine owning it is the cost you get quoted.',
                ],
            ],
            [
                'slug'     => 'renting-dubai-ejari-what-to-expect',
                'title'    => 'Renting in Dubai: what good landlords and tenants both should expect',
                'category' => 'Rentals',
                'date'     => '2026-08-01',
                'read'     => '5 min read',
                'excerpt'  => 'A clean tenancy is mostly about the paperwork nobody thinks about - the registered contract, the handover inventory and a maintenance line that gets answered.',
                'body'     => [
                    'For tenants: insist the contract is registered with EJARI before you hand over the first cheque. That registration is what you produce for a residence visa, a school place or a DEWA connection, and a landlord who resists it is telling you how the next twelve months will go. The same contract should state exactly who pays for what - DEWA, chiller, the building\'s service charges, cleaning, pest control - and who to call when something breaks at 9pm.',
                    'For owners: screening is the whole job. Emirates ID or passport, a salary certificate or trade licence, a reference from a previous landlord and a security deposit worth 5% of the annual rent unfurnished and 10% furnished are worth far more than a big first cheque from someone you have not checked. We let to the tenant who will renew, not the one in a hurry, because a bad tenancy costs more in a year than a good one earns in a vacancy.',
                    'A dated, signed handover inventory protects both sides at the end. Photograph every room, note the condition of appliances and flooring, take the meter readings and have both parties sign before the first box moves in. Deposit disputes in Dubai almost always trace back to that one document being skipped.',
                ],
            ],
            [
                'slug'     => 'selling-dubai-hills-first-two-weeks',
                'title'    => 'Selling in Dubai Hills: how to win the first two weeks',
                'category' => 'Sellers',
                'date'     => '2026-07-15',
                'read'     => '6 min read',
                'excerpt'  => 'A listing gets the most attention, and the most qualified buyers, in the fortnight after it goes live. What to have done before that first viewing rather than during it.',
                'body'     => [
                    'Get the photography done properly, before you list rather than after it goes stale. Buyers scroll on the image first, and a dim phone photo of a villa shot at four in the afternoon will cost you the click before anyone reads a word of the description. Floor plans, a video walkthrough and evening exterior shots matter as much as the interiors - and in the communities that matter most, the permit number and the listing quality are what separate your property from the other nine on the same street.',
                    'Declutter and depersonalise hard. Family photos make a home feel lived-in to you and small to a buyer; the goal is for people to picture their own things in the space. Walk the property from the gate as a stranger would: the front entry, the pool, the garden and the light in each living space set the tone for the whole viewing.',
                    'Then be genuinely easy to book. Villas that can be shown on short notice in the first fortnight get far more traffic than ones with a rigid schedule. Set an answer for viewings, keep the place ready, and let the volume of interest - not a single early offer - tell you whether the price was right.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $slug): ?array
    {
        return collect(static::posts())->firstWhere('slug', $slug);
    }

    /** Newest first, for the listing grid. */
    public static function latest(?int $count = null): array
    {
        $posts = collect(static::posts())
            ->sortByDesc('date')
            ->values();

        return ($count ? $posts->take($count) : $posts)->all();
    }

    public static function dateLabel(string $iso): string
    {
        return \Illuminate\Support\Carbon::parse($iso)->translatedFormat('F j, Y');
    }
}
