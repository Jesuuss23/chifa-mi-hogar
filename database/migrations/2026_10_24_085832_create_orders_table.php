<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();

            // Cliente sin necesidad de crear una cuenta.
            $table->string('customer_name', 150);
            $table->string('customer_phone', 20);
            $table->string('customer_email', 150)->nullable();

            // Entrega.
            $table->string('delivery_type', 20)->default('delivery');
            $table->text('delivery_address')->nullable();
            $table->text('delivery_reference')->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->decimal('delivery_distance_km', 9, 3)->nullable();

            // Montos en soles (PEN).
            $table->string('currency', 3)->default('PEN');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Pago manual.
            $table->string('payment_method', 20);
            $table->string('payment_status', 20)->default('pending');
            $table->foreignId('payment_receipt_media_asset_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();

            $table->timestampTz('payment_verified_at')->nullable();
            $table->foreignId('payment_verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Proceso del pedido.
            $table->string('status', 30)->default('pending');
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('preparing_at')->nullable();
            $table->timestampTz('on_the_way_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index('customer_phone');
        });

        // order_items sorts before orders at the same historical timestamp.
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Remove the dependency before dropping its parent (also on legacy installs).
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['order_id']);
            });
        }

        Schema::dropIfExists('orders');
    }
};