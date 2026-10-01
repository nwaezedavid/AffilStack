<?php

namespace App\Events;

use App\Models\CrmContact;
use App\Models\CrmList;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A contact just joined a CRM list for the first time (CrmList::addContact
 * returned true). Campaign autopilot listens for this.
 */
class CrmContactAddedToList
{
    use Dispatchable, SerializesModels;

    public function __construct(public CrmList $list, public CrmContact $contact) {}
}
