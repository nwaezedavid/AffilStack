<?php

namespace Database\Seeders;

use App\Models\AffiliateSwipe;
use Illuminate\Database\Seeder;

/**
 * The preset Marketing Kit copy affiliates start with. Idempotent: keyed on
 * preset_key, and an existing row is never overwritten, so an admin's edits
 * survive a re-seed. Also run from the create_affiliate_swipes_table
 * migration so production gets the presets on a plain `migrate`.
 *
 * Voice rules (the owner's): conversational, peer-to-peer, anti-hype — no
 * invented income claims and no fake scarcity. The "limited-time" swipe is
 * written to be used only when AffilStack is genuinely running an offer.
 */
class AffiliateSwipeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->presets() as $index => $preset) {
            AffiliateSwipe::firstOrCreate(
                ['preset_key' => $preset['preset_key']],
                $preset + ['is_active' => true, 'sort_order' => ($index + 1) * 10],
            );
        }
    }

    /**
     * @return array<int, array{preset_key: string, type: string, title: string, subject?: string|null, body: string, platform?: string|null}>
     */
    public function presets(): array
    {
        return [
            [
                'preset_key' => 'email-time-saver',
                'type' => 'email',
                'title' => 'Time-saver: get your evenings back',
                'subject' => 'I stopped tab-switching my way through affiliate marketing',
                'body' => <<<'TXT'
Hi {first_name},

Quick confession: for a long time, "doing affiliate marketing" mostly meant tab-switching.

One tab to research the product. One to figure out who actually buys it. Another for writing the blog post, another for the social captions, a spreadsheet for leads, and a different dashboard to check if anyone clicked.

I've moved all of that into one place: AffilStack.

You paste in a product (or pick a niche) and it:
- researches the offer and tells you who the ideal buyer is and where to find them
- helps you find those buyers (there's a built-in CRM and a local-business lead finder)
- writes the campaign for you: blog article, social posts, email sequence, even UGC-style video scripts
- tracks every click and sale so you can see what's working

The part I like most is that it all lives in one dashboard, so I spend my time publishing instead of copying and pasting between tools.

If you want to see how it works, here's the link I use:
{affiliate_link}

Talk soon,
{your_name}

P.S. Full disclosure: that's my affiliate link, so I may earn a commission if you sign up. It doesn't cost you anything extra.
TXT,
            ],
            [
                'preset_key' => 'email-more-commissions',
                'type' => 'email',
                'title' => 'More commissions: promote smarter, not harder',
                'subject' => 'Why most affiliate links never get a single sale',
                'body' => <<<'TXT'
Hi {first_name},

Most affiliate links fail for a boring reason: they get shared in front of the wrong people.

Not because the product is bad. Not because the marketer is lazy. Just because nobody worked out who actually needs the thing, and where those people hang out.

That's the gap AffilStack fills. Before you write a single word, it researches the offer and tells you:
- who the ideal buyer is
- where to find them (including the Google Maps and LinkedIn keywords to search)
- which angle is most likely to get them to click

Then it writes the content for that angle (blog posts, social posts, emails, video scripts) and tracks which links actually turn into sales, so you can double down on what works and drop what doesn't.

Same effort, aimed at the right people. That's how you earn more from the offers you already promote.

Take a look here:
{affiliate_link}

{your_name}

P.S. I'm an AffilStack affiliate, so I may earn a commission if you join through my link, at no extra cost to you.
TXT,
            ],
            [
                'preset_key' => 'email-beginner',
                'type' => 'email',
                'title' => 'Beginner-friendly: your first affiliate campaign',
                'subject' => 'New to affiliate marketing? Start here',
                'body' => <<<'TXT'
Hi {first_name},

If you've been wanting to start affiliate marketing but don't know where to begin, I get it. Most "how to" advice assumes you already know what to promote, who to sell to, and how to write content that converts.

AffilStack takes you through it step by step:

1. Add the product you want to promote (just the name and link).
2. It researches the product and explains, in plain English, who needs it and where to find them.
3. It writes your first pieces of content: a blog post, social posts, an email, even a short video script.
4. You publish, and it tracks your clicks and sales so you can see your progress.

No guessing, and no need to stitch a dozen tools together before you've even made your first sale. There's also a free Learning Centre with tutorials if you like to learn as you go.

Here's where to start:
{affiliate_link}

Rooting for you,
{your_name}

P.S. That's my affiliate link: if you sign up I may earn a commission, and it doesn't change your price.
TXT,
            ],
            [
                'preset_key' => 'email-africa-diaspora',
                'type' => 'email',
                'title' => 'African entrepreneurs & diaspora',
                'subject' => 'Built for us: a pro affiliate stack without 10 subscriptions',
                'body' => <<<'TXT'
Hi {first_name},

If you're building an online business from Lagos, Accra, Nairobi, or from London, Houston or Toronto while staying connected to home, you already know the problem: the "standard" affiliate stack is a pile of separate dollar subscriptions that add up fast.

AffilStack puts the whole thing in one place:
- product research that tells you who buys and where to find them
- a built-in CRM plus a local-business lead finder
- AI-written blog posts, social posts, emails and UGC-style video scripts
- click and sale tracking
- content you can adapt for different markets (Nigeria, Ghana, Kenya, South Africa, the diaspora, the US, the UK)

It's built for people who want to run a professional affiliate business without a big team or a big tools budget.

Have a look and see if it fits how you work:
{affiliate_link}

{your_name}

P.S. Honest note: that's my affiliate link, so I may earn a commission if you sign up, at no extra cost to you.
TXT,
            ],
            [
                'preset_key' => 'email-tool-stack',
                'type' => 'email',
                'title' => 'Tool-stack replacement',
                'subject' => 'How many tools does your affiliate marketing really need?',
                'body' => <<<'TXT'
Hi {first_name},

Count the tools you use to run your affiliate marketing. Research, a writer, a social scheduler, a spreadsheet for leads, a link tracker, an email tool...

Every one is another login, another bill, and another place your work gets stuck.

AffilStack replaces most of that stack with one dashboard:
- Offer research: who buys, where they are, and the best angle
- Find buyers: CRM and a local lead finder, organised per product
- AI content: blog, LinkedIn, X, TikTok, Pinterest, YouTube scripts, emails and UGC-style videos
- Tracking: branded links with click and sale analytics

Fewer tabs, fewer bills, and a clear view of what's actually earning.

See the full feature list here:
{affiliate_link}

{your_name}

P.S. Disclosure: I'm an affiliate, so I may earn a commission if you sign up through this link. Your price is the same either way.
TXT,
            ],
            [
                'preset_key' => 'email-limited-time',
                'type' => 'email',
                'title' => 'Limited-time offer (only use during a real promotion)',
                'subject' => 'AffilStack offer ends [DATE]',
                'body' => <<<'TXT'
Hi {first_name},

Quick heads-up: AffilStack is running [DESCRIBE THE OFFER] until [DATE].

(Only send this email while AffilStack is actually running a promotion, and copy the offer details exactly as AffilStack announces them.)

If you've been thinking about pulling your affiliate research, content and tracking into one place, this is a good time to try it. In one dashboard you can:
- research any product and find out who buys it and where
- find buyers with a built-in CRM and local lead finder
- generate ready-to-publish campaigns with AI
- track every click and sale

The offer is here:
{affiliate_link}

{your_name}

P.S. That's my affiliate link: I may earn a commission if you join, at no extra cost to you.
TXT,
            ],

            [
                'preset_key' => 'social-linkedin',
                'type' => 'social',
                'platform' => 'linkedin',
                'title' => 'LinkedIn: the research-first approach',
                'body' => <<<'TXT'
Most affiliate marketers start by writing content.

The ones who earn consistently start by answering three questions:
1. Who actually needs this product?
2. Where do those people spend their time?
3. What angle makes them click?

I've been using AffilStack to answer all three before I write a word. It researches the offer, helps me find the buyers, then drafts the campaign (blog, LinkedIn, email, video script) and tracks what converts.

If you promote products online, it's worth a look: {affiliate_link}

(Affiliate link: I may earn a commission if you sign up.)
TXT,
            ],
            [
                'preset_key' => 'social-x',
                'type' => 'social',
                'platform' => 'x',
                'title' => 'X: one dashboard',
                'body' => 'Research the product, find the buyers, write the campaign, track the sales. All in one dashboard instead of 6 tabs. I use AffilStack for my affiliate marketing: {affiliate_link} #ad',
            ],
            [
                'preset_key' => 'social-facebook',
                'type' => 'social',
                'platform' => 'facebook',
                'title' => 'Facebook: stop guessing',
                'body' => <<<'TXT'
If you've ever shared an affiliate link and heard crickets, this is for you.

AffilStack researches the product first (who buys it, where they hang out, which angle works), then writes your posts, emails and video scripts for that audience, and tracks every click and sale.

It's how I stopped guessing with my affiliate marketing. Have a look: {affiliate_link}

#ad: I may earn a commission if you sign up through my link.
TXT,
            ],
            [
                'preset_key' => 'social-instagram',
                'type' => 'social',
                'platform' => 'instagram',
                'title' => 'Instagram caption',
                'body' => <<<'TXT'
Research → Content → Sales, in minutes.

My affiliate workflow used to live in 6 different apps. Now it's one: AffilStack researches the product, finds the buyers, writes the content and tracks the sales.

Link in bio to try it (or DM me "STACK" and I'll send it).

#ad #affiliatemarketing #onlinebusiness #aitools #sidehustle
TXT,
            ],
            [
                'preset_key' => 'social-whatsapp',
                'type' => 'social',
                'platform' => 'whatsapp',
                'title' => 'WhatsApp status',
                'body' => 'Doing affiliate marketing? This tool researches the product, finds buyers and writes your posts with AI, all in one place. I use it: {affiliate_link} (my affiliate link)',
            ],
            [
                'preset_key' => 'social-tiktok',
                'type' => 'social',
                'platform' => 'tiktok',
                'title' => 'TikTok caption',
                'body' => 'POV: your whole affiliate business runs from one dashboard. Research → buyers → AI content → sales. Link in bio. #ad #affiliatemarketing #aitools #makemoneyonline #sidehustle',
            ],

            [
                'preset_key' => 'text-try',
                'type' => 'text_link',
                'title' => 'Simple call to action',
                'body' => 'Try AffilStack',
            ],
            [
                'preset_key' => 'text-descriptive',
                'type' => 'text_link',
                'title' => 'Descriptive anchor',
                'body' => 'the AI tool I use to research and promote affiliate offers',
            ],
            [
                'preset_key' => 'text-all-in-one',
                'type' => 'text_link',
                'title' => 'All-in-one platform',
                'body' => 'AffilStack, the all-in-one platform for affiliate marketers',
            ],
            [
                'preset_key' => 'text-resource',
                'type' => 'text_link',
                'title' => 'Resource-page listing',
                'body' => 'AffilStack: product research, buyer leads, AI content and tracking in one place',
            ],
        ];
    }
}
