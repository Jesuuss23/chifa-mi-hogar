<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_whatsapp_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('whatsapp_admin_number_id')
                ->constrained('whatsapp_admin_numbers')
                ->cascadeOnDelete();

            $table->string('status', 30)->default('pending');

            $table->timestamp('sent_at')->nullable();

            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(
                ['order_id', 'whatsapp_admin_number_id'],
                'order_whatsapp_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_whatsapp_notifications');
    }
};