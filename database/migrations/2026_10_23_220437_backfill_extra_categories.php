<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    $now = now();

    $generalId = DB::table('extra_categories')
        ->where('slug', 'general')
        ->value('id');

    if (! $generalId) {
        $generalId = DB::table('extra_categories')
            ->insertGetId([
                'name' => 'General',
                'slug' => 'general',
                'description' => 'Adicionales sin categoría específica.',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    DB::table('extras')
        ->whereNull('extra_category_id')
        ->update(['extra_category_id' => $generalId]);
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
