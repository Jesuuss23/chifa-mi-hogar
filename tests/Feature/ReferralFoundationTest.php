<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ReferralCampaign;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    private function campaign(): ReferralCampaign
    {
        return ReferralCampaign::create([
            'creation_key' => (string) Str::uuid(), 'name' => 'Invita a probar', 'status' => 'active',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(),
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ]);
    }

    private function code(User $user): string
    {
        return app(ReferralService::class)->codeFor($user);
    }

    private function apply(string $code)
    {
        return $this->post(route('customer.referrals.store'), ['referral_code' => $code]);
    }

    public function test_repeated_code_guessing_is_throttled(): void
    {
        $this->campaign();
        $this->actingAs($this->customer());
        for ($i = 0; $i < 5; $i++) {
            $this->apply(str_repeat('A', 24))->assertSessionHasErrors('referral_code');
        }
        $this->apply(str_repeat('A', 24))->assertStatus(429);
        $this->assertDatabaseCount('referral_invitations', 0);
    }

    public function test_post_without_csrf_is_rejected_when_test_bypass_is_disabled(): void
    {
        $this->campaign();
        $code = $this->code($this->customer());
        $this->actingAs($this->customer());
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->apply($code)->assertStatus(419);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
        $this->assertDatabaseCount('referral_invitations', 0);
    }

    public function test_code_is_unique_stable_and_does_not_contain_personal_data(): void
    {
        $user = $this->customer();
        $code = $this->code($user);
        $this->assertMatchesRegularExpression('/\A[0-9A-F]{24}\z/', $code);
        $this->assertSame($code, $this->code($user));
        $this->assertNotSame($code, $this->code($this->customer()));
        $this->assertDatabaseCount('referral_codes', 2);
    }

    public function test_repeated_click_or_retry_after_pause_preserves_one_invitation(): void
    {
        $campaign = $this->campaign();
        $code = $this->code($inviter = $this->customer());
        $this->actingAs($customer = $this->customer());
        $this->apply($code)->assertRedirect(route('cart.index'));
        $this->apply(strtolower($code))->assertSessionHasNoErrors();
        $campaign->update(['status' => 'paused']);
        $this->apply($code)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referral_invitations', 1);
        $this->assertDatabaseHas('referral_invitations', ['customer_id' => $customer->id, 'inviter_id' => $inviter->id, 'campaign_id' => $campaign->id]);
    }

    public function test_database_uniqueness_also_rejects_duplicate_customer(): void
    {
        $this->campaign();
        $code = $this->code($this->customer());
        $this->actingAs($this->customer());
        $this->apply($code)->assertSessionHasNoErrors();
        $row = (array) DB::table('referral_invitations')->first();
        unset($row['id']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('referral_invitations')->insert($row);
    }

    public function test_self_referral_and_changing_owner_are_rejected(): void
    {
        $this->campaign();
        $this->actingAs($user = $this->customer());
        $this->apply($this->code($user))->assertSessionHasErrors('referral_code');
        $this->apply($this->code($this->customer()))->assertSessionHasNoErrors();
        $this->apply($this->code($this->customer()))->assertSessionHasErrors('referral_code');
        $this->assertDatabaseCount('referral_invitations', 1);
    }

    public function test_circular_invitations_are_rejected(): void
    {
        $this->campaign();
        $a = $this->customer(); $b = $this->customer(); $c = $this->customer();
        $aCode = $this->code($a); $bCode = $this->code($b); $cCode = $this->code($c);
        $this->actingAs($b); $this->apply($aCode)->assertSessionHasNoErrors();
        $this->actingAs($c); $this->apply($bCode)->assertSessionHasNoErrors();
        $this->actingAs($a); $this->apply($cCode)->assertSessionHasErrors('referral_code');
        $this->assertDatabaseCount('referral_invitations', 2);
    }

    public function test_closed_future_and_expired_campaigns_reject_and_hide_input(): void
    {
        $campaign = $this->campaign();
        $code = $this->code($this->customer());
        $this->actingAs($this->customer());
        foreach ([['status' => 'paused'], ['status' => 'active', 'starts_at' => now()->addHour()], ['starts_at' => now()->subDay(), 'ends_at' => now()]] as $values) {
            $campaign->update($values);
            $this->apply($code)->assertSessionHasErrors('referral_code');
            $this->get(route('cart.index'))->assertOk()->assertDontSee('name="referral_code"', false);
        }
        $this->assertDatabaseCount('referral_invitations', 0);
    }

    public function test_existing_order_prevents_late_attribution(): void
    {
        $this->campaign();
        $code = $this->code($this->customer());
        $this->actingAs($customer = $this->customer());
        Order::create(['user_id' => $customer->id, 'code' => 'CHF-'.Str::ulid(),
            'customer_name' => 'Cliente', 'customer_phone' => '999999999', 'delivery_type' => 'delivery',
            'subtotal' => 20, 'total' => 20, 'payment_method' => 'cash', 'payment_status' => 'pending', 'status' => 'pending']);
        $this->apply($code)->assertSessionHasErrors('referral_code');
        $this->assertDatabaseCount('referral_invitations', 0);
    }

    public function test_guest_staff_and_tampered_payload_cannot_attribute_other_accounts(): void
    {
        $this->campaign();
        $code = $this->code($owner = $this->customer());
        $this->apply($code)->assertRedirect();
        foreach (['admin', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->apply($code)->assertForbidden();
        }
        $this->actingAs($customer = $this->customer());
        $this->post(route('customer.referrals.store'), ['referral_code' => ['bad']])->assertSessionHasErrors('referral_code');
        $this->post(route('customer.referrals.store'), ['referral_code' => "' OR 1=1 --"])->assertSessionHasErrors('referral_code');
        $this->post(route('customer.referrals.store'), ['referral_code' => $code, 'customer_id' => $owner->id,
            'inviter_id' => $customer->id, 'campaign_id' => 99999, 'reward' => 200])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('referral_invitations', ['customer_id' => $customer->id, 'inviter_id' => $owner->id]);
    }

    public function test_cart_and_account_only_expose_own_code_during_campaign(): void
    {
        $this->campaign();
        $this->get(route('cart.index'))->assertOk()->assertDontSee('name="referral_code"', false);
        $this->actingAs($customer = $this->customer());
        $this->get(route('cart.index'))->assertOk()->assertSee('name="referral_code"', false);
        $this->get(route('customer.account'))->assertOk()->assertSee($this->code($customer));
    }

    public function test_admin_creation_is_idempotent_activation_is_exclusive_and_logged(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.referrals.index'))->assertOk();
        $data = ['creation_key' => (string) Str::uuid(), 'name' => '<script>Test</script>',
            'starts_at' => now('America/Lima')->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now('America/Lima')->addDay()->format('Y-m-d\TH:i')];
        $this->post(route('admin.referrals.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.referrals.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referral_campaigns', 1);
        $campaign = ReferralCampaign::first();
        $this->patch(route('admin.referrals.update', $campaign), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->patch(route('admin.referrals.update', $campaign), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('referral_campaign_events', 2);
        $this->get(route('admin.referrals.index'))->assertOk()->assertDontSee('<script>Test</script>', false);
        $this->post(route('admin.referrals.store'), array_replace($data, ['creation_key' => (string) Str::uuid()]))->assertSessionHasNoErrors();
        $second = ReferralCampaign::latest('id')->first();
        $this->patch(route('admin.referrals.update', $second), ['status' => 'active'])->assertSessionHasErrors('status');
    }

    public function test_customers_cannot_manage_campaigns_and_ended_campaign_cannot_reactivate(): void
    {
        $campaign = $this->campaign();
        $this->actingAs($this->customer())->get(route('admin.referrals.index'))->assertForbidden();
        $this->post(route('admin.referrals.store'), [])->assertForbidden();
        $this->patch(route('admin.referrals.update', $campaign), ['status' => 'ended'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->patch(route('admin.referrals.update', $campaign), ['status' => 'ended'])->assertSessionHasNoErrors();
        $this->patch(route('admin.referrals.update', $campaign), ['status' => 'active'])->assertSessionHasErrors('status');
    }
}
