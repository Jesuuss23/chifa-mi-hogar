<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappAdminNumber extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'is_active',
        'can_control_store',
        'receive_orders',
        'last_inbound_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_control_store' => 'boolean',
            'receive_orders' => 'boolean',
            'last_inbound_at' => 'datetime',
        ];
    }

}