<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReferralCampaign;
use App\Models\ReferralCoupon;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ReferralDiscountService;
use App\Services\ReferralService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReferralDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $inviter;

    private User $customer;

    private Product $product;

    private ReferralCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Bus::fake();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
        StoreSetting::current()->update(['accepting_orders' => true, 'kitchen_latitude' => 0,
            'kitchen_longitude' => 0, 'free_delivery_km' => 0, 'additional_cost_per_km' => 2]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->inviter = User::factory()->create(['role' => 'customer']);
        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->campaign = ReferralCampaign::create([
            'creation_key' => (string) Str::uuid(), 'name' => 'Invita', 'status' => 'active',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'created_by' => $this->admin->id,
            'guest_percent' => 10, 'inviter_percent' => 5, 'minimum_cents' => 1000,
            'maximum_discount_cents' => 500, 'coupon_days' => 30,
        ]);
        $category = Category::create(['name' => 'Carta', 'slug' => 'carta', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Chaufa',
            'slug' => 'chaufa', 'price' => '20.00', 'is_available' => true]);
        app(ReferralService::class)->apply($this->customer, app(ReferralService::class)->codeFor($this->inviter));
        $this->actingAs($this->customer);
    }

    private function data(array $changes = []): array
    {
        return array_replace([
            'checkout_token' => (string) Str::uuid(), 'delivery_type' => 'delivery',
            'delivery_address' => 'Calle 1', 'delivery_latitude' => 0, 'delivery_longitude' => 0.01,
            'customer_name' => 'Cliente', 'customer_phone' => '999999999',
            'payment_method' => 'yape',
            'items' => [['type' => 'product', 'id' => $this->product->id, 'quantity' => 1]],
        ], $changes);
    }

    private function create(array $changes = []): Order
    {
        return app(OrderService::class)->create($this->data($changes));
    }

    private function verify(Order $order): void
    {
        $this->actingAs($this->admin)->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.payment', $order), ['decision' => 'verified'])
            ->assertSessionHasNoErrors();
    }

    private function earnedCoupon(): ReferralCoupon
    {
        $order = $this->create();
        $this->verify($order);
        $this->actingAs($this->inviter);

        return ReferralCoupon::firstOrFail();
    }

    public function test_quote_and_order_use_server_prices_exclude_delivery_and_retries_reserve_once(): void
    {
        $data = $this->data(['discount_amount' => 999, 'total' => 0, 'guest_percent' => 100]);
        $quote = app(OrderService::class)->quote($data);
        $this->assertSame('2.00', $quote['discount_amount']);
        $this->assertSame('4.00', $quote['delivery_fee']);
        $this->assertSame('22.00', $quote['total']);
        $order = app(OrderService::class)->create($data);
        $this->assertSame($quote['total'], $order->total);
        $this->assertSame($order->id, app(OrderService::class)->create($data)->id);
        $this->assertDatabaseCount('referral_order_discounts', 1);
        $this->assertDatabaseCount('referral_coupons', 0);
        $this->assertSame('0.00', $this->create()->discount_amount);
    }

    public function test_combos_are_excluded_by_default_but_admin_can_include_them(): void
    {
        $combo = Combo::create(['name' => 'Duo', 'slug' => 'duo', 'price' => 30, 'is_available' => true, 'show_in_menu' => true]);
        $combo->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);
        $items = [['type' => 'combo', 'id' => $combo->id, 'quantity' => 1]];
        $this->assertSame('0.00', app(OrderService::class)->quote($this->data(['items' => $items]))['discount_amount']);
        $items[] = ['type' => 'product', 'id' => $this->product->id, 'quantity' => 1];
        $this->assertSame('2.00', app(OrderService::class)->quote($this->data(['items' => $items]))['discount_amount']);
        $this->campaign->update(['include_combos' => true]);
        $this->assertSame('5.00', app(OrderService::class)->quote($this->data(['items' => $items]))['discount_amount']);
    }

    public function test_minimum_cap_and_rounding_are_applied_to_eligible_amount(): void
    {
        $this->campaign->update(['minimum_cents' => 2001]);
        $this->assertSame('0.00', app(OrderService::class)->quote($this->data())['discount_amount']);
        $this->campaign->update(['minimum_cents' => 0, 'guest_percent' => 8, 'maximum_discount_cents' => 100]);
        $this->assertSame('1.00', app(OrderService::class)->quote($this->data())['discount_amount']);
        $this->product->update(['price' => '1.99']);
        $this->assertSame('0.15', app(OrderService::class)->quote($this->data())['discount_amount']);
    }

    public function test_pause_expiry_and_zero_cap_disable_new_discounts(): void
    {
        foreach ([['status' => 'paused'], ['status' => 'active', 'ends_at' => now()],
            ['ends_at' => now()->addDay(), 'maximum_discount_cents' => 0]] as $values) {
            $this->campaign->update($values);
            $this->assertSame('0.00', app(OrderService::class)->quote($this->data())['discount_amount']);
        }
    }

    public function test_verified_payment_earns_one_coupon_with_frozen_terms_even_after_pause(): void
    {
        $order = $this->create();
        $this->campaign->update(['status' => 'paused', 'inviter_percent' => 99, 'include_combos' => true]);
        $this->verify($order);
        $coupon = ReferralCoupon::firstOrFail();
        $this->assertEquals(5, $coupon->percent);
        $this->assertFalse($coupon->include_combos);
        $this->assertEquals($this->inviter->id, $coupon->user_id);
        $this->assertEquals($this->admin->id, $coupon->approved_by);
        $this->post(route('admin.referrals.approve', $order))->assertSessionHasNoErrors();
        $this->post(route('admin.referrals.approve', $order))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referral_coupons', 1);
        $this->assertNotNull(DB::table('referral_invitations')->first()->used_at);
    }

    public function test_rejection_does_not_award_and_cancellation_releases_invitation(): void
    {
        $order = $this->create();
        $this->actingAs($this->admin)->patch(route('admin.orders.payment', $order), ['decision' => 'rejected'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referral_coupons', 0);
        $this->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'cancellation_reason' => 'Cliente canceló'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->customer);
        $this->assertSame('2.00', $this->create()->discount_amount);
        $this->assertDatabaseCount('referral_coupons', 0);
        $this->assertNotNull(DB::table('referral_order_discounts')->where('order_id', $order->id)->value('released_at'));
    }

    public function test_coupon_is_optional_personal_reserved_once_and_released_on_cancellation(): void
    {
        $coupon = $this->earnedCoupon();
        $this->assertSame('0.00', app(OrderService::class)->quote($this->data())['discount_amount']);
        $data = $this->data(['referral_coupon_id' => $coupon->id]);
        $quote = app(OrderService::class)->quote($data);
        $this->assertSame('1.00', $quote['discount_amount']);
        $order = app(OrderService::class)->create($data);
        $this->assertSame($order->id, app(OrderService::class)->create($data)->id);
        $this->assertSame($order->id, (int) $coupon->refresh()->reserved_order_id);
        try {
            $this->create(['referral_coupon_id' => $coupon->id]);
            $this->fail('A second checkout used a reserved coupon');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('referral_coupon_id', $e->errors());
        }
        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order),
            ['status' => 'cancelled', 'cancellation_reason' => 'Cliente canceló'])->assertSessionHasNoErrors();
        $this->assertNull($coupon->refresh()->reserved_order_id);
        $this->actingAs($this->inviter);
        $second = $this->create(['referral_coupon_id' => $coupon->id]);
        $this->verify($second);
        $this->assertNotNull($coupon->refresh()->used_at);
        $this->assertDatabaseCount('referral_coupons', 1);
    }

    public function test_expired_and_foreign_coupons_are_rejected_without_creating_an_order(): void
    {
        $coupon = $this->earnedCoupon();
        foreach (['foreign', 'expired'] as $case) {
            if ($case === 'foreign') {
                $this->actingAs($this->customer);
            } else {
                $this->actingAs($this->inviter);
                $coupon->update(['expires_at' => now()]);
            }
            try {
                $this->create(['referral_coupon_id' => $coupon->id]);
                $this->fail('Invalid coupon accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('referral_coupon_id', $e->errors());
            }
        }
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_invalid_cash_amount_rolls_back_without_reserving_discount(): void
    {
        try {
            $this->create(['payment_method' => 'cash', 'cash_payment_type' => 'change', 'cash_received_amount' => 1]);
            $this->fail('Insufficient cash accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash_received_amount', $e->errors());
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertNull(DB::table('referral_invitations')->first()->reserved_order_id);
    }

    public function test_admin_cash_collection_awards_but_delivery_collection_needs_admin_approval(): void
    {
        $order = $this->create(['payment_method' => 'cash', 'cash_payment_type' => 'exact']);
        $driver = User::factory()->create(['role' => 'delivery']);
        $order->update(['status' => 'on_the_way', 'delivery_user_id' => $driver->id]);
        $this->actingAs($driver)->patch(route('delivery.orders.deliver', $order),
            ['confirm_delivery' => 1, 'confirm_collection' => 1])->assertSessionHasNoErrors();
        $this->assertSame('verified', $order->refresh()->payment_status);
        $this->assertDatabaseCount('referral_coupons', 0);
        $this->actingAs($this->admin)->get(route('admin.referrals.index'))->assertOk()->assertSee($order->code);
        $this->post(route('admin.referrals.approve', $order))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referral_coupons', 1);
    }

    public function test_cash_collected_by_admin_uses_discounted_total_and_awards_coupon(): void
    {
        $order = $this->create(['payment_method' => 'cash', 'cash_payment_type' => 'exact']);
        $this->assertSame('22.00', $order->cash_received_amount);
        $order->update(['status' => 'confirmed']);
        $this->actingAs($this->admin)->patch(route('admin.orders.collect-cash', $order), ['confirm_collection' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referral_coupons', 1);
    }

    public function test_admin_terms_validate_roles_ranges_cap_and_paused_state(): void
    {
        $data = ['guest_percent' => 8, 'inviter_percent' => 5, 'minimum_amount' => '20.50',
            'maximum_discount_amount' => '2.05', 'coupon_days' => 15, 'include_combos' => 1];
        $route = route('admin.referrals.terms', $this->campaign);
        $this->patch($route, $data)->assertForbidden();
        $this->actingAs($this->admin)->patch($route, $data)->assertSessionHasErrors('campaign');
        $this->campaign->update(['status' => 'paused']);
        $this->patch($route, array_replace($data, ['guest_percent' => 101]))->assertSessionHasErrors('guest_percent');
        $this->patch($route, array_replace($data, ['maximum_discount_amount' => 0]))->assertSessionHasErrors('maximum_discount_amount');
        $this->patch($route, array_replace($data, ['include_combos' => ['bad']]))->assertSessionHasErrors('include_combos');
        $this->patch($route, $data)->assertSessionHasNoErrors();
        $this->campaign->refresh();
        $this->assertEquals(2050, $this->campaign->minimum_cents);
        $this->assertEquals(205, $this->campaign->maximum_discount_cents);
        $this->assertTrue($this->campaign->include_combos);
    }

    public function test_guest_and_staff_cannot_use_coupons_or_obtain_invitation_discounts(): void
    {
        $coupon = $this->earnedCoupon();
        foreach ([null, $this->admin, User::factory()->create(['role' => 'delivery'])] as $user) {
            auth()->logout();
            if ($user) {
                $this->actingAs($user);
            }
            $this->assertSame('0.00', app(OrderService::class)->quote($this->data())['discount_amount']);
            try {
                app(OrderService::class)->quote($this->data(['referral_coupon_id' => $coupon->id]));
                $this->fail('Staff/guest coupon accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('referral_coupon_id', $e->errors());
            }
        }
    }

    public function test_coupons_remain_available_after_campaign_closes_and_are_private_in_account(): void
    {
        $coupon = $this->earnedCoupon();
        $this->campaign->update(['status' => 'ended']);
        $this->get(route('customer.account'))->assertOk()->assertSee('Mis cupones');
        $this->assertSame('1.00', app(OrderService::class)->quote($this->data(['referral_coupon_id' => $coupon->id]))['discount_amount']);
        $this->actingAs($this->customer)->get(route('customer.account'))->assertOk()->assertDontSee('Mis cupones');
    }

    public function test_database_rejects_duplicate_reward_for_same_invitation(): void
    {
        $coupon = $this->earnedCoupon();
        $this->expectException(QueryException::class);
        $coupon->replicate()->save();
    }

    public function test_html_checkout_preserves_coupon_selection_and_confirms_discounted_order(): void
    {
        $coupon = $this->earnedCoupon();
        $data = $this->data(['referral_coupon_id' => $coupon->id]);
        $this->withSession(['checkout_token' => $data['checkout_token'], 'cart' => [
            'plate' => ['product_id' => $this->product->id, 'quantity' => 1, 'extra_ids' => []],
        ]])->get(route('cart.checkout'))->assertOk()->assertSee('name="referral_coupon_id"', false);
        $this->from(route('cart.checkout'))->post(route('cart.checkout.quote'), $data)
            ->assertSessionHasNoErrors()->assertSessionHas('checkout_quote.discount_amount', '1.00');
        $this->get(route('cart.checkout'))->assertOk()->assertSee('Descuento')->assertSee('1.00');
        $this->post(route('cart.checkout.confirm'), $data)->assertRedirect(route('cart.checkout.success'))->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('1.00', $order->discount_amount);
        $this->assertSame('23.00', $order->total);
        $this->get(route('cart.checkout.success'))->assertOk()->assertSee('Descuento');
    }

    public function test_json_quote_and_confirmation_accept_coupon_and_reject_array_or_injection(): void
    {
        $coupon = $this->earnedCoupon();
        $data = $this->data(['referral_coupon_id' => $coupon->id]);
        foreach ([['bad'], "' OR 1=1 --", -1] as $invalid) {
            $this->postJson(route('checkout.quote'), array_replace($data, ['referral_coupon_id' => $invalid]))
                ->assertUnprocessable()->assertJsonValidationErrors('referral_coupon_id');
        }
        $quote = $this->postJson(route('checkout.quote'), $data)->assertOk()->assertJsonPath('quote.discount_amount', '1.00');
        $data['checkout_token'] = $quote->json('checkout_token');
        $this->postJson(route('checkout.store'), $data)->assertCreated()->assertJsonPath('order.discount_amount', '1.00');
        $this->postJson(route('checkout.store'), $data)->assertCreated()->assertJsonPath('order.discount_amount', '1.00');
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_coupon_and_invitation_never_stack_and_expired_cancelled_coupon_is_not_revived(): void
    {
        $coupon = $this->earnedCoupon();
        // The inviter has earned a coupon but has not placed their own first order.
        $otherInviter = User::factory()->create(['role' => 'customer']);
        app(ReferralService::class)->apply($this->inviter, app(ReferralService::class)->codeFor($otherInviter));
        $order = $this->create(['referral_coupon_id' => $coupon->id]);
        $this->assertSame('1.00', $order->discount_amount); // not 3.00
        $coupon->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order),
            ['status' => 'cancelled', 'cancellation_reason' => 'Cancelado'])->assertSessionHasNoErrors();
        $this->actingAs($this->inviter);
        $this->assertTrue(app(ReferralDiscountService::class)->availableCoupons($this->inviter)->isEmpty());
        $this->expectException(ValidationException::class);
        $this->create(['referral_coupon_id' => $coupon->id]);
    }

    public function test_payment_transaction_rolls_back_coupon_on_failure_and_retry_awards_once(): void
    {
        $order = $this->create();
        try {
            DB::transaction(function () use ($order) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $locked->update(['payment_status' => 'verified', 'payment_verified_by' => $this->admin->id]);
                $this->assertDatabaseCount('referral_coupons', 1);
                throw new \RuntimeException('Simulated failure before commit');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated failure before commit', $e->getMessage());
        }
        $this->assertSame('pending', $order->refresh()->payment_status);
        $this->assertDatabaseCount('referral_coupons', 0);
        $this->verify($order);
        $this->assertDatabaseCount('referral_coupons', 1);
    }

    public function test_customer_cannot_approve_rewards_and_admin_cannot_approve_unpaid_order(): void
    {
        $order = $this->create();
        $this->post(route('admin.referrals.approve', $order))->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.referrals.approve', $order))->assertSessionHasErrors('order');
        $this->assertDatabaseCount('referral_coupons', 0);
    }

    public function test_orders_without_reserved_referral_never_award_retroactive_coupons(): void
    {
        $this->campaign->update(['guest_percent' => 0]);
        $order = $this->create();
        $this->campaign->update(['guest_percent' => 10]);
        $this->verify($order);
        $this->post(route('admin.referrals.approve', $order))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referral_coupons', 0);
    }

    public function test_coupon_uses_its_own_combo_policy_and_remains_spent_after_payment(): void
    {
        $coupon = $this->earnedCoupon();
        $combo = Combo::create(['name' => 'Duo', 'slug' => 'duo', 'price' => 30, 'is_available' => true, 'show_in_menu' => true]);
        $combo->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);
        $data = ['referral_coupon_id' => $coupon->id, 'items' => [['type' => 'combo', 'id' => $combo->id, 'quantity' => 1]]];
        $this->campaign->update(['include_combos' => true]);
        try {
            $this->create($data);
            $this->fail('Campaign change retroactively enabled combos on an issued coupon');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('referral_coupon_id', $e->errors());
        }
        $order = $this->create(['referral_coupon_id' => $coupon->id]);
        $this->verify($order);
        $this->actingAs($this->inviter);
        $this->expectException(ValidationException::class);
        $this->create(['referral_coupon_id' => $coupon->id]);
    }
}
