<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('business_owner', 150)->default('Rossi Rivadeneyra Jara');
            $table->string('privacy_email')->default('chifamihogar@gmail.com');
            $table->string('website_url', 500)->default('https://chifamihogar.com');
            $table->string('business_ruc', 11)->nullable();
            $table->foreignId('logo_media_asset_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logo_media_asset_id');
            $table->dropColumn(['business_owner', 'privacy_email', 'website_url', 'business_ruc']);
        });
    }
};
