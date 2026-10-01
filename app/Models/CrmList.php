<?php

namespace App\Models;

use Database\Factories\CrmListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * Adds a contact (idempotent) and returns whether it was newly added —
     * campaign autopilot uses that to send only to genuinely new members.
     */
    public function addContact(CrmContact $contact): bool
    {
        $changes = $this->contacts()->syncWithoutDetaching([$contact->id => ['added_at' => now()]]);

        return $changes['attached'] !== [];
    }
}
