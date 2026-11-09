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
        'availability_mode',
        'available_weekdays',
        'available_dates',
    ];

    protected function casts(): array
    {
        return [
            'available_weekdays' => 'array',
            'available_dates' => 'array',
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'show_in_menu' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeAvailableNow(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $now = \Carbon\CarbonImmutable::now('America/Lima');

        return $query->where('is_available', true)->where(function ($query) use ($now) {
            $query->where('availability_mode', 'always')
                ->orWhere(function ($query) use ($now) {
                    $query->where('availability_mode', 'weekdays')
                        ->whereJsonContains('available_weekdays', $now->isoWeekday());
                })
                ->orWhere(function ($query) use ($now) {
                    $query->where('availability_mode', 'dates')
                        ->whereJsonContains('available_dates', $now->toDateString());
                });
        });
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