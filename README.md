# BRProperty — Real Estate Website

A real estate website for **Bilal Raza / BRProperty**, Dubai, United Arab Emirates, built with
**Laravel 13 + Vue 3 + Tailwind CSS 4**.

Public pages are server-rendered Blade, so Google gets real HTML on first request.
Vue mounts only over the parts that are genuinely interactive — the property
filter, the photo gallery, the enquiry forms, the mobile menu. Behind a login
there is a small admin area for uploading listings and reading enquiries.

---

## Quick start

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link      # required for uploaded photos

npm run build                 # or: npm run dev
php artisan serve
```

Open <http://localhost:8000>.

**Admin login** — <http://localhost:8000/admin>

| Email | Password |
| --- | --- |
| `admin@example.com` | `password` |

> Change these before deploying anywhere public. See *Going live* below.

---

## Pages

| Route | Page |
| --- | --- |
| `/` | Home — hero, featured listings, services, recent sales, testimonials, service-area map, appraisal form |
| `/about` | About the agent |
| `/properties` | Properties for sale, with filtering |
| `/properties/{slug}` | Individual listing — gallery, features, map, enquiry form |
| `/recently-sold` | Recently sold, with results stats |
| `/buying` | Buying process and buyer FAQs |
| `/selling` | Selling, campaign timeline, seller FAQs, **free appraisal form** |
| `/testimonials` | All client reviews |
| `/contact` | Call / WhatsApp / email / form / office details / map |
| `/privacy` | Privacy policy |
| `/sitemap.xml`, `/robots.txt` | Generated from live data |

---

## The features you asked for

**Mobile-friendly** — every page is built mobile-first. On phones there is a
slide-out menu and a sticky bottom bar with Call, WhatsApp and Appraisal.

**Click-to-call** — `tel:` links in the header, hero, footer, every listing, the
contact page and the mobile bar. Each carries a `data-analytics` attribute so
you can wire up conversion tracking later.

**Property enquiry form** — on every listing, pre-filled with the property, and
tied to that listing in the admin inbox.

**Free appraisal form** — on the home page and `/selling#appraisal`. Captures
address, suburb, property type, bedrooms and selling timeframe.

**WhatsApp and social links** — a floating WhatsApp button on desktop, a bar
button on mobile, and one on each listing that pre-fills a message naming the
property. Social links are in the footer and on the contact page.

**Google Maps / service area** — the home and contact pages have a suburb picker
that loads a map only once a visitor asks for it (no Google request until then).
Listings with coordinates show their own map.

**Easy manual property uploads** — `/admin/properties` — add a listing, drag in
photos, mark it For Sale / Under Offer / Sold, feature it on the home page, or
save it unpublished until you are ready.

**Basic Google SEO** — per-page titles and meta descriptions, canonical URLs,
Open Graph and Twitter cards, a generated `sitemap.xml` and `robots.txt`, and
JSON-LD structured data (`RealEstateAgent` site-wide, `SingleFamilyResidence` +
`BreadcrumbList` on listings, `FAQPage` on the selling page).

---

## Making it yours

> **On the demo content.** The name, photo, phone number and email are real. The
> **twelve listings, the testimonials, the enquiry history and the three headline
> figures in `config/agent.php` (`experience_years`, `properties_sold`,
> `avg_days_on_market`) are invented**, as is the `bio`. The photographs are real
> but they are CC0 stock, not pictures of those addresses. Replace all of it with
> your own before the site is published — those are claims made in your name.

**All the agent details live in one file: `config/agent.php`.** Name, agency,
phone, email, WhatsApp number, office address, opening hours, service areas,
social links, licence number and SEO defaults. Change them there and the whole
site follows — header, footer, every call button, the structured data, the lot.

Replace the placeholder artwork:

- `public/images/agent.svg` — the agent portrait. Swap in a real photo and point
  `agent.photo` at it.
