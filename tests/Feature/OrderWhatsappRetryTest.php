<?php

namespace Tests\Feature;

use App\Jobs\SendOrderWhatsappNotification;
use App\Models\Order;
use App\Models\WhatsappAdminNumber;
use App\Services\MetaWhatsappService;
use App\Services\OrderWhatsappNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderWhatsappRetryTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private WhatsappAdminNumber $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Http::preventStrayRequests();
        $this->order = Order::create([
            'code' => 'CHF-'.Str::ulid(),
            'customer_name' => 'Prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'subtotal' => '20.00', 'total' => '20.00',
            'payment_method' => 'cash', 'status' => 'pending',
        ]);
        $this->admin = WhatsappAdminNumber::create([
            'name' => 'Prueba', 'phone' => '51999999999',
            'is_active' => true, 'receive_orders' => true,
            'can_control_store' => true, 'last_inbound_at' => now(),
        ]);
    }

    public function test_sent_notification_is_not_sent_again(): void
    {
        $service = $this->service(['sent']);
        $service->send($this->order);
        $service->send($this->order);
        $this->assertSame('sent', $this->row()->status);
        $this->assertSame(1, $this->row()->attempts);
        $this->assertNotNull($this->row()->sent_at);
    }

    public function test_rejected_messages_have_backoff_and_three_attempt_limit(): void
    {
        $service = $this->service(['failed', 'failed', 'failed']);
        $service->send($this->order);
        $service->send($this->order);
        $this->assertSame(1, $this->row()->attempts);
        $this->travel(60)->seconds();
        $service->send($this->order);
        $service->send($this->order);
        $this->assertSame(2, $this->row()->attempts);
        $this->travel(300)->seconds();
        $service->send($this->order);
        $this->travel(600)->seconds();
        $service->send($this->order);
        $this->assertSame(3, $this->row()->attempts);
        $this->assertSame('failed', $this->row()->status);
        $this->assertNull($this->row()->next_attempt_at);
    }

    public function test_retry_succeeds_without_resending_to_successful_recipient(): void
    {
        $second = WhatsappAdminNumber::create([
            'name' => 'Segundo', 'phone' => '51988888888',
            'is_active' => true, 'receive_orders' => true,
            'can_control_store' => false, 'last_inbound_at' => now(),
        ]);
        $meta = Mockery::mock(MetaWhatsappService::class);
        $meta->shouldReceive('sendTextResult')->with($this->admin->phone, Mockery::type('string'))
            ->twice()->andReturn('failed', 'sent');
        $meta->shouldReceive('sendTextResult')->with($second->phone, Mockery::type('string'))
            ->once()->andReturn('sent');
        $service = new OrderWhatsappNotificationService($meta);
        $service->send($this->order);
        $this->travel(60)->seconds();
        $service->send($this->order);
        $this->assertSame(2, DB::table('order_whatsapp_notifications')->where('status', 'sent')->count());
    }

    public static function closedWindows(): array
    {
        return ['missing' => [null], 'boundary' => [-23 * 3600], 'old' => [-24 * 3600], 'future' => [60]];
    }

    #[DataProvider('closedWindows')]
    public function test_invalid_window_never_sends_or_consumes_attempt(?int $seconds): void
    {
        $this->admin->update(['last_inbound_at' => $seconds === null ? null : now()->addSeconds($seconds)]);
        $this->service([])->send($this->order);
        $this->assertSame('pending', $this->row()->status);
        $this->assertSame(0, $this->row()->attempts);
    }

    public function test_pending_notification_is_queued_after_window_reopens(): void
    {
        $this->admin->update(['last_inbound_at' => null]);
        $this->service([])->send($this->order);
        Bus::fake();
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        Bus::assertNothingDispatched();

        $this->admin->update(['last_inbound_at' => now()]);
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        Bus::assertDispatched(SendOrderWhatsappNotification::class,
            fn ($job) => $job->orderId === $this->order->id);
        $this->service(['sent'])->send($this->order);
        $this->assertSame('sent', $this->row()->status);
    }

    public function test_uncertain_results_are_not_automatically_retried(): void
    {
        $service = $this->service(['uncertain']);
        $service->send($this->order);
        $this->travel(10)->minutes();
        $service->send($this->order);
        Bus::fake();
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        Bus::assertNothingDispatched();
        $this->assertSame('uncertain', $this->row()->status);
    }

    public function test_interrupted_processing_is_flagged_but_recent_processing_is_preserved(): void
    {
        $id = $this->seedNotification(['status' => 'processing', 'attempt_token' => (string) Str::uuid()]);
        $this->service([])->send($this->order);
        Bus::fake();
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        $this->assertSame('processing', $this->row()->status);
        $this->travel(5)->minutes();
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        $this->assertDatabaseHas('order_whatsapp_notifications', [
            'id' => $id, 'status' => 'uncertain', 'attempt_token' => null,
        ]);
        Bus::assertNothingDispatched();
    }

    public function test_late_worker_does_not_overwrite_a_retired_claim(): void
    {
        $meta = Mockery::mock(MetaWhatsappService::class);
        $meta->shouldReceive('sendTextResult')->once()->andReturnUsing(function () {
            DB::table('order_whatsapp_notifications')->update([
                'status' => 'uncertain', 'attempt_token' => null,
            ]);
            return 'sent';
        });
        (new OrderWhatsappNotificationService($meta))->send($this->order);
        $this->assertSame('uncertain', $this->row()->status);
        $this->assertNull($this->row()->sent_at);
    }

    public static function excludedOrders(): array
    {
        return ['delivered' => ['delivered', 0], 'cancelled' => ['cancelled', 0], 'old' => ['pending', 25]];
    }

    #[DataProvider('excludedOrders')]
    public function test_finished_or_old_orders_are_not_sent_or_queued(string $status, int $hours): void
    {
        $this->order->update(['status' => $status, 'created_at' => now()->subHours($hours)]);
        $this->seedNotification();
        $this->service([])->send($this->order);
        Bus::fake();
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        Bus::assertNothingDispatched();
    }

    public function test_disabled_recipient_and_backoff_are_respected_by_recovery(): void
    {
        $this->seedNotification(['status' => 'failed', 'next_attempt_at' => now()->addMinutes(5)]);
        Bus::fake();
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        Bus::assertNothingDispatched();
        $this->travel(5)->minutes();
        $this->admin->update(['receive_orders' => false]);
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        Bus::assertNothingDispatched();
        $this->admin->update(['receive_orders' => true]);
        $this->artisan('whatsapp:retry-orders')->assertSuccessful();
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }

    public function test_review_mode_does_not_queue_or_modify_rows(): void
    {
        $this->seedNotification(['status' => 'uncertain']);
        Bus::fake();
        $this->artisan('whatsapp:retry-orders --review')->assertSuccessful();
        Bus::assertNothingDispatched();
        $this->assertSame('uncertain', $this->row()->status);
    }

    public static function metaResponses(): array
    {
        return [
            'accepted' => [200, ['messages' => [['id' => 'test-id']]], 'sent'],
            'missing id' => [200, [], 'uncertain'],
            'rejected' => [400, ['error' => ['code' => 100]], 'failed'],
            'rate limited' => [429, [], 'failed'],
            'server error' => [500, [], 'uncertain'],
            'timeout response' => [408, [], 'uncertain'],
        ];
    }

    #[DataProvider('metaResponses')]
    public function test_meta_response_classification(int $status, array $body, string $result): void
    {
        config(['meta_whatsapp.access_token' => 'test-token', 'meta_whatsapp.phone_number_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response($body, $status)]);
        $this->assertSame($result, app(MetaWhatsappService::class)->sendTextResult('51999999999', 'Prueba'));
        Http::assertSentCount(1);
    }

    public function test_network_exception_is_uncertain_and_boolean_api_is_preserved(): void
    {
        config(['meta_whatsapp.access_token' => 'test-token', 'meta_whatsapp.phone_number_id' => '123']);
        Http::fake(fn () => throw new ConnectionException('Timeout de prueba'));
        $this->assertSame('uncertain', app(MetaWhatsappService::class)->sendTextResult('51999999999', 'Prueba'));
        $this->assertFalse(app(MetaWhatsappService::class)->sendText('51999999999', 'Prueba'));
    }

    private function service(array $results): OrderWhatsappNotificationService
    {
        $meta = Mockery::mock(MetaWhatsappService::class);
        if ($results === []) {
            $meta->shouldNotReceive('sendTextResult');
        } else {
            $meta->shouldReceive('sendTextResult')->times(count($results))->andReturn(...$results);
        }
        return new OrderWhatsappNotificationService($meta);
    }

    private function row(): object
    {
        return DB::table('order_whatsapp_notifications')
            ->where('order_id', $this->order->id)
            ->where('whatsapp_admin_number_id', $this->admin->id)->first();
    }

    private function seedNotification(array $overrides = []): int
    {
        return DB::table('order_whatsapp_notifications')->insertGetId(array_replace([
            'order_id' => $this->order->id,
            'whatsapp_admin_number_id' => $this->admin->id,
            'status' => 'pending', 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }
}
