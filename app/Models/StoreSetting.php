<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSetting extends Model
{
    protected $fillable = [
        'yape_number',
        'plin_number',
        'payment_holder',
        'payment_qr_media_asset_id',
        'orders_whatsapp',
        'free_delivery_km',
        'additional_cost_per_km',
        'kitchen_latitude',
        'kitchen_longitude',
        'accepting_orders',
        'closed_message',
        'whatsapp_group_url',
        'google_reviews_url',
    ];

    protected function casts(): array
    {
        return [
            'free_delivery_km' => 'decimal:2',
            'additional_cost_per_km' => 'decimal:2',
            'kitchen_latitude' => 'decimal:7',
            'kitchen_longitude' => 'decimal:7',
            'accepting_orders' => 'boolean',
        ];
    }

    public function paymentQr(): BelongsTo
    {
        return $this->belongsTo(
            MediaAsset::class,
            'payment_qr_media_asset_id'
        );
    }

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'free_delivery_km' => 3,
                'additional_cost_per_km' => 2,
                'accepting_orders' => true,
                'closed_message' =>
                    'Por el momento no estamos recibiendo pedidos.',
            ]
        );
    }
}