- `public/images/properties/*.jpg` — demo listing photos. These are CC0 (public
  domain) stock, credited in `public/images/properties/CREDITS.md`. They are not
  photos of the addresses in the listings — delete them once you upload real ones
  through the admin.
- `public/favicon.svg`

### The palette

Five families, defined as `@theme` tokens at the top of `resources/css/app.css`:

| Family | Role |
| --- | --- |
| **ink** | text, dark sections, and the "Sold" state |
| **sand** | the warm page base |
| **brass** | primary accent — CTAs, "Under Offer", highlights |
| **teal** | "For Sale", active, positive |
| **clay** | warm secondary — Featured, buyers, emphasis |
| **indigo** | informational — appraisals, flats and plots |

**Status colour is semantic and identical everywhere.** For Sale is always teal,
Under Offer always brass, Sold always ink, Featured always clay — on the public
cards, the listing page and the admin tables alike, via `<x-status-chip>` and
`<x-type-chip>`. Every chip carries its label, so colour is never the only signal.

Pages alternate between `band-sand`, `band-teal`, `band-cream` and `band-ink`
instead of running one flat neutral. Every page opens on the same dark
`<x-page-hero>` band, which carries an optional `aside` slot for the form or
portrait that belongs beside the headline.

Chip and label contrast was measured, not guessed: everything clears WCAG AA for
small text (4.5:1). Two early picks failed at 4.36 and 4.15 and were stepped one
notch darker. The logo lives in `public/images/logo-mark.svg` (square
badge), `logo.svg` (horizontal lockup) and `logo-light.svg` (reversed, for dark
backgrounds), with a matching `public/favicon.svg`.

### Prices and land area

`config/agent.php` has a `format` block:

```php
'format' => [
    'price' => ['currency' => 'AED', 'style' => 'western'],
    'area'  => ['unit' => 'sqft'],
],
```

`western` renders `625000` as **AED 625,000**; `subcontinent` renders it as
**PKR 6.25 Lakh**. `sqft` stores plain square feet; `marla` stores land size in
Marla and rolls 20 Marla up into Kanal (`45` → **2 Kanal 5 Marla**). The logic is
in `app/Support/Format.php`.

`property_types` in the same file maps the stored keys to what visitors read
(`land` → "Lot / Land", `townhouse` → "Townhome"), so another market is a
config change rather than a migration.

---

## How it fits together

```
app/
  Http/Controllers/          public site
  Http/Controllers/Admin/    admin area
  Http/Requests/             validation
  Models/                    Property, PropertyImage, Enquiry, Testimonial

config/agent.php             ← all agent details

resources/
  css/app.css                theme tokens + utility classes
  js/
    app.js                   mounts Vue islands over Blade markup
    components/              MobileNav, EnquiryForm, PropertyFilter,
                             PropertyGallery, TestimonialCarousel, ServiceAreaMap,
                             StackedBarChart, HBarChart
  views/
    layouts/app.blade.php    public layout
    components/              island, property-card, section-heading, ...
    components/layouts/      admin layout
    pages/, properties/      public pages
    admin/                   admin screens
      settings/sections/     one Blade partial per settings tab

database/seeders/            12 demo listings, 9 testimonials, sample enquiries
public/images/properties/    49 CC0 listing photos + CREDITS.md
app/Support/                 Format, DashboardMetrics, SiteSettings, Media
tests/Feature/               63 tests
```

### Vue islands

Blade renders the page; Vue takes over marked elements only:

```blade
<x-island name="EnquiryForm" :props="['endpoint' => route('enquiries.store'), 'type' => 'contact']">
    {{-- server-rendered fallback shown until Vue mounts --}}
</x-island>
```

`resources/js/app.js` finds every `[data-vue]` element, reads its JSON props and
mounts the matching component. Each island keeps a sensible fallback inside it,
so search engines and no-JS visitors still get working content — the enquiry
forms include a plain `<form>` fallback that posts to the same endpoint.

### Spam protection

