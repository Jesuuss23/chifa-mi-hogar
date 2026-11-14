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
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_available', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extras');
    }
};
