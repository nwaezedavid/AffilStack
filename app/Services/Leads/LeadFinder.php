<?php

namespace App\Services\Leads;

use App\Jobs\FindLeads;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\LeadRun;
use App\Models\LeadSourceSetting;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\GenerationCompleted;
use App\Services\Credits\CreditManager;
use App\Services\Dashboard\ProgressCounter;
use App\Support\Agents\AgentSettings;
use App\Support\ResearchPresenter;
use Generator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * B2B lead finding: finds buyers for a product, checks their emails, scores
 * how well they fit, tags them and files them into the product's CRM list.
 *
 * Sources, in order: Apollo (decision-makers by job title, when the admin
 * has connected it), then Google Maps businesses with the contact emails
 * published on their own websites, topped up by Hunter when the site lists
 * none. LinkedIn is never scraped — the product page links to LinkedIn
 * searches and message drafts instead.
 *
 * Leads inside the plan's monthly allowance are free; past it each costs
 * credits. Paid providers' results are business emails only, and every
 * lead records where it came from and the lawful basis (B2B legitimate
 * interest) for contacting it.
 */
class LeadFinder
{
    protected ?LeadSourceSetting $settings = null;

    public function __construct(
        protected LeadAllowance $allowance,
        protected CreditManager $credits,
        protected LeadScorer $scorer,
        protected WebsiteEmailFinder $websiteEmails,
        protected GoogleMapsLeadService $maps,
    ) {}

    /**
     * @return array{google_maps: bool, apollo: bool, hunter: bool}
     */
    public function sources(): array
    {
        $settings = $this->settings();

        return [
            'google_maps' => filled(config('services.google_places.api_key')),
            'apollo' => $settings->apolloReady(),
            'hunter' => $settings->hunterReady(),
        ];
    }

    public function isAvailable(): bool
    {
        $sources = $this->sources();

        return $sources['google_maps'] || $sources['apollo'];
    }

    /**
     * Starts a run in the background.
     *
     * @throws LeadFinderException with a message safe to show the customer
     */
    public function queue(User $user, ?Offer $offer, int $target, string $trigger = 'manual'): LeadRun
    {
        $owner = $user->billableUser();

        if (! $this->isAvailable()) {
            Log::warning('Lead finding requested but no lead source (Google Places key or Apollo) is configured.');

            throw new LeadFinderException('Lead finding is being set up — please try again later.');
        }

        $active = LeadRun::query()->where('user_id', $owner->id)
            ->when($offer, fn ($query) => $query->where('offer_id', $offer->id))
            ->whereIn('status', [LeadRun::STATUS_QUEUED, LeadRun::STATUS_RUNNING])
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if ($active) {
            throw new LeadFinderException('AffilStack is already finding leads for this product — they\'ll appear in a few minutes.');
        }

        if ($this->allowance->dailyRemaining($owner) === 0) {
            throw new LeadFinderException('You\'ve reached today\'s lead limit. More leads can be found tomorrow.');
        }

        if ($this->affordable($owner) === 0) {
            throw new LeadFinderException('You\'ve used this month\'s included leads and don\'t have enough credits for more. Buy a credit top-up or upgrade your plan.');
        }

        $run = LeadRun::create([
            'user_id' => $owner->id,
            'offer_id' => $offer?->id,
            'trigger' => $trigger,
            'target' => max(1, min(max((array) config('leads.run_sizes', [100])), $target)),
            'status' => LeadRun::STATUS_QUEUED,
        ]);

        FindLeads::dispatch($run);

        return $run;
    }

