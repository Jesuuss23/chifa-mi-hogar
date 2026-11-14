<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_campaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('guest_percent')->default(0);
            $table->unsignedSmallInteger('inviter_percent')->default(0);
            $table->unsignedInteger('minimum_cents')->default(0);
            $table->unsignedInteger('maximum_discount_cents')->default(0);
            $table->unsignedSmallInteger('coupon_days')->default(30);
            $table->boolean('include_combos')->default(false);
        });
        Schema::table('referral_invitations', function (Blueprint $table) {
            $table->foreignId('reserved_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->timestamp('used_at')->nullable();
        });
        Schema::create('referral_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->unique()->constrained('referral_invitations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('percent');
            $table->unsignedInteger('minimum_cents');
            $table->unsignedInteger('maximum_discount_cents');
            $table->boolean('include_combos')->default(false);
            $table->timestamp('expires_at');
            $table->foreignId('reserved_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });
        Schema::create('referral_order_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained('referral_invitations')->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('referral_coupons')->restrictOnDelete();
            $table->json('terms');
            $table->unsignedInteger('eligible_cents');
            $table->unsignedInteger('discount_cents');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_order_discounts');
        Schema::dropIfExists('referral_coupons');
        Schema::table('referral_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reserved_order_id');
            $table->dropColumn('used_at');
        });
        Schema::table('referral_campaigns', function (Blueprint $table) {
            $table->dropColumn(['guest_percent', 'inviter_percent', 'minimum_cents', 'maximum_discount_cents', 'coupon_days', 'include_combos']);
        });
    }
};
