<?php

namespace Tests\Feature;

use App\Models\WhatsappAdminNumber;
use App\Services\MetaWhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappInboundWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-29T12:00:00Z'));
        config([
            'meta_whatsapp.app_secret' => 'webhook-test-secret',
            'meta_whatsapp.phone_number_id' => '123456789',
        ]);
        $this->mock(MetaWhatsappService::class)->shouldNotReceive('sendText');
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public static function existingWindows(): array
    {
        return [
            'first message' => [null, -60, -60],
            'newer message' => [-120, -60, -60],
            'older message' => [-30, -60, -30],
            'same timestamp' => [-60, -60, -60],
        ];
    }

    #[DataProvider('existingWindows')]
    public function test_window_only_moves_forward(?int $storedOffset, int $messageOffset, int $expectedOffset): void
    {
        $admin = $this->admin($storedOffset);
        $this->sendWebhook($this->payload(now()->addSeconds($messageOffset)->timestamp))->assertOk();
        $this->assertTrue($admin->fresh()->last_inbound_at->equalTo(now()->addSeconds($expectedOffset)));
        $this->assertDatabaseCount('whatsapp_command_logs', 0);
    }

    public function test_older_worker_cannot_overwrite_a_newer_committed_timestamp(): void
    {
        $admin = $this->admin(-120);
        $newer = now()->subSeconds(10);
        $interleaved = false;

        DB::listen(function ($query) use ($admin, $newer, &$interleaved) {
            if (! $interleaved
                && str_starts_with(strtolower($query->sql), 'select')
                && str_contains($query->sql, 'whatsapp_admin_numbers')
                && in_array($admin->phone, $query->bindings, true)) {
                // The SELECT already returned the old value. Reproduce another
                // webhook committing a newer value before this worker's UPDATE.
                $interleaved = true;
                DB::table('whatsapp_admin_numbers')->where('id', $admin->id)
                    ->update(['last_inbound_at' => $newer]);
            }
        });

        $this->sendWebhook($this->payload(now()->subSeconds(60)->timestamp))->assertOk();
        $this->assertTrue($interleaved);
        $this->assertTrue($admin->fresh()->last_inbound_at->equalTo($newer));
    }

    public static function invalidTimestamps(): array
    {
        return ['missing' => [null], 'malformed' => ['invalid'], 'future' => ['future']];
    }

    #[DataProvider('invalidTimestamps')]
    public function test_invalid_timestamp_does_not_change_window(int|string|null $timestamp): void
    {
        $admin = $this->admin(-120);
        $before = $admin->fresh()->getAttributes();
        $value = $timestamp === 'future' ? now()->addMinutes(3)->timestamp : $timestamp;
        $this->sendWebhook($this->payload($value))->assertOk();
        $this->assertSame($before, $admin->fresh()->getAttributes());
    }

    public static function invalidSignatures(): array
    {
        return ['missing' => [''], 'incorrect' => ['sha256=incorrect']];
    }

    #[DataProvider('invalidSignatures')]
    public function test_unsigned_or_invalidly_signed_requests_do_not_change_window(string $signature): void
    {
        $admin = $this->admin(-120);
        $before = $admin->fresh()->getAttributes();
        $this->sendWebhook($this->payload(now()->timestamp), $signature)->assertForbidden();
        $this->assertSame($before, $admin->fresh()->getAttributes());
    }

    public function test_another_business_phone_does_not_change_window(): void
    {
        $admin = $this->admin(-120);
        $before = $admin->fresh()->getAttributes();
        $payload = $this->payload(now()->timestamp);
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = 'other-phone';
        $this->sendWebhook($payload)->assertOk();
        $this->assertSame($before, $admin->fresh()->getAttributes());
    }

    public function test_inactive_sender_does_not_change_window(): void
    {
        $admin = $this->admin(-120);
        $admin->update(['is_active' => false]);
        $before = $admin->fresh()->getAttributes();
        $this->sendWebhook($this->payload(now()->timestamp))->assertOk();
        $this->assertSame($before, $admin->fresh()->getAttributes());
    }

    public function test_sender_disabled_after_read_does_not_get_window_updated(): void
    {
        $admin = $this->admin(-120);
        $interleaved = false;
        DB::listen(function ($query) use ($admin, &$interleaved) {
            if (! $interleaved
                && str_starts_with(strtolower($query->sql), 'select')
                && str_contains($query->sql, 'whatsapp_admin_numbers')
                && in_array($admin->phone, $query->bindings, true)) {
                $interleaved = true;
                DB::table('whatsapp_admin_numbers')->where('id', $admin->id)
                    ->update(['is_active' => false]);
            }
        });

        $this->sendWebhook($this->payload(now()->timestamp))->assertOk();
        $this->assertTrue($interleaved);
        $this->assertFalse($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->last_inbound_at->equalTo(now()->subSeconds(120)));
    }

    private function admin(?int $offset): WhatsappAdminNumber
    {
        return WhatsappAdminNumber::create([
            'name' => 'Administrador de prueba',
            'phone' => '51999999999',
            'is_active' => true,
            'can_control_store' => false,
            'receive_orders' => true,
            'last_inbound_at' => $offset === null ? null : now()->addSeconds($offset),
        ]);
    }

    private function payload(int|string|null $timestamp): array
    {
        $message = [
            'id' => 'wamid.test-message',
            'from' => '51999999999',
            'type' => 'text',
            'text' => ['body' => 'Hola'],
        ];
        if ($timestamp !== null) {
            $message['timestamp'] = (string) $timestamp;
        }

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [['changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '123456789'],
                    'messages' => [$message],
                ],
            ]]]],
        ];
    }

    private function sendWebhook(array $payload, ?string $signature = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature ??= 'sha256='.hash_hmac('sha256', $body, 'webhook-test-secret');

        return $this->call('POST', '/api/webhooks/meta/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body);
    }
}
