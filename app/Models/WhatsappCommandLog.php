<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappCommandLog extends Model
{
    protected $fillable = [
        'meta_message_id',
        'sender_phone',
        'command',
        'result',
    ];
}