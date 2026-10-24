<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $fillable = [
        'original_path',
        'thumbnail_path',
        'card_path',
        'detail_path',
        'original_name',
        'mime_type',
        'original_size',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'original_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function thumbnailUrl(): string
    {
        return asset('storage/' . $this->thumbnail_path);
    }

    public function cardUrl(): string
    {
        return asset('storage/' . $this->card_path);
    }

    public function detailUrl(): string
    {
        return asset('storage/' . $this->detail_path);
    }
}