Enquiry forms carry a hidden honeypot field and the endpoint is rate limited to
10 submissions per minute per IP. Admin login is limited to 5 attempts.

---

## The admin dashboard

`/admin` opens on a dashboard rather than a list:

- **Four stat tiles** — listings on the market, portfolio value, sold in the last
  12 months, and enquiries in the last 30 days with a period-on-period delta.
- **Pipeline strip** — for sale / under offer / sold / drafts.
- **Enquiries per week** — twelve weeks, stacked by enquiry type, with a hover
  tooltip and a **Show table** toggle that swaps the chart for the same numbers.
- **Most enquired listings** — which live listings are actually pulling interest
  over the last 90 days. Usually the most useful number on the page.
- **Portfolio value by area** — where the money on the books sits.
- Latest enquiries and recently added listings.

### Settings

`/admin/settings` makes everything in `config/agent.php` editable without touching
code, across seven sections: **Business** (name, agency, bio, headline figures),
**Contact**, **Office & areas** (address, hours, service areas), **Social links**,
**Branding** (logo uploads), **SEO**, and **Currency & units**.

Saved values live in a `settings` table as dot keys (`name`, `office.street`,
`social.facebook`) and are overlaid onto `config('agent.*')` at boot by
`AppServiceProvider`. That means **the config file stays the source of defaults**
and every existing `config('agent.…')` call keeps working untouched — and
**Reset to file defaults** on any section deletes its rows and restores the file.

Currency and land-unit changes are previewed live before you save. Changing the
land unit does **not** convert stored numbers — it only relabels them.

### Profile

`/admin/profile` is the signed-in account, deliberately separate from the public
contact details: name, sign-in email, password (current password required;
changing it signs out every other device), and the agent portrait, which uploads
straight through to the home page, About page and every listing.

The queries live in `app/Support/DashboardMetrics.php`, kept out of the controller
so they can be tested directly. The charts are Vue islands
(`StackedBarChart.vue`, `HBarChart.vue`) drawing inline SVG — no charting library.

**Chart colours are validated, not chosen by eye.** The three categorical hues in
`resources/css/viz.css` were run through a colour-vision-deficiency checker against
the white card surface they render on and pass every gate (lightness band, chroma
floor, CVD separation, normal-vision floor, contrast — all-pairs). If you change
them, re-validate rather than eyeballing. Identity never rests on colour alone:
every chart has a legend, direct value labels, and a table view.

---

## Tests

```bash
php artisan test
```

96 tests covering page rendering, filtering, SEO output, enquiry validation and
rate limiting, admin auth and guest redirects, property CRUD with uploads, image
deletion, testimonial management, the dashboard metric queries, settings
save/validate/reset round-trips, and the profile and password flows.

---

## Going live

> Deploying to **Hostinger shared hosting**? The full walkthrough — SSH keys,
> database, document root, and the redeploy loop — is in
> [DEPLOYMENT.md](DEPLOYMENT.md).


1. Set `APP_ENV=production`, `APP_DEBUG=false` and a real `APP_URL` in `.env`.
2. Replace the seeded admin account:
   ```bash
   php artisan tinker
   >>> App\Models\User::first()->update(['email' => 'you@example.com', 'password' => bcrypt('a-long-password')]);
   ```
   Or delete the seeded user and create your own.
3. Replace the placeholder phone, office address and founder photo — either in
   `config/agent.php` or through **Settings → Business** — and swap the seeded
   listings, testimonials and enquiry history for real ones.
4. `npm run build`, then `php artisan optimize` (caches config, routes and views).
5. `php artisan storage:link` on the server.
6. Point `mail` config at a real mailer if you want emailed enquiry
   notifications — right now enquiries land in the admin inbox only.
7. Submit `https://yourdomain.com/sitemap.xml` in Google Search Console.

### Enquiry notification emails

Enquiries are stored and shown at `/admin/enquiries`. To also get an email,
create a Mailable and dispatch it from `EnquiryController::store()` after the
record is created.
