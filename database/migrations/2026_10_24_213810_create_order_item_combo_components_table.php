<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_combo_components', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->string('component_type', 20);
            $table->unsignedBigInteger('catalog_item_id');

            $table->string('component_name', 150);

            $table->unsignedInteger('included_quantity');
            $table->unsignedInteger('selected_quantity');
            $table->unsignedInteger('additional_quantity');

            $table->decimal('unit_price', 10, 2);
            $table->decimal('additional_total', 10, 2);

            $table->timestamps();

            $table->index(['order_item_id', 'component_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_combo_components');
    }
};