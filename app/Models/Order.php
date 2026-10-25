<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'checkout_token',
        'tracking_token',
        'user_id',
        'code',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_type',
        'delivery_address',
        'delivery_reference',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_distance_km',
        'currency',
        'subtotal',
        'delivery_fee',
        'discount_amount',
        'total',
        'payment_method',
        'payment_status',
        'payment_receipt_media_asset_id',
        'payment_verified_at',
        'payment_verified_by',
        'status',
        'customer_notes',
        'internal_notes',
        'cancellation_reason',
        'confirmed_at',
        'preparing_at',
        'on_the_way_at',
        'delivered_at',
        'cancelled_at',
        'cash_payment_type',
        'cash_received_amount',
        'cash_change_amount',
        'delivery_user_id',
        'driver_latitude',
        'driver_longitude',
        'driver_location_updated_at',
        'driver_tracking_started_at',
        'driver_tracking_stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_latitude' => 'decimal:7',
            'delivery_longitude' => 'decimal:7',
            'delivery_distance_km' => 'decimal:3',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_verified_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'preparing_at' => 'datetime',
            'on_the_way_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cash_received_amount' => 'decimal:2',
            'cash_change_amount' => 'decimal:2',
            'driver_latitude' => 'decimal:7',
            'driver_longitude' => 'decimal:7',
            'driver_location_updated_at' => 'datetime',
            'driver_tracking_started_at' => 'datetime',
            'driver_tracking_stopped_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(
            MediaAsset::class,
            'payment_receipt_media_asset_id'
        );
    }

    public function paymentVerifier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'payment_verified_by'
        );
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rating(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderRating::class);
    }
    public function deliveryUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'delivery_user_id'
        );
    }
}