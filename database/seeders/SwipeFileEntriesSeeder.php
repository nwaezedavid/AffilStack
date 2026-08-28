<?php

namespace Database\Seeders;

use App\Models\SwipeFileEntry;
use Illuminate\Database\Seeder;

/**
 * Starter swipe file library so the page isn't empty on a fresh install.
 * These are well-established copywriting/marketing structures (curiosity
 * gaps, before/after contrast, specific-number proof, us-vs-them framing)
 * adapted per niche — not fabricated quotes or attributed testimonials.
 * Admins add/replace entries from Admin → Content → Swipe Files.
 */
class SwipeFileEntriesSeeder extends Seeder
{
    public function run(): void
    {
        $niches = [
            'health_wellness' => [
                'hooks' => [
                    ['title' => 'Ignored Symptom Confession', 'content' => "I ignored this symptom for 3 years. Here's what happened when I finally got it checked.", 'notes' => 'Confession + consequence — creates urgency through personal stakes without diagnosing the reader.'],
                    ['title' => 'Doctor Never Mentioned', 'content' => 'The $12 supplement my doctor never mentioned (but wishes she had).', 'notes' => 'Authority-adjacent curiosity gap; the low price removes the "too good to be true" objection.'],
                ],
                'subject_lines' => [
                    ['title' => 'Labs Came Back', 'content' => "Your labs came back... and this is what they didn't tell you", 'notes' => 'Curiosity gap tied to something the reader already relates to.'],
                    ['title' => 'Energy Crash Reframe', 'content' => '3 signs your energy crash isn\'t "just stress"', 'notes' => 'Numbered list + reframes a common assumption — both proven open-rate drivers.'],
                ],
                'thumbnail_styles' => [
                    ['title' => 'Energy Before/After', 'content' => 'Split-screen: tired/pale face on left, energized/glowing face on right, bold text overlay "DAY 1 vs DAY 30".', 'notes' => 'Before/after visual contrast is the highest-CTR pattern in health content.'],
                    ['title' => 'Not-That-One Bottle', 'content' => 'Close-up of a hand holding a pill bottle, with a red circle-and-slash icon over a different generic bottle in the background.', 'notes' => 'Visually implies "not that one, this one" without naming a competitor.'],
                ],
            ],
            'finance_investing' => [
                'hooks' => [
                    ['title' => 'Specific-Number Proof', 'content' => "I put $500 into this in January. Here's what it's worth now.", 'notes' => 'Specific numbers + time-bound curiosity — a classic proof-hook structure.'],
                    ['title' => 'Hidden Fee Grievance', 'content' => 'Nobody explains this fee your bank charges you every single month.', 'notes' => 'Us-vs-them framing (reader vs. institution) taps into an existing grievance.'],
                ],
                'subject_lines' => [
                    ['title' => 'Bank Math', 'content' => 'The math your bank hopes you never do', 'notes' => 'Antagonist framing — implies the institution benefits from the reader not knowing.'],
                    ['title' => '401k Discovery', 'content' => 'I was today years old when I found this out about my 401(k)', 'notes' => 'Relatable meme-format phrasing performs well as a subject line for financial surprises.'],
                ],
                'thumbnail_styles' => [
                    ['title' => 'Upward Chart Highlight', 'content' => 'Simple line chart trending sharply upward, a big bold dollar figure in the corner, a red circle highlighting the inflection point.', 'notes' => 'Charts with one highlighted moment read faster than dense data.'],
                    ['title' => 'Stack Comparison', 'content' => 'Two stacks of cash side by side — one labeled "What I was doing" (small stack), one labeled "What I do now" (tall stack).', 'notes' => 'Physical size comparison communicates magnitude instantly.'],
                ],
            ],
            'saas_software' => [
                'hooks' => [
                    ['title' => 'Consolidation Hook', 'content' => "I replaced 4 tools with this one. My team didn't believe me until they tried it.", 'notes' => 'Consolidation angle — appeals to the reader\'s existing tool fatigue.'],
                    ['title' => 'Buried Feature', 'content' => "This feature is buried 3 menus deep — and it's the reason I'll never switch tools.", 'notes' => 'Insider-knowledge framing rewards a reader for paying attention.'],
                ],
                'subject_lines' => [
                    ['title' => 'Ignored Setting', 'content' => "The setting you're probably ignoring (it changes everything)", 'notes' => 'Implies the reader is missing something specific and fixable, not a vague upgrade.'],
                    ['title' => 'Onboarding Cut', 'content' => 'We cut our onboarding time in half. Here\'s the exact workflow.', 'notes' => 'Specific, credible metric plus the promise of a reusable process.'],
                ],
                'thumbnail_styles' => [
                    ['title' => 'Consolidated Icons', 'content' => 'Cluttered desktop with 5 app icons crossed out in red, replaced by a single clean app icon with a green checkmark.', 'notes' => 'Visual simplification mirrors the "fewer tools" promise in the hook.'],
                    ['title' => 'Circled Metric Jump', 'content' => 'Screenshot of a dashboard with one metric circled in red and an arrow pointing to a dramatically higher number.', 'notes' => 'Draws the eye to one number rather than a busy full dashboard.'],
                ],
            ],
            'beauty_skincare' => [
                'hooks' => [
                    ['title' => 'Esthetician Swap', 'content' => 'My esthetician told me to stop using this — and start using this instead.', 'notes' => 'Authority endorsement plus a clear before/after action, not just a product mention.'],
                    ['title' => 'Budget Beats Premium', 'content' => 'This $9 product outperformed my $80 serum in 2 weeks.', 'notes' => 'Price-gap surprise — cheap beating expensive is inherently counter-intuitive and shareable.'],
                ],
                'subject_lines' => [
                    ['title' => 'Aging Rule Reversal', 'content' => "The skincare 'rule' that's actually aging you faster", 'notes' => 'Contradicts common advice — strong curiosity driver in a crowded niche.'],
                    ['title' => 'One-Thing Change', 'content' => 'What changed after I stopped doing THIS one thing', 'notes' => 'Vague-but-specific — names an action without spoiling it, driving the open.'],
                ],
                'thumbnail_styles' => [
                    ['title' => 'Skin Texture Split', 'content' => 'Extreme close-up split-screen of skin texture — left labeled "Before" with visible texture, right labeled "After" smooth and glowing.', 'notes' => 'Macro detail builds more credibility than a full-face shot for skincare specifically.'],
                    ['title' => 'Price Callout', 'content' => 'Product bottle front and center with a hand-drawn red arrow and a circled low price tag.', 'notes' => 'Hand-drawn elements read as authentic/unsponsored, increasing trust.'],
                ],
            ],
            'fitness' => [
                'hooks' => [
                    ['title' => 'Cardio Swap', 'content' => 'I stopped doing cardio and lost more weight. Here\'s the exact swap I made.', 'notes' => 'Contradicts conventional wisdom (cardio = weight loss), creating a curiosity gap.'],
                    ['title' => 'Ab Routine Replacement', 'content' => 'This 5-minute move replaced my entire ab routine.', 'notes' => 'Time-scarcity + simplification — a strong combo for a busy audience.'],
                ],
                'subject_lines' => [
                    ['title' => 'Effort Reframe', 'content' => "Why your workouts stopped working (it's not effort)", 'notes' => 'Removes blame from the reader, which lowers resistance to opening.'],
                    ['title' => 'Plateau Fix', 'content' => 'The 5-minute fix for the plateau you\'re stuck at', 'notes' => 'Names a specific, common frustration (plateau) rather than a generic promise.'],
                ],
                'thumbnail_styles' => [
                    ['title' => 'Week Counter Transformation', 'content' => 'Side-by-side body transformation photos with a bold week counter ("WEEK 1" / "WEEK 8") stamped in the corner.', 'notes' => 'The counter makes the timeframe concrete, which increases believability.'],
                    ['title' => 'Freeze-Frame Muscle Callout', 'content' => 'Single exercise mid-movement freeze-frame with a big red circle around the muscle group being worked.', 'notes' => 'Answers "what does this actually work?" before the viewer even clicks.'],
                ],
            ],
            'home_diy' => [
                'hooks' => [
                    ['title' => 'Contractor Cost Avoided', 'content' => 'This $15 tool saved me from paying a contractor $400.', 'notes' => 'Extreme cost-gap number is the whole hook — let the math do the persuading.'],
                    ['title' => 'Remodel Regret', 'content' => 'I wish someone told me about this before I remodeled my kitchen.', 'notes' => 'Regret framing appeals to readers currently planning a similar project.'],
                ],
                'subject_lines' => [
                    ['title' => 'Wish I Bought Sooner', 'content' => "The tool I wish I'd bought years ago", 'notes' => 'Personal regret angle performs consistently well for practical/DIY products.'],
                    ['title' => 'Fix Cost Comparison', 'content' => 'How I fixed this for $15 (not $400)', 'notes' => 'Leads with the specific dollar comparison right in the subject line.'],
                ],
                'thumbnail_styles' => [
                    ['title' => 'Room Before/After', 'content' => 'Wide-angle before/after room shot split down the middle, dramatic lighting contrast between the two halves.', 'notes' => 'Wide shots work better than close-ups for whole-room transformations.'],
                    ['title' => 'Price Crossout', 'content' => 'Tool in hand mid-use with a bold price tag graphic ("$15") next to a crossed-out higher price.', 'notes' => 'The crossed-out higher price is a classic anchor-and-discount visual.'],
                ],
            ],
            'tech_gadgets' => [
                'hooks' => [
                    ['title' => 'Wrong Setting Fix', 'content' => "I used the 'wrong' setting for 2 years. This fixed it in 10 seconds.", 'notes' => 'Time-to-fix (10 seconds) contrasted with time-wasted (2 years) is the hook.'],
                    ['title' => 'Accessory Justifies Purchase', 'content' => 'This $30 accessory made my $1,200 phone actually worth it.', 'notes' => 'Small accessory unlocking value from a big prior purchase is a strong upsell angle.'],
                ],
                'subject_lines' => [
                    ['title' => 'Battery Drain Setting', 'content' => "The setting that's draining your battery right now", 'notes' => '"Right now" urgency plus a fixable, specific complaint.'],
                    ['title' => 'Worth-It Accessory', 'content' => '$30 that made my $1,200 purchase worth it', 'notes' => 'Leads with the price gap, same structure as the matching hook above.'],
                ],
                'thumbnail_styles' => [
                    ['title' => 'Upgrade Pairing', 'content' => 'Product held up next to the device it upgrades, an oversized red arrow pointing between them, bold "GAME CHANGER" text.', 'notes' => 'Pairing the accessory visually with the device it improves clarifies the value instantly.'],
                    ['title' => 'Settings Before/After', 'content' => 'Split-screen of a settings menu — left showing the default (dim) state, right showing the "fixed" (optimized) state.', 'notes' => 'Screenshots read as more credible than staged photos for software-adjacent fixes.'],
                ],
            ],
            'general' => [
                'hooks' => [
                    ['title' => 'Nobody Talks About This', 'content' => 'Nobody talks about this, but it changed everything for me.', 'notes' => 'Works across almost any niche — the vagueness is intentional, built to be filled in.'],
                    ['title' => 'Skeptic Converted', 'content' => 'I was skeptical too — until I actually tried it.', 'notes' => 'Pre-empts the reader\'s own skepticism, which is often the biggest objection.'],
                ],
                'subject_lines' => [
                    ['title' => 'Quick Question', 'content' => 'Quick question about [problem they have]', 'notes' => 'Personal, low-pressure framing that reads like a real message, not a broadcast.'],
                    ['title' => 'Small Time, Big Result', 'content' => 'This took me 5 minutes and saved me hours', 'notes' => 'Time-investment-vs-payoff framing works for almost any product category.'],
                ],
                'thumbnail_styles' => [
                    ['title' => 'Reaction Face', 'content' => 'Reaction-face close-up (shocked/wide-eyed expression) with a bold red arrow pointing at the product or result.', 'notes' => 'The most-used YouTube thumbnail pattern across virtually every niche.'],
                    ['title' => 'Text-Only High Contrast', 'content' => 'Text-on-background thumbnail: a big bold question mark next to the product, no face, high-contrast colors.', 'notes' => 'A reliable fallback when no strong photo/face asset is available.'],
                ],
            ],
        ];

        $typeKeyToColumn = [
            'hooks' => 'hook',
            'subject_lines' => 'subject_line',
            'thumbnail_styles' => 'thumbnail_style',
        ];

        $sortOrder = 0;

        foreach ($niches as $niche => $groups) {
            foreach ($groups as $groupKey => $entries) {
                foreach ($entries as $entry) {
                    SwipeFileEntry::updateOrCreate(
                        ['niche' => $niche, 'type' => $typeKeyToColumn[$groupKey], 'title' => $entry['title']],
                        [
                            'content' => $entry['content'],
                            'notes' => $entry['notes'],
                            'is_published' => true,
                            'sort_order' => $sortOrder++,
                        ],
                    );
                }
            }
        }
    }
}
