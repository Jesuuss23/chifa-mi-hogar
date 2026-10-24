<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasMediaAsset;
class Category extends Model
{
    use SoftDeletes;
    use HasMediaAsset;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'media_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            return $this->hasMany(Product::class);
        }
}