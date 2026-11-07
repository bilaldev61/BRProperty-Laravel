<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Media;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /** Section => the config keys it owns. Drives both the form and the reset. */
    private const SECTIONS = [
        'business' => ['name', 'title', 'agency', 'tagline', 'license', 'bio',
                       'experience_years', 'properties_sold', 'avg_days_on_market'],
        'contact'  => ['phone', 'phone_dial', 'office_phone', 'email', 'whatsapp'],
        'office'   => ['office.street', 'office.suburb', 'office.state', 'office.postcode',
                       'office.country', 'office.lat', 'office.lng', 'hours', 'service_areas'],
        'social'   => ['social.facebook', 'social.instagram', 'social.linkedin', 'social.youtube'],
        'branding' => ['photo', 'avatar', 'logo_mark', 'logo', 'logo_light'],
        'seo'      => ['seo.site_name', 'seo.description', 'seo.keywords', 'seo.locale', 'seo.twitter'],
        'format'   => ['format.price.currency', 'format.price.style', 'format.area.unit'],
    ];

    public function index(Request $request)
    {
        $section = $request->query('section', 'business');
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        return view('admin.settings.index', [
            'section'  => $section,
            'sections' => $this->sectionLabels(),
            'agent'    => config('agent'),
        ]);
    }

    public function update(Request $request, string $section, SiteSettings $settings)
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        $values = match ($section) {
            'business' => $this->business($request),
            'contact'  => $this->contact($request),
            'office'   => $this->office($request),
            'social'   => $this->social($request),
            'branding' => $this->branding($request),
            'seo'      => $this->seo($request),
            'format'   => $this->format($request),
        };

        $settings->put($values);

        return redirect()
            ->route('admin.settings.index', ['section' => $section])
            ->with('status', $this->sectionLabels()[$section].' settings saved.');
    }

    /** Put a section back to the values in config/agent.php. */
    public function reset(string $section, SiteSettings $settings)
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        $settings->reset(...self::SECTIONS[$section]);

        return redirect()
            ->route('admin.settings.index', ['section' => $section])
            ->with('status', $this->sectionLabels()[$section].' settings reset to the defaults in config/agent.php.');
    }

    /* ------------------------------------------------------------------ */

    private function business(Request $request): array
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'title'   => ['required', 'string', 'max:80'],
            'agency'  => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'license' => ['nullable', 'string', 'max:120'],
            'bio'     => ['nullable', 'string', 'max:4000'],
            'experience_years'   => ['nullable', 'integer', 'min:0', 'max:80'],
            'properties_sold'    => ['nullable', 'integer', 'min:0', 'max:100000'],
            'avg_days_on_market' => ['nullable', 'integer', 'min:0', 'max:2000'],
        ]);

        // The bio is one paragraph per blank-line-separated block.
        $data['bio'] = $this->paragraphs($data['bio'] ?? '');
        $data['license'] = $data['license'] ?? '';

        foreach (['experience_years', 'properties_sold', 'avg_days_on_market'] as $k) {
            $data[$k] = (int) ($data[$k] ?? 0);
        }

        return $data;
    }

    private function contact(Request $request): array
    {
        $data = $request->validate([
            'phone'        => ['required', 'string', 'max:40'],
            'phone_dial'   => ['required', 'string', 'max:24', 'regex:/^\+?[0-9]+$/'],
            'office_phone' => ['nullable', 'string', 'max:40'],
            'email'        => ['required', 'email:rfc', 'max:180'],
            'whatsapp'     => ['nullable', 'string', 'max:24', 'regex:/^[0-9]*$/'],
        ], [
            'phone_dial.regex' => 'The dial number may only contain digits and a leading +.',
            'whatsapp.regex'   => 'The WhatsApp number may only contain digits, with no + or spaces.',
        ]);

        $data['office_phone'] = $data['office_phone'] ?? '';
        $data['whatsapp'] = $data['whatsapp'] ?? '';

        return $data;
    }

    private function office(Request $request): array
    {
        $data = $request->validate([
            'street'   => ['required', 'string', 'max:160'],
            'suburb'   => ['required', 'string', 'max:120'],
            'state'    => ['required', 'string', 'max:40'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'country'  => ['required', 'string', 'size:2'],
            'lat'      => ['nullable', 'numeric', 'between:-90,90'],
            'lng'      => ['nullable', 'numeric', 'between:-180,180'],
            'hours'    => ['nullable', 'string', 'max:1000'],
            'service_areas' => ['nullable', 'string', 'max:2000'],
        ]);

        // A nullable field the form did not submit is absent from $data entirely,
        // so every optional key is read with a default rather than by index.
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;

        return [
            'office.street'   => $data['street'],
            'office.suburb'   => $data['suburb'],
            'office.state'    => $data['state'],
            'office.postcode' => $data['postcode'] ?? '',
            'office.country'  => strtoupper($data['country']),
            'office.lat'      => $lat === null || $lat === '' ? null : (float) $lat,
            'office.lng'      => $lng === null || $lng === '' ? null : (float) $lng,
            'hours'           => $this->pairs($data['hours'] ?? ''),
            'service_areas'   => $this->lines($data['service_areas'] ?? ''),
        ];
    }

    private function social(Request $request): array
    {
        $data = $request->validate([
            'facebook'  => ['nullable', 'url', 'max:200'],
            'instagram' => ['nullable', 'url', 'max:200'],
            'linkedin'  => ['nullable', 'url', 'max:200'],
            'youtube'   => ['nullable', 'url', 'max:200'],
        ]);

        return collect($data)
            ->mapWithKeys(fn ($v, $k) => ['social.'.$k => $v ?? ''])
            ->all();
    }

    private function branding(Request $request): array
    {
        $request->validate([
            'logo_mark'  => ['nullable', 'image', 'mimes:svg,png,jpg,jpeg,webp', 'max:1024'],
            'logo'       => ['nullable', 'image', 'mimes:svg,png,jpg,jpeg,webp', 'max:1024'],
            'logo_light' => ['nullable', 'image', 'mimes:svg,png,jpg,jpeg,webp', 'max:1024'],
        ]);

        $values = [];

        foreach (['logo_mark', 'logo', 'logo_light'] as $field) {
            if ($request->hasFile($field)) {
                $values[$field] = $this->replaceUpload(config('agent.'.$field), $request->file($field));
            }
        }

        return $values;
    }

    private function seo(Request $request): array
    {
        $data = $request->validate([
            'site_name'   => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:300'],
            'keywords'    => ['nullable', 'string', 'max:300'],
            'locale'      => ['required', 'string', 'max:12'],
            'twitter'     => ['nullable', 'string', 'max:40'],
        ]);

        return collect($data)->mapWithKeys(fn ($v, $k) => ['seo.'.$k => $v ?? ''])->all();
    }

    private function format(Request $request): array
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'max:8'],
            'style'    => ['required', Rule::in(['subcontinent', 'western'])],
            'unit'     => ['required', Rule::in(['marla', 'sqm'])],
        ]);

        return [
            'format.price.currency' => strtoupper($data['currency']),
            'format.price.style'    => $data['style'],
            'format.area.unit'      => $data['unit'],
        ];
    }

    /* ------------------------------------------------------------------ */

    private function replaceUpload(?string $current, $file): string
    {
        if (Media::isUpload($current)) {
            Storage::disk('public')->delete($current);
        }

        return $file->store('branding', 'public');
    }

    /** "Mon - Fri | 9-5" per line => ['Mon - Fri' => '9-5'] */
    private function pairs(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => array_map('trim', explode('|', $line, 2)))
            ->filter(fn ($parts) => count($parts) === 2 && $parts[0] !== '')
            ->mapWithKeys(fn ($parts) => [$parts[0] => $parts[1]])
            ->all();
    }

    /** One entry per line. */
    private function lines(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();
    }

    /** Blank-line separated blocks. */
    private function paragraphs(string $text): array
    {
        return collect(preg_split('/\R\s*\R/', trim($text)))
            ->map(fn ($p) => trim(preg_replace('/\s*\R\s*/', ' ', $p)))
            ->filter()
            ->values()
            ->all();
    }

    private function sectionLabels(): array
    {
        return [
            'business' => 'Business',
            'contact'  => 'Contact',
            'office'   => 'Office & areas',
            'social'   => 'Social links',
            'branding' => 'Branding',
            'seo'      => 'SEO',
            'format'   => 'Currency & units',
        ];
    }
}
