<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_whatsapp_notifications', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->uuid('attempt_token')->nullable();
            $table->index(['status', 'next_attempt_at'], 'order_whatsapp_retry_index');
        });

        // El código anterior no distinguía rechazos de timeouts.
        DB::table('order_whatsapp_notifications')->where('status', 'failed')->update([
            'status' => 'uncertain',
            'last_error' => 'Fallo anterior sin resultado verificable. Revisar antes de reenviar.',
        ]);
    }

    public function down(): void
    {
        Schema::table('order_whatsapp_notifications', function (Blueprint $table) {
            $table->dropIndex('order_whatsapp_retry_index');
            $table->dropColumn(['attempts', 'next_attempt_at', 'attempt_token']);
        });
        // No convertir uncertain a failed: el código anterior podría reenviarlos.
    }
};
