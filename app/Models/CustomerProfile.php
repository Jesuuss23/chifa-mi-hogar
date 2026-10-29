<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    protected $fillable = [
        'phone', 'delivery_address', 'delivery_reference',
        'delivery_latitude', 'delivery_longitude',
    ];

    protected function casts(): array
    {
        return ['delivery_latitude' => 'decimal:7', 'delivery_longitude' => 'decimal:7'];
    }
}
