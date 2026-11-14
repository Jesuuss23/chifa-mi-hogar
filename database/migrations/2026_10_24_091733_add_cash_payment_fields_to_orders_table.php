<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cash_payment_type', 20)->nullable();
            $table->decimal('cash_received_amount', 12, 2)->nullable();
            $table->decimal('cash_change_amount', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'cash_payment_type',
                'cash_received_amount',
                'cash_change_amount',
            ]);
        });
    }
};