<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemComboComponent extends Model
{
    protected $fillable = [
        'order_item_id',
        'component_type',
        'catalog_item_id',
        'component_name',
        'included_quantity',
        'selected_quantity',
        'additional_quantity',
        'unit_price',
        'additional_total',
    ];

    protected function casts(): array
    {
        return [
            'included_quantity' => 'integer',
            'selected_quantity' => 'integer',
            'additional_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'additional_total' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}