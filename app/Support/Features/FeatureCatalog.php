<?php

namespace App\Support\Features;

use App\Models\FeaturePage;

/**
 * The default copy and screenshots for every public feature page
 * (/features/{slug}) — what AffilStack actually does today, written for
 * the marketing site. Seeded once into feature_pages (see the
 * 2026_10_08_110002 migration and FeaturePageSeeder); from then on every
 * word is edited in Website → Features, never here.
 *
 * Images are file names in public/images/features (real screenshots of
 * the app taken with demo data — see the affilstack:demo-data command).
 */
class FeatureCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ── Research & prospecting ─────────────────────────────────
            [
                'slug' => 'offer-research',
                'name' => 'Offer Research',
                'icon' => 'magnifying-glass-circle',
                'menu_group' => 'research',
                'menu_description' => 'A full buyer brief on any product in minutes.',
                'hero_eyebrow' => 'Offer Research',
                'hero_headline' => 'Know exactly who buys a product *before* you promote it',
                'hero_subheadline' => 'Paste a product name or affiliate link. AffilStack researches it with AI and hands you the ideal customer, their pain points, buyer personas, the best channel and angle — plus ready-to-use Google Maps and LinkedIn search keywords to find those buyers today.',
                'hero_default_image' => 'offer-research.webp',
                'hero_image_alt' => 'AffilStack research results for Notion showing the ideal customer, recommended channel and angle',
                'sections' => [
                    [
                        'heading' => 'A buyer brief, not a wall of text',
                        'body' => 'Every product you add gets its own research page. AI studies the offer and writes down who needs it, why they buy, and where they spend their time — so every email, post and video you create afterwards starts from the same sharp understanding.',
                        'bullets' => ['Product overview and who it is really for', 'Pain points in your buyers\' own words', 'Buyer personas with job titles and motivations', 'The recommended channel, a second channel and the angle to lead with'],
                        'default_image' => 'research-personas.webp',
                        'image_alt' => 'Buyer personas and pain points generated for a product',
                    ],
                    [
                        'heading' => 'Search keywords that find real buyers',
                        'body' => 'Research doesn\'t stop at "who". You also get the exact Google Maps searches and LinkedIn keywords (with Boolean strings and search tips) that surface the businesses and decision-makers who need the product — one click opens them in Maps or LinkedIn.',
                        'bullets' => ['Google Maps niches and suggested locations', 'LinkedIn keywords and Boolean search strings', 'A suggested Maps niche and city to start with'],
                        'default_image' => 'research-keywords.webp',
                        'image_alt' => 'Google Maps and LinkedIn keyword suggestions on a product research page',
                    ],
                    [
                        'heading' => 'One hub for everything you create',
                        'body' => 'Below the research, every feature for that product is one click away: find buyers, launch a campaign, write content for eight channels, track the clicks. Each tool already knows the product, so you never re-explain it.',
                        'bullets' => ['Feature tiles for every content channel', 'A dedicated CRM list per product', 'Progress you can see at a glance'],
                        'default_image' => 'get-started.webp',
                        'image_alt' => 'The Get Started dashboard guiding a new product from research to first campaign',
                    ],
                ],
                'steps' => [
                    ['title' => 'Add a product', 'description' => 'Paste the product name, its website and your affiliate link — from PartnerStack, Impact, Amazon or any network.'],
                    ['title' => 'Let AI research it', 'description' => 'In a couple of minutes you get the full buyer brief, personas, channels and search keywords.'],
                    ['title' => 'Act on it', 'description' => 'Click straight into finding buyers, writing content or launching a campaign — every tool reuses the research.'],
                ],
                'faqs' => [
                    ['question' => 'Which products can I research?', 'answer' => 'Any product or service you can promote with an affiliate link — SaaS tools, hosting, courses, physical products or local services. You can research as many products as your plan\'s active-product limit allows.'],
                    ['question' => 'Is the research generic AI text?', 'answer' => 'No. It is structured around the decisions an affiliate has to make — who to target, where to find them, which channel and angle to use — and it feeds every other feature for that product.'],
                ],
                'cta_heading' => 'Research your first product today',
                'cta_text' => 'Every plan includes Offer Research. Pick one and add a product in under a minute.',
            ],
            [
                'slug' => 'google-maps-lead-finder',
                'name' => 'Google Maps Lead Finder',
                'icon' => 'map-pin',
                'menu_group' => 'research',
                'menu_description' => 'Find local businesses and save them to your CRM.',
                'hero_eyebrow' => 'Google Maps lead finder + CRM',
                'hero_headline' => 'Turn Google Maps into a *list of buyers*',
                'hero_subheadline' => 'Search Google Maps for the businesses that need your product — dentists, gyms, agencies, salons — and import the best ones straight into that product\'s CRM list, with their website, phone and address.',
                'hero_default_image' => 'local-leads.webp',
                'hero_image_alt' => 'Local Leads search results from Google Maps ready to import into the CRM',
                'sections' => [
                    [
                        'heading' => 'Search by niche and city',
                        'body' => 'Start from the Maps niches your research suggested or type your own. Results come back with ratings, websites and contact details so you can decide who is worth reaching out to.',
                        'bullets' => ['Uses the Maps keywords from your product research', 'Ratings, website, phone and address for each business', 'Import one business or the whole page'],
                        'default_image' => 'local-leads.webp',
                        'image_alt' => 'Google Maps business results in AffilStack',
                    ],
                    [
                        'heading' => 'A CRM list for every product',
                        'body' => 'Each product gets its own list, so the dentists you found for one offer never receive a campaign about another. Open a list to see every contact, their status and what they\'ve opened, clicked or replied to.',
                        'bullets' => ['Lists organised by product, colour-coded', 'Contact status from new to replied', 'One click from a list to a new email campaign'],
                        'default_image' => 'crm-list.webp',
                        'image_alt' => 'A CRM list of local businesses for one product',
                    ],
                ],
                'steps' => [
                    ['title' => 'Pick a niche and location', 'description' => 'Use a suggestion from your research or type any search you would type into Google Maps.'],
                    ['title' => 'Import the right businesses', 'description' => 'Save them to the product\'s CRM list — with the Chrome extension you can also add them while browsing Maps.'],
                    ['title' => 'Email the whole list', 'description' => 'Click "Create campaign" and AI writes a personalised email for every contact.'],
                ],
                'faqs' => [
                    ['question' => 'Is the built-in Maps search on every plan?', 'answer' => 'The Maps keywords and the Chrome extension\'s "Add to CRM" work on every plan. The built-in Local Leads search inside the dashboard is included on plans with the Google Maps channel — see Pricing for details.'],
                    ['question' => 'How many contacts can I store?', 'answer' => 'Each plan has a CRM contact allowance (some are unlimited). Contacts are shared across your lists, so adding one business to two lists only counts once.'],
                ],
                'cta_heading' => 'Find your first local buyers',
                'cta_text' => 'Research a product, open its Maps keywords and fill your first CRM list today.',
            ],
            [
                'slug' => 'chrome-extension',
                'name' => 'Chrome Extension',
                'icon' => 'puzzle-piece',
                'menu_group' => 'research',
                'menu_description' => 'Add Maps businesses and LinkedIn people in one click.',
                'hero_eyebrow' => 'AffilStack for Chrome',
                'hero_headline' => 'Prospect *while you browse* Google Maps and LinkedIn',
                'hero_subheadline' => 'The AffilStack Chrome extension reads the Google Maps listing or LinkedIn profile you\'re looking at, adds it to the right product\'s CRM list in one click, and writes a personalised LinkedIn message or reply for you.',
                'hero_default_image' => 'extension-popup.webp',
                'hero_image_alt' => 'The AffilStack Chrome extension popup adding a Google Maps business to a CRM list',
                'sections' => [
                    [
                        'heading' => 'Google Maps → CRM in one click',
                        'body' => 'Open any business on Google Maps, click the extension and choose the product. The name, website, phone and address are saved to that product\'s list — no copy and paste.',
                        'bullets' => ['Picks up the business details automatically', 'Saves to the right product\'s list', 'Warns you if the contact is already in your CRM'],
                        'default_image' => 'extension-popup.webp',
                        'image_alt' => 'Extension popup saving a business from Google Maps',
                    ],
                    [
                        'heading' => 'LinkedIn messages that sound like you',
                        'body' => 'On a LinkedIn profile, the extension drafts a short, personal connection message based on the person\'s headline and your product research. When they answer, paste their reply and get a suggested response that moves the conversation forward. You always send it yourself.',
                        'bullets' => ['Personalised first message from the profile', 'Reply assistant for every answer', 'Nothing is ever sent automatically'],
                        'default_image' => 'extension-page.webp',
                        'image_alt' => 'The Browser Extension page in the AffilStack dashboard',
                    ],
                ],
                'steps' => [
                    ['title' => 'Install and connect', 'description' => 'Download the extension from your dashboard, load it in Chrome and connect it with your personal key.'],
                    ['title' => 'Browse Maps or LinkedIn', 'description' => 'Open a business or a profile you would like to work with.'],
                    ['title' => 'Click the extension', 'description' => 'Add them to a CRM list or draft the message — then carry on browsing.'],
                ],
                'faqs' => [
                    ['question' => 'Does the extension message people automatically?', 'answer' => 'No. It writes the message and you choose whether to send it. AffilStack never automates actions on LinkedIn, which keeps your account safe.'],
                    ['question' => 'Which browsers are supported?', 'answer' => 'Google Chrome and Chromium-based browsers such as Edge and Brave.'],
                ],
                'cta_heading' => 'Prospect faster from today',
                'cta_text' => 'The extension is included on every plan.',
            ],
            [
                'slug' => 'linkedin-prospecting',
                'name' => 'LinkedIn Prospecting',
                'icon' => 'briefcase',
                'menu_group' => 'research',
                'menu_description' => 'Search strings, DM sequences and a reply assistant.',
                'hero_eyebrow' => 'LinkedIn prospecting',
                'hero_headline' => 'Start real conversations with *decision-makers*',
                'hero_subheadline' => 'Get the exact LinkedIn keywords and Boolean searches to find people who need your product, a soft DM sequence written for them, and an AI reply assistant for every answer — you stay in control of every message.',
                'hero_default_image' => 'linkedin-prospecting.webp',
                'hero_image_alt' => 'LinkedIn keywords and a DM sequence generated for a product',
                'sections' => [
                    [
                        'heading' => 'Find the right people',
                        'body' => 'Keywords, job titles and Boolean strings tailored to the product, with search tips for LinkedIn\'s free search. Click one and the search opens in LinkedIn.',
                        'bullets' => ['Keywords and job titles that buy this product', 'Copy-ready Boolean search strings', 'Tips to narrow results without Sales Navigator'],
                        'default_image' => 'research-keywords.webp',
                        'image_alt' => 'LinkedIn keyword suggestions from product research',
                    ],
                    [
                        'heading' => 'A DM sequence that doesn\'t feel like spam',
                        'body' => 'A connection note and follow-ups that lead with value and only mention the product when it helps. Edit any line, then send it yourself.',
                        'bullets' => ['Connection request, follow-up and soft offer', 'Written from your research, not a template', 'Your affiliate link is cloaked and tracked'],
                        'default_image' => 'linkedin-prospecting.webp',
                        'image_alt' => 'A LinkedIn DM sequence written by AI',
                    ],
                    [
                        'heading' => 'An assistant for every reply',
                        'body' => 'Paste what the prospect said and AffilStack suggests a reply that answers the question, handles the objection or books the next step.',
                        'bullets' => ['Handles objections in your tone', 'Suggests the next step', 'Works from the dashboard and the Chrome extension'],
                        'default_image' => 'linkedin-reply-assistant.webp',
                        'image_alt' => 'The LinkedIn reply assistant drafting a response',
                    ],
                ],
                'steps' => [
                    ['title' => 'Generate the search keywords', 'description' => 'Open LinkedIn for your product and get keywords and Boolean strings.'],
                    ['title' => 'Connect with a DM sequence', 'description' => 'Send the personalised connection note and follow-ups yourself.'],
                    ['title' => 'Reply with the assistant', 'description' => 'Paste their answer and send the suggested response.'],
                ],
                'faqs' => [
                    ['question' => 'Will this get my LinkedIn account restricted?', 'answer' => 'AffilStack never logs into LinkedIn or sends anything for you — it writes, you send. That keeps you well inside LinkedIn\'s rules.'],
                    ['question' => 'Do I need Sales Navigator?', 'answer' => 'No. The keywords and Boolean strings work in LinkedIn\'s standard search.'],
                ],
                'cta_heading' => 'Book more conversations on LinkedIn',
                'cta_text' => 'LinkedIn prospecting is included on every plan.',
            ],

            // ── Outreach & CRM ──────────────────────────────────────────
            [
                'slug' => 'ai-email-campaigns',
                'name' => 'AI Email Campaigns & Autopilot',
                'icon' => 'paper-airplane',
                'menu_group' => 'outreach',
                'menu_description' => 'Personalised cold email to a whole list — then on autopilot.',
                'hero_eyebrow' => 'AI email campaigns',
                'hero_headline' => 'Email a whole list, *personalised for every contact*',
                'hero_subheadline' => 'Pick a CRM list and AI writes a cold email for that product from your research. Edit it with a live preview, approve and send from your own Gmail or SMTP inbox — then switch on autopilot so every new lead gets it automatically.',
                'hero_default_image' => 'campaign-results.webp',
                'hero_image_alt' => 'An email campaign showing sent, opened, clicked and replied recipients',
                'sections' => [
                    [
                        'heading' => 'Written for the product, personalised for the person',
                        'body' => 'The campaign starts from your product research and merges each contact\'s name and business into the message. You see exactly what each person will receive before anything is sent.',
                        'bullets' => ['AI first draft from your research', 'Live preview with real contact details', 'Your cloaked affiliate link, tracked per campaign'],
                        'default_image' => 'campaign-editor.webp',
                        'image_alt' => 'The campaign editor with an AI-written email and a live preview',
                    ],
                    [
                        'heading' => 'Every recipient, every status',
                        'body' => 'After sending, the campaign page becomes its history: who received it, who opened, who clicked and who replied. Send to new contacts on the list with one click, pause or resume at any time.',
                        'bullets' => ['Sent, opened, clicked and replied per contact', 'Send to new contacts added since', 'Pause and resume whenever you like'],
                        'default_image' => 'campaign-results.webp',
                        'image_alt' => 'Campaign recipients with open and reply status',
                    ],
                    [
                        'heading' => 'Autopilot for new leads',
                        'body' => 'Turn on autopilot and anyone you add to the list later — from Google Maps, the Chrome extension or an import — receives the campaign automatically, within your sending limits.',
                        'bullets' => ['Runs in the background every few minutes', 'Respects daily sending limits', 'Unsubscribes are honoured automatically'],
                        'default_image' => 'crm-list.webp',
                        'image_alt' => 'A CRM list feeding an autopilot campaign',
                    ],
                ],
                'steps' => [
                    ['title' => 'Connect your inbox', 'description' => 'Connect Gmail or any SMTP mailbox under Email Sending so campaigns come from you.'],
                    ['title' => 'Pick a list and review the draft', 'description' => 'AI writes the email; edit anything and check the live preview.'],
                    ['title' => 'Approve and send', 'description' => 'Track opens, clicks and replies — and turn on autopilot for new leads.'],
                ],
                'faqs' => [
                    ['question' => 'Which email address do campaigns come from?', 'answer' => 'Your own — a connected Gmail account or any SMTP mailbox. Replies land in your inbox and in AffilStack\'s Inbox.'],
                    ['question' => 'Do you add an unsubscribe link?', 'answer' => 'Yes. Every campaign email includes an unsubscribe link and unsubscribed contacts are never emailed again.'],
                ],
                'cta_heading' => 'Send your first campaign this week',
                'cta_text' => 'Connect your inbox, pick a list and let AI write the first draft.',
            ],
            [
                'slug' => 'inbox-ai-replies',
                'name' => 'Inbox & AI Replies',
                'icon' => 'inbox-arrow-down',
                'menu_group' => 'outreach',
                'menu_description' => 'Every reply in one place, with an AI-drafted answer.',
                'hero_eyebrow' => 'Inbox & AI replies',
                'hero_headline' => 'Never leave a *warm reply* waiting',
                'hero_subheadline' => 'When a contact answers your campaign, AffilStack picks it up from your connected inbox, shows the whole conversation and drafts a reply that moves them towards the sale. Edit it and send it from your own address.',
                'hero_default_image' => 'inbox.webp',
                'hero_image_alt' => 'An inbox conversation with an AI-drafted reply ready to send',
                'sections' => [
                    [
                        'heading' => 'All your replies, unread first',
                        'body' => 'Replies are matched to the contact and the campaign they answered, so you always know the context. Unread conversations rise to the top.',
                        'bullets' => ['Reply detection for Gmail and IMAP mailboxes', 'Full thread per contact', 'Contact status updates to "replied" automatically'],
                        'default_image' => 'inbox.webp',
                        'image_alt' => 'The AffilStack inbox with conversations from campaign contacts',
                    ],
                    [
                        'heading' => 'Draft the reply with AI',
                        'body' => 'One click writes a reply using the conversation, the product research and the contact\'s details. Change a word or rewrite it, then send it threaded in the same conversation.',
                        'bullets' => ['Answers questions and objections', 'Keeps your tone', 'Sent from your own connected inbox'],
                        'default_image' => 'inbox.webp',
                        'image_alt' => 'An AI-drafted reply in the inbox',
                    ],
                ],
                'steps' => [
                    ['title' => 'Connect your mailbox', 'description' => 'Gmail or IMAP — reply reading is switched on in Email Sending.'],
                    ['title' => 'Open a conversation', 'description' => 'See the full thread and which campaign it came from.'],
                    ['title' => 'Draft, edit and send', 'description' => 'Let AI write the reply, adjust it, and send.'],
                ],
                'faqs' => [
                    ['question' => 'Does AffilStack read my whole mailbox?', 'answer' => 'It only looks for replies from your CRM contacts so it can show them next to the campaign. Everything else in your mailbox is ignored.'],
                ],
                'cta_heading' => 'Turn replies into sales',
                'cta_text' => 'Inbox & AI replies come with every plan that includes campaigns.',
            ],

            // ── Content ─────────────────────────────────────────────────
            self::contentFeature('blog-articles', 'Blog Articles', 'document-text', 'SEO articles with your link and disclosure built in.',
                'SEO blog articles that *rank and convert*',
                'Give it a keyword — or let AI choose one — and get a long-form, SEO-structured article for your product with a meta description, your cloaked affiliate link and the right disclosure already in place.',
                'blog-article.webp',
                [
                    ['heading' => 'Long-form, structured for search', 'body' => 'Headings, a strong introduction, comparison points and a conclusion that sends readers to your link — written about the real product from your research.', 'bullets' => ['Target keyword of your choice', 'Meta description included', 'Ready for WordPress, Medium or LinkedIn'], 'default_image' => 'blog-article.webp', 'image_alt' => 'A generated SEO blog article for a product'],
                    ['heading' => 'Planned on your content calendar', 'body' => 'Every article lands on your content calendar with a status and target date, plus a reminder to refresh it later so it keeps ranking.', 'bullets' => ['Draft, scheduled and published statuses', 'Refresh reminders for older articles'], 'default_image' => 'content-calendar.webp', 'image_alt' => 'The content calendar with a scheduled blog article'],
                ],
                [['title' => 'Choose a keyword', 'description' => 'Type the search you want to rank for, or leave it blank.'], ['title' => 'Generate the article', 'description' => 'It takes about a minute.'], ['title' => 'Publish and schedule', 'description' => 'Copy it to your blog and track it on the calendar.']],
                [['question' => 'Is the affiliate disclosure included?', 'answer' => 'Yes. The article includes the disclosure wording for the country you set on the product, and your link is cloaked so clicks are tracked.']],
            ),
            self::contentFeature('linkedin-content', 'LinkedIn Content', 'newspaper', 'Posts and long-form articles that build authority.',
                'LinkedIn posts that make you *the expert they buy from*',
                'A scroll-stopping LinkedIn post and a long-form article for your product, written from your research so your prospects see you as the person who understands their problem.',
                'linkedin-content.webp',
                [
                    ['heading' => 'Posts and articles in one pass', 'body' => 'A hook-led post for the feed and a long-form LinkedIn article that goes deeper — both mention the product naturally, never as a hard sell.', 'bullets' => ['Hook, story and call to action', 'Long-form article for LinkedIn\'s publishing tool', 'Export to a document in one click'], 'default_image' => 'linkedin-content.webp', 'image_alt' => 'A LinkedIn post and article generated for a product'],
                ],
                [['title' => 'Generate the post', 'description' => 'Open LinkedIn for your product and create the post and article.'], ['title' => 'Edit in your voice', 'description' => 'Change any line until it sounds like you.'], ['title' => 'Publish', 'description' => 'Post it and add it to your content calendar.']],
                [['question' => 'Can AffilStack post to LinkedIn for me?', 'answer' => 'You publish it yourself — AffilStack writes the content and keeps it organised on your calendar.']],
            ),
            self::contentFeature('youtube-scripts', 'YouTube Scripts', 'play-circle', 'Review scripts plus titles, descriptions and tags.',
                'YouTube scripts with a hook that *keeps them watching*',
                'AI writes a beat-by-beat YouTube review or tutorial script with B-roll ideas, then the title options, description (with your link and disclosure), keywords and tags to rank it.',
                'youtube-script.webp',
                [
                    ['heading' => 'Record-ready scripts', 'body' => 'A strong opening hook, a clear structure and B-roll suggestions for every beat, so you can film in one take.', 'bullets' => ['Hook, sections and call to action', 'B-roll ideas for every beat', 'Written about the real product'], 'default_image' => 'youtube-script.webp', 'image_alt' => 'A YouTube script generated for a product'],
                    ['heading' => 'Upload-ready metadata', 'body' => 'Title options, a description with your cloaked link and disclosure, keywords and tags — paste them straight into YouTube Studio.', 'bullets' => ['Several title options to test', 'Description with link and disclosure', 'Keywords and tags'], 'default_image' => 'youtube-script.webp', 'image_alt' => 'YouTube title, description and tags'],
                ],
                [['title' => 'Generate the script', 'description' => 'One click from the product\'s YouTube page.'], ['title' => 'Record it', 'description' => 'Read it on camera or over screen recordings.'], ['title' => 'Generate the metadata and upload', 'description' => 'Title, description and tags, ready for YouTube Studio.']],
                [['question' => 'Which plans include YouTube?', 'answer' => 'YouTube scripts are part of the content channels on most plans — the Pricing page lists the channels on each plan.']],
            ),
            self::contentFeature('ugc-video', 'UGC Video', 'video-camera', 'Talking-avatar videos for TikTok, Reels and Shorts.',
                'UGC-style videos *without a camera*',
                'Pick a proven creative angle, choose an AI presenter and voice, let AI write the script, edit it in your own words, then render a vertical video ready for TikTok, Instagram Reels and YouTube Shorts.',
                'ugc-video.webp',
                [
                    ['heading' => 'Angles, presenter, script, video', 'body' => 'Five scroll-stopping angles to choose from, a presenter and voice you like, and a script you can edit line by line before the video is rendered.', 'bullets' => ['5 creative angles per product', 'AI avatars and voices', 'Editable script before rendering'], 'default_image' => 'ugc-video.webp', 'image_alt' => 'UGC video creation steps for a product'],
                    ['heading' => 'Captions and hashtags for every platform', 'body' => 'Every video comes with captions and hashtags. Download it or publish it to your connected TikTok or Instagram account.', 'bullets' => ['9:16 vertical video', 'Captions and hashtags', 'Download or publish'], 'default_image' => 'ugc-video.webp', 'image_alt' => 'A finished UGC video with captions'],
                ],
                [['title' => 'Pick an angle', 'description' => 'Choose from five angles written for the product.'], ['title' => 'Choose your presenter', 'description' => 'An AI avatar and a voice.'], ['title' => 'Edit the script and render', 'description' => 'Then download or publish the video.']],
                [['question' => 'Do UGC videos use credits?', 'answer' => 'Yes — rendering a video uses more credits than text content. The exact cost is shown before you generate.']],
            ),
            self::contentFeature('tiktok-videos', 'TikTok Videos', 'musical-note', 'Scripts, on-screen text cues and captions.',
                'TikTok videos you can *film on your phone*',
                'A short-form script, timed on-screen text cues, a caption with your link and the hashtags to reach buyers — everything you need to film and post one TikTok.',
                'tiktok.webp',
                [
                    ['heading' => 'Everything for one video', 'body' => 'The script is timed for short-form, with text cues that tell you what to show on screen and when.', 'bullets' => ['Hook in the first two seconds', 'Timed on-screen text cues', 'Caption and hashtags'], 'default_image' => 'tiktok.webp', 'image_alt' => 'A TikTok video package for a product'],
                ],
                [['title' => 'Generate the package', 'description' => 'Script, cues, caption and hashtags.'], ['title' => 'Film it', 'description' => 'Follow the cues on your phone.'], ['title' => 'Post it', 'description' => 'Paste the caption and hashtags.']],
                [['question' => 'Do I have to appear on camera?', 'answer' => 'No — the cues work just as well for screen recordings or product shots. For a presenter without filming, use UGC Video.']],
            ),
            self::contentFeature('x-threads', 'X Threads', 'chat-bubble-bottom-center-text', 'Value-packed threads with hooks to test.',
                'X threads that *teach before they sell*',
                'A complete X (Twitter) thread that gives real value first, the disclosure on the first post, alternative opening hooks to A/B test and the best time to post.',
                'x-thread.webp',
                [
                    ['heading' => 'A thread and three hooks', 'body' => 'Post the thread as written or swap in one of the alternative hooks to see which gets more reach.', 'bullets' => ['Full thread, ready to post', 'Three alternative hooks', 'Suggested posting time'], 'default_image' => 'x-thread.webp', 'image_alt' => 'An X thread generated for a product'],
                ],
                [['title' => 'Generate the thread', 'description' => 'One click from the product page.'], ['title' => 'Pick a hook', 'description' => 'Use the one you like best.'], ['title' => 'Post and pin it', 'description' => 'Pin it to your profile for evergreen clicks.']],
                [['question' => 'Is my link tracked?', 'answer' => 'Yes — the link in the thread is your cloaked AffilStack link, so every click is counted under the X channel.']],
            ),
            self::contentFeature('pinterest-pins', 'Pinterest Pins', 'photo', 'Three pin variants with image prompts and alt text.',
                'Pinterest pins that drive *evergreen clicks*',
                'Three pins to test — each with a title, keyword-rich description, an image prompt for Canva or AI image tools and alt text — plus the board to pin them to.',
                'pinterest-pins.webp',
                [
                    ['heading' => 'Three pins to test', 'body' => 'Different angles for the same product so you learn what your audience saves and clicks.', 'bullets' => ['Titles and SEO descriptions', 'Image prompts for Canva or AI tools', 'Alt text and a suggested board'], 'default_image' => 'pinterest-pins.webp', 'image_alt' => 'Three Pinterest pin variants generated for a product'],
                ],
                [['title' => 'Generate the pins', 'description' => 'Three variants in one go.'], ['title' => 'Design the images', 'description' => 'Use the image prompts in Canva.'], ['title' => 'Pin them', 'description' => 'To the suggested board.']],
                [['question' => 'Does AffilStack create the images?', 'answer' => 'It writes detailed image prompts you can use in Canva or any AI image tool, so the pins match your brand.']],
            ),
            self::contentFeature('email-nurture', 'Email Nurture', 'envelope-open', 'A 5-email sequence for one real prospect.',
                'A 5-email sequence written for *one real prospect*',
                'Pick a contact from your CRM and AI writes five emails personalised to them — warm intro, value, proof, offer and follow-up — which you send from your connected inbox.',
                'email-nurture.webp',
                [
                    ['heading' => 'Personal, not mass email', 'body' => 'Nurture sequences are for your best prospects: every email uses what you know about the person and the product.', 'bullets' => ['Intro, value, proof, offer and follow-up', 'Suggested timing between emails', 'Send each step in one click'], 'default_image' => 'email-nurture.webp', 'image_alt' => 'A five-email nurture sequence for one CRM contact'],
                ],
                [['title' => 'Add the prospect to your CRM', 'description' => 'The Chrome extension does it for you.'], ['title' => 'Generate the sequence', 'description' => 'Pick the contact and create five emails.'], ['title' => 'Send step by step', 'description' => 'Follow the suggested timing.']],
                [['question' => 'How is this different from a campaign?', 'answer' => 'A campaign sends one email to a whole list. A nurture sequence is five emails written for one person you really want to win.']],
            ),

            // ── Grow & track ────────────────────────────────────────────
            [
                'slug' => 'link-tracking',
                'name' => 'Link Cloaking & Click Tracking',
                'icon' => 'cursor-arrow-rays',
                'menu_group' => 'grow',
                'menu_description' => 'Branded links that show which channel gets clicks.',
                'hero_eyebrow' => 'Link cloaking & click tracking',
                'hero_headline' => 'See *which channel* actually gets the clicks',
                'hero_subheadline' => 'Every affiliate link in your generated content becomes a clean, cloaked AffilStack link automatically — one per product per channel — so you can see whether blog, LinkedIn, email or TikTok is driving your clicks.',
                'hero_default_image' => 'tracked-links.webp',
                'hero_image_alt' => 'Cloaked links with click counts per channel',
                'sections' => [
                    [
                        'heading' => 'Cloaked automatically',
                        'body' => 'You never paste a raw affiliate link again. Content is generated with a short link that redirects to your affiliate URL and records the click.',
                        'bullets' => ['One link per product per channel', 'Short, clean URLs', 'No setup needed'],
                        'default_image' => 'tracked-links.webp',
                        'image_alt' => 'The Links & Clicks page',
                    ],
                    [
                        'heading' => 'Clicks over time',
                        'body' => 'Open any link to see clicks by day, country and device, so you know when and where your audience engages.',
                        'bullets' => ['Daily click chart', 'Countries and devices', 'Unique vs total clicks'],
                        'default_image' => 'link-clicks.webp',
                        'image_alt' => 'Click analytics for a single tracked link',
                    ],
                ],
                'steps' => [
                    ['title' => 'Add your affiliate link', 'description' => 'When you add a product.'],
                    ['title' => 'Generate content', 'description' => 'Links are cloaked per channel automatically.'],
                    ['title' => 'Watch the clicks', 'description' => 'Compare channels on Links & Clicks.'],
                ],
                'faqs' => [
                    ['question' => 'Will cloaking break my affiliate tracking?', 'answer' => 'No. The cloaked link redirects straight to your affiliate URL, so the network still sets its own cookie and credits you.'],
                ],
                'cta_heading' => 'Know what is working',
                'cta_text' => 'Link tracking is included on every plan.',
            ],
            [
                'slug' => 'earnings-tracker',
                'name' => 'Earnings Tracker',
                'icon' => 'banknotes',
                'menu_group' => 'grow',
                'menu_description' => 'Commissions by product and channel in one view.',
                'hero_eyebrow' => 'Earnings tracker',
                'hero_headline' => 'Every commission, *mapped to what earned it*',
                'hero_subheadline' => 'Bring your revenue in from every affiliate network — by hand or with a CSV export — and AffilStack maps it back to your products and channels, so you can see which offer and which channel is really paying.',
                'hero_default_image' => 'earnings.webp',
                'hero_image_alt' => 'Earnings by product and by channel',
                'sections' => [
                    [
                        'heading' => 'All your networks in one place',
                        'body' => 'Add an entry in seconds or upload a CSV from PartnerStack, Impact, Amazon or any network — columns such as Date, Amount, Status and Sub ID are detected automatically.',
                        'bullets' => ['Manual entries or CSV import', 'Automatic column detection', 'Unmatched entries to assign later'],
                        'default_image' => 'earnings.webp',
                        'image_alt' => 'The earnings dashboard with totals by product',
                    ],
                ],
                'steps' => [
                    ['title' => 'Export from your network', 'description' => 'Download your conversions or payouts.'],
                    ['title' => 'Import or add entries', 'description' => 'Upload the CSV or add an entry by hand.'],
                    ['title' => 'Compare products and channels', 'description' => 'Double down on what pays.'],
                ],
                'faqs' => [
                    ['question' => 'Does AffilStack connect to my affiliate network?', 'answer' => 'You import earnings with a CSV export or add them manually — that works with every network, including ones without an API.'],
                ],
                'cta_heading' => 'See what is paying',
                'cta_text' => 'The earnings tracker is included on every plan.',
            ],
            [
                'slug' => 'content-calendar',
                'name' => 'Content Calendar',
                'icon' => 'calendar-days',
                'menu_group' => 'grow',
                'menu_description' => 'Everything you create, planned across channels.',
                'hero_eyebrow' => 'Content calendar',
                'hero_headline' => 'Every piece of content, *on one calendar*',
                'hero_subheadline' => 'Everything you generate — across every product and channel — lands on one calendar with a status and a target date, plus reminders to refresh blog articles and follow up on LinkedIn.',
                'hero_default_image' => 'content-calendar.webp',
                'hero_image_alt' => 'The content calendar with planned posts across channels',
                'sections' => [
                    [
                        'heading' => 'Plan, publish, refresh',
                        'body' => 'Move content from draft to scheduled to published, and get reminders computed from what you have actually published.',
                        'bullets' => ['All channels in one view', 'Status and target date per piece', 'Refresh and follow-up reminders'],
                        'default_image' => 'content-calendar.webp',
                        'image_alt' => 'Calendar entries with statuses',
                    ],
                ],
                'steps' => [
                    ['title' => 'Generate content', 'description' => 'It is added to the calendar automatically.'],
                    ['title' => 'Set a date', 'description' => 'Plan when each piece goes out.'],
                    ['title' => 'Mark it published', 'description' => 'And act on the reminders.'],
                ],
                'faqs' => [
                    ['question' => 'Can my team use the calendar?', 'answer' => 'Yes — team seats can plan the calendar for the products they have access to.'],
                ],
                'cta_heading' => 'Stay consistent',
                'cta_text' => 'The content calendar is included on every plan.',
            ],
            [
                'slug' => 'competitor-angles',
                'name' => 'Competitor Angles',
                'icon' => 'eye',
                'menu_group' => 'grow',
                'menu_description' => 'See overused angles and find the gap.',
                'hero_eyebrow' => 'Competitor angles',
                'hero_headline' => 'Stop saying what *every other affiliate* says',
                'hero_subheadline' => 'AI scans the angles other affiliates typically use for a product, rates how saturated each one is and suggests a fresh angle nobody is using yet.',
                'hero_default_image' => 'competitor-angles.webp',
                'hero_image_alt' => 'A saturation map of competitor angles with a differentiation opportunity',
                'sections' => [
                    [
                        'heading' => 'A saturation map',
                        'body' => 'See which angles are overused, which are moderately used and where there is room — then lead with the gap in every piece of content.',
                        'bullets' => ['Saturation rating per angle', 'A differentiated angle to use', 'Feeds your next content'],
                        'default_image' => 'competitor-angles.webp',
                        'image_alt' => 'Competitor angle analysis',
                    ],
                ],
                'steps' => [
                    ['title' => 'Run the scan', 'description' => 'From the product\'s Competitor angles page.'],
                    ['title' => 'Avoid the crowded angles', 'description' => 'Skip what everyone is saying.'],
                    ['title' => 'Use the gap', 'description' => 'Lead your next content with the fresh angle.'],
                ],
                'faqs' => [],
                'cta_heading' => 'Stand out from other affiliates',
                'cta_text' => 'Competitor angles are included on every plan.',
            ],
            [
                'slug' => 'localization',
                'name' => 'Localization',
                'icon' => 'globe-europe-africa',
                'menu_group' => 'grow',
                'menu_description' => 'Adapt content for Nigeria, the UK, Kenya and more.',
                'hero_eyebrow' => 'Localization',
                'hero_headline' => 'One product, *ready for every market*',
                'hero_subheadline' => 'Take content you already generated and rewrite it for another market — local spelling, currency, cultural references and the right disclosure wording — for Nigeria, Ghana, Kenya, the UK, the US, Canada and more.',
                'hero_default_image' => 'localization.webp',
                'hero_image_alt' => 'Localizing a blog article for a different market',
                'sections' => [
                    [
                        'heading' => 'More than translation',
                        'body' => 'Localization adapts examples, prices and references so the piece reads like it was written for that audience — and swaps the disclosure for the one that market expects.',
                        'bullets' => ['Local spelling and currency', 'Cultural references that land', 'Market-specific disclosure'],
                        'default_image' => 'localization.webp',
                        'image_alt' => 'A localized copy of generated content',
                    ],
                ],
                'steps' => [
                    ['title' => 'Generate content first', 'description' => 'In any other feature.'],
                    ['title' => 'Pick the piece and the market', 'description' => 'From the product\'s Localization page.'],
                    ['title' => 'Publish the localized copy', 'description' => 'It appears on your calendar with the market\'s flag.'],
                ],
                'faqs' => [],
                'cta_heading' => 'Reach buyers in every market',
                'cta_text' => 'Localization is included on every plan.',
            ],
            [
                'slug' => 'swipe-files',
                'name' => 'Swipe Files',
                'icon' => 'rectangle-stack',
                'menu_group' => 'grow',
                'menu_description' => 'Proven hooks, subject lines and thumbnail styles.',
                'hero_eyebrow' => 'Swipe files',
                'hero_headline' => 'Never start from *a blank page*',
                'hero_subheadline' => 'A curated library of proven hooks, email subject lines and thumbnail styles by niche — patterns to start from, curated by the AffilStack team rather than generated by AI.',
                'hero_default_image' => 'swipe-files.webp',
                'hero_image_alt' => 'The swipe file library filtered by niche',
                'sections' => [
                    [
                        'heading' => 'Curated by niche',
                        'body' => 'Filter by niche and type, copy what fits, and adapt it to your product.',
                        'bullets' => ['Hooks for video and social', 'Email subject lines', 'Thumbnail styles'],
                        'default_image' => 'swipe-files.webp',
                        'image_alt' => 'Swipe file entries',
                    ],
                ],
                'steps' => [
                    ['title' => 'Open Swipe Files', 'description' => 'From the dashboard menu.'],
                    ['title' => 'Filter by niche', 'description' => 'Find patterns that fit your product.'],
                    ['title' => 'Adapt and use', 'description' => 'Copy the pattern into your content.'],
                ],
                'faqs' => [],
                'cta_heading' => 'Write faster with proven patterns',
                'cta_text' => 'Swipe files are included on every plan.',
            ],
            [
                'slug' => 'team-seats',
                'name' => 'Team Seats',
                'icon' => 'user-group',
                'menu_group' => 'grow',
                'menu_description' => 'Logins for your VA or team, with the right access.',
                'hero_eyebrow' => 'Team seats',
                'hero_headline' => 'Bring your team in *without sharing a password*',
                'hero_subheadline' => 'Give a VA or teammate their own login. They create content and plan the calendar on your credits — without access to billing, earnings or anything you don\'t share.',
                'hero_default_image' => 'team-seats.webp',
                'hero_image_alt' => 'The Team page with invited members and their roles',
                'sections' => [
                    [
                        'heading' => 'The right access for each person',
                        'body' => 'On most plans a seat is scoped to exactly one product. On the Business plan seats share every product, and you choose whether each person is a Member (create and plan) or a Manager (can also publish).',
                        'bullets' => ['Separate logins, shared credits', 'Scoped to one product or shared', 'Member and Manager roles'],
                        'default_image' => 'team-seats.webp',
                        'image_alt' => 'Team members and roles',
                    ],
                ],
                'steps' => [
                    ['title' => 'Invite a teammate', 'description' => 'Enter their name and email.'],
                    ['title' => 'Choose their access', 'description' => 'A product or a role.'],
                    ['title' => 'They log in', 'description' => 'And get to work on your credits.'],
                ],
                'faqs' => [
                    ['question' => 'How many seats do I get?', 'answer' => 'Each plan includes a number of team seats — see the Pricing page.'],
                ],
                'cta_heading' => 'Grow with a team',
                'cta_text' => 'Team seats are included on every plan.',
            ],
            [
                'slug' => 'api-integrations',
                'name' => 'API & Integrations',
                'icon' => 'code-bracket-square',
                'menu_group' => 'grow',
                'menu_description' => 'Zapier, Make, n8n, Sheets, WordPress or your own code.',
                'hero_eyebrow' => 'API & integrations',
                'hero_headline' => 'Connect AffilStack to *the tools you already use*',
                'hero_subheadline' => 'Send new contacts out, pull generated content in and start product research automatically — with Zapier, Make, n8n, Google Sheets, WordPress or your own code through the REST API and SDKs.',
                'hero_default_image' => 'api-hub.webp',
                'hero_image_alt' => 'The API & Integrations hub with ready-made recipes',
                'sections' => [
                    [
                        'heading' => 'Recipes and a real API',
                        'body' => 'Start from ready-made integration recipes or build your own with API keys, webhooks and SDKs for popular languages.',
                        'bullets' => ['Personal API keys', 'Webhooks for new contacts and content', 'SDKs and copy-ready examples'],
                        'default_image' => 'api-hub.webp',
                        'image_alt' => 'API keys and integration recipes',
                    ],
                ],
                'steps' => [
                    ['title' => 'Create an API key', 'description' => 'On the API & Integrations page.'],
                    ['title' => 'Pick a recipe', 'description' => 'Zapier, Make, n8n, Sheets or WordPress.'],
                    ['title' => 'Automate', 'description' => 'Contacts out, content in, research on demand.'],
                ],
                'faqs' => [],
                'cta_heading' => 'Automate your affiliate workflow',
                'cta_text' => 'API access is available on every plan.',
            ],
        ];
    }

    /**
     * A content-channel page — the shared shape keeps the eight channel
     * entries readable.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<int, array{title: string, description: string}>  $steps
     * @param  array<int, array{question: string, answer: string}>  $faqs
     * @return array<string, mixed>
     */
    protected static function contentFeature(string $slug, string $name, string $icon, string $menuDescription, string $headline, string $subheadline, string $image, array $sections, array $steps, array $faqs): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'icon' => $icon,
            'menu_group' => 'content',
            'menu_description' => $menuDescription,
            'hero_eyebrow' => $name,
            'hero_headline' => $headline,
            'hero_subheadline' => $subheadline,
            'hero_default_image' => $image,
            'hero_image_alt' => $name.' generated by AffilStack for a real product',
            'sections' => $sections,
            'steps' => $steps,
            'faqs' => $faqs,
            'cta_heading' => 'Try '.$name.' on your next product',
            'cta_text' => 'Research a product and generate it in a couple of clicks.',
        ];
    }

    /**
     * Inserts any catalog page whose slug isn't in the table yet, in
     * catalog order. Never touches existing rows.
     */
    public static function seedMissing(): void
    {
        $existing = FeaturePage::query()->pluck('slug')->all();

        foreach (static::all() as $index => $page) {
            if (in_array($page['slug'], $existing, true)) {
                continue;
            }

            FeaturePage::query()->create($page + [
                'sort_order' => ($index + 1) * 10,
                'show_in_menu' => true,
                'is_published' => true,
            ]);
        }
    }
}