    /**
     * How many leads the account can still pay for: the free allowance
     * plus whatever its credit balance covers.
     */
    public function affordable(User $owner): int
    {
        $free = $this->allowance->freeRemaining($owner);

        if ($free === PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        $perLead = max(1, $this->allowance->costPerPaidLead());

        return $free + intdiv($this->credits->balance($owner), $perLead);
    }

    /**
     * Called by the FindLeads job. Always leaves the run completed or failed.
     */
    public function run(LeadRun $run): void
    {
        $run->update(['status' => LeadRun::STATUS_RUNNING, 'started_at' => now()]);
        $owner = $run->user->billableUser();
        $offer = $run->offer;

        try {
            $target = min($run->target, $this->allowance->dailyRemaining($owner), $this->affordable($owner), $this->contactRoom($owner));

            if ($target <= 0) {
                $this->finish($run, LeadRun::STATUS_FAILED, 'No leads were found: today\'s limit, your contact limit or your credits ran out.');

                return;
            }

            $known = $this->knownKeys($owner);
            $leads = [];
            $found = 0;
            $sourcesUsed = [];

            foreach ($this->candidates($offer, $target) as $candidate) {
                if (count($leads) >= $target) {
                    break;
                }

                $found++;

                if ($this->isKnown($candidate, $known)) {
                    continue;
                }

                $lead = $this->complete($candidate);

                if ($lead === null) {
                    continue;
                }

                // The same business under another listing (a second branch, an older import).
                $domain = LeadEmail::domainOfUrl($lead['website'] ?? null);

                if ($lead['source'] === 'google_maps' && $domain && isset($known['domains'][$domain])) {
                    continue;
                }

                $this->remember($lead, $known);
                $leads[] = $lead;
                $sourcesUsed[$lead['source']] = true;
            }

            $run->update(['found' => $found, 'sources' => array_keys($sourcesUsed)]);

            $saved = $this->save($run, $owner, $offer, $leads, $this->scorer->score($offer, $leads));
            $message = $saved === 0
                ? 'No new leads this time — every match was already in your CRM or had no way to contact them. Try again with more leads or after editing the product research.'
                : "Found {$saved} new ".str('lead')->plural($saved).' and added them to your CRM.';

            $this->finish($run, LeadRun::STATUS_COMPLETED, $run->message ?? $message);
        } catch (Throwable $e) {
            report($e);
            $this->finish($run, LeadRun::STATUS_FAILED, $e instanceof LeadSourceException || $e instanceof GoogleMapsException
                ? 'A lead source had a problem, so this run stopped. Please try again in a few minutes.'
                : 'Something went wrong while finding leads. Please try again.');
        }
    }

    /**
     * Raw candidates from every connected source, best source first.
     *
     * @return Generator<int, array<string, mixed>>
     */
    protected function candidates(?Offer $offer, int $target): Generator
    {
        $research = $offer ? new ResearchPresenter($offer) : null;
        $sources = $this->sources();

        if ($sources['apollo'] && $research) {
            $titles = $research->linkedinKeywords()['people'];

            if ($titles !== []) {
                $apollo = new ApolloClient((string) $this->settings()->apolloKey());

                for ($page = 1; $page <= 3; $page++) {
                    $people = $apollo->searchPeople($titles, array_slice($research->locations(), 0, 3), $research->linkedinKeywords()['companies'], min(100, $target * 2), $page);

                    foreach ($people as $person) {
                        yield ['source' => 'apollo', 'external_id' => $person['id'], 'name' => $person['name'], 'title' => $person['title'], 'company' => $person['company']];
                    }

                    if (count($people) < min(100, $target * 2)) {
                        break;
                    }
                }
            }
        }

        if ($sources['google_maps'] && $research) {
            $keywords = array_slice(array_column($research->mapsKeywords(), 'keyword'), 0, 4);
            $locations = array_slice($research->locations(), 0, 3);

            foreach ($keywords as $keyword) {
                foreach ($locations as $location) {
                    foreach ($this->maps->search($keyword, $location) as $place) {
                        yield [
                            'source' => 'google_maps',
                            'external_id' => $place['place_id'],
                            'name' => $place['name'],
                            'company' => $place['name'],
                            'location' => $place['address'],
                            'type' => $place['type'],
                            'keyword' => $keyword,
                            'source_url' => $place['maps_url'],
                        ];
                    }
                }
            }
        }
    }

    /**
     * Fills in the contact details for one candidate; null when it can't
     * be reached (no usable email or phone).
     *
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>|null
     */
    protected function complete(array $candidate): ?array
    {
        $checker = new EmailChecker($this->hunter());

        if ($candidate['source'] === 'apollo') {
            $person = (new ApolloClient((string) $this->settings()->apolloKey()))->enrich($candidate['external_id']);
            $email = $person['email'] ?? null;

            // Paid providers: business emails only.
            if (! $email || LeadEmail::isFreeMail($email)) {
                return null;
            }

            $status = $checker->check($email);

            if (! EmailChecker::isGood($status)) {
                return null;
            }

            return array_merge($candidate, array_filter($person ?? [], fn ($value) => filled($value)), [
                'email' => strtolower($email),
                'email_status' => $status,
                'email_source' => 'apollo',
                'source_url' => $person['linkedin_url'] ?? null,
            ]);
        }

        $details = $this->maps->details($candidate['external_id']);
        $lead = array_merge($candidate, ['phone' => $details['phone'], 'website' => $details['website']]);

        $email = null;
        $emailSource = null;

        if ($details['website']) {
            // A free-mail address a business publishes on its own site is its public contact.
            $email = $this->websiteEmails->find($details['website'])[0] ?? null;
            $emailSource = $email ? 'website' : null;

            if (! $email && ($hunter = $this->hunter()) && ($domain = LeadEmail::domainOfUrl($details['website']))) {
                $match = collect($hunter->domainSearch($domain, 3))->first(fn (array $row): bool => ! LeadEmail::isFreeMail($row['email']));

                if ($match) {
                    $email = $match['email'];
                    $emailSource = 'hunter';
                    $lead['name'] = $match['name'] ?: $lead['name'];
                    $lead['title'] = $match['title'];
                    $lead['linkedin_url'] = $match['linkedin_url'];
                }
            }
        }

        if ($email) {
            $status = $checker->check($email);

            if ($status === 'invalid') {
                $email = null;
            } else {
                $lead['email'] = $email;
                $lead['email_status'] = $status;
                $lead['email_source'] = $emailSource;
            }
        }

        return filled($lead['email'] ?? null) || filled($lead['phone'] ?? null) ? $lead : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $leads
     * @param  array<int, array{score: int, reason: string}>  $scores
     */
    protected function save(LeadRun $run, User $owner, ?Offer $offer, array $leads, array $scores): int
    {
        $list = $offer ? CrmList::forOffer($offer) : null;
        $free = $this->allowance->freeRemaining($owner);
        $saved = $withEmail = $freeLeads = $spent = 0;

        // Best fits first, so a run that stops early keeps the strongest leads.
        $order = collect($leads)->keys()->sortByDesc(fn (int $index): int => $scores[$index]['score'] ?? 0);

        foreach ($order as $index) {
            $lead = $leads[$index];
            $score = $scores[$index] ?? $this->scorer->ruleScore($offer, $lead);
            $goodEmail = filled($lead['email'] ?? null) && EmailChecker::isGood((string) ($lead['email_status'] ?? ''));
            $isFree = $free > 0;
            $cost = $isFree ? 0 : (int) config('credits.costs.lead_found') + ($goodEmail ? (int) config('credits.costs.lead_email_checked') : 0);

            if (! $isFree && ! $this->credits->hasEnough($owner, $cost)) {
                $run->message = "Found {$saved} new ".str('lead')->plural($saved).' — stopped early because your credits ran out.';
                break;
            }

            if ($owner->crmContactLimitReached()) {
                $run->message = "Found {$saved} new ".str('lead')->plural($saved).' — stopped early because your plan\'s contact limit was reached.';
                break;
            }

            $contact = CrmContact::create([
                'user_id' => $owner->id,
                'name' => $lead['name'] ?: ($lead['company'] ?? 'Lead'),
                'company' => $lead['company'] ?? null,
                'title' => $lead['title'] ?? null,
                'email' => $lead['email'] ?? null,
                'phone' => $lead['phone'] ?? null,
                'website' => $lead['website'] ?? null,
                'location' => $lead['location'] ?? null,
                'source' => $lead['source'],
                'status' => 'new',
                'lead_score' => $score['score'],
                'tags' => $this->tags($offer, $lead, $score['score']),
                'raw_data' => array_filter([
                    'google_place_id' => $lead['source'] === 'google_maps' ? $lead['external_id'] : null,
                    'apollo_id' => $lead['source'] === 'apollo' ? $lead['external_id'] : null,
                    'type' => $lead['type'] ?? null,
                    'keyword' => $lead['keyword'] ?? null,
                    'linkedin_url' => $lead['linkedin_url'] ?? null,
                    'source_url' => $lead['source_url'] ?? null,
                    'email_source' => $lead['email_source'] ?? null,
                    'email_status' => $lead['email_status'] ?? null,
                    'score_reason' => $score['reason'],
                    'lead_run_id' => $run->id,
                    'found_at' => now()->toIso8601String(),
                    'lawful_basis' => 'legitimate_interest_b2b',
                ], fn ($value) => $value !== null && $value !== ''),
            ]);

            if ($isFree) {
                $free--;
                $freeLeads++;
            } else {
                $this->credits->spend($owner, $cost, 'lead_found', $contact);
                $spent += $cost;
            }

            $list?->addContact($contact);
            $saved++;
            $withEmail += filled($contact->email) ? 1 : 0;
        }

        $run->fill(['saved' => $saved, 'with_email' => $withEmail, 'free_leads' => $freeLeads, 'credits_spent' => $spent])->save();

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $lead
     * @return array<int, string>
     */
    protected function tags(?Offer $offer, array $lead, int $score): array
    {
        $location = (string) ($lead['location'] ?? '');
        $country = $location !== '' ? trim((string) collect(explode(',', $location))->last()) : null;

        return array_values(array_unique(array_filter([
            'AffilStack lead',
            $offer?->product_name,
            ['google_maps' => 'Google Maps', 'apollo' => 'Apollo', 'hunter' => 'Hunter'][$lead['source']] ?? null,
            $score >= 80 ? 'High fit' : ($score >= 50 ? 'Medium fit' : 'Low fit'),
            $lead['keyword'] ?? null,
            $country && ! preg_match('/\d/', $country) ? $country : null,
        ])));
    }

    /**
     * @return array{emails: array<string, true>, domains: array<string, true>, ids: array<string, true>}
     */
    protected function knownKeys(User $owner): array
    {
        $known = ['emails' => [], 'domains' => [], 'ids' => []];

        $owner->crmContacts()->select(['id', 'email', 'website', 'raw_data'])->lazyById(500)->each(function (CrmContact $contact) use (&$known): void {
            if ($contact->email) {
                $known['emails'][strtolower($contact->email)] = true;
            }

            if ($domain = LeadEmail::domainOfUrl($contact->website)) {
                $known['domains'][$domain] = true;
            }

            foreach (['google_place_id', 'apollo_id'] as $key) {
                if ($id = $contact->raw_data[$key] ?? null) {
                    $known['ids'][$id] = true;
                }
            }
        });

        return $known;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array{emails: array<string, true>, domains: array<string, true>, ids: array<string, true>}  $known
     */
    protected function isKnown(array $candidate, array $known): bool
    {
        return isset($known['ids'][$candidate['external_id']]);
    }

    /**
     * @param  array<string, mixed>  $lead
     * @param  array{emails: array<string, true>, domains: array<string, true>, ids: array<string, true>}  $known
     */
    protected function remember(array &$lead, array &$known): void
    {
        $known['ids'][$lead['external_id']] = true;

        // Already in the CRM under another source: keep the lead, but not a second copy of its email.
        if (($email = $lead['email'] ?? null) && isset($known['emails'][strtolower($email)])) {
            unset($lead['email'], $lead['email_status'], $lead['email_source']);
        }

        if ($email = $lead['email'] ?? null) {
            $known['emails'][strtolower($email)] = true;
        }

        if ($domain = LeadEmail::domainOfUrl($lead['website'] ?? null)) {
            $known['domains'][$domain] = true;
        }
    }

    protected function contactRoom(User $owner): int
    {
        $limit = (int) ($owner->activeSubscription?->plan?->contact_limit ?? 0);

        return $limit === 0 ? PHP_INT_MAX : max(0, $limit - $owner->crmContacts()->count());
    }

    protected function finish(LeadRun $run, string $status, string $message): void
    {
        $run->fill(['status' => $status, 'message' => $message, 'finished_at' => now()])->save();
        ProgressCounter::forget($run->user);

        if ($run->trigger === 'hunter_agent') {
            $strong = CrmContact::query()->where('user_id', $run->user_id)->where('raw_data->lead_run_id', $run->id)->where('lead_score', '>=', 80)->count();

            if ($run->saved > 0) {
                AgentSettings::log($run->user, 'lead_hunter', 'found_leads', "Found {$run->saved} new ".str('lead')->plural($run->saved).' for '.($run->offer?->product_name ?? 'your product').($strong > 0 ? ", {$strong} of them a strong fit." : '.'), $run, ['saved' => $run->saved, 'strong' => $strong]);
            }

            if ($strong === 0) {
                return; // Nightly runs stay quiet unless there's something worth a look.
            }
        }

        $run->user->notify(new GenerationCompleted(
            module: 'lead_finder',
            title: $run->offer?->product_name ?? 'Your leads',
            success: $status === LeadRun::STATUS_COMPLETED && $run->saved > 0,
            url: $run->offer ? route('offers.show', $run->offer) : route('crm.index'),
            errorMessage: $message,
        ));
    }

    protected function hunter(): ?HunterClient
    {
        return $this->settings()->hunterReady() ? new HunterClient((string) $this->settings()->hunterKey()) : null;
    }

    protected function settings(): LeadSourceSetting
    {
        return $this->settings ??= LeadSourceSetting::current();
    }
}
