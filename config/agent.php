<?php

/*
|--------------------------------------------------------------------------
| Agent / Site Details
|--------------------------------------------------------------------------
|
| Everything specific to the business lives here. Change these values and the
| whole site updates - header, footer, contact page, click-to-call buttons,
| WhatsApp links, SEO tags and the JSON-LD structured data.
|
| NOTE: the three figures under "Headline numbers" and the `bio` and `license`
| entries are placeholders. Put your real numbers and words in before this site
| goes anywhere public - they are claims made in your name.
|
*/

return [
    'name'      => 'Bilal Raza',
    'founder'   => 'Bilal Raza',
    'title'     => 'Founder',
    'agency'    => 'BRProperty',

    // The small line set under the wordmark in the logo lockup (header, footer,
    // emails). It describes the business, not the location - the office block
    // below already carries the address.
    'descriptor' => 'Real Estate & Consultancy',

    'tagline'   => 'A Dubai real estate and consultancy house - sales, leasing and property management across Downtown, Business Bay, Dubai Marina and the western crescent.',
    'photo'     => 'images/agent.webp',
    'avatar'    => 'images/agent-avatar.webp',
    'logo'      => 'images/logo.svg',
    'logo_mark' => 'images/logo-mark.svg',
    'logo_light' => 'images/logo-light.svg',
    'hero_image' => 'images/hero-bg.webp',

    // Leave blank until you have a real registration to show (RERA / ORN).
    'license'   => '',

    /* Headline numbers - PLACEHOLDERS, replace with your own */
    'experience_years'   => 12,
    'properties_sold'    => 340,
    'avg_days_on_market' => 27,

    /* PLACEHOLDER copy - rewrite in your own words */
    'bio' => [
        "We are a Dubai real estate and consultancy house handling residential property across Downtown, Business Bay, Dubai Marina and the western crescent, with regular work in Jumeirah Village Circle, Dubai Hills Estate, Arabian Ranches and the Palm. Apartments, villas, townhouses, plots and the occasional income portfolio.",
        "Most of the trouble in a Dubai deal is paperwork, not price. Title deed, NOC, service charge statements, developer handover documents, snagging reports, mortgage pre-approval - our team pulls all of it before you commit, and we tell you plainly when something does not add up.",
        "We work on a short list of properties at a time rather than a long one, which means when you call about a listing you get somebody who has actually walked it. If a deal is wrong for you, we will say so - there will be another one.",
    ],

    'phone'         => '+971 50 123 4567',
    'phone_dial'    => '+971501234567',
    'office_phone'  => '+971 4 123 4567',
    'email'         => 'hello@brproperty.ae',
    'whatsapp'      => '971501234567',

    'office' => [
        'street'   => 'Office 1204, Burj Al Salam, Sheikh Zayed Road',
        'suburb'   => 'Business Bay',
        'state'    => 'Dubai',
        // Dubai has no postal codes - the field is kept for markets that do.
        'postcode' => '',
        'country'  => 'AE',
        'lat'      => 25.2268,
        'lng'      => 55.2825,
    ],

    'hours' => [
        'Monday - Friday' => '9:00am - 7:00pm',
        'Saturday'        => '10:00am - 4:00pm',
        'Sunday'          => 'By appointment',
    ],

    /*
    |--------------------------------------------------------------------------
    | Viewings and appointments
    |--------------------------------------------------------------------------
    |
    | When the phone agent may offer viewings. Hours are local (timezone below)
    | and a day can have several windows. `auto_confirm` false means a booked
    | slot is a request the agent confirms - the calendar here does not know
    | about the rest of the agent's day.
    |
    */
    'appointments' => [
        'timezone'          => 'Asia/Dubai',
        'slot_minutes'      => 60,
        'min_notice_hours'  => 3,
        'days_ahead'        => 14,
        'auto_confirm'      => (bool) env('APPOINTMENTS_AUTO_CONFIRM', false),
        'hours' => [
            'mon' => [['10:00', '20:00']],
            'tue' => [['10:00', '20:00']],
            'wed' => [['10:00', '20:00']],
            'thu' => [['10:00', '20:00']],
            'fri' => [['10:00', '12:30'], ['14:30', '20:00']],
            'sat' => [['10:00', '20:00']],
            'sun' => [],
        ],
    ],

    'service_areas' => [
        'Business Bay',
        'Downtown Dubai',
        'Dubai Marina',
        'Jumeirah Lake Towers',
        'Palm Jumeirah',
        'Jumeirah',
        'Al Barsha',
        'Dubai Hills Estate',
        'Arabian Ranches',
        'Dubai Creek Harbour',
        'Sobha Hartland',
        'Mohammed Bin Rashid City',
        'Jumeirah Village Circle',
        'Al Furjan',
        'The Springs',
        'The Meadows',
        'Emirates Hills',
        'Damac Hills',
        'Mirdif',
        'Tilal Al Ghaf',
    ],

    'social' => [
        'facebook'  => 'https://facebook.com/',
        'instagram' => 'https://instagram.com/',
        'linkedin'  => 'https://linkedin.com/in/',
        'youtube'   => 'https://youtube.com/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Local formatting
    |--------------------------------------------------------------------------
    |
    | `price.style` - 'subcontinent' renders 42500000 as "4.25 Crore".
    |                 'western' renders it as "42,500,000".
    | `area.unit`   - 'marla' stores land size in Marla and rolls 20 Marla up
    |                 into Kanal. 'sqft' stores plain square feet.
    |
    */
    'format' => [
        'price' => ['currency' => 'AED', 'style' => 'western'],
        'area'  => ['unit' => 'sqft'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | This site is published as a portfolio piece, so the admin login is
    | pre-filled and the credentials are shown on screen. Set DEMO_LOGIN=false
    | (or drop the line) the moment it holds anything real.
    |
    */
    'demo' => [
        'enabled'  => env('DEMO_LOGIN', false),
        'email'    => env('DEMO_LOGIN_EMAIL', 'admin@example.com'),
        'password' => env('DEMO_LOGIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local vocabulary
    |--------------------------------------------------------------------------
    |
    | The database stores the generic keys; these are what visitors read. Change
    | the right-hand side for another market without touching a migration.
    |
    */
    'property_types' => [
        'house'     => 'Villa',
        'apartment' => 'Apartment',
        'townhouse' => 'Townhouse',
        'land'      => 'Plot / Land',
        'acreage'   => 'Estate',
    ],

    /* Price bands offered in the listing filter, in UAE dirhams. */
    'price_bands' => [
        ''         => 'Any',
        '750000'   => 'AED 750K',
        '1000000'  => 'AED 1M',
        '1500000'  => 'AED 1.5M',
        '2000000'  => 'AED 2M',
        '3000000'  => 'AED 3M',
        '5000000'  => 'AED 5M',
        '10000000' => 'AED 10M+',
    ],

    /* Annual-rent bands offered in the rental filter, in UAE dirhams. */
    'rent_bands' => [
        ''       => 'Any',
        '40000'  => 'AED 40K',
        '60000'  => 'AED 60K',
        '80000'  => 'AED 80K',
        '100000' => 'AED 100K',
        '150000' => 'AED 150K',
        '250000' => 'AED 250K+',
    ],

    /*
    | How a rental or a purchase actually runs, for "how does renting work?"
    | questions the assistant answers without the model. Steps only: fees,
    | deposits and cheque plans vary by landlord and building, so they are left
    | to the team rather than quoted as if they were fixed.
    */
    'process' => [
        'renting' => [
            'label' => 'How renting works in Dubai',
            'steps' => [
                'Agree the annual rent with the landlord, then agree how many cheques it is split into - the cheque plan is negotiated, not fixed.',
                'Pay a security deposit, held against the condition of the home and returned when you hand it back.',
                'Register the tenancy with Ejari and open a DEWA account for utilities.',
                'Collect the keys and move in.',
            ],
            'note' => 'Commission, deposit and cheque plan are confirmed up front for each home - :agent will go through them with you.',
        ],
        'buying' => [
            'label' => 'How buying works in Dubai',
            'steps' => [
                'Agree the price and sign the sale agreement, with a deposit held against it.',
                'The seller applies for the no-objection certificate from the developer or landlord so the sale can transfer.',
                'The transfer is registered with the Dubai Land Department, which charges a fee based on the price.',
                'Ownership goes into your name at the Land Department and the title deed is issued.',
            ],
            'note' => 'Fees, deposit size and timelines are confirmed for each home - :agent will go through them with you.',
        ],
    ],

    'seo' => [
        'site_name'   => 'BRProperty',
        'description' => 'BRProperty is a Dubai real estate and consultancy house handling buying, selling, leasing and property management across Downtown Dubai, Business Bay, Dubai Marina, JVC and Dubai Hills. Browse listings, recent sales and request a free valuation.',
        'keywords'    => 'Dubai real estate, apartments for sale Dubai, villa for sale Dubai Marina, property for rent Business Bay, Downtown Dubai apartments, Dubai property management, free property valuation Dubai, Dubai real estate agency',
        'locale'      => 'en_US',
        'twitter'     => '@brproperty',

        /* Paste the content value from Search Console / Bing Webmaster Tools. */
        'google_verification' => env('GOOGLE_SITE_VERIFICATION', ''),
        'bing_verification'   => env('BING_SITE_VERIFICATION', ''),

        /* GA4 measurement id, e.g. G-XXXXXXX. Blank = no analytics loaded. */
        'analytics_id' => env('GA_MEASUREMENT_ID', ''),
    ],
];
