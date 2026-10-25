<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'item_type',
        'catalog_item_id',
        'item_name',
        'item_description',
        'quantity',
        'unit_price',
        'extras_unit_total',
        'line_total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'extras_unit_total' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(OrderItemExtra::class);
    }
    public function comboComponents(): HasMany
    {
        return $this->hasMany(OrderItemComboComponent::class);
    }
}