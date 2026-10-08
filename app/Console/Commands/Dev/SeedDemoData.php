<?php

namespace App\Console\Commands\Dev;

use App\Models\ApiToken;
use App\Models\CrmCampaign;
use App\Models\CrmCampaignRecipient;
use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\CrmList;
use App\Models\CrmReply;
use App\Models\Earning;
use App\Models\EmailConnection;
use App\Models\Generation;
use App\Models\LinkClick;
use App\Models\LinkedinReplyDraft;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralClick;
use App\Models\Subscription;
use App\Models\TrackedLink;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT ONLY — fills a local database with one realistic demo
 * customer ("Ada Okafor") whose account shows every feature in action, so
 * the marketing screenshots in public/images/features can be retaken after
 * a redesign. Refuses to run in production. Everything it creates belongs
 * to the demo user and her team seats; --fresh deletes them first.
 *
 * Usage: php artisan affilstack:demo-data --fresh
 * Log in as ada@demo.affilstack.test / demo-password.
 */
#[Signature('affilstack:demo-data {--fresh : Delete the demo customer and everything she owns first}')]
#[Description('DEV ONLY: seed a demo customer with research, CRM, campaigns, content, clicks and earnings for screenshots')]
class SeedDemoData extends Command
{
    public const EMAIL = 'ada@demo.affilstack.test';

    public const PASSWORD = 'demo-password';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('affilstack:demo-data is for local development only and never runs in production.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->wipe();
        } elseif (User::withTrashed()->where('email', self::EMAIL)->exists()) {
            $this->warn('The demo customer already exists — run with --fresh to rebuild it.');

            return self::SUCCESS;
        }

        // Nothing here should reach a real inbox, webhook or queue worker.
        Event::fake();

        DB::transaction(function (): void {
            $ada = $this->customer();
            $notion = $this->notion($ada);
            $hostinger = $this->hostinger($ada);
            $this->content($ada, $notion);
            [$notionList, $hostingerList] = $this->crm($ada, $notion, $hostinger);
            $this->campaign($ada, $notion, $notionList);
            $this->draftCampaign($ada, $hostinger, $hostingerList);
            $this->linksAndEarnings($ada, $notion, $hostinger);
            $this->team($ada, $notion, $hostinger);
            $this->integrations($ada);
            $this->referrals($ada);
        });

        $this->info('Demo customer ready: '.self::EMAIL.' / '.self::PASSWORD);

