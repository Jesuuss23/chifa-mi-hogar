<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostPurchaseGoogleReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function order(array $attributes = []): Order
    {
        return Order::create(array_replace([
            'code' => 'CHF-'.Str::ulid(),
            'tracking_token' => (string) Str::uuid(),
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'subtotal' => 20,
            'total' => 20,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
        ], $attributes));
    }

    public static function customers(): array
    {
        return ['guest' => [false], 'registered' => [true]];
    }

    #[DataProvider('customers')]
    public function test_confirmation_shows_configured_link_without_order_data(bool $registered): void
    {
        $user = $registered ? User::factory()->create(['role' => 'customer']) : null;
        if ($user) {
            $this->actingAs($user);
        }
        $url = 'https://search.google.com/local/writereview?placeid=test-place&source=website';
        StoreSetting::current()->update(['google_reviews_url' => $url]);
        $order = $this->order(['user_id' => $user?->id]);
        $response = $this->withSession(['last_confirmed_order_id' => $order->id])
            ->get(route('cart.checkout.success'))->assertOk()->assertSee('Danos tu opinión');
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $links = (new \DOMXPath($document))->query('//a[@id="google-review-link"]');
        $this->assertCount(1, $links);
        $link = $links->item(0);
        $this->assertSame($url, $link->getAttribute('href'));
        $this->assertSame('_blank', $link->getAttribute('target'));
        $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
        $this->assertSame('no-referrer', $link->getAttribute('referrerpolicy'));
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('orders', 1);
        Http::assertNothingSent();
    }

    public static function invalidUrls(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,test'],
            'relative' => ['/reseñas'],
            'credentials' => ['https://user:secret@google.com/'],
            'malformed' => ['not a url'],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_missing_or_unsafe_url_does_not_render_button(?string $url): void
    {
        StoreSetting::current()->update(['google_reviews_url' => $url]);
        $order = $this->order();
        $this->withSession(['last_confirmed_order_id' => $order->id])
            ->get(route('cart.checkout.success'))->assertOk()
            ->assertDontSee('id="google-review-link"', false);
        Http::assertNothingSent();
    }

    public function test_no_confirmation_session_still_redirects_to_cart(): void
    {
        StoreSetting::current()->update(['google_reviews_url' => 'https://g.page/r/example/review']);
        $this->get(route('cart.checkout.success'))->assertRedirect(route('cart.index'));
    }

    public function test_invitation_does_not_depend_on_payment_or_internal_rating(): void
    {
        StoreSetting::current()->update(['google_reviews_url' => 'https://g.page/r/example/review']);
        foreach (['pending', 'delivered', 'cancelled'] as $status) {
            $order = $this->order(['status' => $status]);
            $this->withSession(['last_confirmed_order_id' => $order->id])
                ->get(route('cart.checkout.success'))->assertOk()
                ->assertSee('id="google-review-link"', false);
        }
    }
}
