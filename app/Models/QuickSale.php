<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickSale extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sold_on' => 'date', 'items' => 'array', 'total_cents' => 'integer', 'catalog_total_cents' => 'integer'];
    }

    public function setSoldOnAttribute(string $value): void
    {
        // Keep the business day as a DATE, including on SQLite (no appended midnight).
        $this->attributes['sold_on'] = \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
