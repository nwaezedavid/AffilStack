<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreativeTaskPreviewController;
use App\Http\Controllers\CrmEmailTrackingController;
use App\Http\Controllers\Dashboard\ApiAccessController;
use App\Http\Controllers\Dashboard\BlogController;
use App\Http\Controllers\Dashboard\CompetitorAngleController;
use App\Http\Controllers\Dashboard\ContentCalendarController;
use App\Http\Controllers\Dashboard\CrmController;
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
use App\Http\Controllers\Dashboard\XController;
use App\Http\Controllers\Dashboard\YouTubeController;
use App\Http\Controllers\FlutterwaveWebhookController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');
Route::get('/help', [HelpController::class, 'index'])->name('help.index');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

// Static, admin-editable pages — content lives in the site_pages table
// (Filament: Content > Site Pages) so legal copy can be updated without a
// code deploy.
Route::get('/about', [PageController::class, 'show'])->defaults('slug', 'about')->name('about');
Route::get('/terms', [PageController::class, 'show'])->defaults('slug', 'terms')->name('terms');
Route::get('/privacy', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('privacy');
Route::get('/refund-policy', [PageController::class, 'show'])->defaults('slug', 'refund-policy')->name('refund-policy');
Route::get('/cookie-policy', [PageController::class, 'show'])->defaults('slug', 'cookie-policy')->name('cookie-policy');

Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');

// Public, unauthenticated — whoever clicks a cloaked link is the offer's own
// audience, not an AffilStack user. See LinkCloakingService.
Route::get('/go/{code}', [LinkController::class, 'redirect'])->name('links.redirect');

// Public, unauthenticated — whoever clicks a referral link is a prospective
// signup, not an AffilStack user yet. See ReferralController.
Route::get('/r/{code}', [ReferralController::class, 'redirect'])->name('referrals.redirect');

// CRM/email dashboard: public, unauthenticated — the open pixel and click
// redirect are hit by the CRM contact's own email client, never by an
// AffilStack user, and the unsubscribe link must work without logging in.
// See CrmEmailService/CrmEmailTrackingController.
Route::get('/e/o/{token}.png', [CrmEmailTrackingController::class, 'open'])->name('crm.track.open');
Route::get('/e/c/{token}', [CrmEmailTrackingController::class, 'click'])->name('crm.track.click');
Route::get('/crm/unsubscribe/{token}', [CrmController::class, 'unsubscribe'])->name('crm.unsubscribe');

Route::post('/webhooks/flutterwave', [FlutterwaveWebhookController::class, 'handle'])->name('webhooks.flutterwave');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');

// The only door into an account: pick a plan, pay, get created. No open
// registration exists anywhere in this app — see RegistrationController.
//
// /get-started/callback MUST be registered before /get-started/{plan} —
// both are GET requests under the same prefix, and Laravel matches routes
// in registration order, so a wildcard registered first would swallow
// "callback" as a {plan} route-model-binding lookup and 404 on every real
// payment redirect. (Caught by tests/Feature/PaymentSignupFlowTest.)
Route::get('/pricing', [RegistrationController::class, 'pricing'])->name('registration.pricing');
Route::get('/get-started/callback', [RegistrationController::class, 'callback'])->name('registration.callback');
Route::get('/get-started/{plan}', [RegistrationController::class, 'showForm'])->name('registration.form');
Route::post('/get-started/{plan}', [RegistrationController::class, 'store'])->name('registration.store');
Route::post('/get-started/{plan}/google', [GoogleAuthController::class, 'redirectForSignup'])->name('registration.google');

// "Continue with Google" — one callback for both the login page and the
// signup form; see GoogleAuthController for how it tells them apart.
Route::get('/auth/google', [GoogleAuthController::class, 'redirectForLogin'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

// Tony (the Creative Agent) preview — lets an admin see exactly what a
// pending creative AgentTask would look like live, rendered through the
// real public templates, before a super-admin ever approves it. Admin-only
// (checked inside the controller); deliberately outside the dashboard
// group above since it has nothing to do with a user's own account. See
// CreativeTaskPreviewController — nothing here writes to the database.
Route::middleware('auth')->get('/admin-preview/creative-tasks/{agentTask}', [CreativeTaskPreviewController::class, 'show'])->name('creative-tasks.preview');

Route::middleware(['auth', 'verified', 'restrict-agency-seats'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/callback', [BillingController::class, 'callback'])->name('billing.callback');

    // Task #7: the "Intelligence Centre" AI self-assessment dashboard — see
    // IntelligenceCentreService. Owner-only, like billing/CRM/earnings.
    Route::get('/intelligence-centre', [IntelligenceCentreController::class, 'index'])->name('intelligence-centre.index');
    Route::post('/intelligence-centre', [IntelligenceCentreController::class, 'store'])->name('intelligence-centre.store');

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
    Route::post('/offers/{offer}/linkedin/reply-assistant', [LinkedInReplyAssistantController::class, 'store'])->name('offers.linkedin.reply-assistant.store');
    Route::post('/generations/{generation}/nurture-started', [ContentCalendarController::class, 'markSequenceStarted'])->name('generations.nurture-started');
    Route::post('/generations/{generation}/localize', [LocalizationController::class, 'store'])->name('generations.localize');
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
    Route::post('/email-connections/smtp', [EmailConnectionController::class, 'storeSmtp'])->name('email-connections.smtp.store');
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

    Route::get('/leads', [LeadFinderController::class, 'index'])->name('leads.index');
    Route::post('/leads/search', [LeadFinderController::class, 'search'])->name('leads.search');
    Route::post('/leads/import', [LeadFinderController::class, 'import'])->name('leads.import');

    Route::get('/links', [DashboardLinkController::class, 'index'])->name('links.index');
    Route::get('/links/{trackedLink}', [DashboardLinkController::class, 'show'])->name('links.show');

    Route::get('/earnings', [EarningsController::class, 'index'])->name('earnings.index');
    Route::post('/earnings', [EarningsController::class, 'storeManual'])->name('earnings.store');
    Route::post('/earnings/import', [EarningsController::class, 'storeImport'])->name('earnings.import');
    Route::patch('/earnings/{earning}/assign-offer', [EarningsController::class, 'assignOffer'])->name('earnings.assign-offer');
    Route::delete('/earnings/{earning}', [EarningsController::class, 'destroy'])->name('earnings.destroy');

    Route::get('/referrals', [DashboardReferralController::class, 'index'])->name('referrals.index');
    Route::post('/referrals/payout-method', [DashboardReferralController::class, 'savePayoutMethod'])->name('referrals.payout-method');
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

    // The general-purpose API's dashboard side (task #6) — deliberately
    // available to team seats (both isolated and shared), unlike the
    // extension above: see config('agency.seat_allowed_routes').
    Route::get('/api-access', [ApiAccessController::class, 'index'])->name('api-access.index');
    Route::post('/api-access/tokens', [ApiAccessController::class, 'createToken'])->name('api-access.tokens.store');
    Route::delete('/api-access/tokens/{token}', [ApiAccessController::class, 'revokeToken'])->name('api-access.tokens.destroy');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');

    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');

    // These fixed /support/chat* segments must be registered before the
    // /support/{ticket} wildcard below, or "chat" gets swallowed as a
    // ticket ID and 404s on route-model binding.
    Route::get('/support/chat', [SupportChatController::class, 'show'])->name('support.chat');
    Route::post('/support/chat/message', [SupportChatController::class, 'message'])->middleware('throttle:20,1')->name('support.chat.message');
    Route::post('/support/chat/escalate', [SupportChatController::class, 'escalate'])->name('support.chat.escalate');

    Route::get('/support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');
    Route::post('/support/{ticket}/rate', [SupportTicketController::class, 'rate'])->name('support.rate');
});
