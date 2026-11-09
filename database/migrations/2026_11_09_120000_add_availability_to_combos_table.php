<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->string('availability_mode', 16)->default('always');
            $table->json('available_weekdays')->nullable();
            $table->json('available_dates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropColumn(['availability_mode', 'available_weekdays', 'available_dates']);
        });
    }
};
