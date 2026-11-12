<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create();
        app(SiteSettings::class)->forget();
    }

    /** The facade is typed to the contract, which carries no assertions. */
    private function publicDisk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk;
    }

    public static function sections(): array
    {
        return [
            'business' => ['business'],
            'contact' => ['contact'],
            'office' => ['office'],
            'social' => ['social'],
            'branding' => ['branding'],
            'seo' => ['seo'],
            'format' => ['format'],
            'assistant' => ['assistant'],
        ];
    }

    #[DataProvider('sections')]
    public function test_each_section_renders(string $section): void
    {
        $this->actingAs($this->agent)->get("/admin/settings?section={$section}")->assertOk();
    }

    public function test_an_unknown_section_is_a_404(): void
    {
        $this->actingAs($this->agent)->get('/admin/settings?section=nope')->assertNotFound();
        $this->actingAs($this->agent)->put('/admin/settings/nope', [])->assertNotFound();
    }

    public function test_saving_contact_details_changes_the_public_site(): void
    {
        $this->actingAs($this->agent)->put('/admin/settings/contact', [
            'phone'      => '+92 300 111 2223',
            'phone_dial' => '+923001112223',
            'email'      => 'new@example.com',
            'whatsapp'   => '923001112223',
        ])->assertRedirect();

        $this->get('/contact')
            ->assertOk()
            ->assertSee('tel:+923001112223', false)
            ->assertSee('new@example.com');
    }

    public function test_a_bad_dial_number_is_rejected(): void
    {
        $this->actingAs($this->agent)->put('/admin/settings/contact', [
            'phone' => 'x',
            'phone_dial' => 'not-a-number',
            'email' => 'nope',
            'whatsapp' => '+92 300',
        ])->assertSessionHasErrors(['phone_dial', 'email', 'whatsapp']);

        $this->assertSame(0, Setting::count());
    }

    public function test_resetting_a_section_restores_the_config_defaults(): void
    {
        $original = config('agent.phone_dial');

        $this->actingAs($this->agent)->put('/admin/settings/contact', [
            'phone' => '+92 300 111 2223',
            'phone_dial' => '+923001112223',
            'email' => 'new@example.com',
        ]);
        $this->assertNotSame($original, config('agent.phone_dial'));

        $this->actingAs($this->agent)->delete('/admin/settings/contact')->assertRedirect();

        $this->assertSame(0, Setting::where('key', 'phone_dial')->count());
        app(SiteSettings::class)->apply();
        $this->assertSame($original, config('agent.phone_dial'));
    }

    public function test_the_bio_textarea_becomes_paragraphs(): void
    {
        $this->actingAs($this->agent)->put('/admin/settings/business', [
            'name' => 'Z',
            'title' => 'Dealer',
            'agency' => 'ZH',
            'bio' => "First para.\n\nSecond para\nwrapped onto two lines.\n\n\nThird.",
        ])->assertRedirect();

        $this->assertSame(
            ['First para.', 'Second para wrapped onto two lines.', 'Third.'],
            Setting::find('bio')->value,
        );
    }

    public function test_hours_and_service_areas_parse_from_textareas(): void
    {
        $this->actingAs($this->agent)->put('/admin/settings/office', [
            'street' => 'Y Block',
            'suburb' => 'Lahore',
            'state' => 'Punjab',
            'country' => 'pk',
            'hours' => "Mon - Fri | 10am - 8pm\nSaturday | 10am - 6pm\n\ngarbage-with-no-pipe",
            'service_areas' => "DHA\n\n  Gulberg  \nModel Town",
        ])->assertRedirect();

        $this->assertSame(['Mon - Fri' => '10am - 8pm', 'Saturday' => '10am - 6pm'], Setting::find('hours')->value);
        $this->assertSame(['DHA', 'Gulberg', 'Model Town'], Setting::find('service_areas')->value);
        $this->assertSame('PK', Setting::find('office.country')->value);
    }

    public function test_switching_price_style_changes_how_prices_read(): void
    {
        $this->actingAs($this->agent)->put('/admin/settings/format', [
            'currency' => 'pkr',
            'style' => 'western',
            'unit' => 'sqm',
        ])->assertRedirect();

        app(SiteSettings::class)->apply();

        $this->assertSame('PKR 42,500,000', \App\Support\Format::price(42500000));
        $this->assertSame('20 m²', \App\Support\Format::area(20));
    }

    public function test_uploading_a_logo_replaces_the_previous_upload(): void
    {
        Storage::fake('public');

        $this->actingAs($this->agent)->put('/admin/settings/branding', [
            'logo_mark' => UploadedFile::fake()->image('one.png'),
        ])->assertRedirect();

        $first = Setting::find('logo_mark')->value;
        $this->publicDisk()->assertExists($first);

        app(SiteSettings::class)->apply();

        $this->actingAs($this->agent)->put('/admin/settings/branding', [
            'logo_mark' => UploadedFile::fake()->image('two.png'),
        ])->assertRedirect();

        $this->publicDisk()->assertMissing($first);
        $this->publicDisk()->assertExists(Setting::find('logo_mark')->value);
    }

    public function test_with_no_database_reachable_the_config_defaults_apply(): void
    {
        // `composer install` on a fresh clone boots the app before any
        // database exists; the cache read must not take the install down.
        Cache::shouldReceive('rememberForever')->andThrow(new \RuntimeException('Database file does not exist.'));

        $this->assertSame([], app(SiteSettings::class)->all());
        app(SiteSettings::class)->apply();
        $this->assertSame(require config_path('agent.php'), config('agent'));
    }

    public function test_guests_cannot_read_or_change_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect(route('admin.login'));
        $this->put('/admin/settings/contact', ['phone' => 'x'])->assertRedirect(route('admin.login'));
        $this->delete('/admin/settings/contact')->assertRedirect(route('admin.login'));

        $this->assertSame(0, Setting::count());
    }

    public function test_assistant_knobs_save_and_apply_to_the_running_config(): void
    {
        $this->actingAs($this->agent)->put('/admin/settings/assistant', [
            'history'         => 6,
            'max_messages'    => 20,
            'retention_days'  => 30,
            'daily_limit'     => 200,
            'effort'          => 'none',
            'max_tool_rounds' => 2,
            'model_enabled'   => '1',
        ])->assertRedirect();

        $this->assertSame(6, config('ai.chat.history'));
        $this->assertSame(20, config('ai.chat.max_messages'));
        $this->assertSame(30, config('ai.chat.retention_days'));
        $this->assertSame(200, config('ai.chat.daily_limit'));
        $this->assertSame('none', config('ai.chat.effort'));
        $this->assertSame(2, config('ai.chat.max_tool_rounds'));
        $this->assertTrue(config('ai.chat.model_enabled'));

        // An unticked box means off, not "leave as it was".
        $this->assertFalse(config('ai.chat.quick_answers'));
    }

    public function test_turning_the_model_off_keeps_the_key_and_serves_rules_only(): void
    {
        $this->app->instance(\App\Support\Ai\AiConnector::class, new \Tests\Support\FakeAiConnector());

        $assistant = app(\App\Services\Assistant\PropertyAssistant::class);
        $this->assertTrue($assistant->modelAvailable());

        $this->actingAs($this->agent)->put('/admin/settings/assistant', [
            'history'         => 12,
            'max_messages'    => 30,
            'retention_days'  => 90,
            'daily_limit'     => 1500,
            'effort'          => 'low',
            'max_tool_rounds' => 4,
            'quick_answers'   => '1',
        ])->assertRedirect();

        $this->assertFalse($assistant->modelAvailable());
        $this->assertTrue(app(\App\Support\Ai\AiConnector::class)->isConfigured());
    }

    public function test_assistant_knobs_are_bounded(): void
    {
        $this->actingAs($this->agent)->put('/admin/settings/assistant', [
            'history'         => 2,
            'max_messages'    => 500,
            'retention_days'  => 1,
            'daily_limit'     => 10,
            'effort'          => 'wild',
            'max_tool_rounds' => 9,
        ])->assertSessionHasErrors(['history', 'max_messages', 'retention_days', 'daily_limit', 'effort', 'max_tool_rounds']);

        $this->assertSame(0, Setting::count());
    }

    public function test_resetting_the_assistant_section_restores_the_file_defaults(): void
    {
        $original = config('ai.chat.history');
        $originalEffort = config('ai.chat.effort');
        $originalRounds = config('ai.chat.max_tool_rounds');

        $this->actingAs($this->agent)->put('/admin/settings/assistant', [
            'history'         => 6,
            'max_messages'    => 20,
            'retention_days'  => 30,
            'daily_limit'     => 200,
            'effort'          => 'medium',
            'max_tool_rounds' => 1,
        ])->assertRedirect();

        $this->assertSame(6, config('ai.chat.history'));
        $this->assertSame('medium', config('ai.chat.effort'));
        $this->assertSame(1, config('ai.chat.max_tool_rounds'));

        $this->actingAs($this->agent)->delete('/admin/settings/assistant')->assertRedirect();

        app(SiteSettings::class)->apply();
        $this->assertSame($original, config('ai.chat.history'));
        $this->assertSame($originalEffort, config('ai.chat.effort'));
        $this->assertSame($originalRounds, config('ai.chat.max_tool_rounds'));
    }

    public function test_the_model_and_driver_cannot_be_overridden_from_the_settings_table(): void
    {
        $model = config('ai.chat.model');
        $driver = config('ai.driver');

        Setting::create(['key' => 'ai.chat.model', 'value' => 'not-a-model']);
        Setting::create(['key' => 'ai.driver', 'value' => 'nope']);
        Cache::forget(SiteSettings::CACHE_KEY);

        app(SiteSettings::class)->apply();

        $this->assertSame($model, config('ai.chat.model'));
        $this->assertSame($driver, config('ai.driver'));
    }
}
