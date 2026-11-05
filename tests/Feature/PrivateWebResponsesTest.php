<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectPrivateWebResponses;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PrivateWebResponsesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public static function privateNames(): array
    {
        return array_map(fn ($name) => [$name], [
            'cart.index', 'cart.checkout', 'cart.checkout.success', 'cart.checkout.status',
            'checkout.store', 'customer.account', 'customer.password.reset',
            'customer.google.callback', 'customer.facebook.callback', 'login',
            'admin.login', 'admin.legal.preview', 'delivery.orders.show', 'orders.tracking.route',
        ]);
    }

    #[DataProvider('privateNames')]
    public function test_private_route_policy_also_applies_to_non_html_responses(string $name): void
    {
        $request = Request::create('/example?index=true');
        $route = (new Route('GET', '/example', fn () => null))->name($name);
        $request->setRouteResolver(fn () => $route);
        $original = new Response('{"ok":true}', 200, [
            'Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=3600',
        ]);
        $result = (new ProtectPrivateWebResponses())->handle($request, fn () => $original);
        $this->assertSame($original, $result);
        $this->assertSame('{"ok":true}', $result->getContent());
        $this->assertSame('noindex, nofollow', $result->headers->get('X-Robots-Tag'));
        $this->assertTrue($result->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($result->headers->hasCacheControlDirective('private'));
        $this->assertFalse($result->headers->hasCacheControlDirective('public'));
        $this->assertSame('no-referrer', $result->headers->get('Referrer-Policy'));
    }

    public static function publicNames(): array
    {
        return array_map(fn ($name) => [$name], [
            'storefront.index', 'storefront.show', 'combos.show', 'legal.privacy', 'sitemap',
        ]);
    }

    #[DataProvider('publicNames')]
    public function test_public_response_headers_are_not_changed(string $name): void
    {
        $request = Request::create('/example?route=customer.account');
        $request->setRouteResolver(fn () => (new Route('GET', '/example', fn () => null))->name($name));
        $original = new Response('public', 200, ['Cache-Control' => 'public, max-age=600']);
        $headers = $original->headers->all();
        $result = (new ProtectPrivateWebResponses())->handle($request, fn () => $original);
        $this->assertSame($headers, $result->headers->all());
    }

    public function test_cart_and_login_are_protected_through_web_stack(): void
    {
        foreach (['cart.index', 'login', 'customer.register', 'customer.password.request'] as $name) {
            $response = $this->get(route($name))->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertHeader('Referrer-Policy', 'no-referrer');
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        }
    }

    public function test_auth_redirect_is_protected_without_changing_destination(): void
    {
        $this->get(route('customer.account'))->assertRedirect(route('login'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_customer_account_is_not_cacheable(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $response = $this->actingAs($customer)->get(route('customer.account'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function test_catalog_remains_public_and_keeps_social_metadata(): void
    {
        StoreSetting::current();
        $this->get(route('storefront.index'))->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('property="og:title"', false);
    }
}
