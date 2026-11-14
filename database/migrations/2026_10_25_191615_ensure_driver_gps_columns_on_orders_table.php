<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up(): void
        {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'driver_latitude')) {
                    $table->decimal('driver_latitude', 10, 7)->nullable();
                }

                if (!Schema::hasColumn('orders', 'driver_longitude')) {
                    $table->decimal('driver_longitude', 10, 7)->nullable();
                }

                if (!Schema::hasColumn('orders', 'driver_location_updated_at')) {
                    $table->timestamp('driver_location_updated_at')->nullable();
                }

                if (!Schema::hasColumn('orders', 'driver_tracking_started_at')) {
                    $table->timestamp('driver_tracking_started_at')->nullable();
                }

                if (!Schema::hasColumn('orders', 'driver_tracking_stopped_at')) {
                    $table->timestamp('driver_tracking_stopped_at')->nullable();
                }
            });
        }

        public function down(): void
        {
            // No eliminamos columnas en esta migración correctiva
            // para evitar borrar datos GPS preexistentes.
        }
};
