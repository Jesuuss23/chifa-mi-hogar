<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('legal_address', 500)->nullable();
            $table->text('privacy_hosting')->nullable();
            $table->text('privacy_retention')->nullable();
            $table->text('privacy_database')->nullable();
            $table->boolean('legal_pages_published')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'legal_address', 'privacy_hosting', 'privacy_retention',
                'privacy_database', 'legal_pages_published',
            ]);
        });
    }
};