        return self::SUCCESS;
    }

    protected function wipe(): void
    {
        $ids = User::withTrashed()->where('email', self::EMAIL)->pluck('id');
        $ids = $ids->merge(User::withTrashed()->whereIn('agency_owner_id', $ids)->pluck('id'))
            ->merge(User::withTrashed()->where('email', 'like', '%@demo-referral.affilstack.test')->pluck('id'));

        if ($ids->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            $offerIds = Offer::whereIn('user_id', $ids)->pluck('id');
            $linkIds = TrackedLink::whereIn('user_id', $ids)->pluck('id');
            $contactIds = CrmContact::whereIn('user_id', $ids)->pluck('id');
            $campaignIds = CrmCampaign::whereIn('user_id', $ids)->pluck('id');

            LinkClick::whereIn('tracked_link_id', $linkIds)->delete();
            CrmReply::whereIn('user_id', $ids)->delete();
            CrmEmailSend::whereIn('user_id', $ids)->delete();
            CrmCampaignRecipient::whereIn('crm_campaign_id', $campaignIds)->delete();
            CrmCampaign::whereIn('id', $campaignIds)->delete();
            DB::table('crm_contact_list')->whereIn('crm_contact_id', $contactIds)->delete();
            CrmList::whereIn('user_id', $ids)->delete();
            CrmContact::whereIn('id', $contactIds)->delete();
            Earning::whereIn('user_id', $ids)->delete();
            TrackedLink::whereIn('id', $linkIds)->delete();
            LinkedinReplyDraft::whereIn('user_id', $ids)->delete();
            Generation::whereIn('user_id', $ids)->delete();
            Offer::whereIn('id', $offerIds)->delete();
            EmailConnection::whereIn('user_id', $ids)->delete();
            ApiToken::whereIn('user_id', $ids)->delete();
            WebhookEndpoint::whereIn('user_id', $ids)->delete();
            ReferralClick::whereIn('user_id', $ids)->delete();
            Referral::whereIn('referrer_id', $ids)->orWhereIn('referred_user_id', $ids)->delete();
            Subscription::whereIn('user_id', $ids)->delete();
            User::withTrashed()->whereIn('id', $ids)->forceDelete();
        });
    }

    protected function customer(): User
    {
        $ada = User::forceCreate([
            'name' => 'Ada Okafor',
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'company_name' => 'Okafor Digital',
            'country' => 'Nigeria',
            'city' => 'Lagos',
            'state_region' => 'Lagos',
            'timezone' => 'Africa/Lagos',
            'business_industry' => 'Digital marketing',
            'business_website' => 'https://okafordigital.ng',
            'credits_balance' => 1840,
            'referral_code' => 'ADAOKAFOR',
            'onboarding_completed_at' => now()->subDays(20),
            'created_at' => now()->subDays(45),
        ]);

        $plan = Plan::where('slug', 'business')->first() ?? Plan::orderByDesc('price_monthly_cents')->first();

        Subscription::forceCreate([
            'user_id' => $ada->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'gateway' => 'paystack',
            'current_period_start' => now()->subDays(12),
            'current_period_end' => now()->addDays(18),
        ]);

        EmailConnection::forceCreate([
            'user_id' => $ada->id,
            'provider' => 'smtp',
            'preset' => 'zoho',
            'credentials' => ['host' => 'smtp.zoho.com', 'port' => 587, 'username' => 'ada@okafordigital.ng', 'password' => 'demo', 'encryption' => 'tls', 'from_email' => 'ada@okafordigital.ng', 'from_name' => 'Ada Okafor'],
            'connected_email' => 'ada@okafordigital.ng',
            'verified_at' => now()->subDays(30),
            'verification_status' => 'success',
            'verification_message' => 'Sent a test email successfully.',
            'last_checked_at' => now()->subHours(3),
            'daily_limit' => 150,
            'footer_business_name' => 'Okafor Digital',
            'footer_address' => '14 Admiralty Way, Lekki Phase 1, Lagos',
            'imap_status' => 'success',
            'imap_credentials' => ['host' => 'imap.zoho.com', 'port' => 993, 'username' => 'ada@okafordigital.ng', 'password' => 'demo', 'encryption' => 'ssl'],
            'imap_message' => 'Checking for replies every few minutes.',
            'imap_verified_at' => now()->subDays(30),
            'imap_last_polled_at' => now()->subMinutes(4),
        ]);

        return $ada;
    }

    protected function notion(User $ada): Offer
    {
        $research = [
            'product_overview' => 'Notion is an all-in-one workspace where teams write docs, manage projects, build wikis and track tasks in one place. Agencies and small businesses use it to replace a pile of disconnected tools with one shared system their whole team actually keeps up to date.',
            'ideal_customer_summary' => 'Founders and operations leads at 5–50 person agencies, consultancies and startups who are drowning in scattered Google Docs, WhatsApp threads and spreadsheets. They pay for tools that save the team hours every week and make onboarding new staff painless.',
            'who_needs_it' => [
                'individuals' => ['Freelance designers and developers', 'Consultants managing several clients', 'Founders running a lean team'],
                'businesses' => ['Digital and creative agencies', 'Tech startups', 'Property management firms', 'Private schools and tutoring centres'],
            ],
            'pain_points' => [
                'Client briefs, SOPs and passwords scattered across WhatsApp, Drive and email',
                'New hires take weeks to find how things are done',
                'Projects slip because nobody knows who owns the next task',
                'Paying for five tools that don\'t talk to each other',
            ],
            'buyer_personas' => [
                ['name' => 'The overwhelmed agency founder', 'description' => 'Runs a 12-person agency in Lagos, answers the same questions all day and wants one place where the team finds every brief and process.'],
                ['name' => 'The operations lead who loves a system', 'description' => 'Owns tools at a growing startup; wants docs, projects and the company wiki in one workspace that scales with the team.'],
                ['name' => 'The diaspora consultant', 'description' => 'Works with clients in London and Lagos across time zones and needs async updates everyone can see.'],
            ],
            'where_to_find' => ['LinkedIn: agency founders and COOs in Lagos, Nairobi and London', 'Facebook group: Lagos Digital Marketing Professionals', 'r/agency and r/Notion on Reddit', 'Google Maps: marketing agencies and co-working spaces', 'Twitter/X: #buildinpublic founders', 'Techpoint Africa and TechCabal newsletters'],
            'recommended_channel' => 'linkedin',
            'recommended_channel_reason' => 'Notion\'s buyers are decision-makers at agencies and startups who are active on LinkedIn and respond to practical, process-focused content. A credible post plus a soft DM converts far better than a cold ad for a tool teams must agree to adopt.',
            'recommended_angle' => 'Show the exact client-onboarding workspace your agency built in Notion — and offer the template free to anyone who asks.',
            'secondary_channel' => 'google_maps',
            'suggested_maps_niche' => 'marketing agency',
            'suggested_maps_location' => 'Lagos, Nigeria',
            'google_maps_keywords' => [
                ['keyword' => 'marketing agency', 'why' => 'Agencies juggle many clients and need a shared workspace.'],
                ['keyword' => 'web design company', 'why' => 'Design teams manage briefs, feedback and handovers.'],
                ['keyword' => 'co-working space', 'why' => 'Members are freelancers and startups — and the space can refer them.'],
                ['keyword' => 'accounting firm', 'why' => 'Firms need SOPs and client checklists in one place.'],
                ['keyword' => 'real estate agency', 'why' => 'Listings, viewings and agents to coordinate.'],
                ['keyword' => 'private school', 'why' => 'Staff handbooks and lesson plans for many teachers.'],
                ['keyword' => 'software company', 'why' => 'Product docs and sprint planning.'],
            ],
            'suggested_locations' => ['Lagos, Nigeria', 'Abuja, Nigeria', 'Nairobi, Kenya', 'Accra, Ghana', 'London, United Kingdom'],
            'linkedin_keywords' => [
                'people' => ['Agency founder', 'Head of operations', 'Chief operating officer', 'Project manager', 'Creative director', 'Startup founder'],
                'companies' => ['Digital marketing agency', 'Creative agency', 'Software development', 'Management consulting', 'EdTech'],
            ],
            'linkedin_search_tips' => [
                'Filter people by "2nd connections" and location "Lagos" to start with warm-ish prospects.',
                'Add "agency" to the company keyword box to skip big corporates.',
                'Open with a question about how they onboard new clients — not with the product.',
            ],
        ];

        $offer = Offer::forceCreate([
            'user_id' => $ada->id,
            'product_name' => 'Notion',
            'product_url' => 'https://www.notion.so',
            'affiliate_network' => 'partnerstack',
            'affiliate_link' => 'https://affiliate.notion.so/ada-okafor',
            'status' => 'ready',
            'ideal_customer_summary' => $research['ideal_customer_summary'],
            'where_to_find' => implode("\n", $research['where_to_find']),
            'recommended_channel' => $research['recommended_channel'],
            'recommended_angle' => $research['recommended_angle'],
            'research_data' => $research,
            'disclosure_country' => 'NG',
            'suggested_maps_niche' => $research['suggested_maps_niche'],
            'suggested_maps_location' => $research['suggested_maps_location'],
            'created_at' => now()->subDays(21),
            'updated_at' => now()->subDays(21),
        ]);

        $this->generation($ada, $offer, 'research', json_encode($research), $research, 21);

        return $offer;
    }

    protected function hostinger(User $ada): Offer
    {
        $research = [
            'product_overview' => 'Hostinger is affordable web hosting with a free domain, one-click WordPress and an AI website builder. It gets small businesses online fast without needing a developer.',
            'ideal_customer_summary' => 'Small business owners — salons, clinics, restaurants and shops — who still rely on Instagram or WhatsApp and want a proper website customers can find on Google, at a price that makes sense in naira.',
            'who_needs_it' => [
                'individuals' => ['Freelancers building a portfolio', 'Bloggers and creators', 'Web designers with clients'],
                'businesses' => ['Restaurants and cafés', 'Beauty salons and spas', 'Dental and medical clinics', 'Local shops'],
            ],
            'pain_points' => ['No website, so Google shows competitors first', 'Website quotes from developers are too expensive', 'Slow, unreliable cheap hosting'],
            'buyer_personas' => [
                ['name' => 'The busy salon owner', 'description' => 'Fully booked through Instagram but losing walk-ins to salons that appear on Google with a site and booking page.'],
                ['name' => 'The freelance web designer', 'description' => 'Builds sites for clients and wants reliable hosting with a commission on every referral.'],
            ],
            'where_to_find' => ['Google Maps: salons, clinics and restaurants without a website', 'Instagram business accounts with "DM to order"', 'Facebook groups for small business owners in Nigeria', 'Jiji and Nairaland business sections'],
            'recommended_channel' => 'google_maps',
            'recommended_channel_reason' => 'Local businesses without a website are visible on Google Maps — you can see who has no site and reach them with a precise, helpful offer.',
            'recommended_angle' => 'Your customers are searching for you on Google — here\'s how to get a website live this weekend for less than one day\'s sales.',
            'secondary_channel' => 'youtube',
            'suggested_maps_niche' => 'beauty salon',
            'suggested_maps_location' => 'Lekki, Lagos',
            'google_maps_keywords' => [
                ['keyword' => 'beauty salon', 'why' => 'Many run on Instagram only.'],
                ['keyword' => 'dental clinic', 'why' => 'Patients search before booking.'],
                ['keyword' => 'restaurant', 'why' => 'Menus and online orders.'],
                ['keyword' => 'boutique', 'why' => 'Online store potential.'],
                ['keyword' => 'event centre', 'why' => 'Bookings and galleries.'],
                ['keyword' => 'car wash', 'why' => 'Easy wins with a one-page site.'],
            ],
            'suggested_locations' => ['Lekki, Lagos', 'Ikeja, Lagos', 'Wuse, Abuja', 'Port Harcourt, Nigeria'],
            'linkedin_keywords' => [
                'people' => ['Small business owner', 'Web designer', 'Freelance developer', 'Founder'],
                'companies' => ['Web design', 'Retail', 'Hospitality', 'Health clinics'],
            ],
            'linkedin_search_tips' => ['Search for web designers — they resell hosting to every client.'],
        ];

        $offer = Offer::forceCreate([
            'user_id' => $ada->id,
            'product_name' => 'Hostinger',
            'product_url' => 'https://www.hostinger.com',
            'affiliate_network' => 'impact',
            'affiliate_link' => 'https://hostinger.com?REFERRALCODE=ADAOKAFOR',
            'status' => 'ready',
            'ideal_customer_summary' => $research['ideal_customer_summary'],
            'where_to_find' => implode("\n", $research['where_to_find']),
            'recommended_channel' => $research['recommended_channel'],
            'recommended_angle' => $research['recommended_angle'],
            'research_data' => $research,
            'disclosure_country' => 'NG',
            'suggested_maps_niche' => $research['suggested_maps_niche'],
            'suggested_maps_location' => $research['suggested_maps_location'],
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(9),
        ]);

        $this->generation($ada, $offer, 'research', json_encode($research), $research, 9);

        return $offer;
    }

    /**
     * One finished piece for every content feature, spread over the content
     * calendar.
     */
    protected function content(User $ada, Offer $notion): void
    {
        $link = Offer::LINK_PLACEHOLDER;

        $blog = [
            'title' => 'How Lagos Agencies Run Every Client From One Notion Workspace',
            'slug' => 'lagos-agencies-notion-client-workspace',
            'meta_description' => 'The exact Notion setup busy agencies use to onboard clients, track projects and stop living in WhatsApp — with a free template.',
            'target_keyword' => 'notion for agencies',
            'article_markdown' => "## Why agencies outgrow WhatsApp and Google Docs\n\nIf your team asks \"where's the latest brief?\" more than once a day, you don't have a people problem — you have a system problem. Most agencies in Lagos run client work across WhatsApp groups, shared drives and a spreadsheet nobody updates.\n\n## The one-workspace setup\n\nNotion lets you keep **client briefs, project boards, SOPs and meeting notes** in one place, linked together. Here's the structure we recommend:\n\n- **Clients** — one page per client with contacts, contracts and brand assets\n- **Projects** — a board view with owners and due dates\n- **Playbooks** — your onboarding checklist and how-tos\n\n## Onboarding a client in 15 minutes\n\nDuplicate the client template, fill the brief and share a read-only portal with the client. Your team sees every next step on the project board.\n\n## Try it free\n\nNotion's free plan is enough to test this with one client. [Start with Notion here]({$link}).",
            'suggested_tags' => ['notion', 'agency operations', 'productivity', 'client onboarding'],
        ];
        $this->generation($ada, $notion, 'blog_article', $blog['article_markdown'], $blog, 6, ['keyword' => 'notion for agencies'], 'published', -4);

        $keywords = [
            'search_keywords' => ['agency founder', 'head of operations agency', 'creative director', 'COO startup', 'project manager agency', 'operations manager'],
            'boolean_search_strings' => ['("agency founder" OR "managing director") AND (digital OR creative) AND Lagos', '("head of operations" OR COO) AND (startup OR agency)'],
            'job_titles' => ['Founder', 'Managing Director', 'Head of Operations', 'COO', 'Project Manager', 'Creative Director'],
            'industries' => ['Marketing Services', 'Advertising Services', 'Software Development', 'Business Consulting'],
            'groups_to_join' => ['Lagos Digital Marketing Professionals', 'Agency Owners Africa', 'Notion Builders Community'],
        ];
        $this->generation($ada, $notion, 'linkedin_keywords', json_encode($keywords), $keywords, 5);

        $dms = ['messages' => [
            ['step' => 1, 'send_timing' => 'Day 0 — with your connection request', 'goal' => 'Connect', 'message' => 'Hi {{first_name}}, I work with agency founders on how they run client work. Saw your team just took on two new brands — congrats! Would love to connect.'],
            ['step' => 2, 'send_timing' => 'Day 2 — after they accept', 'goal' => 'Start a conversation', 'message' => 'Thanks for connecting, {{first_name}}. Quick question — where does your team keep client briefs and SOPs today? Most agencies I speak to say "WhatsApp and prayer" 😅'],
            ['step' => 3, 'send_timing' => 'Day 5 if they reply', 'goal' => 'Offer value', 'message' => 'That\'s exactly why I built a client-onboarding workspace in Notion. Happy to share the template — it\'s free, no strings.'],
            ['step' => 4, 'send_timing' => 'Day 9 if no reply', 'goal' => 'Soft follow-up', 'message' => 'No worries if now isn\'t the time, {{first_name}}. The template\'s here whenever you need it.'],
        ]];
        $this->generation($ada, $notion, 'linkedin_dm_sequence', json_encode($dms), $dms, 5);

        $post = [
            'post_text' => "Our agency used to run on 14 WhatsApp groups.\n\nBriefs got lost. New hires asked the same questions for weeks. Deadlines slipped.\n\nSo we moved everything into one Notion workspace:\n→ A page per client\n→ One board for every project\n→ Playbooks for how we work\n\nOnboarding a new client now takes 15 minutes.\n\nWant the template? Comment \"WORKSPACE\" and I'll send it. Or start here: {$link}",
            'image_prompt' => 'A clean laptop screen showing a Notion client dashboard, warm Lagos office background, natural light.',
            'best_posting_time' => 'Tuesday 8–10am WAT',
        ];
        $this->generation($ada, $notion, 'linkedin_post', json_encode($post), $post, 4, [], 'scheduled', 2);

        $article = [
            'headline' => 'The Agency Operating System: Running 20 Clients Without the Chaos',
            'article_markdown' => "## The problem isn't your team\n\nEvery growing agency hits the same wall...\n\n## One workspace, three databases\n\nClients, Projects and Playbooks — linked together.\n\n## Start today\n\nGrab the free template and [try Notion]({$link}).",
            'suggested_cover_image_prompt' => 'Minimal illustration of connected workspace cards on a navy background.',
        ];
        $this->generation($ada, $notion, 'linkedin_article', json_encode($article), $article, 4, [], 'draft', 6);

        $script = [
            'working_title' => 'I Replaced 5 Tools With Notion (Agency Setup Tour)',
            'hook' => 'If your agency lives in WhatsApp, this 9-minute tour will save your team ten hours a week.',
            'script_markdown' => "**[00:00]** Hook — the WhatsApp chaos and what it costs.\n\n**[00:45]** Intro — who I am and the agency setup we'll build.\n\n**[01:30]** Section 1 — the Clients database and a client page.\n\n**[03:40]** Section 2 — the project board, owners and due dates.\n\n**[06:10]** Section 3 — playbooks and onboarding a new hire.\n\n**[08:20]** CTA — get the template and start free with the link below.",
            'b_roll_suggestions' => ['Phone buzzing with WhatsApp notifications', 'Scrolling the Notion client database', 'Dragging a card across the project board', 'New hire reading the onboarding playbook', 'Template download page'],
            'estimated_length_minutes' => 9,
        ];
        $this->generation($ada, $notion, 'youtube_script', $script['script_markdown'], $script, 3, [], 'scheduled', 5);

        $meta = [
            'title_options' => ['I Replaced 5 Tools With Notion (Full Agency Setup)', 'Notion for Agencies: The Only Setup You Need', 'How We Run 20 Clients in One Notion Workspace', 'Stop Running Your Agency on WhatsApp', 'Notion Agency Template Tour (Free Download)'],
            'description' => "The exact Notion workspace our agency uses to run every client.\n\nIn this video:\n• The Clients database\n• Project boards with owners\n• Playbooks for onboarding\n\nStart with Notion: {$link}",
            'keywords' => ['notion for agencies', 'notion agency template', 'agency project management', 'notion tutorial'],
            'tags' => ['notion', 'agency', 'productivity', 'project management', 'notion template', 'notion tutorial'],
            'category' => 'Science & Technology',
            'thumbnail_prompt' => 'Split screen: messy WhatsApp chats vs a clean Notion board, bold text "5 TOOLS → 1".',
        ];
        $this->generation($ada, $notion, 'youtube_metadata', json_encode($meta), $meta, 3);

        $angles = ['angles' => [
            ['name' => 'Before/After workspace', 'format' => 'problem-solution testimonial', 'hook_idea' => '"This is what our agency looked like before Notion…" (shows 14 WhatsApp groups)', 'why_it_works' => 'Founders instantly recognise the chaos.'],
            ['name' => 'Day in the life of an ops lead', 'format' => 'day-in-the-life', 'hook_idea' => '"Come run a 12-person agency with me — from one tab."', 'why_it_works' => 'Shows the product in real daily use.'],
            ['name' => 'Myth: Notion is only for nerds', 'format' => 'myth-busting reaction', 'hook_idea' => '"Everyone says Notion is too complicated. Watch this."', 'why_it_works' => 'Tackles the top objection head-on.'],
            ['name' => 'Free template drop', 'format' => 'tutorial/how-to', 'hook_idea' => '"Steal my client onboarding template in 30 seconds."', 'why_it_works' => 'A freebie drives comments and saves.'],
            ['name' => 'The new-hire test', 'format' => 'relatable rant', 'hook_idea' => '"If your new hire asks where the brief is, watch this."', 'why_it_works' => 'Speaks to a daily frustration.'],
        ]];
        $anglesGen = $this->generation($ada, $notion, 'ugc_angles', json_encode($angles), $angles, 2);

        $ugc = [
            'script' => "**Hook:** This is what our agency looked like before Notion.\n\n**Body:** Fourteen WhatsApp groups, briefs everywhere, and a new hire who asked me the same question eight times. Now every client has one page, every project has one board, and onboarding takes 15 minutes.\n\n**CTA:** Link in bio to start free — and comment WORKSPACE for my template.",
            'on_screen_text_ideas' => ['14 WhatsApp groups 😩', 'One page per client', 'Onboarding: 15 minutes', 'Free template ↓'],
            'platforms' => [
                ['platform' => 'TikTok', 'title' => 'Agency chaos → one workspace', 'caption' => 'From 14 WhatsApp groups to one Notion workspace. Template in bio ✨', 'tags' => ['notion', 'agencylife', 'productivity'], 'posting_tip' => 'Post 7–9pm WAT.'],
                ['platform' => 'Instagram Reels', 'title' => 'How we run our agency', 'caption' => 'The setup that saved us 10 hours a week. Link in bio.', 'tags' => ['notiontemplate', 'smallbusiness'], 'posting_tip' => 'Use the trending audio.'],
                ['platform' => 'YouTube Shorts', 'title' => 'Notion for agencies in 30s', 'caption' => 'Full tour on the channel.', 'tags' => ['notion', 'shorts'], 'posting_tip' => 'Pin a comment with the link.'],
            ],
            'video_script' => 'This is what our agency looked like before Notion. Fourteen WhatsApp groups and briefs everywhere. Now every client has one page and onboarding takes fifteen minutes. Start free with the link in my bio.',
        ];
        $this->generation($ada, $notion, 'ugc_content', $ugc['script'], $ugc, 2, ['angles_generation_id' => $anglesGen->id, 'angle_index' => 0], 'scheduled', 3);

        $thread = [
            'hook_variants' => ['Our agency ran on 14 WhatsApp groups. Here\'s the one-workspace system that replaced them 🧵', 'Most agencies don\'t have a people problem. They have a system problem. A thread:', 'I onboard a new client in 15 minutes now. Here\'s exactly how:'],
            'thread' => [
                ['position' => 1, 'text' => 'Our agency ran on 14 WhatsApp groups. Here\'s the one-workspace system that replaced them 🧵'],
                ['position' => 2, 'text' => '1/ The problem: briefs lost in chats, SOPs in someone\'s head, and new hires asking the same 10 questions.'],
                ['position' => 3, 'text' => '2/ The fix: three linked Notion databases — Clients, Projects, Playbooks.'],
                ['position' => 4, 'text' => '3/ Every client gets one page: contacts, contract, brand assets, and a live project board.'],
                ['position' => 5, 'text' => '4/ Result: onboarding a client takes 15 minutes, and the team stopped asking "where is…?"'],
                ['position' => 6, 'text' => '5/ You can start on the free plan with one client: '.$link],
            ],
            'best_posting_time' => 'Weekdays 8–9am WAT',
        ];
        $this->generation($ada, $notion, 'x_thread', json_encode($thread), $thread, 2, [], 'published', -1);

        $tiktok = [
            'script' => "**[0:00]** Hook: \"Your agency doesn't need another WhatsApp group.\"\n\n**[0:03]** Problem: show the 14 groups.\n\n**[0:10]** Product: scroll the Notion client page and board.\n\n**[0:25]** Proof: \"Onboarding now takes 15 minutes.\"\n\n**[0:32]** CTA: \"Template + link in bio.\"",
            'on_screen_text' => [
                ['timing' => '0:00-0:03', 'text' => 'Stop making WhatsApp groups'],
                ['timing' => '0:03-0:10', 'text' => '14 groups. 0 answers.'],
                ['timing' => '0:10-0:25', 'text' => 'One page per client'],
                ['timing' => '0:25-0:32', 'text' => 'Onboarding: 15 min'],
                ['timing' => '0:32-0:38', 'text' => 'Free template ↓'],
            ],
            'caption' => "Agency owners, this one's for you 👀 Free template + {$link} (link in bio)",
            'hashtags' => ['notion', 'agencylife', 'lagosbusiness', 'productivityhacks'],
        ];
        $this->generation($ada, $notion, 'tiktok_video', $tiktok['script'], $tiktok, 1, [], 'scheduled', 1);

        $pins = [
            'pins' => [
                ['title' => 'Notion Agency Template: Run Every Client From One Page', 'description' => 'The Notion workspace agencies use to keep briefs, projects and SOPs in one place. Free template inside — set up your client portal in 15 minutes.', 'image_prompt' => 'Vertical 2:3 pin, clean mockup of a Notion client dashboard on a laptop, bold title text at the top, navy and gold palette.', 'alt_text' => 'Laptop showing a Notion client dashboard with projects and tasks.'],
                ['title' => 'Client Onboarding Checklist for Agencies (Notion)', 'description' => 'A step-by-step onboarding checklist your whole team can follow, built in Notion and free to copy.', 'image_prompt' => 'Vertical checklist graphic with ticked items over a soft beige background.', 'alt_text' => 'Checklist of client onboarding steps.'],
                ['title' => '5 Tools Your Agency Can Replace With Notion', 'description' => 'Docs, wiki, project boards, CRM-lite and meeting notes — one workspace that saves your team hours every week.', 'image_prompt' => 'Five app icons merging into one Notion logo, vertical layout.', 'alt_text' => 'Five productivity app icons merging into one.'],
            ],
            'board_suggestion' => 'A board about agency systems and productivity',
            'keywords' => ['notion template', 'agency management', 'client onboarding', 'productivity system'],
        ];
        $this->generation($ada, $notion, 'pinterest_pin', json_encode($pins), $pins, 1, [], 'draft', 8);

        $competitor = [
            'common_angles' => [
                ['angle_name' => 'Ultimate productivity hack', 'example_headline' => 'The ONE app that will 10x your productivity', 'typical_channel' => 'YouTube reviews', 'why_it_works' => 'Big promises get clicks.', 'saturation' => 'high'],
                ['angle_name' => 'Aesthetic student setup', 'example_headline' => 'My aesthetic Notion setup for uni 🌸', 'typical_channel' => 'TikTok', 'why_it_works' => 'Visual and shareable.', 'saturation' => 'high'],
                ['angle_name' => 'Free template giveaway', 'example_headline' => 'Free Notion template for freelancers', 'typical_channel' => 'Pinterest', 'why_it_works' => 'Low commitment for the viewer.', 'saturation' => 'medium'],
                ['angle_name' => 'Notion vs ClickUp comparison', 'example_headline' => 'Notion vs ClickUp: which is better in 2026?', 'typical_channel' => 'Blog SEO content', 'why_it_works' => 'Captures buyers comparing tools.', 'saturation' => 'medium'],
                ['angle_name' => 'Agency operations case study', 'example_headline' => 'How a Lagos agency runs 20 clients in Notion', 'typical_channel' => 'LinkedIn', 'why_it_works' => 'Proof from a real business.', 'saturation' => 'low'],
            ],
            'differentiation_opportunity' => 'Almost nobody shows Notion running a real African agency end-to-end. A practical, local case study with a downloadable client-onboarding template speaks directly to business buyers rather than students.',
            'recommended_adjustment' => 'Lead with the agency case study and the onboarding template instead of generic productivity claims.',
        ];
        $this->generation($ada, $notion, 'competitor_angles', json_encode($competitor), $competitor, 1);

        $localized = "## Why UK agencies outgrow Slack threads and Google Docs\n\nIf your team asks \"where's the latest brief?\" more than once a day, you don't have a people problem — you have a systems problem. Most agencies in London run client work across Slack channels, shared drives and a spreadsheet nobody updates…\n\n*Prices shown in GBP. This article contains affiliate links — I may earn a commission at no extra cost to you.*";
        $blogGen = Generation::where('offer_id', $notion->id)->where('module', 'blog_article')->first();
        $this->generation($ada, $notion, 'blog_article', $localized, array_merge($blog, ['title' => 'How London Agencies Run Every Client From One Notion Workspace', 'article_markdown' => $localized]), 0, [], 'draft', 10, ['target_market' => 'GB', 'localized_from_id' => $blogGen?->id]);
    }

    /**
     * @return array{0: CrmList, 1: CrmList}
     */
    protected function crm(User $ada, Offer $notion, Offer $hostinger): array
    {
        $notionList = CrmList::forceCreate(['user_id' => $ada->id, 'offer_id' => $notion->id, 'name' => 'Notion — Lagos agencies', 'color' => 'brand', 'description' => 'Agencies and studios from Google Maps and LinkedIn.']);
        $hostingerList = CrmList::forceCreate(['user_id' => $ada->id, 'offer_id' => $hostinger->id, 'name' => 'Hostinger — Lekki businesses', 'color' => 'emerald', 'description' => 'Local businesses without a website.']);

        $notionContacts = [
            ['Chidinma Eze', 'Brightwave Creative Agency', 'Founder', 'chidinma@brightwave.ng', 'google_maps', 'Victoria Island, Lagos', 'contacted'],
            ['Tunde Bakare', 'Pixelforge Studios', 'Creative Director', 'tunde@pixelforge.studio', 'google_maps', 'Yaba, Lagos', 'qualified'],
            ['Amaka Nwosu', 'Lumen Digital', 'Head of Operations', 'amaka@lumendigital.co', 'linkedin', 'Lekki, Lagos', 'contacted'],
            ['Kwame Mensah', 'Akwaaba Marketing', 'Managing Director', 'kwame@akwaabamarketing.com', 'linkedin', 'Accra, Ghana', 'contacted'],
            ['Funke Adeyemi', 'Coral & Co. Consulting', 'COO', 'funke@coralco.ng', 'google_maps', 'Ikoyi, Lagos', 'qualified'],
            ['Ibrahim Musa', 'Northgate Web Solutions', 'Founder', 'ibrahim@northgateweb.ng', 'google_maps', 'Wuse II, Abuja', 'contacted'],
            ['Grace Otieno', 'Savannah Labs', 'Project Manager', 'grace@savannahlabs.co.ke', 'linkedin', 'Nairobi, Kenya', 'new'],
            ['Emeka Obi', 'Hive Co-working Lekki', 'Community Manager', 'emeka@hivecowork.ng', 'google_maps', 'Lekki Phase 1, Lagos', 'contacted'],
            ['Zainab Bello', 'Spark Studio', 'Founder', 'zainab@sparkstudio.ng', 'chrome_extension', 'Surulere, Lagos', 'contacted'],
            ['Daniel Okon', 'Bluepeak Consulting', 'Partner', 'daniel@bluepeak.ng', 'linkedin', 'Port Harcourt', 'new'],
            ['Ngozi Ike', 'Mosaic Brand Studio', 'Creative Lead', 'ngozi@mosaicbrand.ng', 'chrome_extension', 'Ikeja, Lagos', 'contacted'],
            ['Samuel Adjei', 'Ridgeway Digital', 'COO', 'samuel@ridgewaydigital.com', 'linkedin', 'London, UK', 'new'],
        ];
        $hostingerContacts = [
            ['Blessing Okoro', 'Glow Beauty Lounge', 'Owner', 'hello@glowbeautylounge.ng', 'google_maps', 'Admiralty Way, Lekki', 'new'],
            ['Dr. Kemi Lawal', 'SmileCare Dental Clinic', 'Lead Dentist', 'info@smilecaredental.ng', 'google_maps', 'Lekki Phase 1, Lagos', 'contacted'],
            ['Yusuf Adamu', 'Suya Spot Lekki', 'Manager', 'orders@suyaspot.ng', 'google_maps', 'Chevron Drive, Lekki', 'new'],
            ['Tolu Ajayi', 'The Little Boutique', 'Founder', 'tolu@littleboutique.ng', 'google_maps', 'Ikate, Lekki', 'new'],
            ['Chika Umeh', 'Prestige Event Centre', 'Bookings', 'bookings@prestigeevents.ng', 'google_maps', 'Lekki-Epe Expressway', 'new'],
        ];

        $make = function (array $rows, CrmList $list, int $daysAgo) use ($ada): array {
            $contacts = [];

            foreach ($rows as $i => [$name, $company, $title, $email, $source, $location, $status]) {
                $contact = CrmContact::forceCreate([
                    'user_id' => $ada->id,
                    'name' => $name,
                    'company' => $company,
                    'title' => $title,
                    'email' => $email,
                    'phone' => '+234 80'.random_int(1, 9).' '.random_int(100, 999).' '.random_int(1000, 9999),
                    'website' => 'https://'.Str::after($email, '@'),
                    'source' => $source,
                    'location' => $location,
                    'status' => $status,
                    'raw_data' => in_array($source, ['google_maps', 'chrome_extension'], true) ? ['captured_via' => 'chrome_extension'] : null,
                    'unsubscribe_token' => Str::random(40),
                    'created_at' => now()->subDays($daysAgo)->addHours($i * 3),
                    'updated_at' => now()->subDays($daysAgo)->addHours($i * 3),
                ]);
                $list->contacts()->attach($contact->id, ['added_at' => $contact->created_at]);
                $contacts[] = $contact;
            }

            return $contacts;
        };

        $make($notionContacts, $notionList, 14);
        $make($hostingerContacts, $hostingerList, 6);

        // An email nurture sequence for the hottest Notion prospect.
        $funke = CrmContact::where('user_id', $ada->id)->where('name', 'Funke Adeyemi')->first();
        $nurture = ['emails' => [
            ['step' => 1, 'send_timing' => 'Day 0', 'subject' => 'Your client onboarding, Funke', 'body' => "Hi Funke,\n\nLoved Coral & Co.'s recent rebrand work. I noticed you're hiring two consultants — how are you planning to onboard them?\n\nI've put together a simple Notion workspace consultancies use to keep every client, SOP and template in one place. Happy to share it.\n\nAda"],
            ['step' => 2, 'send_timing' => 'Day 3 if no reply', 'subject' => 'The 15-minute onboarding checklist', 'body' => "Hi Funke,\n\nHere's the checklist we use to get a new client live in 15 minutes…\n\nAda"],
            ['step' => 3, 'send_timing' => 'Day 6', 'subject' => 'How Brightwave cut status meetings in half', 'body' => "Hi Funke,\n\nA quick story from another Lagos team…\n\nAda"],
            ['step' => 4, 'send_timing' => 'Day 9', 'subject' => 'Want the template?', 'body' => "Hi Funke,\n\nYou can try the full setup free on Notion's starter plan: ".Offer::LINK_PLACEHOLDER."\n\nAda"],
            ['step' => 5, 'send_timing' => 'Day 14 if no reply', 'subject' => 'Closing the loop', 'body' => "Hi Funke,\n\nI'll leave it here — the template is yours whenever the timing is right.\n\nAda"],
        ]];
        $this->generation($ada, $notion, 'email_nurture', json_encode($nurture), $nurture, 1, ['contact_id' => $funke?->id], 'scheduled', 0);

        LinkedinReplyDraft::forceCreate([
            'user_id' => $ada->id,
            'offer_id' => $notion->id,
            'their_message' => 'Thanks Ada! We use Trello and Google Drive right now. Is Notion really worth switching for a 9-person team? Migration sounds painful.',
            'draft_reply' => "Totally fair question, Tunde. For a 9-person team the switch is usually a weekend, not a month — you can import Trello boards and Google Docs straight in.\n\nThe big win is having briefs, tasks and SOPs linked in one place, so nobody hunts across tools. Want me to send the agency template so you can test it with one client first? No pressure either way.",
            'created_at' => now()->subHours(5),
        ]);

        return [$notionList, $hostingerList];
    }

    protected function campaign(User $ada, Offer $notion, CrmList $list): void
    {
        $campaign = CrmCampaign::forceCreate([
            'user_id' => $ada->id,
            'crm_list_id' => $list->id,
            'offer_id' => $notion->id,
            'name' => 'Notion for agencies — October outreach',
            'subject' => '{first_name}, a calmer way to run {company}\'s clients',
            'body' => "Hi {first_name},\n\nMost agency founders I speak to in Lagos run client work across WhatsApp, Drive and a spreadsheet nobody updates.\n\nWe built a simple Notion workspace — one page per client, one board per project, and a playbook for onboarding — and it cut our status meetings in half.\n\nWould it help if I sent you the template? You can try it free: {affiliate_link}\n\nBest,\nAda Okafor\nOkafor Digital",
            'status' => 'sent',
            'autopilot' => true,
            'personalize_opening' => true,
            'follow_up_steps' => [['delay_days' => 3, 'subject' => 'Quick follow-up', 'body' => 'Hi {first_name}, just bumping this in case it got buried.']],
            'tone' => 'friendly',
            'credits_spent' => 8,
            'generated_at' => now()->subDays(8),
            'approved_at' => now()->subDays(8),
            'last_sent_at' => now()->subHours(6),
            'created_at' => now()->subDays(8),
        ]);

        $states = ['clicked', 'replied', 'opened', 'clicked', 'opened', 'sent', 'queued', 'opened', 'sent', 'skipped', 'opened', 'queued'];
        $contacts = $list->contacts()->orderBy('crm_contacts.id')->get();

        foreach ($contacts as $i => $contact) {
            $state = $states[$i % count($states)];
            $sentAt = now()->subDays(7)->addHours($i * 5);
            $recipient = CrmCampaignRecipient::forceCreate([
                'crm_campaign_id' => $campaign->id,
                'crm_contact_id' => $contact->id,
                'user_id' => $ada->id,
                'status' => in_array($state, ['opened', 'clicked'], true) ? 'sent' : $state,
                'source' => $i >= 9 ? 'autopilot' : 'manual',
                'steps_sent' => in_array($state, ['queued', 'skipped'], true) ? 0 : ($i % 3 === 0 ? 2 : 1),
                'next_step_due_at' => in_array($state, ['sent', 'opened'], true) ? now()->addDays(2) : null,
                'last_sent_at' => in_array($state, ['queued', 'skipped'], true) ? null : $sentAt,
                'replied_at' => $state === 'replied' ? $sentAt->copy()->addHours(20) : null,
                'opening_line' => 'Loved '.$contact->company.'\'s recent work.',
                'error_message' => $state === 'skipped' ? 'No email address on this contact.' : null,
                'created_at' => $sentAt,
                'updated_at' => $state === 'replied' ? $sentAt->copy()->addHours(20) : $sentAt,
            ]);

            if (in_array($state, ['queued', 'skipped'], true)) {
                continue;
            }

            $send = CrmEmailSend::forceCreate([
                'user_id' => $ada->id,
                'crm_contact_id' => $contact->id,
                'crm_campaign_id' => $campaign->id,
                'crm_campaign_recipient_id' => $recipient->id,
                'kind' => 'campaign',
                'sequence_step' => 1,
                'subject' => Str::before($contact->name, ' ').', a calmer way to run your clients',
                'body' => 'Hi '.Str::before($contact->name, ' ').",\n\nMost agency founders I speak to in Lagos run client work across WhatsApp, Drive and a spreadsheet nobody updates. We built a simple Notion workspace — one page per client, one board per project — and it cut our status meetings in half.\n\nWould it help if I sent you the template?\n\nBest,\nAda",
                'status' => 'sent',
                'tracking_token' => Str::random(40),
                'message_id' => '<'.Str::uuid().'@okafordigital.ng>',
                'sent_at' => $sentAt,
                'opened_at' => in_array($state, ['opened', 'clicked', 'replied'], true) ? $sentAt->copy()->addHours(2) : null,
                'open_count' => in_array($state, ['opened', 'clicked', 'replied'], true) ? 2 : 0,
                'first_clicked_at' => $state === 'clicked' ? $sentAt->copy()->addHours(3) : null,
                'click_count' => $state === 'clicked' ? 1 : 0,
            ]);

            if ($state === 'replied') {
                CrmReply::forceCreate([
                    'user_id' => $ada->id,
                    'crm_contact_id' => $contact->id,
                    'crm_email_send_id' => $send->id,
                    'crm_campaign_id' => $campaign->id,
                    'crm_campaign_recipient_id' => $recipient->id,
                    'provider' => 'imap',
                    'message_id' => '<'.Str::uuid().'@pixelforge.studio>',
                    'in_reply_to' => $send->message_id,
                    'from_email' => $contact->email,
                    'from_name' => $contact->name,
                    'subject' => 'Re: '.$send->subject,
                    'body_text' => "Hi Ada,\n\nFunny timing — we were literally arguing about this in our standup. We use Trello and Google Drive right now and it's a mess.\n\nIs Notion really worth switching for a 9-person team? And does the template work if some clients want their own view?\n\nThanks,\nTunde",
                    'body_clean' => "Hi Ada,\n\nFunny timing — we were literally arguing about this in our standup. We use Trello and Google Drive right now and it's a mess.\n\nIs Notion really worth switching for a 9-person team? And does the template work if some clients want their own view?\n\nThanks,\nTunde",
                    'matched_by' => 'in_reply_to',
                    'received_at' => $sentAt->copy()->addHours(20),
                    'read_at' => null,
                ]);
                $contact->forceFill(['replied_at' => $sentAt->copy()->addHours(20), 'status' => 'qualified'])->save();
            }
        }
    }

    /**
     * A campaign still in the editor (draft) — what "review and approve" looks like.
     */
    protected function draftCampaign(User $ada, Offer $hostinger, CrmList $list): void
    {
        CrmCampaign::forceCreate([
            'user_id' => $ada->id,
            'crm_list_id' => $list->id,
            'offer_id' => $hostinger->id,
            'name' => 'Hostinger — websites for Lekki businesses',
            'subject' => '{first_name}, customers are searching for {company} on Google',
            'body' => "Hi {first_name},\n\nI found {company} on Google Maps — great reviews! I noticed there's no website linked to your listing yet, so people who search for you can't book or see your prices.\n\nHostinger gets a professional site live in a weekend, with a free domain, for less than one day's sales. I can show you exactly how.\n\nHave a look here: {affiliate_link}\n\nWarm regards,\nAda Okafor\nOkafor Digital",
            'status' => 'draft',
            'autopilot' => false,
            'personalize_opening' => true,
            'follow_up_steps' => [['delay_days' => 4, 'subject' => 'Did you see this, {first_name}?', 'body' => 'Hi {first_name}, just checking you saw my note about getting {company} online.']],
            'tone' => 'friendly',
            'credits_spent' => 8,
            'generated_at' => now()->subMinutes(12),
        ]);
    }

    protected function linksAndEarnings(User $ada, Offer $notion, Offer $hostinger): void
    {
        $channels = [
            [$notion, 'blog_article', 412], [$notion, 'linkedin_post', 268], [$notion, 'crm_campaign', 154],
            [$notion, 'x_thread', 97], [$notion, 'tiktok_video', 188], [$notion, 'youtube_metadata', 76],
            [$hostinger, 'general', 131], [$hostinger, 'email_nurture', 42],
        ];
        $countries = ['NG' => 52, 'GB' => 14, 'US' => 10, 'GH' => 9, 'KE' => 8, 'CA' => 4, 'ZA' => 3];
        $devices = ['mobile' => 64, 'desktop' => 31, 'tablet' => 5];
        $browsers = ['Chrome' => 58, 'Safari' => 24, 'Edge' => 8, 'Firefox' => 5, 'Opera' => 5];
        $weighted = function (array $weights): string {
            $roll = random_int(1, array_sum($weights));

            foreach ($weights as $value => $weight) {
                if (($roll -= $weight) <= 0) {
                    return (string) $value;
                }
            }

            return (string) array_key_first($weights);
        };

        foreach ($channels as [$offer, $module, $total]) {
            $link = TrackedLink::forceCreate([
                'user_id' => $ada->id,
                'offer_id' => $offer->id,
                'module' => $module,
                'code' => Str::lower(Str::random(7)),
                'destination_url' => $offer->affiliate_link,
                'clicks_count' => $total,
                'created_at' => now()->subDays(30),
            ]);

            $rows = [];

            for ($i = 0; $i < $total; $i++) {
                // More clicks in recent days — a growing account.
                $daysAgo = (int) floor(29 * (1 - sqrt(random_int(0, 1000) / 1000)));
                $rows[] = [
                    'tracked_link_id' => $link->id,
                    'ip_address' => '102.89.'.random_int(1, 254).'.'.random_int(1, 254),
                    'user_agent' => 'Mozilla/5.0',
                    'device_type' => $weighted($devices),
                    'browser' => $weighted($browsers),
                    'country' => $weighted($countries),
                    'referrer' => null,
                    'clicked_at' => Carbon::now()->subDays($daysAgo)->setTime(random_int(7, 22), random_int(0, 59))->min(now()->subMinutes(random_int(5, 90))),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                LinkClick::insert($chunk);
            }
        }

        $notionLinks = TrackedLink::where('offer_id', $notion->id)->get()->keyBy('module');
        $hostingerLink = TrackedLink::where('offer_id', $hostinger->id)->where('module', 'general')->first();
        $entries = [
            [$notion, 'partnerstack', $notionLinks['blog_article'] ?? null, 4800, 'paid', 26], [$notion, 'partnerstack', $notionLinks['linkedin_post'] ?? null, 4800, 'paid', 22],
            [$notion, 'partnerstack', $notionLinks['crm_campaign'] ?? null, 9600, 'approved', 15], [$notion, 'partnerstack', $notionLinks['blog_article'] ?? null, 4800, 'approved', 11],
            [$notion, 'partnerstack', $notionLinks['tiktok_video'] ?? null, 4800, 'pending', 4], [$notion, 'partnerstack', $notionLinks['linkedin_post'] ?? null, 9600, 'pending', 2],
            [$hostinger, 'impact', $hostingerLink, 6000, 'paid', 18], [$hostinger, 'impact', $hostingerLink, 6000, 'approved', 8],
            [$hostinger, 'impact', $hostingerLink, 9000, 'pending', 3], [$notion, 'partnerstack', $notionLinks['x_thread'] ?? null, 4800, 'approved', 6],
        ];

        foreach ($entries as $i => [$offer, $network, $link, $cents, $status, $daysAgo]) {
            Earning::forceCreate([
                'user_id' => $ada->id,
                'tracked_link_id' => $link?->id,
                'offer_id' => $offer->id,
                'network' => $network,
                'source' => $i % 2 === 0 ? 'csv' : 'manual',
                'external_ref' => strtoupper(Str::random(10)),
                'amount_cents' => $cents,
                'currency' => 'USD',
                'status' => $status,
                'converted_at' => now()->subDays($daysAgo),
            ]);
        }
    }

    protected function team(User $ada, Offer $notion, Offer $hostinger): void
    {
        $seats = [
            ['Bisi Adebayo', 'bisi@demo-team.affilstack.test', 'manager', $notion],
            ['Kelechi Nnaji', 'kelechi@demo-team.affilstack.test', 'member', $notion],
            ['Halima Sani', 'halima@demo-team.affilstack.test', 'member', $hostinger],
        ];

        foreach ($seats as $i => [$name, $email, $role, $offer]) {
            User::forceCreate([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'agency_owner_id' => $ada->id,
                'seat_offer_id' => $offer->id,
                'seat_role' => $role,
                'credits_balance' => 0,
                'created_at' => now()->subDays(12 - $i * 3),
                'last_active_at' => now()->subHours(2 + $i * 7),
            ]);
        }
    }

    protected function integrations(User $ada): void
    {
        foreach ([['WordPress auto-publish', 30], ['Zapier — new contacts to Sheets', 6], ['Chrome extension — Ada\'s laptop', 0]] as [$name, $usedHoursAgo]) {
            ApiToken::forceCreate([
                'user_id' => $ada->id,
                'type' => 'personal',
                'scope' => 'full',
                'is_sandbox' => false,
                'name' => $name,
                'token_hash' => hash('sha256', Str::random(40)),
                'last_used_at' => $usedHoursAgo === 0 ? now()->subMinutes(18) : now()->subHours($usedHoursAgo),
            ]);
        }

        WebhookEndpoint::forceCreate([
            'user_id' => $ada->id,
            'url' => 'https://hooks.zapier.com/hooks/catch/1234567/abcde/',
            'secret' => Str::random(40),
            'events' => ['contact.created', 'generation.completed'],
            'is_active' => true,
            'last_triggered_at' => now()->subHours(2),
        ]);
    }

    protected function referrals(User $ada): void
    {
        for ($i = 0; $i < 64; $i++) {
            ReferralClick::forceCreate([
                'user_id' => $ada->id,
                'ip_address' => '102.88.'.random_int(1, 254).'.'.random_int(1, 254),
                'user_agent' => 'Mozilla/5.0',
                'banner_key' => ['leaderboard', 'square', null][$i % 3],
                'source' => ['banner', 'link', 'email'][$i % 3],
                'clicked_at' => now()->subDays(random_int(0, 29)),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $extra
     */
    protected function generation(User $ada, Offer $offer, string $module, string $output, array $meta, int $daysAgo, array $input = [], ?string $calendarStatus = null, ?int $scheduledInDays = null, array $extra = []): Generation
    {
        $created = now()->subDays($daysAgo)->subHours(random_int(1, 8));

        return Generation::forceCreate(array_merge([
            'user_id' => $ada->id,
            'offer_id' => $offer->id,
            'module' => $module,
            'input' => $input,
            'output' => $output,
            'output_meta' => $meta,
            'credits_spent' => $module === 'research' ? 10 : 4,
            'status' => 'completed',
            'calendar_status' => $calendarStatus ?? 'draft',
            'scheduled_for' => $scheduledInDays !== null ? now()->addDays($scheduledInDays)->toDateString() : null,
            'published_at' => $calendarStatus === 'published' ? now()->addDays((int) $scheduledInDays) : null,
            'created_at' => $created,
            'updated_at' => $created,
        ], $extra));
    }
}
