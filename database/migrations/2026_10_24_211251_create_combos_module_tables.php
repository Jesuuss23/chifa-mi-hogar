<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Precio de venta elegido por el administrador.
            $table->decimal('price', 10, 2);

            $table->string('image_path')->nullable();

            $table->boolean('is_available')->default(true);
            $table->boolean('show_in_menu')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('combo_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('combo_id')
                ->constrained('combos')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('extra_id')
                ->nullable()
                ->constrained('extras')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            $table->unique(['combo_id', 'product_id']);
            $table->unique(['combo_id', 'extra_id']);
        });

        Schema::create('combo_recommendations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('combo_id')
                ->constrained('combos')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['combo_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_recommendations');
        Schema::dropIfExists('combo_items');
        Schema::dropIfExists('combos');
    }
};