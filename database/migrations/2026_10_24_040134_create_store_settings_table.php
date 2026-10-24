<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();

            // Pagos manuales
            $table->string('yape_number', 20)->nullable();
            $table->string('plin_number', 20)->nullable();
            $table->string('payment_holder', 150)->nullable();
            $table->foreignId('payment_qr_media_asset_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();

            // Delivery
            $table->string('orders_whatsapp', 20)->nullable();
            $table->decimal('free_delivery_km', 8, 2)->default(3);
            $table->decimal('additional_cost_per_km', 8, 2)->default(2);
            $table->decimal('kitchen_latitude', 10, 7)->nullable();
            $table->decimal('kitchen_longitude', 10, 7)->nullable();

            // Atención
            $table->boolean('accepting_orders')->default(true);
            $table->text('closed_message')->nullable();

            // Comunidad
            $table->text('whatsapp_group_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};