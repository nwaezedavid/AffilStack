<?php

namespace Tests\Feature;

use App\Filament\Resources\SiteLanguages\Pages\CreateSiteLanguage;
use App\Filament\Resources\SiteLanguages\Pages\ListSiteLanguages;
use App\Filament\Resources\Translations\Pages\EditTranslation;
use App\Jobs\ScanSiteForTranslations;
use App\Jobs\TranslatePendingStrings;
use App\Models\CrmCampaign;
use App\Models\Offer;
use App\Models\SiteLanguage;
use App\Models\SiteSetting;
use App\Models\Translation;
use App\Models\User;
use App\Notifications\CrmCampaignPaused;
use App\Services\AI\AIProvider;
use App\Support\Translation\Catalog;
use App\Support\Translation\HtmlTranslator;
use App\Support\Translation\Languages;
use App\Support\Translation\MachineTranslator;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature 6: the website, dashboard, partner portal and emails in French
 * and Spanish. Website pages get their own /fr/… addresses; the dashboard
 * follows the person's chosen language; customers' own data is never sent
 * for translation or stored.
 */
class TranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Queue::fake();
    }

    protected function translate(string $locale, string $source, string $translation, string $status = 'machine'): Translation
    {
        return Translation::create([
            'locale' => $locale,
            'hash' => Translation::hashFor($source),
            'source' => $source,
            'translation' => $translation,
            'status' => $status,
        ]);
    }

    protected function assertNoMarkers(TestResponse|string $response): void
    {
        $content = is_string($response) ? $response : (string) $response->getContent();

        $this->assertDoesNotMatchRegularExpression('/[\x{E000}-\x{E003}]/u', $content);
        $this->assertStringNotContainsString('afs-raw-', $content);
    }

    public function test_a_french_website_page_is_translated_and_links_stay_in_french(): void
    {
        $this->translate('fr', 'Talk To An Expert', 'Parler à un expert');
        $this->translate('fr', 'Plans &amp; pricing · AffilStack', 'Offres et tarifs · AffilStack');

        $response = $this->get('/fr/pricing')->assertOk();
        $content = (string) $response->getContent();

        $this->assertStringContainsString('<html lang="fr"', $content);
        $this->assertStringContainsString('Parler à un expert', $content);
        $this->assertStringContainsString('<title>Offres et tarifs · AffilStack</title>', $content);
        $this->assertStringNotContainsString('Talk To An Expert', $content);

        // Its own address for Google, and links to its other languages.
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/fr/pricing">#', $content);
        $this->assertMatchesRegularExpression('#<link rel="alternate" hreflang="es" href="[^"]*/es/pricing">#', $content);
        $this->assertMatchesRegularExpression('#<link rel="alternate" hreflang="x-default" href="[^"]*/pricing">#', $content);

        // Website links keep the visitor in French.
        $this->assertMatchesRegularExpression('#<a href="[^"]*/fr/faq"#', $content);
        $this->assertDoesNotMatchRegularExpression('#<a href="http://[^/"]+/faq"#', $content);

        // The language switcher links aren't rewritten.
        $this->assertMatchesRegularExpression('#<a href="http://[^/"]+/pricing" hreflang="en"#', $content);

        // Everything not translated yet is queued for Claude.
        $this->assertTrue(Translation::where('locale', 'fr')->where('source', 'Get Started')->where('status', 'pending')->exists());
        Queue::assertPushed(TranslatePendingStrings::class, fn ($job) => $job->locale === 'fr');

        $this->assertNoMarkers($response);
    }

    public function test_english_has_no_prefix(): void
    {
        $this->get('/en/pricing')->assertRedirect('/pricing')->assertStatus(301);
    }

    public function test_an_unknown_language_prefix_is_just_a_missing_page(): void
    {
        $this->get('/zz/pricing')->assertNotFound();
    }

    public function test_english_pages_list_their_other_languages(): void
    {
        $content = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="en"', $content);
        $this->assertMatchesRegularExpression('#<link rel="alternate" hreflang="fr" href="[^"]*/fr/pricing">#', $content);
        $this->assertStringNotContainsString(route('locale.stay'), $content);
    }

    public function test_a_visitor_whose_browser_prefers_french_is_offered_french(): void
    {
        $this->translate('fr', 'Read this page in French?', 'Lire cette page en français ?');

        $response = $this->get('/pricing', ['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.5'])->assertOk();

        $response->assertSee('Lire cette page en français ?');
        $this->assertMatchesRegularExpression('#href="[^"]*/fr/pricing"[^>]*data-locale-link#', (string) $response->getContent());
        $this->assertStringContainsString('Accept-Language', (string) $response->headers->get('Vary'));
        $this->assertStringContainsString('<html lang="en"', (string) $response->getContent());
    }

    public function test_no_suggestion_after_saying_no(): void
    {
        $this->get(route('locale.stay', ['return' => '/pricing']))
            ->assertRedirect('/pricing')
            ->assertCookie(Languages::DISMISSED_COOKIE, '1');

        $this->withCookie(Languages::DISMISSED_COOKIE, '1')
            ->get('/pricing', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertDontSee(route('locale.stay'), false);
    }

    public function test_staying_in_english_never_redirects_off_site(): void
    {
        $this->get(route('locale.stay', ['return' => '//evil.example/x']))->assertRedirect('/');
        $this->get(route('locale.stay', ['return' => 'https://evil.example/x']))->assertRedirect('/');
        $this->get(route('locale.stay', ['return' => '/\\evil.example']))->assertRedirect('/');
    }

    public function test_a_language_prefix_on_a_dashboard_page_remembers_the_language_instead(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/fr/offers')
            ->assertRedirect('/offers')
            ->assertCookie(Languages::COOKIE, 'fr');
    }

    public function test_the_dashboard_follows_the_chosen_language_but_never_translates_customer_data(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        Offer::create([
            'user_id' => $user->id, 'product_name' => 'Zebra Gizmo Deluxe', 'product_url' => 'https://example.com/gizmo',
            'affiliate_network' => 'ShareASale', 'status' => 'ready',
        ]);
        $this->translate('fr', 'Profile &amp; Security', 'Profil et sécurité');

        $response = $this->actingAs($user)->get(route('offers.index'))->assertOk();
        $content = (string) $response->getContent();

        $this->assertStringContainsString('<html lang="fr"', $content);
        $this->assertStringContainsString('Profil et sécurité', $content);
        $this->assertStringContainsString('Zebra Gizmo Deluxe', $content);
        $this->assertNoMarkers($response);

        // The customer's product never reaches the translations table (nor
        // Claude): only AffilStack's own text is queued.
        $this->assertTrue(Translation::where('locale', 'fr')->exists());
        $this->assertFalse(Translation::where('source', 'like', '%Zebra%')->exists());
        $this->assertFalse(Translation::where('source', 'like', '%'.$user->email.'%')->exists());
        $this->assertFalse(Translation::where('source', 'like', '%'.$user->name.'%')->exists());
    }

    public function test_fixed_messages_are_translated_but_messages_with_customer_data_are_not(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $this->translate('fr', 'Password set — welcome to the AffilStack partner program!', 'Mot de passe enregistré !');

        $this->actingAs($user)->withSession(['success' => 'Password set — welcome to the AffilStack partner program!'])
            ->get(route('offers.index'))->assertOk()->assertSee('Mot de passe enregistré !');

        $response = $this->actingAs($user)->withSession(['success' => 'Offer “Zebra Gizmo Deluxe” deleted.'])
            ->get(route('offers.index'))->assertOk()->assertSee('Offer “Zebra Gizmo Deluxe” deleted.');

        $this->assertFalse(Translation::where('source', 'like', '%Zebra%')->exists());
        $this->assertNoMarkers($response);
    }

    public function test_english_dashboard_is_left_alone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('offers.index'))->assertOk();

        $this->assertStringContainsString('Profile & Security', (string) $response->getContent());
        $this->assertSame(0, Translation::count());
        $this->assertNoMarkers($response);
    }

    public function test_the_remembered_language_applies_before_signing_in(): void
    {
        $this->translate('fr', 'Log in', 'Se connecter');

        $response = $this->withCookie(Languages::COOKIE, 'fr')->get(route('login'))->assertOk();

        $this->assertStringContainsString('<html lang="fr"', (string) $response->getContent());
        $this->assertNoMarkers($response);
    }

    public function test_choosing_a_language_saves_it_for_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('offers.index'))
            ->post(route('locale.update'), ['locale' => 'es'])
            ->assertRedirect(route('offers.index'))
            ->assertCookie(Languages::COOKIE, 'es');

        $this->assertSame('es', $user->fresh()->locale);
        $this->assertSame('es', $user->fresh()->preferredLocale());

        $this->actingAs($user)->from(route('offers.index'))->post(route('locale.update'), ['locale' => 'en']);
        $this->assertNull($user->fresh()->locale);
    }

    public function test_choosing_a_language_on_the_website_goes_to_that_page_in_the_language(): void
    {
        $this->from(url('/fr/pricing'))
            ->post(route('locale.update'), ['locale' => 'es'])
            ->assertRedirect(url('/es/pricing'));

        $this->from(url('/pricing'))
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect(url('/pricing'));
    }

    public function test_choosing_a_language_never_redirects_off_site(): void
    {
        $this->from('https://evil.example/phish')
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect('/');
    }

    public function test_only_live_languages_can_be_chosen(): void
    {
        SiteLanguage::where('code', 'es')->update(['status' => 'draft']);
        Languages::flush();

        $this->post(route('locale.update'), ['locale' => 'es'])->assertSessionHasErrors('locale');
        $this->post(route('locale.update'), ['locale' => 'de'])->assertSessionHasErrors('locale');
    }

    public function test_a_draft_language_is_previewable_but_hidden_from_google_and_the_switcher(): void
    {
        SiteLanguage::where('code', 'es')->first()->update(['status' => 'draft']);

        $spanish = (string) $this->get('/es/pricing')->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $spanish);

        $english = (string) $this->get('/pricing')->getContent();
        $this->assertStringNotContainsString('hreflang="es"', $english);
        $this->assertStringContainsString('hreflang="fr"', $english);
    }

    public function test_a_html_fragment_sent_back_as_json_is_translated(): void
    {
        $this->translate('fr', 'Hello there', 'Bonjour');

        $this->app['router']->middleware('web')->get('/_test/fragment', fn () => response()->json(['html' => '<p>Hello there</p>', 'ok' => true]));

        $user = User::factory()->create(['locale' => 'fr']);

        $response = $this->actingAs($user)->getJson('/_test/fragment')->assertOk();

        $this->assertSame('<p>Bonjour</p>', $response->json('html'));
        $this->assertNoMarkers($response);
    }

    public function test_the_sitemap_lists_every_language(): void
    {
        $content = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $content);
        $this->assertMatchesRegularExpression('#<loc>[^<]*/fr/pricing</loc>#', $content);
        $this->assertMatchesRegularExpression('#<xhtml:link rel="alternate" hreflang="es" href="[^"]*/es/pricing"\s*/>#', $content);
        $this->assertSame(1, preg_match_all('#<loc>[^<]*/fr/pricing</loc>#', $content));
    }

    public function test_scanning_collects_the_website_text(): void
    {
        $this->artisan('translations:scan', ['locale' => ['fr']])->assertSuccessful();

        $this->assertTrue(Translation::where('locale', 'fr')->where('source', 'Talk To An Expert')->exists());
        $this->assertFalse(Translation::where('locale', 'es')->exists());
    }

    public function test_emails_to_a_french_customer_are_translated_without_their_data(): void
    {
        config(['mail.default' => 'array']);
        $user = User::factory()->create(['locale' => 'fr']);
        $campaign = CrmCampaign::factory()->create(['user_id' => $user->id, 'name' => 'Quokka Spring Push']);
        $this->translate('fr', 'Fix email sending', 'Réparer l’envoi des e-mails');

        $user->notify(new CrmCampaignPaused($campaign));

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages[0]->getOriginalMessage();
        $html = (string) $email->getHtmlBody();

        $this->assertStringContainsString('Réparer l’envoi des e-mails', $html);
        $this->assertStringContainsString('Quokka Spring Push', $html);
        $this->assertStringContainsString('lang="fr"', $html);
        $this->assertNoMarkers($html);
        $this->assertNoMarkers((string) $email->getTextBody());
        $this->assertNoMarkers((string) $email->getSubject());

        $this->assertTrue(Translation::where('locale', 'fr')->where('source', 'like', '%<v1/>%')->exists());
        $this->assertFalse(Translation::where('source', 'like', '%Quokka%')->exists());
    }

    public function test_emails_in_english_are_untouched(): void
    {
        config(['mail.default' => 'array']);
        $user = User::factory()->create();
        $campaign = CrmCampaign::factory()->create(['user_id' => $user->id, 'name' => 'Quokka Spring Push']);

        $user->notify(new CrmCampaignPaused($campaign));

        $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
        $this->assertStringContainsString('Fix email sending', (string) $email->getHtmlBody());
        $this->assertNoMarkers((string) $email->getHtmlBody());
        $this->assertSame(0, Translation::count());
    }

    public function test_claude_translates_waiting_text_keeping_placeholders(): void
    {
        $good = $this->translate('fr', 'Read our <g1>pricing</g1>', '', 'pending');
        $bad = $this->translate('fr', 'Hello <v1/>', '', 'pending');
        Translation::query()->update(['translation' => null]);
        SiteSetting::set(MachineTranslator::GLOSSARY_SETTING, 'Offer = Offre');

        $ai = $this->mock(AIProvider::class);
        $ai->shouldReceive('generateJson')->times(3)->withArgs(function (string $system, string $user) {
            $items = json_decode($user, true)['items'] ?? [];

            return str_contains($system, 'French') && str_contains($system, 'Offer = Offre') && in_array('Hello <v1/>', $items, true);
        })->andReturnUsing(fn (string $system, string $user) => ['translations' => array_map(
            fn (string $source) => $source === 'Read our <g1>pricing</g1>' ? 'Lisez nos <g1>tarifs</g1>' : 'Bonjour', // dropped <v1/>
            json_decode($user, true)['items'],
        )]);

        $translator = app(MachineTranslator::class);

        $this->assertSame(['translated' => 1, 'failed' => 0, 'remaining' => 1], $translator->run('fr'));
        $this->assertSame('Lisez nos <g1>tarifs</g1>', $good->fresh()->translation);
        $this->assertSame('machine', $good->fresh()->status);
        $this->assertSame('pending', $bad->fresh()->status);

        // A wrong answer is retried, then left for a person.
        $translator->run('fr');
        $this->assertSame(['translated' => 0, 'failed' => 1, 'remaining' => 0], $translator->run('fr'));
        $this->assertSame('failed', $bad->fresh()->status);
        $this->assertNull($bad->fresh()->translation);
    }

    public function test_nothing_is_sent_to_claude_while_paused(): void
    {
        $this->translate('fr', 'Hello', '', 'pending');
        SiteSetting::setFlag(MachineTranslator::PAUSED_SETTING, true);

        $this->mock(AIProvider::class)->shouldNotReceive('generateJson');

        $this->assertSame(0, app(MachineTranslator::class)->run('fr')['translated']);
    }

    public function test_the_background_job_keeps_going_while_it_makes_progress(): void
    {
        $translator = $this->mock(MachineTranslator::class);
        $translator->shouldReceive('run')->twice()->andReturn(
            ['translated' => 40, 'failed' => 0, 'remaining' => 12],
            ['translated' => 0, 'failed' => 3, 'remaining' => 3],
        );

        (new TranslatePendingStrings('fr'))->handle($translator);
        Queue::assertPushed(TranslatePendingStrings::class, 1);

        (new TranslatePendingStrings('fr'))->handle($translator);
        Queue::assertPushed(TranslatePendingStrings::class, 1);
    }

    public function test_the_catalog_knows_affilstacks_own_text_only(): void
    {
        $this->assertTrue(Catalog::has('Profile & Security'));
        $this->assertTrue(Catalog::has('Fix email sending'));
        $this->assertFalse(Catalog::has('Zebra Gizmo Deluxe'));
        $this->assertFalse(Catalog::has('https://example.com'));
    }

    public function test_admin_adding_a_language_scans_the_website(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(CreateSiteLanguage::class)
            ->fillForm(['code' => 'de', 'status' => 'draft', 'name' => 'German', 'native_name' => 'Deutsch', 'sort_order' => 3])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Languages::exists('de'));
        $this->assertFalse(Languages::isLive('de'));
        Queue::assertPushed(ScanSiteForTranslations::class, fn ($job) => $job->locales === ['de']);
    }

    public function test_a_language_code_cannot_clash_with_a_website_address(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(CreateSiteLanguage::class)
            ->fillForm(['code' => 'fr', 'status' => 'draft', 'name' => 'French', 'native_name' => 'Français'])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    public function test_admin_editing_a_translation_keeps_its_placeholders_and_marks_it_reviewed(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $row = $this->translate('fr', 'Read our <g1>pricing</g1>', 'Lisez nos <g1>tarifs</g1>');

        Livewire::actingAs($admin)
            ->test(EditTranslation::class, ['record' => $row->getRouteKey()])
            ->fillForm(['translation' => 'Lisez nos tarifs'])
            ->call('save')
            ->assertHasFormErrors(['translation']);

        Livewire::actingAs($admin)
            ->test(EditTranslation::class, ['record' => $row->getRouteKey()])
            ->fillForm(['translation' => "Découvrez  nos\n<g1>tarifs</g1>"])
            ->call('save')
            ->assertHasNoFormErrors();

        $row->refresh();
        $this->assertSame('Découvrez nos <g1>tarifs</g1>', $row->translation);
        $this->assertSame('reviewed', $row->status);
    }

    public function test_admin_translation_settings_save_the_glossary_and_pause(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(ListSiteLanguages::class)
            ->callAction('settings', ['glossary' => 'Offer = Offre', 'paused' => true])
            ->assertHasNoActionErrors();

        $this->assertSame('Offer = Offre', SiteSetting::get(MachineTranslator::GLOSSARY_SETTING));
        $this->assertTrue(MachineTranslator::paused());
    }

    public function test_translation_never_breaks_a_page(): void
    {
        $this->assertTrue(HtmlTranslator::available());

        // A translation that dropped a placeholder is ignored, not shown.
        $this->translate('fr', 'Talk To An Expert', 'Parler <g1>à</g1> un expert');

        $response = $this->get('/fr/pricing')->assertOk();

        $response->assertSee('Talk To An Expert');
        $this->assertNoMarkers($response);
    }
}
