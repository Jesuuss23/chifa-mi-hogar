<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_access_token_id')->unique()->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->text('fcm_token');
            $table->string('token_hash', 64)->unique();
            $table->timestamps();
        });
        Schema::create('admin_push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('admin_push_devices')->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('claimed_at')->nullable();
            $table->string('result', 40)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'device_id']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_push_messages');
        Schema::dropIfExists('admin_push_devices');
    }
};
