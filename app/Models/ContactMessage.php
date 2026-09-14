<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A submission from the public Contact Us page. Admin-visible under
 * Support > Contact Messages; the admin notification is sent from
 * ContactController via the ContactMessageReceived notification.
 */
#[Fillable(['name', 'email', 'subject', 'message', 'ip_address', 'status'])]
class ContactMessage extends Model
{
    //
}
