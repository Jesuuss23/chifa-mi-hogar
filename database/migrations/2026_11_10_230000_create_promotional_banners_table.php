<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotional_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('combo_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('mobile_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotional_banners');
    }
};
