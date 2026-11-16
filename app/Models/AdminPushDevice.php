<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;

class AdminPushDevice extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['fcm_token', 'token_hash'];

    protected function casts(): array
    {
        return ['fcm_token' => 'encrypted'];
    }

    public function accessToken()
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    public function authorized(): bool
    {
        $token = $this->accessToken;
        $expiration = config('sanctum.expiration');
        return $token && $token->tokenable instanceof User && $token->tokenable->isAdmin()
            && str_starts_with($token->name, 'admin-mobile:')
            && $token->can('admin-mobile:read')
            && $token->expires_at && $token->expires_at->isFuture()
            && (! $expiration || $token->created_at->gt(now()->subMinutes((int) $expiration)));
    }
}
