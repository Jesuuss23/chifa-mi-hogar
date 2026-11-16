<?php

namespace Tests\Feature;

use App\Models\AdminPushDevice;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminPushDelivery;
use App\Services\FirebaseAdminMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AdminPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin_push.enabled' => true, 'cache.default' => 'array',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Http::preventStrayRequests();
    }

    private function createMobileSessionToken(string $role = 'admin'): string
    {
        return User::factory()->create(['role' => $role])
            ->createToken('admin-mobile:test', ['admin-mobile:read'], now()->addDays(30))->plainTextToken;
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();
        return $this->withHeaders(['Authorization' => 'Bearer '.$token]);
    }

    private function register(string $token, string $fcm = 'test-device-token-1234567890'): void
    {
        $this->bearer($token)->postJson('/api/v1/admin/push/register', ['fcm_token' => $fcm])->assertNoContent();
    }

    private function order(): Order
    {
        return Order::create([
            'code' => 'CHF-'.Str::ulid(), 'checkout_token' => (string) Str::uuid(),
            'tracking_token' => (string) Str::uuid(), 'customer_name' => 'Private customer',
            'customer_phone' => '999999999', 'delivery_type' => 'delivery',
            'subtotal' => 20, 'total' => 20, 'payment_method' => 'cash',
            'payment_status' => 'pending', 'status' => 'pending',
        ]);
    }

    public function test_registration_is_idempotent_encrypted_and_cannot_be_taken_by_another_session(): void
    {
        $first = $this->createMobileSessionToken();
        $this->register($first);
        $this->register($first);
        $this->assertDatabaseCount('admin_push_devices', 1);
        $this->assertNotSame('test-device-token-1234567890', DB::table('admin_push_devices')->value('fcm_token'));
        $this->assertArrayNotHasKey('fcm_token', AdminPushDevice::first()->toArray());
        $this->bearer($this->createMobileSessionToken())->postJson('/api/v1/admin/push/register', [
            'fcm_token' => 'test-device-token-1234567890',
        ])->assertStatus(409);
        $this->assertDatabaseCount('admin_push_devices', 1);
    }

    public function test_customer_delivery_web_session_and_expired_tokens_cannot_register(): void
    {
        foreach (['customer', 'delivery'] as $role) {
            $this->bearer($this->createMobileSessionToken($role))->postJson('/api/v1/admin/push/register', [
                'fcm_token' => 'test-device-token-1234567890',
            ])->assertForbidden();
        }
        $expired = $this->createMobileSessionToken();
        PersonalAccessToken::findToken($expired)->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->bearer($expired)->postJson('/api/v1/admin/push/register', [
            'fcm_token' => 'test-device-token-1234567890',
        ])->assertUnauthorized();
        $this->flushHeaders();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')
            ->postJson('/api/v1/admin/push/register', ['fcm_token' => 'test-device-token-1234567890'])->assertUnauthorized();
        $this->assertDatabaseCount('admin_push_devices', 0);
    }

    public function test_invalid_registration_and_disabled_campaign_do_not_write(): void
    {
        $token = $this->createMobileSessionToken();
        foreach (['short', "token-with-\nnewline-123456", str_repeat('a', 4097), ['injection']] as $value) {
            $this->bearer($token)->postJson('/api/v1/admin/push/register', ['fcm_token' => $value])->assertUnprocessable();
        }
        config(['admin_push.enabled' => false]);
        $this->bearer($token)->postJson('/api/v1/admin/push/register', [
            'fcm_token' => 'test-device-token-1234567890',
        ])->assertStatus(503);
        $this->assertDatabaseCount('admin_push_devices', 0);
    }

    public function test_order_and_notification_roll_back_together_without_http(): void
    {
        $this->register($this->createMobileSessionToken());
        try {
            DB::transaction(function () {
                $this->order();
                $this->assertDatabaseCount('admin_push_messages', 1);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('admin_push_messages', 0);
        Http::assertNothingSent();
    }

    public function test_repeated_processing_does_not_resend_a_success(): void
    {
        $this->register($this->createMobileSessionToken());
        $order = $this->order();
        $id = DB::table('admin_push_messages')->value('id');
        $this->mock(FirebaseAdminMessaging::class)->shouldReceive('send')->once()
            ->with('test-device-token-1234567890', $order->id)
            ->andReturn(['status' => 'sent', 'result' => 'accepted']);
        app(AdminPushDelivery::class)->send($id);
        app(AdminPushDelivery::class)->send($id);
        $this->assertDatabaseHas('admin_push_messages', ['id' => $id, 'status' => 'sent', 'attempts' => 1]);
    }

    public function test_logout_cascades_devices_and_pending_notifications(): void
    {
        $token = $this->createMobileSessionToken();
        $this->register($token);
        $this->order();
        $this->bearer($token)->postJson('/api/v1/admin/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('admin_push_devices', 0);
        $this->assertDatabaseCount('admin_push_messages', 0);
    }

    public function test_opt_out_only_removes_current_session(): void
    {
        $first = $this->createMobileSessionToken();
        $this->register($first);
        $second = $this->createMobileSessionToken();
        $this->register($second, 'second-device-token-1234567890');
        $this->bearer($first)->postJson('/api/v1/admin/push/unregister')->assertNoContent();
        $this->assertDatabaseCount('admin_push_devices', 1);
        $this->assertSame('second-device-token-1234567890', AdminPushDevice::first()->fcm_token);
        $this->assertNotNull(PersonalAccessToken::findToken($first));
    }

    public function test_role_change_without_model_events_still_prevents_sending(): void
    {
        $token = $this->createMobileSessionToken();
        $this->register($token);
        $this->order();
        User::whereKey(PersonalAccessToken::findToken($token)->tokenable_id)->update(['role' => 'customer']);
        $this->mock(FirebaseAdminMessaging::class)->shouldNotReceive('send');
        app(AdminPushDelivery::class)->send(DB::table('admin_push_messages')->value('id'));
        $this->assertDatabaseHas('admin_push_messages', ['status' => 'skipped']);
    }

    public function test_rotation_does_not_deliver_old_pending_messages_to_new_registration(): void
    {
        $token = $this->createMobileSessionToken();
        $this->register($token);
        $this->order();
        $this->register($token, 'rotated-device-token-1234567890');
        $this->mock(FirebaseAdminMessaging::class)->shouldNotReceive('send');
        app(AdminPushDelivery::class)->send(DB::table('admin_push_messages')->value('id'));
        $this->assertDatabaseHas('admin_push_messages', ['status' => 'skipped']);
    }

    public function test_retries_respect_backoff_and_three_attempt_limit(): void
    {
        $this->register($this->createMobileSessionToken());
        $this->order();
        $id = DB::table('admin_push_messages')->value('id');
        $this->mock(FirebaseAdminMessaging::class)->shouldReceive('send')->times(3)
            ->andReturn(['status' => 'retry', 'result' => 'http_429', 'delay' => 60]);
        $service = app(AdminPushDelivery::class);
        $service->send($id);
        $service->send($id);
        $this->assertDatabaseHas('admin_push_messages', ['attempts' => 1, 'status' => 'pending']);
        $this->travel(3)->minutes();
        $service->send($id);
        $this->travel(3)->minutes();
        $service->send($id);
        $service->send($id);
        $this->assertDatabaseHas('admin_push_messages', ['attempts' => 3, 'status' => 'failed']);
    }

    public function test_uncertain_and_interrupted_sends_are_not_retried(): void
    {
        $this->register($this->createMobileSessionToken());
        $this->order();
        $id = DB::table('admin_push_messages')->value('id');
        $this->mock(FirebaseAdminMessaging::class)->shouldReceive('send')->once()
            ->andReturn(['status' => 'uncertain', 'result' => 'network_uncertain']);
        app(AdminPushDelivery::class)->send($id);
        app(AdminPushDelivery::class)->send($id);
        $this->order();
        DB::table('admin_push_messages')->where('status', 'pending')->update([
            'status' => 'processing', 'claimed_at' => now()->subMinutes(11),
        ]);
        $this->artisan('app:send-admin-push')->assertSuccessful();
        $this->assertSame(2, DB::table('admin_push_messages')->where('status', 'uncertain')->count());
    }

    public function test_expired_session_finished_and_old_orders_are_skipped(): void
    {
        $token = $this->createMobileSessionToken();
        $this->register($token);
        $this->mock(FirebaseAdminMessaging::class)->shouldNotReceive('send');
        foreach (['delivered', 'cancelled', 'old', 'expired'] as $case) {
            $order = $this->order();
            if ($case === 'old') $order->forceFill(['created_at' => now()->subHour()])->save();
            elseif ($case === 'expired') PersonalAccessToken::findToken($token)->forceFill(['expires_at' => now()->subMinute()])->save();
            else $order->forceFill(['status' => $case])->save();
            app(AdminPushDelivery::class)->send(DB::table('admin_push_messages')->where('order_id', $order->id)->value('id'));
        }
        $this->assertSame(4, DB::table('admin_push_messages')->where('status', 'skipped')->count());
    }
}
