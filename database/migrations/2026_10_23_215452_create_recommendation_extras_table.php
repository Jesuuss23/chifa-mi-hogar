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
    Schema::create('recommendation_extras', function (Blueprint $table) {
        $table->id();

        $table->foreignId('product_recommendation_id')
            ->constrained('product_recommendations')
            ->cascadeOnDelete();

        $table->foreignId('extra_id')
            ->constrained('extras')
            ->restrictOnDelete();

        $table->unsignedSmallInteger('quantity')->default(1);

        $table->unique(
            ['product_recommendation_id', 'extra_id'],
            'recommendation_extra_unique'
        );
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_extras');
    }
};
