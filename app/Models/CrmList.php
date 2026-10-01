<?php

namespace App\Models;

use App\Events\CrmContactAddedToList;
use Database\Factories\CrmListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of CRM contacts — usually "the prospects for one product",
 * created automatically the first time a contact is saved for that product
 * (see forOffer()). Campaigns are sent to a list, never to the whole CRM.
 */
#[Fillable(['user_id', 'offer_id', 'name', 'color', 'description'])]
class CrmList extends Model
{
    /** @use HasFactory<CrmListFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(CrmContact::class, 'crm_contact_list')->withPivot('added_at');
    }

    /**
     * The default list for a researched product, created on first use.
     * Owned by the offer's owner so a team seat adding contacts files them
     * on the account's shared list.
     */
    public static function forOffer(Offer $offer): self
    {
        return static::firstOrCreate(
            ['user_id' => $offer->user_id, 'offer_id' => $offer->id],
            ['name' => $offer->product_name.' prospects'],
        );
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(CrmCampaign::class);
    }

    /**
     * Adds a contact (idempotent) and returns whether it was newly added —
     * campaign autopilot uses that to send only to genuinely new members:
     * a genuinely new member fires CrmContactAddedToList, which queues the
     * list's autopilot campaigns for them (see QueueAutopilotCampaignSends).
     * Every entry point (CRM form, CSV import, Local Leads, the Chrome
     * extension API) adds through here so autopilot never misses one.
     */
    public function addContact(CrmContact $contact): bool
    {
        $changes = $this->contacts()->syncWithoutDetaching([$contact->id => ['added_at' => now()]]);
        $added = $changes['attached'] !== [];

        if ($added) {
            CrmContactAddedToList::dispatch($this, $contact);
        }

        return $added;
    }

    /**
     * Tailwind classes for this list's colour chip. Classes are spelled out
     * in full so Tailwind's scanner keeps them in the build.
     */
    public function chipClasses(): string
    {
        return match ($this->color) {
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            'teal' => 'bg-teal-50 text-teal-700 ring-teal-600/20',
            'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            'gold' => 'bg-gold-300/25 text-gold-600 ring-gold-400/30',
            default => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        };
    }

    public function dotClass(): string
    {
        return match ($this->color) {
            'emerald' => 'bg-emerald-500',
            'violet' => 'bg-violet-500',
            'amber' => 'bg-amber-500',
            'rose' => 'bg-rose-500',
            'teal' => 'bg-teal-500',
            'sky' => 'bg-sky-500',
            'gold' => 'bg-gold-400',
            default => 'bg-brand-500',
        };
    }

    public function isProductList(): bool
    {
        return $this->offer_id !== null;
    }
}
