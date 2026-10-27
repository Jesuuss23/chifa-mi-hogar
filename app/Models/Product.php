<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\HasMediaAsset;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Product extends Model
{
    use SoftDeletes;
    use HasMediaAsset;
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'image_path',
        'is_available',
        'is_featured',
        'sort_order',
        'media_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(Extra::class);
    }
    public function recommendations(): HasMany
    {
        return $this->hasMany(
            ProductRecommendation::class,
            'product_id'
        );
    }
}