<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\WhatsappAdminNumber;
use App\Services\MetaWhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappCommandAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-29T12:00:00Z'));
        config([
            'meta_whatsapp.app_secret' => 'command-test-secret',
            'meta_whatsapp.phone_number_id' => '123456789',
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public static function revokedPermissions(): array
    {
        return [
            'open loses control' => ['ABRIR CHIFA', 'control', false],
            'close loses control' => ['CERRAR CHIFA', 'control', true],
            'status loses control' => ['ESTADO CHIFA', 'control', true],
            'window loses active status' => ['ACTIVAR VENTANA', 'inactive', true],
            'phone changes' => ['ABRIR CHIFA', 'phone', false],
            'record deleted' => ['ABRIR CHIFA', 'deleted', false],
        ];
    }

    #[DataProvider('revokedPermissions')]
    public function test_changed_permissions_are_rechecked_before_executing(
        string $command, string $change, bool $open
    ): void {
        StoreSetting::current()->update(['accepting_orders' => $open]);
        $before = StoreSetting::current()->getAttributes();
        $admin = $this->admin();
        $interleaved = false;
        $this->mock(MetaWhatsappService::class)->shouldNotReceive('sendText');

        DB::listen(function ($query) use ($admin, $change, &$interleaved) {
            if (! $interleaved
                && str_starts_with(strtolower($query->sql), 'select')
                && str_contains($query->sql, 'whatsapp_admin_numbers')
                && in_array($admin->phone, $query->bindings, true)) {
                // The first lookup has returned the original permissions.
                // Commit a change before the command's locked lookup.
                $interleaved = true;
                $row = DB::table('whatsapp_admin_numbers')->where('id', $admin->id);
                if ($change === 'deleted') {
                    $row->delete();
                } else {
                    $row->update(match ($change) {
                        'control' => ['can_control_store' => false],
                        'inactive' => ['is_active' => false],
                        'phone' => ['phone' => '51988888888'],
                    });
                }
            }
        });

        $this->sendCommand($command)->assertOk();
        $this->assertTrue($interleaved);
        $this->assertSame($before, StoreSetting::current()->getAttributes());
        $this->assertDatabaseCount('whatsapp_command_logs', 0);
    }

    public static function allowedCommands(): array
    {
        return [
            'open' => ['ABRIR CHIFA', false, true],
            'close' => ['CERRAR CHIFA', true, false],
            'status' => ['ESTADO CHIFA', true, true],
            'window' => ['ACTIVAR VENTANA', true, true],
        ];
    }

    #[DataProvider('allowedCommands')]
    public function test_authorized_commands_keep_existing_behavior(string $command, bool $before, bool $after): void
    {
        $this->admin();
        StoreSetting::current()->update(['accepting_orders' => $before]);
        $this->mock(MetaWhatsappService::class)->shouldReceive('sendText')
            ->once()->with('51999999999', \Mockery::type('string'))->andReturn(true);

        $this->sendCommand($command)->assertOk();
        $this->assertSame($after, StoreSetting::current()->accepting_orders);
        $this->assertDatabaseCount('whatsapp_command_logs', 1);
        $this->assertDatabaseHas('whatsapp_command_logs', [
            'meta_message_id' => 'wamid.command-test',
            'sender_phone' => '51999999999',
            'command' => $command,
            'result' => $after ? 'open' : 'closed',
        ]);
    }

    public function test_active_notification_recipient_can_activate_window_without_control_permission(): void
    {
        $admin = $this->admin();
        $admin->update(['can_control_store' => false]);
        StoreSetting::current()->update(['accepting_orders' => false]);
        $this->mock(MetaWhatsappService::class)->shouldReceive('sendText')->once()->andReturn(true);

        $this->sendCommand('ACTIVAR VENTANA')->assertOk();
        $this->assertFalse(StoreSetting::current()->accepting_orders);
        $this->assertDatabaseCount('whatsapp_command_logs', 1);
    }

    public function test_duplicate_message_does_not_execute_or_reply_again(): void
    {
        $this->admin();
        StoreSetting::current()->update(['accepting_orders' => false]);
        $this->mock(MetaWhatsappService::class)->shouldReceive('sendText')->once()->andReturn(true);

        $this->sendCommand('ABRIR CHIFA')->assertOk();
        $this->assertTrue(StoreSetting::current()->accepting_orders);
        // A later administrative change must survive a repeated webhook.
        StoreSetting::current()->update(['accepting_orders' => false]);
        $this->sendCommand('ABRIR CHIFA')->assertOk();
        $this->assertFalse(StoreSetting::current()->accepting_orders);
        $this->assertDatabaseCount('whatsapp_command_logs', 1);
    }

    private function admin(): WhatsappAdminNumber
    {
        return WhatsappAdminNumber::create([
            'name' => 'Administrador de prueba',
            'phone' => '51999999999',
            'is_active' => true,
            'can_control_store' => true,
            'receive_orders' => true,
        ]);
    }

    private function sendCommand(string $command): \Illuminate\Testing\TestResponse
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [['changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '123456789'],
                    'messages' => [[
                        'id' => 'wamid.command-test',
                        'from' => '51999999999',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => $command],
                    ]],
                ],
            ]]]],
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/webhooks/meta/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'command-test-secret'),
        ], $body);
    }
}
