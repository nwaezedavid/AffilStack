<?php

use App\Http\Controllers\Admin\AnalyticsExportController;
use App\Http\Controllers\AffiliateBannerController;
use App\Http\Controllers\AnalyticsBeaconController;
use App\Http\Controllers\Api\WordPress\ConnectorController as WordPressConnectorController;
use App\Http\Controllers\BannerEmbedController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BlogNewsletterConfirmController;
use App\Http\Controllers\CheckoutCountryController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreativeTaskPreviewController;
use App\Http\Controllers\CreditTopupController;
use App\Http\Controllers\CrmEmailTrackingController;
use App\Http\Controllers\Dashboard\ApiAccessController;
use App\Http\Controllers\Dashboard\ApiWalletController;
use App\Http\Controllers\Dashboard\BlogController;
use App\Http\Controllers\Dashboard\CompetitorAngleController;
use App\Http\Controllers\Dashboard\ContentCalendarController;
use App\Http\Controllers\Dashboard\CrmCampaignController;
use App\Http\Controllers\Dashboard\CrmController;
use App\Http\Controllers\Dashboard\CrmImportController;
use App\Http\Controllers\Dashboard\CrmInboxController;
use App\Http\Controllers\Dashboard\CrmListController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\EarningsController;
use App\Http\Controllers\Dashboard\EmailConnectionController;
use App\Http\Controllers\Dashboard\EmailNurtureController;
use App\Http\Controllers\Dashboard\ExtensionController;
use App\Http\Controllers\Dashboard\IntelligenceCentreController;
use App\Http\Controllers\Dashboard\LeadFinderController;
use App\Http\Controllers\Dashboard\LinkController as DashboardLinkController;
use App\Http\Controllers\Dashboard\LinkedInController;
use App\Http\Controllers\Dashboard\LinkedInReplyAssistantController;
use App\Http\Controllers\Dashboard\LocalizationController;
use App\Http\Controllers\Dashboard\OfferController;
use App\Http\Controllers\Dashboard\OfferFeatureController;
use App\Http\Controllers\Dashboard\OnboardingController;
use App\Http\Controllers\Dashboard\PinterestController;
use App\Http\Controllers\Dashboard\ReferralController as DashboardReferralController;
use App\Http\Controllers\Dashboard\SocialConnectionController;
use App\Http\Controllers\Dashboard\SocialPublishController;
use App\Http\Controllers\Dashboard\SupportChatController;
use App\Http\Controllers\Dashboard\SupportTicketController;
use App\Http\Controllers\Dashboard\SwipeFileController;
use App\Http\Controllers\Dashboard\TeamController;
use App\Http\Controllers\Dashboard\TikTokController;
use App\Http\Controllers\Dashboard\UgcController;
use App\Http\Controllers\Dashboard\WordPressController;
use App\Http\Controllers\Dashboard\WordPressSendController;
use App\Http\Controllers\Dashboard\XController;
use App\Http\Controllers\Dashboard\YouTubeController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\FeaturePageController;
use App\Http\Controllers\FlutterwaveWebhookController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GoogleSiteAnalyticsController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LifecycleEmailPreferenceController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\MobileAppRedirectController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Partner\PayoutsController as PartnerPayoutsController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedirectFallbackController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\TutorialController;
use App\Http\Middleware\EnsurePanelLoginCompleted;
use App\Http\Middleware\RejectCrossSiteBeacon;
use App\Support\SiteDomains;
use Filament\Facades\Filament;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Audit item #7 (caching/performance) — every page in this group has no
// per-visitor content and no form to CSRF-protect, so a browser/CDN is
// allowed to reuse a response for a few minutes instead of hitting Laravel
// on every visit. See CachePublicPage — it still backs off automatically
// whenever a flash message is present. /contact is deliberately kept out
// of this group; it has a form.
SiteDomains::marketing(function () {
    Route::middleware('cache-public-page')->group(function () {
        Route::view('/', 'marketing.home')->name('home');
        Route::get('/help', [HelpController::class, 'index'])->name('help.index');
        Route::get('/learn', [TutorialController::class, 'index'])->name('tutorials.index');

        // Static, admin-editable pages — content lives in the site_pages table
        // (Filament: Content > Site Pages) so legal copy can be updated without
        // a code deploy.
        Route::get('/about', [PageController::class, 'show'])->defaults('slug', 'about')->name('about');
        Route::get('/terms', [PageController::class, 'show'])->defaults('slug', 'terms')->name('terms');
        Route::get('/privacy', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('privacy');
        Route::get('/refund-policy', [PageController::class, 'show'])->defaults('slug', 'refund-policy')->name('refund-policy');
        Route::get('/cookie-policy', [PageController::class, 'show'])->defaults('slug', 'cookie-policy')->name('cookie-policy');
    });
});

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
SiteDomains::marketing(function () {
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
});

// --- Agent 02: mobile apps smart link -------------------------------------
// /app sends Android visitors to Google Play, iPhone/iPad visitors to the App
// Store and everyone else to the homepage apps section (Content → Mobile
// Apps). Registered before the /{slug} catch-all below so it isn't swallowed.
SiteDomains::marketing(function () {
    Route::get('/app', MobileAppRedirectController::class)->name('mobile-app.redirect');
});
// --- End agent 02 ------------------------------------------------------------

// The ONLY admin URL anyone should ever need to type or bookmark. Every
// other admin screen lives under /afs-admin/* (see AdminPanelProvider's
// ->path()) — this route's one job is to be a stable front door into that
// panel's login screen. Built from Filament::getPanel('admin')->getLoginUrl()
// rather than a hardcoded '/afs-admin/login' string so it can never drift
// out of sync if the panel path or login slug ever changes again. Must be
// registered before the generic /{slug} SitePage route further down, or
// this would 404 through PageController::show instead.
SiteDomains::app(function () {
    Route::get('/afs-login', fn () => redirect()->to(Filament::getPanel('admin')->getLoginUrl()))
        ->name('admin.login-redirect');
});

// app.affilstack.com/ on its own: straight to the dashboard (the login page
// for anyone signed out). Only exists when the app has its own host; on a
// single host "/" is the homepage.
if (SiteDomains::isSplit()) {
    SiteDomains::app(function () {
        Route::get('/', fn () => redirect()->route('dashboard'))->name('app.home');
    });
}

// Manual override for CheckoutCountryResolver's auto-detected country —
// public, no auth needed: it's used from the pre-signup pricing/signup
// pages as much as from the logged-in billing page.
Route::post('/checkout-country', [CheckoutCountryController::class, 'update'])->name('checkout.country.update');

SiteDomains::marketing(function () {
    Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1,contact.store')->name('contact.store');
});

// Public, unauthenticated — whoever clicks a cloaked link is the offer's own
// audience, not an AffilStack user. See LinkCloakingService.
// Throttled per visitor IP: every hit writes a click row and queues a geo lookup.
Route::get('/go/{code}', [LinkController::class, 'redirect'])->middleware('throttle:60,1,links.redirect')->name('links.redirect');

// Public, unauthenticated — whoever clicks a referral link is a prospective
// signup, not an AffilStack user yet. See ReferralController.
Route::get('/r/{code}', [ReferralController::class, 'redirect'])->middleware('throttle:60,1,referrals.redirect')->name('referrals.redirect');

// CRM/email dashboard: public, unauthenticated — the open pixel and click
// redirect are hit by the CRM contact's own email client, never by an
// AffilStack user, and the unsubscribe link must work without logging in.
// See CrmEmailService/CrmEmailTrackingController.
Route::get('/e/o/{token}.png', [CrmEmailTrackingController::class, 'open'])->name('crm.track.open');
Route::get('/e/c/{token}', [CrmEmailTrackingController::class, 'click'])->name('crm.track.click');
Route::get('/crm/unsubscribe/{token}', [CrmController::class, 'unsubscribe'])->name('crm.unsubscribe');

// First-party page-view beacon (item #4, statistics area) — fired from
// partials/analytics-beacon.blade.php on every marketing/dashboard page
// load. Excluded from CSRF in bootstrap/app.php alongside the webhooks,
// for the same "can't always guarantee a fresh token" reason.
// Agent 04: + RejectCrossSiteBeacon (refuses cross-site posts, since these skip CSRF).
Route::middleware(['throttle:120,1,analytics-beacons', RejectCrossSiteBeacon::class])->group(function () {
    Route::post('/internal/analytics/view', [AnalyticsBeaconController::class, 'record'])->name('analytics.beacon.view');
    Route::post('/internal/analytics/duration', [AnalyticsBeaconController::class, 'duration'])->name('analytics.beacon.duration');
});

Route::post('/webhooks/flutterwave', [FlutterwaveWebhookController::class, 'handle'])->middleware('throttle:120,1,webhooks.flutterwave')->name('webhooks.flutterwave');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->middleware('throttle:120,1,webhooks.stripe')->name('webhooks.stripe');
Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])->middleware('throttle:120,1,webhooks.paystack')->name('webhooks.paystack');
Route::post('/webhooks/paypal', [PayPalWebhookController::class, 'handle'])->middleware('throttle:120,1,webhooks.paypal')->name('webhooks.paypal');

