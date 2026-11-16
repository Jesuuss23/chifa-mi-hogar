<?php

namespace Tests\Feature;

use App\Services\FirebaseAdminMessaging;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseAdminMessagingTest extends TestCase
{
    private string $credentialsPath;
    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->publicKey = openssl_pkey_get_details($key)['key'];
        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm-test-');
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account', 'project_id' => 'test-project',
            'client_email' => 'test@test-project.iam.gserviceaccount.com',
            'private_key_id' => 'test-key', 'private_key' => $privateKey,
        ]));
        config(['admin_push.credentials' => $this->credentialsPath]);
    }

    protected function tearDown(): void
    {
        if (isset($this->credentialsPath) && is_file($this->credentialsPath)) unlink($this->credentialsPath);
        parent::tearDown();
    }

    public function test_signed_oauth_and_data_only_payload_without_customer_information(): void
    {
        $publicKey = $this->publicKey;
        Http::fake(function ($request) use ($publicKey) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                [$header, $claims, $signature] = explode('.', $request['assertion']);
                $decode = static fn ($value) => base64_decode(strtr($value, '-_', '+/'));
                $this->assertSame(1, openssl_verify($header.'.'.$claims, $decode($signature), $publicKey, OPENSSL_ALGO_SHA256));
                $payload = json_decode($decode($claims), true);
                $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $payload['scope']);
                $this->assertSame('https://oauth2.googleapis.com/token', $payload['aud']);
                return Http::response(['access_token' => 'test-oauth-token', 'expires_in' => 3600]);
            }
            $this->assertSame('https://fcm.googleapis.com/v1/projects/test-project/messages:send', $request->url());
            $message = $request['message'];
            $this->assertSame(['type' => 'new_order', 'order_id' => '25'], $message['data']);
            $this->assertArrayNotHasKey('notification', $message);
            $this->assertSame('com.chifamihogar.admin', $message['android']['restricted_package_name']);
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-oauth-token'));
            return Http::response(['name' => 'projects/test-project/messages/test']);
        });
        $this->assertSame('sent', app(FirebaseAdminMessaging::class)->send('device-token', 25)['status']);
        Http::assertSentCount(2);
    }

    public function test_unregistered_is_distinguished_from_invalid_payload_and_retry_after_is_respected(): void
    {
        Cache::put('admin-fcm-oauth:'.hash('sha256', 'test@test-project.iam.gserviceaccount.comtest-key'), 'test-token', 3000);
        Http::fake(['fcm.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['details' => [[
                '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED',
            ]]]], 404)
            ->push(['error' => ['status' => 'INVALID_ARGUMENT']], 400)
            ->push([], 429, ['Retry-After' => '120'])
            ->push([], 200)]);
        $service = app(FirebaseAdminMessaging::class);
        $this->assertSame('invalid', $service->send('test-device', 1)['status']);
        $this->assertSame('failed', $service->send('test-device', 1)['status']);
        $retry = $service->send('test-device', 1);
        $this->assertSame('retry', $retry['status']);
        $this->assertSame(120, $retry['delay']);
        $this->assertSame('failed', $service->send('test-device', 1)['status']);
    }

    public function test_network_failure_does_not_leak_exception_or_retry_uncertain_delivery(): void
    {
        Cache::put('admin-fcm-oauth:'.hash('sha256', 'test@test-project.iam.gserviceaccount.comtest-key'), 'test-token', 3000);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('secret-body'));
        $result = app(FirebaseAdminMessaging::class)->send('secret-device-token', 1);
        $this->assertSame(['status' => 'uncertain', 'result' => 'network_uncertain'], $result);
    }

    public function test_missing_credentials_do_not_send_http(): void
    {
        config(['admin_push.credentials' => $this->credentialsPath.'.missing']);
        $this->assertSame('failed', app(FirebaseAdminMessaging::class)->send('device', 1)['status']);
        Http::assertNothingSent();
    }
}
