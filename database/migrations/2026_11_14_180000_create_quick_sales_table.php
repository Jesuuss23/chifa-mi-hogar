<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_key')->unique();
            $table->string('payload_hash', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('sold_on');
            $table->string('customer_name', 120)->nullable();
            $table->string('payment_method', 10);
            $table->json('items');
            $table->unsignedBigInteger('catalog_total_cents');
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();
            $table->index(['sold_on', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_sales');
    }
};