// The only door into a CUSTOMER account: pick a plan, pay, get created. No
// open registration exists anywhere in this app — see RegistrationController.
// (The one other way an account gets created is an approved affiliate
// application — see AffiliateApplicationService::approve() — and that one
// still requires manual admin review, never self-service.)
//
// /get-started/callback MUST be registered before /get-started/{plan} —
// both are GET requests under the same prefix, and Laravel matches routes
// in registration order, so a wildcard registered first would swallow
// "callback" as a {plan} route-model-binding lookup and 404 on every real
// payment redirect. (Caught by tests/Feature/PaymentSignupFlowTest.)
// Audit item #7 (caching/performance) — see the cache-public-page group
// above; applied individually here since this route sits in its own
// ordering-sensitive block (see the comment above).
SiteDomains::marketing(function () {
    Route::middleware('cache-public-page')->get('/pricing', [RegistrationController::class, 'pricing'])->name('registration.pricing');
});
SiteDomains::app(function () {
    Route::get('/get-started/callback', [RegistrationController::class, 'callback'])->middleware('throttle:30,1,registration.callback')->name('registration.callback');
    Route::get('/get-started/{plan}', [RegistrationController::class, 'showForm'])->name('registration.form');
    Route::post('/get-started/{plan}', [RegistrationController::class, 'store'])->middleware('throttle:10,1,registration.store')->name('registration.store');
    Route::post('/get-started/{plan}/google', [GoogleAuthController::class, 'redirectForSignup'])->name('registration.google');
});

