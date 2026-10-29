<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // The orders migration adds the FK after both tables exist.
            $table->foreignId('order_id');

            // product o recommendation.
            $table->string('item_type', 30);

            // ID del producto o recomendación original.
            // Se conserva como referencia histórica.
            $table->unsignedBigInteger('catalog_item_id')->nullable();

            // Copia de los datos al momento de comprar.
            $table->string('item_name', 200);
            $table->text('item_description')->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('extras_unit_total', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);

            $table->text('notes')->nullable();

            $table->timestampsTz();

            $table->index(['item_type', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};