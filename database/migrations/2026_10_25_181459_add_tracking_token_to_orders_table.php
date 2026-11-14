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
        $table->decimal('driver_latitude', 10, 7)->nullable();
        $table->decimal('driver_longitude', 10, 7)->nullable();
        $table->timestamp('driver_location_updated_at')->nullable();
        $table->timestamp('driver_tracking_started_at')->nullable();
        $table->timestamp('driver_tracking_stopped_at')->nullable();
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropColumn([
            'driver_latitude',
            'driver_longitude',
            'driver_location_updated_at',
            'driver_tracking_started_at',
            'driver_tracking_stopped_at',
        ]);
    });
}
};
