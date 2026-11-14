<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'categories',
            'extra_categories',
            'extras',
            'products',
            'product_recommendations',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('media_asset_id')
                    ->nullable()
                    ->constrained('media_assets')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'product_recommendations',
            'products',
            'extras',
            'extra_categories',
            'categories',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('media_asset_id');
            });
        }
    }
};