<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectOrderTrackingResponse;
use App\Models\Order;
use App\Services\DeliveryRouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderTrackingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->order = Order::create([
            'code' => 'CHF-'.Str::ulid(),
            'tracking_token' => (string) Str::uuid(),
            'customer_name' => 'Prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'subtotal' => '20.00',
            'total' => '20.00',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
    }

    public static function trackingEndpoints(): array
    {
        return ['page' => ['show'], 'status' => ['status'], 'route' => ['route']];
    }

    #[DataProvider('trackingEndpoints')]
    public function test_guest_tracking_responses_are_not_indexable_or_cacheable(string $endpoint): void
    {
        $response = $this->get(route('orders.tracking.'.$endpoint, [
            'token' => $this->order->tracking_token,
        ]));
        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));

        if ($endpoint === 'show') {
            $response->assertViewIs('storefront.order-tracking')
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
                ->assertSee('<meta name="referrer" content="no-referrer">', false)
                ->assertSee($this->order->code);
        } elseif ($endpoint === 'status') {
            $response->assertJsonPath('status', 'pending')
                ->assertJsonPath('payment_status', 'pending');
        } else {
            $response->assertJsonPath('available', false)
                ->assertJsonPath('driver', null);
        }
        Http::assertNothingSent();
    }

    public function test_live_route_keeps_coordinates_and_privacy_headers(): void
    {
        $this->order->update([
            'status' => 'on_the_way',
            'driver_tracking_started_at' => now(),
            'driver_location_updated_at' => now(),
            'driver_latitude' => -12.0,
            'driver_longitude' => -77.0,
            'delivery_latitude' => -12.01,
            'delivery_longitude' => -77.01,
        ]);
        $route = [
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [[-77.0, -12.0], [-77.01, -12.01]],
            ],
            'distance_km' => 2,
        ];
        $this->mock(DeliveryRouteService::class, function ($mock) use ($route) {
            $mock->shouldReceive('getRoute')->once()
                ->withArgs(fn (Order $order) => $order->is($this->order))
                ->andReturn($route);
        });

        $response = $this->get(route('orders.tracking.route', [
            'token' => $this->order->tracking_token,
        ]));
        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertJsonPath('available', true)
            ->assertJsonPath('gps_fresh', true)
            ->assertJsonPath('route.geometry.type', 'LineString')
            ->assertJsonPath('destination.latitude', -12.01);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        Http::assertNothingSent();
    }

    #[DataProvider('trackingEndpoints')]
    public function test_unknown_tracking_token_still_returns_not_found(string $endpoint): void
    {
        $this->get(route('orders.tracking.'.$endpoint, [
            'token' => (string) Str::uuid(),
        ]))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_public_catalog_routes_do_not_use_tracking_privacy_middleware(): void
    {
        foreach (['storefront.index', 'storefront.show', 'combos.index', 'combos.show'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertNotContains(ProtectOrderTrackingResponse::class, $route->gatherMiddleware());
        }
    }
}