// The affiliate program — public landing page, partner sign-in and the
// Partner Portal — on affiliate.affilstack.com (AFFILIATE_SUBDOMAIN), or
// under /affiliate on the website when no subdomain is set. Same route
// names either way. See routes/partner.php.
require __DIR__.'/partner.php';

// "Continue with Google" — one callback for both the login page and the
// signup form; see GoogleAuthController for how it tells them apart.
SiteDomains::app(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirectForLogin'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

// Tony (the Creative Agent) preview — lets an admin see exactly what a
// pending creative AgentTask would look like live, rendered through the
// real public templates, before a super-admin ever approves it. Admin-only
// (checked inside the controller); deliberately outside the dashboard
// group above since it has nothing to do with a user's own account. See
// CreativeTaskPreviewController — nothing here writes to the database.
SiteDomains::app(function () {
    Route::middleware(['auth', EnsurePanelLoginCompleted::class])->get('/admin-preview/creative-tasks/{agentTask}', [CreativeTaskPreviewController::class, 'show'])->name('creative-tasks.preview');

    Route::middleware(['auth', EnsurePanelLoginCompleted::class])->group(function () {
        Route::get('/admin/google-site-analytics/connect', [GoogleSiteAnalyticsController::class, 'redirect'])->name('google-site-analytics.connect');
        Route::get('/admin/google-site-analytics/callback', [GoogleSiteAnalyticsController::class, 'callback'])->name('google-site-analytics.callback');
    });

    Route::middleware(['auth', 'verified', 'not-suspended', 'restrict-agency-seats', 'restrict-affiliate-only'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::delete('/billing/scheduled-change', [BillingController::class, 'cancelScheduledChange'])->name('billing.cancel-scheduled-change');
        Route::get('/billing/callback', [BillingController::class, 'callback'])->name('billing.callback');
        Route::post('/billing/refund', [BillingController::class, 'requestRefund'])->name('billing.request-refund');

        // "Users should be able to buy more credit tokens if their monthly
        // allocation finishes" — a one-time purchase, entirely separate from
        // the plan-checkout flow above. Reuses billing.callback for the actual
        // payment confirmation (see BillingController::callback() and
        // PaymentProcessor::process()'s 'credit_topup' branch) — only the
        // checkout-initiation step needed a new route/controller.
        Route::get('/credits/top-up', [CreditTopupController::class, 'index'])->name('credit-topups.index');
        Route::post('/credits/top-up/{package}/checkout', [CreditTopupController::class, 'checkout'])->name('credit-topups.checkout');

        // "I want users to be able to submit their review from their dashboard"
        // — one testimonial per account; see TestimonialController for why a
        // re-submission always goes back to pending review.
        Route::get('/my-review', [TestimonialController::class, 'edit'])->name('testimonial.edit');
        Route::post('/my-review', [TestimonialController::class, 'update'])->name('testimonial.update');

        // Saved payment methods (audit item #2) — captured passively, see
        // PaymentMethodRecorder; these two actions are all a user can do here.
        Route::patch('/billing/payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.set-default');
        Route::delete('/billing/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

        // Task #7: the "Intelligence Centre" AI self-assessment dashboard — see
        // IntelligenceCentreService. Owner-only, like billing/CRM/earnings.
        Route::get('/intelligence-centre', [IntelligenceCentreController::class, 'index'])->name('intelligence-centre.index');
        Route::post('/intelligence-centre', [IntelligenceCentreController::class, 'store'])->middleware('throttle:paid-lookups')->name('intelligence-centre.store');

        Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
        Route::get('/offers/create', [OfferController::class, 'create'])->name('offers.create');
        Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
        Route::get('/offers/{offer}', [OfferController::class, 'show'])->name('offers.show');
        Route::patch('/offers/{offer}/disclosure', [OfferController::class, 'updateDisclosure'])->name('offers.disclosure.update');

        Route::post('/offers/{offer}/blog-article', [BlogController::class, 'store'])->name('offers.blog.store');

        Route::post('/offers/{offer}/competitor-angles', [CompetitorAngleController::class, 'scan'])->name('offers.competitor.scan');

        Route::post('/offers/{offer}/linkedin/keywords', [LinkedInController::class, 'keywords'])->name('offers.linkedin.keywords');
        Route::post('/offers/{offer}/linkedin/dm-sequence', [LinkedInController::class, 'dmSequence'])->name('offers.linkedin.dm');
        Route::post('/offers/{offer}/linkedin/post', [LinkedInController::class, 'post'])->name('offers.linkedin.post');
        Route::post('/offers/{offer}/linkedin/article', [LinkedInController::class, 'article'])->name('offers.linkedin.article');
        Route::get('/offers/{offer}/linkedin/reply-assistant', [LinkedInReplyAssistantController::class, 'index'])->name('offers.linkedin.reply-assistant');
        Route::post('/offers/{offer}/linkedin/reply-assistant', [LinkedInReplyAssistantController::class, 'store'])->middleware('throttle:paid-lookups')->name('offers.linkedin.reply-assistant.store');
        Route::post('/generations/{generation}/nurture-started', [ContentCalendarController::class, 'markSequenceStarted'])->name('generations.nurture-started');
        Route::post('/generations/{generation}/localize', [LocalizationController::class, 'store'])->middleware('throttle:paid-lookups')->name('generations.localize');
        Route::get('/generations/{generation}/linkedin/export', [LinkedInController::class, 'export'])->name('generations.linkedin.export');

        Route::post('/offers/{offer}/youtube/script', [YouTubeController::class, 'script'])->name('offers.youtube.script');
        Route::post('/offers/{offer}/youtube/metadata', [YouTubeController::class, 'metadata'])->name('offers.youtube.metadata');

        Route::post('/offers/{offer}/ugc/angles', [UgcController::class, 'angles'])->name('offers.ugc.angles');
        Route::post('/offers/{offer}/ugc/content', [UgcController::class, 'content'])->name('offers.ugc.content');
        Route::post('/offers/{offer}/ugc/video', [UgcController::class, 'video'])->name('offers.ugc.video');

        Route::post('/offers/{offer}/x/thread', [XController::class, 'thread'])->name('offers.x.thread');

        Route::post('/offers/{offer}/tiktok/video', [TikTokController::class, 'video'])->name('offers.tiktok.video');

        Route::post('/offers/{offer}/pinterest/pins', [PinterestController::class, 'pins'])->name('offers.pinterest.pins');

        Route::post('/offers/{offer}/nurture/generate', [EmailNurtureController::class, 'generate'])->name('offers.nurture.generate');
        Route::post('/generations/{generation}/nurture/send', [EmailNurtureController::class, 'send'])->name('generations.nurture.send');

        Route::get('/crm', [CrmController::class, 'index'])->name('crm.index');
        Route::post('/crm', [CrmController::class, 'store'])->name('crm.store');
        Route::patch('/crm/{contact}', [CrmController::class, 'update'])->name('crm.update');
        Route::delete('/crm/{contact}', [CrmController::class, 'destroy'])->name('crm.destroy');
        Route::get('/crm-export', [CrmController::class, 'export'])->name('crm.export');

        // Task #1: connect Gmail/SMTP for CRM nurture sending. Owner-only,
        // like the rest of CRM — see config('agency.seat_allowed_routes').
        Route::get('/email-connections', [EmailConnectionController::class, 'index'])->name('email-connections.index');
        Route::get('/email-connections/gmail/redirect', [EmailConnectionController::class, 'redirectToGoogle'])->name('email-connections.gmail.redirect');
        Route::get('/email-connections/gmail/callback', [EmailConnectionController::class, 'handleGoogleCallback'])->name('email-connections.gmail.callback');
        Route::post('/email-connections/smtp', [EmailConnectionController::class, 'storeSmtp'])->middleware('throttle:10,1,email-connections.smtp.store')->name('email-connections.smtp.store');
        Route::delete('/email-connections', [EmailConnectionController::class, 'disconnect'])->name('email-connections.destroy');

        // Task #3 (LinkedIn) + task #2 (YouTube/TikTok/Instagram): one
        // "Connected Accounts" hub for every user-owned social OAuth
        // connection — see SocialConnectionController.
        Route::get('/social-connections', [SocialConnectionController::class, 'index'])->name('social-connections.index');
        Route::get('/social-connections/{provider}/redirect', [SocialConnectionController::class, 'redirectToProvider'])->name('social-connections.redirect');
        Route::get('/social-connections/{provider}/callback', [SocialConnectionController::class, 'callback'])->name('social-connections.callback');
        Route::delete('/social-connections/{provider}', [SocialConnectionController::class, 'disconnect'])->name('social-connections.destroy');

        // Task #2: "Publish to X" on a rendered ugc_video generation — see
        // SocialPublishController and each SocialPublishProvider implementation.
        Route::post('/generations/{generation}/publish/{provider}', [SocialPublishController::class, 'store'])->name('generations.publish');
        Route::post('/generations/{generation}/wordpress', [WordPressSendController::class, 'store'])->middleware('throttle:30,1,generations.wordpress')->name('generations.wordpress');

        // The AffilStack Connector WordPress plugin — see WordPressController.
        Route::get('/wordpress', [WordPressController::class, 'index'])->name('wordpress.index');
        Route::get('/wordpress/plugin/download', [WordPressController::class, 'download'])->name('wordpress.download');
        Route::get('/wordpress/connect', [WordPressController::class, 'connect'])->name('wordpress.connect');
        Route::post('/wordpress/connect', [WordPressController::class, 'approve'])->middleware('throttle:20,1,wordpress.approve')->name('wordpress.approve');
        Route::patch('/wordpress/sites/{site}', [WordPressController::class, 'update'])->name('wordpress.sites.update');
        Route::post('/wordpress/sites/{site}/test', [WordPressController::class, 'test'])->middleware('throttle:20,1,wordpress.test')->name('wordpress.sites.test');
        Route::delete('/wordpress/sites/{site}', [WordPressController::class, 'destroy'])->name('wordpress.sites.destroy');

        Route::get('/leads', [LeadFinderController::class, 'index'])->middleware('throttle:paid-lookups')->name('leads.index');
        Route::post('/leads/search', [LeadFinderController::class, 'search'])->middleware('throttle:paid-lookups')->name('leads.search');
        Route::post('/leads/import', [LeadFinderController::class, 'import'])->middleware('throttle:paid-lookups')->name('leads.import');

        Route::get('/links', [DashboardLinkController::class, 'index'])->name('links.index');
        Route::get('/links/{trackedLink}', [DashboardLinkController::class, 'show'])->name('links.show');

        Route::get('/earnings', [EarningsController::class, 'index'])->name('earnings.index');
        Route::post('/earnings', [EarningsController::class, 'storeManual'])->name('earnings.store');
        Route::post('/earnings/import', [EarningsController::class, 'storeImport'])->name('earnings.import');
        Route::patch('/earnings/{earning}/assign-offer', [EarningsController::class, 'assignOffer'])->name('earnings.assign-offer');
        Route::delete('/earnings/{earning}', [EarningsController::class, 'destroy'])->name('earnings.destroy');

        Route::get('/referrals', [DashboardReferralController::class, 'index'])->name('referrals.index');
        Route::post('/referrals/payout-method', [DashboardReferralController::class, 'savePayoutMethod'])->name('referrals.payout-method');
        Route::post('/referrals/payout-method/resolve', [PartnerPayoutsController::class, 'resolveAccount'])->middleware('throttle:15,1,referrals.payout-resolve')->name('referrals.payout-resolve');
        Route::post('/referrals/payout', [DashboardReferralController::class, 'requestPayout'])->name('referrals.payout');

        Route::get('/calendar', [ContentCalendarController::class, 'index'])->name('calendar.index');
        Route::patch('/calendar/{generation}', [ContentCalendarController::class, 'update'])->name('calendar.update');

        Route::get('/swipe-files', [SwipeFileController::class, 'index'])->name('swipe-files.index');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('/team/{seat}', [TeamController::class, 'destroy'])->name('team.destroy');

        // Browser capture extension (item 11) — owner-only, deliberately absent
        // from config('agency.seat_allowed_routes'): see ExtensionController.
        Route::get('/extension', [ExtensionController::class, 'index'])->name('extension.index');
        Route::post('/extension/tokens', [ExtensionController::class, 'createToken'])->name('extension.tokens.store');
        Route::delete('/extension/tokens/{token}', [ExtensionController::class, 'revokeToken'])->name('extension.tokens.destroy');
        Route::patch('/extension/clips/{clip}', [ExtensionController::class, 'attachClip'])->name('extension.clips.attach');
        Route::delete('/extension/clips/{clip}', [ExtensionController::class, 'destroyClip'])->name('extension.clips.destroy');
        Route::get('/extension/download', [ExtensionController::class, 'download'])->name('extension.download');
        Route::get('/extension/store-package', [ExtensionController::class, 'storePackage'])->name('extension.store-package');

        // The general-purpose API's dashboard side (task #6) — deliberately
        // available to team seats (both isolated and shared), unlike the
        // extension above: see config('agency.seat_allowed_routes').
        Route::get('/api-access', [ApiAccessController::class, 'index'])->name('api-access.index');
        Route::post('/api-access/tokens', [ApiAccessController::class, 'createToken'])->name('api-access.tokens.store');
        Route::delete('/api-access/tokens/{token}', [ApiAccessController::class, 'revokeToken'])->name('api-access.tokens.destroy');

        // Outbound webhooks (audit gap #7) — deliberately NOT added to
        // config('agency.seat_allowed_routes'): owner-only, same as Referrals
        // and Earnings, two of the four events a webhook can subscribe to.
        Route::post('/api-access/webhooks', [ApiAccessController::class, 'storeWebhook'])->name('api-access.webhooks.store');
        Route::patch('/api-access/webhooks/{webhook}/toggle', [ApiAccessController::class, 'toggleWebhook'])->name('api-access.webhooks.toggle');
        Route::delete('/api-access/webhooks/{webhook}', [ApiAccessController::class, 'destroyWebhook'])->name('api-access.webhooks.destroy');
        // API roadmap item #10 — resend one past delivery as a new one.
        Route::post('/api-access/webhooks/deliveries/{delivery}/replay', [ApiAccessController::class, 'replayWebhookDelivery'])->name('api-access.webhooks.deliveries.replay');

        // API usage prepay wallet — owner-only just like outbound webhooks
        // above (a seat's own metered API calls still draw from the owner's
        // wallet automatically — see ApiWalletManager's billableUser()
        // resolution — so only the owner should fund or configure it),
        // deliberately NOT added to config('agency.seat_allowed_routes')
        // either, same reasoning as the webhook routes.
        Route::post('/api-access/wallet/checkout', [ApiWalletController::class, 'checkout'])->name('api-access.wallet.checkout');
        Route::patch('/api-access/wallet/settings', [ApiWalletController::class, 'updateSettings'])->name('api-access.wallet.settings');

        // --- Relaunch (agent 07): live connection checks + API hub extras ---
        // "Test connection" on Connected Accounts (seat-allowed, like the rest
        // of that page); a sample delivery to one webhook endpoint (owner-only,
        // like the other webhook routes); SDK downloads for the API hub.
        Route::post('/social-connections/{provider}/verify', [SocialConnectionController::class, 'verify'])->middleware('throttle:20,1,social-connections.verify')->name('social-connections.verify');
        Route::post('/api-access/webhooks/{webhook}/test', [ApiAccessController::class, 'testWebhook'])->middleware('throttle:10,1,api-access.webhooks.test')->name('api-access.webhooks.test');
        Route::get('/api-access/sdk/{language}', [ApiAccessController::class, 'downloadSdk'])->whereIn('language', ['js', 'python'])->name('api-access.sdk');
        // --- end agent 07 ---

        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

        Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
        Route::get('/support/create', [SupportTicketController::class, 'create'])->name('support.create');
        Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');

        // These fixed /support/chat* segments must be registered before the
        // /support/{ticket} wildcard below, or "chat" gets swallowed as a
        // ticket ID and 404s on route-model binding.
        Route::get('/support/chat', [SupportChatController::class, 'show'])->name('support.chat');
        Route::post('/support/chat/message', [SupportChatController::class, 'message'])->middleware('throttle:support-chat')->name('support.chat.message');
        Route::post('/support/chat/escalate', [SupportChatController::class, 'escalate'])->middleware('throttle:5,60,support.chat.escalate')->name('support.chat.escalate');

        // --- Agent 10: Get Started journey — research status polling, the
        // dedicated per-feature product pages, and the welcome tour. Seat
        // access for these is granted in config('agency.seat_allowed_routes').
        Route::get('/offers/{offer}/research-status', [OfferController::class, 'researchStatus'])->name('offers.research.status');
        Route::post('/offers/{offer}/research/retry', [OfferController::class, 'retryResearch'])->name('offers.research.retry');
        Route::get('/offers/{offer}/features/{feature}', [OfferFeatureController::class, 'show'])->name('offers.feature');
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
        Route::post('/onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
        Route::get('/onboarding/restart', [OnboardingController::class, 'restart'])->name('onboarding.restart');
        // --- end Agent 10

        Route::get('/support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
        Route::post('/support/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');
        Route::post('/support/{ticket}/rate', [SupportTicketController::class, 'rate'])->name('support.rate');
    });

    // ---------------------------------------------------------------------
    // Agent 08 — CRM lists, campaigns (with autopilot), the replies Inbox and
    // the Email Sending connection checks. Same middleware stack as the
    // dashboard group above; owner-only like the rest of the CRM (none of
    // these are in config('agency.seat_allowed_routes')).
    // ---------------------------------------------------------------------
    Route::middleware(['auth', 'verified', 'not-suspended', 'restrict-agency-seats', 'restrict-affiliate-only'])->group(function () {
        Route::post('/crm/contacts/bulk', [CrmController::class, 'bulk'])->name('crm.contacts.bulk');

        Route::post('/crm/lists', [CrmListController::class, 'store'])->name('crm.lists.store');
        Route::get('/crm/lists/{crmList}', [CrmListController::class, 'show'])->name('crm.lists.show');
        Route::patch('/crm/lists/{crmList}', [CrmListController::class, 'update'])->name('crm.lists.update');
        Route::delete('/crm/lists/{crmList}', [CrmListController::class, 'destroy'])->name('crm.lists.destroy');

        Route::post('/crm/import', [CrmImportController::class, 'upload'])->middleware('throttle:20,1,crm.import.upload')->name('crm.import.upload');
        Route::get('/crm/import/{token}', [CrmImportController::class, 'preview'])->name('crm.import.preview');
        Route::post('/crm/import/{token}', [CrmImportController::class, 'store'])->name('crm.import.store');

        Route::get('/crm/campaigns', [CrmCampaignController::class, 'index'])->name('crm.campaigns.index');
        Route::get('/crm/campaigns/create', [CrmCampaignController::class, 'create'])->name('crm.campaigns.create');
        Route::post('/crm/campaigns', [CrmCampaignController::class, 'store'])->middleware('throttle:paid-lookups')->name('crm.campaigns.store');
        Route::get('/crm/campaigns/{campaign}', [CrmCampaignController::class, 'show'])->name('crm.campaigns.show');
        Route::put('/crm/campaigns/{campaign}', [CrmCampaignController::class, 'update'])->name('crm.campaigns.update');
        Route::delete('/crm/campaigns/{campaign}', [CrmCampaignController::class, 'destroy'])->name('crm.campaigns.destroy');
        Route::post('/crm/campaigns/{campaign}/send-new', [CrmCampaignController::class, 'sendToNew'])->name('crm.campaigns.send-new');
        Route::patch('/crm/campaigns/{campaign}/autopilot', [CrmCampaignController::class, 'autopilot'])->name('crm.campaigns.autopilot');
        Route::post('/crm/campaigns/{campaign}/pause', [CrmCampaignController::class, 'pause'])->name('crm.campaigns.pause');
        Route::post('/crm/campaigns/{campaign}/resume', [CrmCampaignController::class, 'resume'])->name('crm.campaigns.resume');

        Route::get('/crm/inbox', [CrmInboxController::class, 'index'])->name('crm.inbox');
        Route::post('/crm/inbox/check', [CrmInboxController::class, 'check'])->middleware('throttle:6,1,crm.inbox.check')->name('crm.inbox.check');
        Route::get('/crm/inbox/{contact}', [CrmInboxController::class, 'show'])->name('crm.inbox.show');
        Route::post('/crm/inbox/replies/{reply}/draft', [CrmInboxController::class, 'draft'])->middleware('throttle:paid-lookups')->name('crm.inbox.draft');
        Route::post('/crm/inbox/replies/{reply}/send', [CrmInboxController::class, 'send'])->middleware('throttle:30,1,crm.inbox.send')->name('crm.inbox.send');

        Route::post('/email-connections/test', [EmailConnectionController::class, 'test'])->middleware('throttle:10,1,email-connections.test')->name('email-connections.test');
        Route::post('/email-connections/imap', [EmailConnectionController::class, 'storeImap'])->middleware('throttle:10,1,email-connections.imap.store')->name('email-connections.imap.store');
        Route::delete('/email-connections/imap', [EmailConnectionController::class, 'destroyImap'])->name('email-connections.imap.destroy');
        Route::patch('/email-connections/settings', [EmailConnectionController::class, 'updateSettings'])->name('email-connections.settings');
    });
});

// --- Agent 11: public feature pages + dedicated FAQ page -------------------
// Registered before the generic /{slug} SitePage route below, which would
// otherwise swallow /features and /faq. Content: Website → Features / FAQ.
SiteDomains::marketing(function () {
    Route::middleware('cache-public-page')->group(function () {
        Route::get('/features', [FeaturePageController::class, 'index'])->name('features.index');
        Route::get('/features/{slug}', [FeaturePageController::class, 'show'])
            ->where('slug', '[a-z0-9-]{1,100}')
            ->name('features.show');
        Route::get('/faq', [FaqController::class, 'index'])->name('faq.index');
    });
    // --- end Agent 11 ----------------------------------------------------------

    // Any admin-created SitePage whose slug isn't one of the five dedicated
    // routes above (About/Terms/Privacy/Refund Policy/Cookie Policy) lands
    // here — the Filament "Site Pages" form accepts any free-text slug, so
    // without this a brand-new page would have no live URL at all. Registered
    // this late (right before the final fallback, after every other
    // single-segment route in this file) so it can never shadow a real route
    // like /help, /pricing, or /dashboard — Laravel matches routes in
    // registration order and those all come first. The slug constraint keeps
    // it from swallowing anything that isn't actually slug-shaped.
    Route::middleware('cache-public-page')
        ->get('/{slug}', [PageController::class, 'show'])
        ->where('slug', '[a-z0-9-]{1,100}')
        ->name('page.show');

    // RankMath-style redirects manager + 404 monitor (see Redirect/NotFoundLog).
    // Must stay the LAST route registered: Route::fallback() only ever fires
    // once every route above has already failed to match, and it deliberately
    // skips the admin panel/API/webhook paths inside the controller itself.
    Route::fallback([RedirectFallbackController::class, 'handle'])->middleware('throttle:120,1,fallback');
});

// --- Agent 01: favicon set for browsers and search engines -----------------
// Stable root URLs (/favicon.ico, /favicon-48x48.png, /apple-touch-icon.png,
// /site.webmanifest, ...) generated from the uploaded square logo — see
// FaviconController/FaviconGenerator. No session/CSRF/cookie middleware: a
// favicon response must be cacheable and never set a cookie. (Route::fallback
// above is always matched last regardless of registration order.)
Route::get('/{file}', [FaviconController::class, 'show'])
    ->where('file', 'favicon\.ico|favicon-(48x48|96x96|192x192|512x512)\.png|apple-touch-icon\.png|site\.webmanifest')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        AddQueuedCookiesToResponse::class,
        EncryptCookies::class,
    ])
    ->name('favicons.show');
// --- end Agent 01 ----------------------------------------------------------

// The link in the blog newsletter's confirmation email (double opt-in).
// Signed and valid for 7 days; see BlogNewsletterConfirmController.
Route::get('/newsletter/confirm', BlogNewsletterConfirmController::class)
    ->middleware(['signed', 'throttle:30,1,signed-links'])
    ->name('blog.newsletter.confirm');

// ── Lifecycle email preferences (agent 03) ──────────────────────────────
// Signed per-user links from the footer of birthday/festive/re-engagement
// emails — see LifecycleEmailPreferenceController. The POST route is the
// RFC 8058 one-click target mail clients call directly, so it skips CSRF.
Route::middleware(['signed', 'throttle:30,1,signed-links'])->group(function () {
    Route::get('/email/lifecycle/unsubscribe/{user}', [LifecycleEmailPreferenceController::class, 'unsubscribe'])->name('lifecycle.unsubscribe');
    Route::post('/email/lifecycle/unsubscribe/{user}', [LifecycleEmailPreferenceController::class, 'oneClickUnsubscribe'])
        ->withoutMiddleware([PreventRequestForgery::class])
        ->name('lifecycle.unsubscribe.one-click');
    Route::post('/email/lifecycle/resubscribe/{user}', [LifecycleEmailPreferenceController::class, 'resubscribe'])->name('lifecycle.resubscribe');
});

// ── Profile & Security tabs (agent 03) ──────────────────────────────────
// Self-service profile editing — deliberately outside the seat/affiliate
// route allowlists' group (like 'profile' itself, every account type may
// manage its own profile). See ProfileController.
SiteDomains::app(function () {
    Route::middleware(['auth', 'verified', 'not-suspended'])->group(function () {
        Route::put('/profile/personal', [ProfileController::class, 'updatePersonal'])->name('profile.personal.update');
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->middleware('throttle:20,1,profile.photo.update')->name('profile.photo.update');
        Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
        Route::put('/profile/business', [ProfileController::class, 'updateBusiness'])->name('profile.business.update');
        Route::put('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications.update');
    });
});

// The AffilStack Connector plugin's update download — reached only through
// the short-lived signed link plugin-info hands a connected site.
Route::get('/wordpress/plugin/package/{version}', [WordPressConnectorController::class, 'package'])
    ->middleware(['signed', 'throttle:30,1,wordpress.package'])
    ->where('version', '[0-9.]+')
    ->name('wordpress.plugin.package');

// ---- Agent 05: affiliate Marketing Kit — public banner images & embeds ----
// Served outside the "web" middleware group on purpose: no session, no CSRF
// token, no cookies of any kind — these are loaded by other people's
// websites and email clients. Only the /embed/* routes may be framed by any
// site (AllowEmbedding → SecurityHeaders drops X-Frame-Options and sends
// frame-ancestors *); every other route keeps its existing protections.
Route::withoutMiddleware('web')->group(function () {
    Route::get('/banners/{design}/{size}.{format}', [AffiliateBannerController::class, 'image'])
        ->where(['design' => '[a-z0-9-]{1,40}', 'size' => '\d{2,4}x\d{2,4}', 'format' => 'svg|png'])
        ->name('affiliate-banners.image');
    Route::get('/brand-assets/{asset}.{format}', [AffiliateBannerController::class, 'brandAsset'])
        ->where(['asset' => 'icon|wordmark-light|wordmark-dark', 'format' => 'svg|png'])
        ->name('affiliate-banners.brand-asset');

    Route::middleware('allow-embedding')->group(function () {
        Route::get('/embed/banner.js', [BannerEmbedController::class, 'script'])->name('embed.banner-script');
        Route::get('/embed/banner/{size}', [BannerEmbedController::class, 'frame'])
            ->where('size', '\d{2,4}x\d{2,4}')
            ->middleware('throttle:240,1,embed.banner')
            ->name('embed.banner');
    });
});
// ---- end Agent 05 ----

// ---- Agent 04: cookie consent + first-party analytics ---------------------
// Behaviour events from window.affilstackTrack() and the consent log. Same
// rules as the page-view beacon above: no auth (logged-out visitors too), no
// CSRF token (cached pages), cross-site posts refused, rate-limited per IP.
Route::middleware(RejectCrossSiteBeacon::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->group(function () {
        Route::post('/internal/analytics/event', [AnalyticsBeaconController::class, 'event'])
            ->middleware('throttle:analytics-events')
            ->name('analytics.beacon.event');
        Route::post('/internal/consent', [ConsentController::class, 'store'])
            ->middleware('throttle:analytics-consent')
            ->name('consent.store');
    });

// Streamed CSV downloads for Analytics → Downloads / Audiences in the admin
// panel. Same session as the panel; EnsurePanelLoginCompleted signs out any
// session that didn't pass the panel's two-factor login, and the controller
// itself only serves full admins and audit-logs every export.
SiteDomains::app(function () {
    Route::middleware(['auth', EnsurePanelLoginCompleted::class, 'throttle:30,1,admin-exports'])
        ->get('/admin/analytics/export/{type}', [AnalyticsExportController::class, 'download'])
        ->where('type', '[a-z_]{3,30}')
        ->name('admin.analytics.export');
});
// ---- end Agent 04 -----------------------------------------------------------
