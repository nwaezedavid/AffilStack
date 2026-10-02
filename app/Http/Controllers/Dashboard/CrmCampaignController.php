<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmCampaign;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Services\AI\AIGenerationException;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Crm\CampaignManager;
use App\Services\Crm\CampaignMergeFields;
use App\Services\Crm\SendingQuota;
use App\Services\Links\LinkCloakingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * CRM campaigns: pick a list → AI writes a personalised cold email for that
 * product from its research → the user edits it with a live preview →
 * "Approve and Send". Afterwards the same page is the campaign's history
 * (every recipient's status), with "Send to N new contacts", autopilot and
 * pause/resume. Agent 10's product pages link here with ?list=.
 */
class CrmCampaignController extends Controller
{
    public function __construct(protected CampaignManager $manager) {}

    public function index(): View
    {
        $campaigns = auth()->user()->crmCampaigns()
            ->with(['list', 'offer:id,product_name'])
            ->withCount([
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->whereIn('status', ['sent', 'replied']),
                'recipients as replied_count' => fn ($q) => $q->where('status', 'replied'),
                'sends as opened_count' => fn ($q) => $q->whereNotNull('opened_at'),
            ])
            ->latest()
            ->paginate(20);

        return view('dashboard.crm.campaigns.index', compact('campaigns'));
    }

    public function create(Request $request, CreditManager $credits): View
    {
        $user = auth()->user();
        $lists = $user->crmLists()->with('offer:id,product_name')->withCount('contacts')->orderByRaw('offer_id is null')->orderBy('name')->get();
        $selected = $lists->firstWhere('id', (int) $request->query('list')) ?? $lists->first();

        return view('dashboard.crm.campaigns.create', [
            'lists' => $lists,
            'selected' => $selected,
            'cost' => (int) config('crm.credits.campaign_generate'),
            'openingCost' => (int) config('crm.credits.personal_opening'),
            'balance' => $credits->balance($user),
            'connection' => $user->emailConnection,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'integer', Rule::exists('crm_lists', 'id')->where('user_id', auth()->id())],
            'name' => 'nullable|string|max:150',
            'goal' => 'nullable|string|max:500',
            'tone' => 'nullable|in:friendly and professional,warm and casual,direct and concise,consultative expert',
            'follow_ups' => 'nullable|integer|min:0|max:3',
            'personalize_opening' => 'nullable|boolean',
            'mode' => 'required|in:ai,manual',
        ]);

        $list = CrmList::findOrFail($validated['list_id']);
        $offer = $list->offer;

        if ($validated['mode'] === 'manual') {
            $campaign = CrmCampaign::create([
                'user_id' => auth()->id(),
                'crm_list_id' => $list->id,
                'offer_id' => $offer?->id,
                'name' => ($validated['name'] ?? null) ?: ($offer?->product_name ?? $list->name).' outreach — '.now()->format('M j'),
                'subject' => 'Quick idea for {company}',
                'body' => "Hi {first_name},\n\n\n\n".($offer ? "You can take a look here: {affiliate_link}\n\n" : '').'Best,',
                'personalize_opening' => (bool) ($validated['personalize_opening'] ?? false),
                'follow_up_steps' => [],
                'status' => 'draft',
            ]);

            return redirect()->route('crm.campaigns.show', $campaign)->with('success', 'Blank campaign created — write your email below.');
        }

        try {
            $campaign = $this->manager->generate(auth()->user(), $list, $offer, [
                'name' => $validated['name'] ?? null,
                'goal' => $validated['goal'] ?? null,
                'tone' => $validated['tone'] ?? null,
                'follow_ups' => (int) ($validated['follow_ups'] ?? 2),
                'personalize_opening' => (bool) ($validated['personalize_opening'] ?? false),
            ]);
        } catch (InsufficientCreditsException $e) {
            return back()->withInput()->with('error', $e->getMessage().' Top up your credits, or choose "Write it myself".');
        } catch (AIGenerationException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('crm.campaigns.show', $campaign)->with('success', 'Your campaign is written — review and edit it, then click "Approve and Send".');
    }

    public function show(CrmCampaign $campaign, SendingQuota $quota, CampaignMergeFields $mergeFields, LinkCloakingService $links): View
    {
        $this->authorizeCampaign($campaign);
        $user = auth()->user();
        $campaign->load(['list', 'offer']);

        $samples = $campaign->list->contacts()->orderByRaw("case when email is null or email = '' then 1 else 0 end")->limit(10)->get();
        $previewLink = $campaign->offer ? $links->getOrCreateForOffer($campaign->offer, 'email_campaign')->short_url : '';
        $sampleData = $samples->map(fn (CrmContact $c) => [
            'id' => $c->id,
            'label' => ($c->name ?: $c->company ?: 'Contact #'.$c->id).($c->email ? '' : ' (no email)'),
            'email' => $c->email,
            'values' => $mergeFields->valuesFor($c, $campaign->offer, $campaign->personalize_opening ? '[AI opening line written for this contact at send time]' : null, $previewLink),
        ])->values();

        if ($sampleData->isEmpty()) {
            $placeholder = new CrmContact(['name' => 'Ada Obi', 'company' => 'Sunrise Dental', 'location' => '12 Allen Ave, Ikeja, Lagos, Nigeria', 'website' => 'https://sunrisedental.ng', 'email' => 'ada@sunrisedental.ng']);
            $sampleData = collect([['id' => 0, 'label' => 'Example contact', 'email' => 'ada@sunrisedental.ng', 'values' => $mergeFields->valuesFor($placeholder, $campaign->offer, null, $previewLink ?: 'https://example.com/go/abc123')]]);
        }

        $common = [
            'campaign' => $campaign,
            'connection' => $user->emailConnection,
            'samples' => $sampleData,
            'fields' => CampaignMergeFields::FIELDS,
            'remainingToday' => $quota->remaining($user),
            'dailyCap' => $quota->cap($user),
        ];

        if ($campaign->isDraft()) {
            return view('dashboard.crm.campaigns.edit', $common + [
                'audience' => $campaign->list->contacts()->count(),
                'withEmail' => $campaign->list->contacts()->whereNotNull('email')->where('email', '!=', '')->whereNull('unsubscribed_at')->count(),
                'unknownFields' => $mergeFields->unknownFields($campaign->subject.' '.$campaign->body.' '.collect($campaign->follow_up_steps ?? [])->pluck('body')->implode(' ')),
                'openingCost' => (int) config('crm.credits.personal_opening'),
            ]);
        }

        $recipients = $campaign->recipients()->with(['contact', 'sends'])->latest('updated_at')->paginate(30);
        $counts = $campaign->recipients()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('dashboard.crm.campaigns.show', $common + [
            'recipients' => $recipients,
            'counts' => $counts,
            'opened' => $campaign->sends()->whereNotNull('opened_at')->distinct('crm_contact_id')->count('crm_contact_id'),
            'clicked' => $campaign->sends()->whereNotNull('first_clicked_at')->distinct('crm_contact_id')->count('crm_contact_id'),
            'newContacts' => $campaign->newContactsQuery()->count(),
        ]);
    }

    /**
     * Saves the draft; with intent=approve, then launches it.
     */
    public function update(Request $request, CrmCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        abort_unless($campaign->isDraft(), 422, 'Only a draft can be edited.');

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:10000',
            'autopilot' => 'nullable|boolean',
            'personalize_opening' => 'nullable|boolean',
            'follow_ups' => 'nullable|array|max:3',
            'follow_ups.*.delay_days' => 'nullable|integer|min:1|max:30',
            'follow_ups.*.subject' => 'nullable|string|max:200',
            'follow_ups.*.body' => 'nullable|string|max:5000',
            'intent' => 'nullable|in:save,approve',
        ]);

        $campaign->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'autopilot' => (bool) ($validated['autopilot'] ?? false),
            'personalize_opening' => (bool) ($validated['personalize_opening'] ?? false),
            'follow_up_steps' => collect($validated['follow_ups'] ?? [])
                ->filter(fn ($step) => trim((string) ($step['body'] ?? '')) !== '')
                ->map(fn ($step) => ['delay_days' => (int) ($step['delay_days'] ?? 3), 'subject' => (string) ($step['subject'] ?? ''), 'body' => (string) $step['body']])
                ->values()
                ->all(),
        ]);

        if (($validated['intent'] ?? 'save') !== 'approve') {
            return back()->with('success', 'Draft saved.');
        }

        $connection = auth()->user()->emailConnection;

        if (config('crm.require_connection') && (! $connection || ! $connection->isVerified())) {
            return back()->with('error', 'Connect and verify your Gmail or SMTP on the Email Sending page first — campaigns always send from your own address.');
        }

        try {
            $queued = $this->manager->approve($campaign->fresh());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('crm.campaigns.show', $campaign)->with('success', "Approved! {$queued} contact(s) queued — emails go out in paced batches from {$connection?->connected_email}.");
    }

    public function sendToNew(CrmCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        try {
            $queued = $this->manager->sendToNewContacts($campaign);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $queued > 0 ? "{$queued} new contact(s) queued for this campaign." : 'Everyone on the list has already been sent this campaign.');
    }

    public function autopilot(Request $request, CrmCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        $on = $request->boolean('autopilot');
        $campaign->update(['autopilot' => $on]);

        return back()->with('success', $on
            ? 'Autopilot is on — every new contact added to "'.$campaign->list->name.'" will get this campaign automatically.'
            : 'Autopilot is off — new contacts wait until you click "Send to new contacts".');
    }

    public function pause(CrmCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        abort_unless($campaign->isLaunched(), 422);
        $this->manager->pause($campaign, 'manual');

        return back()->with('success', 'Campaign paused — nothing more will send until you resume it.');
    }

    public function resume(CrmCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        abort_unless($campaign->isPaused(), 422);

        $connection = auth()->user()->emailConnection;

        if (config('crm.require_connection') && (! $connection || ! $connection->isVerified())) {
            return back()->with('error', 'Your email connection still needs fixing — verify it on the Email Sending page, then resume.');
        }

        $this->manager->resume($campaign);

        return back()->with('success', 'Campaign resumed.');
    }

    public function destroy(CrmCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        if (! $campaign->isDraft()) {
            return back()->with('error', 'Sent campaigns stay in your history. Pause it instead.');
        }

        $campaign->delete();

        return redirect()->route('crm.campaigns.index')->with('success', 'Draft deleted.');
    }

    protected function authorizeCampaign(CrmCampaign $campaign): void
    {
        abort_unless($campaign->user_id === auth()->id(), 403);
    }
}
