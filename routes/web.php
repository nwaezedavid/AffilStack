<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\Dashboard\BlogController;
use App\Http\Controllers\Dashboard\CompetitorAngleController;
use App\Http\Controllers\Dashboard\ContentCalendarController;
use App\Http\Controllers\Dashboard\CrmController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\EarningsController;
use App\Http\Controllers\Dashboard\EmailNurtureController;
use App\Http\Controllers\Dashboard\LinkController as DashboardLinkController;
use App\Http\Controllers\Dashboard\LinkedInController;
use App\Http\Controllers\Dashboard\LocalizationController;
use App\Http\Controllers\Dashboard\OfferController;
use App\Http\Controllers\Dashboard\ReferralController as DashboardReferralController;
use App\Http\Controllers\Dashboard\SupportChatController;
use App\Http\Controllers\Dashboard\SupportTicketController;
use App\Http\Controllers\Dashboard\SwipeFileController;
use App\Http\Controllers\Dashboard\TikTokController;
use App\Http\Controllers\Dashboard\UgcController;
use App\Http\Controllers\Dashboard\XController;
use App\Http\Controllers\Dashboard\YouTubeController;
use App\Http\Controllers\FlutterwaveWebhookController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');
Route::get('/help', [HelpController::class, 'index'])->name('help.index');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

// Public, unauthenticated — whoever clicks a cloaked link is the offer's own
// audience, not an AffiliStack user. See LinkCloakingService.
Route::get('/go/{code}', [LinkController::class, 'redirect'])->name('links.redirect');

// Public, unauthenticated — whoever clicks a referral link is a prospective
// signup, not an AffiliStack user yet. See ReferralController.
Route::get('/r/{code}', [ReferralController::class, 'redirect'])->name('referrals.redirect');

Route::post('/webhooks/flutterwave', [FlutterwaveWebhookController::class, 'handle'])->name('webhooks.flutterwave');

// The only door into an account: pick a plan, pay, get created. No open
// registration exists anywhere in this app — see RegistrationController.
Route::get('/pricing', [RegistrationController::class, 'pricing'])->name('registration.pricing');
Route::get('/get-started/{plan}', [RegistrationController::class, 'showForm'])->name('registration.form');
Route::post('/get-started/{plan}', [RegistrationController::class, 'store'])->name('registration.store');
Route::get('/get-started/callback', [RegistrationController::class, 'callback'])->name('registration.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/callback', [BillingController::class, 'callback'])->name('billing.callback');

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
    Route::post('/generations/{generation}/nurture-started', [ContentCalendarController::class, 'markSequenceStarted'])->name('generations.nurture-started');
    Route::post('/generations/{generation}/localize', [LocalizationController::class, 'store'])->name('generations.localize');

    Route::post('/offers/{offer}/youtube/script', [YouTubeController::class, 'script'])->name('offers.youtube.script');
    Route::post('/offers/{offer}/youtube/metadata', [YouTubeController::class, 'metadata'])->name('offers.youtube.metadata');

    Route::post('/offers/{offer}/ugc/angles', [UgcController::class, 'angles'])->name('offers.ugc.angles');
    Route::post('/offers/{offer}/ugc/content', [UgcController::class, 'content'])->name('offers.ugc.content');

    Route::post('/offers/{offer}/x/thread', [XController::class, 'thread'])->name('offers.x.thread');

    Route::post('/offers/{offer}/tiktok/video', [TikTokController::class, 'video'])->name('offers.tiktok.video');

    Route::post('/offers/{offer}/nurture/generate', [EmailNurtureController::class, 'generate'])->name('offers.nurture.generate');

    Route::get('/crm', [CrmController::class, 'index'])->name('crm.index');
    Route::post('/crm', [CrmController::class, 'store'])->name('crm.store');
    Route::patch('/crm/{contact}', [CrmController::class, 'update'])->name('crm.update');
    Route::delete('/crm/{contact}', [CrmController::class, 'destroy'])->name('crm.destroy');
    Route::get('/crm-export', [CrmController::class, 'export'])->name('crm.export');

    Route::get('/links', [DashboardLinkController::class, 'index'])->name('links.index');
    Route::get('/links/{trackedLink}', [DashboardLinkController::class, 'show'])->name('links.show');

    Route::get('/earnings', [EarningsController::class, 'index'])->name('earnings.index');
    Route::post('/earnings', [EarningsController::class, 'storeManual'])->name('earnings.store');
    Route::post('/earnings/import', [EarningsController::class, 'storeImport'])->name('earnings.import');
    Route::patch('/earnings/{earning}/assign-offer', [EarningsController::class, 'assignOffer'])->name('earnings.assign-offer');
    Route::delete('/earnings/{earning}', [EarningsController::class, 'destroy'])->name('earnings.destroy');

    Route::get('/referrals', [DashboardReferralController::class, 'index'])->name('referrals.index');

    Route::get('/calendar', [ContentCalendarController::class, 'index'])->name('calendar.index');
    Route::patch('/calendar/{generation}', [ContentCalendarController::class, 'update'])->name('calendar.update');

    Route::get('/swipe-files', [SwipeFileController::class, 'index'])->name('swipe-files.index');

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
