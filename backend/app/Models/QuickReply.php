<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A saved reply for the WhatsApp inbox, with {slots} filled from the chat. */
class QuickReply extends Model
{
    protected $fillable = ['title', 'shortcut', 'body', 'sort_order', 'created_by_admin_id'];

    protected $casts = ['sort_order' => 'integer'];
}
