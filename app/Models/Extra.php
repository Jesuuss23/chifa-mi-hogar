<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasMediaAsset;
class Extra extends Model
{
    use SoftDeletes, HasMediaAsset;
    protected $fillable = [
        'extra_category_id',
        'media_asset_id',
        'name',
        'description',
        'price',
        'sort_order',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }


    public function extraCategory(): BelongsTo
    {
        return $this->belongsTo(ExtraCategory::class);
    }
}