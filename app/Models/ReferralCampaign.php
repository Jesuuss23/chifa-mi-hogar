<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralCampaign extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public static function accepting(): ?self
    {
        return static::where('status', 'active')->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())->first();
    }
}
