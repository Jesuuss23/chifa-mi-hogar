<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('creation_key')->unique();
            $table->string('name', 100);
            $table->string('status', 12)->default('draft');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code', 24)->unique();
            $table->timestamps();
        });
        Schema::create('referral_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('referral_campaigns')->restrictOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->unique()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['campaign_id', 'inviter_id']);
        });
        Schema::create('referral_campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('referral_campaigns')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 20);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_campaign_events');
        Schema::dropIfExists('referral_invitations');
        Schema::dropIfExists('referral_codes');
        Schema::dropIfExists('referral_campaigns');
    }
};
