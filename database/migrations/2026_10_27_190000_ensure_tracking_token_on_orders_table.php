<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'tracking_token')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->uuid('tracking_token')->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        // Migracion correctiva: conservar tokens y enlaces ya compartidos.
        // La columna puede haber existido antes de ejecutar esta migracion.
    }
};
