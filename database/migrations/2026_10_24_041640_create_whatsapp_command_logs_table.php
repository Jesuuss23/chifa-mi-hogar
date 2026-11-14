<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_command_logs', function (Blueprint $table) {
            $table->id();
            $table->string('meta_message_id', 255)->unique();
            $table->string('sender_phone', 20);
            $table->string('command', 50);
            $table->string('result', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_command_logs');
    }
};