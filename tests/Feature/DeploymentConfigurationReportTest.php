<?php

namespace Tests\Feature;

use App\Services\DeploymentConfigurationReport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeploymentConfigurationReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('env', 'production');
        Http::fake();
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        config([
            'app.debug' => false, 'app.url' => 'https://chifamihogar.com',
            'session.secure' => true, 'session.driver' => 'database',
            'turnstile.enabled' => true, 'turnstile.site_key' => 'site-canary',
            'turnstile.secret_key' => 'turnstile-secret-canary',
            'turnstile.hostnames' => ['chifamihogar.com'],
            'google_login.enabled' => false, 'facebook_login.enabled' => false,
            'mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp',
            'queue.default' => 'database', 'queue.connections.database.driver' => 'database',
            'meta_whatsapp.access_token' => 'whatsapp-token-canary',
            'meta_whatsapp.phone_number_id' => 'phone-id-canary',
            'meta_whatsapp.verify_token' => 'verify-secret-canary',
            'meta_whatsapp.app_secret' => 'app-secret-canary',
            'meta_whatsapp.api_version' => 'v23.0',
        ]);
    }

    private function report(): array
    {
        return collect(app(DeploymentConfigurationReport::class)->checks())->keyBy('id')->all();
    }

    public function test_json_never_outputs_credentials_or_claims_real_delivery(): void
    {
        $before = config('meta_whatsapp');
        $this->assertSame(0, Artisan::call('app:check-deployment', ['--json' => true]));
        $output = Artisan::output();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(0, $report['requires_review']);
        $this->assertNotEmpty($report['manual_checks']);
        foreach (['whatsapp-token-canary', 'phone-id-canary', 'verify-secret-canary',
            'app-secret-canary', 'turnstile-secret-canary', 'site-canary'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
        $this->assertSame($before, config('meta_whatsapp'));
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    public function test_development_settings_and_non_delivering_drivers_require_review(): void
    {
        $this->app->instance('env', 'local');
        config(['app.debug' => true, 'app.url' => 'http://localhost',
            'session.secure' => null, 'session.driver' => 'array',
            'mail.default' => 'log', 'queue.default' => 'sync']);
        $report = $this->report();
        foreach (['entorno', 'debug', 'url', 'sesion-segura', 'sesion-persistente', 'correo', 'cola'] as $id) {
            $this->assertSame('REVISAR', $report[$id]['status']);
        }
        $this->artisan('app:check-deployment')->assertExitCode(1);
    }

    public function test_optional_social_providers_are_distinguished_from_broken_enabled_providers(): void
    {
        $report = $this->report();
        $this->assertSame('DESACTIVADO', $report['google_login']['status']);
        $this->assertSame('DESACTIVADO', $report['facebook_login']['status']);
        config(['google_login.enabled' => true, 'google_login.client_id' => 'client-canary',
            'google_login.client_secret' => '', 'google_login.redirect_uri' => 'https://chifamihogar.com/callback']);
        $this->assertSame('REVISAR', $this->report()['google_login']['status']);
    }

    public function test_enabled_provider_requires_https_return_without_embedded_credentials(): void
    {
        config(['facebook_login.enabled' => true, 'facebook_login.app_id' => 'id-canary',
            'facebook_login.app_secret' => 'facebook-secret-canary',
            'facebook_login.graph_version' => 'v23.0']);
        foreach (['http://chifamihogar.com/callback', 'https://user:secret@chifamihogar.com/callback', 'not-a-url'] as $url) {
            config(['facebook_login.redirect_uri' => $url]);
            $this->assertSame('REVISAR', $this->report()['facebook_login']['status']);
        }
        config(['facebook_login.redirect_uri' => 'https://chifamihogar.com/callback']);
        $this->assertSame('CONFIGURADO', $this->report()['facebook_login']['status']);
        Artisan::call('app:check-deployment', ['--json' => true]);
        $this->assertStringNotContainsString('facebook-secret-canary', Artisan::output());
    }

    public function test_disabled_or_incomplete_turnstile_and_whatsapp_are_flagged(): void
    {
        config(['turnstile.enabled' => false, 'meta_whatsapp.app_secret' => '   ']);
        $this->assertSame('REVISAR', $this->report()['turnstile']['status']);
        $this->assertSame('REVISAR', $this->report()['whatsapp']['status']);
        config(['turnstile.enabled' => true, 'turnstile.hostnames' => []]);
        $this->assertSame('REVISAR', $this->report()['turnstile']['status']);
    }

    public function test_composite_or_custom_mail_and_queue_drivers_are_not_assumed_working(): void
    {
        config(['mail.default' => 'failover', 'queue.default' => 'custom',
            'queue.connections.custom.driver' => 'custom']);
        $this->assertSame('REVISAR', $this->report()['correo']['status']);
        $this->assertSame('REVISAR', $this->report()['cola']['status']);
        Http::assertNothingSent();
    }
}
