<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->string('customer_name', 150);
            $table->unsignedTinyInteger('rating');
            $table->text('comment');

            $table->string('source', 30)->default('website');
            $table->string('status', 20)->default('pending');

            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('moderated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};