<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\HasMediaAsset;

class Combo extends Model
{
    use SoftDeletes, HasMediaAsset;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'image_path',
        'is_available',
        'show_in_menu',
        'sort_order',
        'media_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'show_in_menu' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    public function recommendedForProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'combo_recommendations'
        )->withPivot('sort_order')->withTimestamps();
    }

    public function getReferencePriceAttribute(): float
    {
        $this->loadMissing('items.product', 'items.extra');

        return round(
            $this->items->sum(function ($item) {
                $price = $item->product
                    ? (float) $item->product->price
                    : (float) ($item->extra?->price ?? 0);

                return $price * $item->quantity;
            }),
            2
        );
    }
}