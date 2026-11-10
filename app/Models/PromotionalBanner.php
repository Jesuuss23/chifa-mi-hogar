<?php

namespace App\Models;

use App\Models\Concerns\HasMediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionalBanner extends Model
{
    use HasMediaAsset;

    protected $fillable = ['title', 'product_id', 'combo_id', 'media_asset_id',
        'mobile_media_asset_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function mobileMediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'mobile_media_asset_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(function (Builder $query) {
            $query->where(function (Builder $query) {
                $query->whereNull('combo_id')->whereHas('product', fn (Builder $product) => $product
                    ->where('is_available', true)->whereHas('category', fn (Builder $category) => $category->where('is_active', true)));
            })->orWhere(function (Builder $query) {
                $query->whereNull('product_id')->whereHas('combo', fn (Builder $combo) => $combo->availableNow()->where('show_in_menu', true));
            });
        });
    }

    public function destinationUrl(): string
    {
        return $this->product_id
            ? route('storefront.show', $this->product->slug)
            : route('combos.show', $this->combo->slug);
    }
}
