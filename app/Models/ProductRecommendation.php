<?php

namespace App\Models;

use App\Models\Concerns\HasMediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProductRecommendation extends Model
{
    use SoftDeletes, HasMediaAsset;

    protected $table = 'product_recommendations';

    protected $fillable = [
        'product_id',
        'media_asset_id',
        'name',
        'slug',
        'description',
        'image_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(
            Extra::class,
            'recommendation_extras',
            'product_recommendation_id',
            'extra_id'
        )->withPivot('quantity');
    }
}