<?php

namespace App\Models;

use App\Models\Concerns\HasMediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExtraCategory extends Model
{
    use SoftDeletes, HasMediaAsset;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'media_asset_id',
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

    public function extras(): HasMany
    {
        return $this->hasMany(Extra::class);
    }
}