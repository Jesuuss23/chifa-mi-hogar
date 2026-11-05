<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerOrderRatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_customer_rates_once_and_retry_preserves_original_feedback(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        $otherOrder = $this->order($other);

        $this->actingAs($customer)->post(route('customer.orders.rate', $order), [
            'rating' => 4,
            'comment' => 'Llegó caliente.',
            'user_id' => $other->id,
            'order_id' => $otherOrder->id,
        ])->assertRedirect(route('customer.account'))->assertSessionHasNoErrors();

        $rating = OrderRating::firstOrFail();
        $this->assertEquals($customer->id, $rating->user_id);
        $this->assertEquals($order->id, $rating->order_id);
        $before = $rating->getAttributes();

        $this->postJson(route('customer.orders.rate', $order), [
            'rating' => 1, 'comment' => 'Segundo envío.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rating')
            ->assertJsonPath('errors.rating.0', 'Este pedido ya fue valorado.');

        $this->assertDatabaseCount('order_ratings', 1);
        $this->assertSame($before, $rating->fresh()->getAttributes());
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public static function unfinishedStates(): array
    {
        return [['pending'], ['confirmed'], ['preparing'], ['on_the_way'], ['cancelled']];
    }

    #[DataProvider('unfinishedStates')]
    public function test_only_delivered_orders_can_be_rated(string $status): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer, ['status' => $status]);
        $this->actingAs($customer)->postJson(route('customer.orders.rate', $order), ['rating' => 5])
            ->assertUnprocessable()->assertJsonValidationErrors('rating');
        $this->assertDatabaseCount('order_ratings', 0);
        $this->assertSame($status, $order->fresh()->status);
    }

    public static function foreignOrders(): array
    {
        return ['other customer' => [false], 'guest order' => [true]];
    }

    #[DataProvider('foreignOrders')]
    public function test_customer_cannot_rate_an_order_they_do_not_own(bool $guest): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $owner = $guest ? null : User::factory()->create(['role' => 'customer']);
        $order = $this->order($owner);
        $this->actingAs($customer)->postJson(route('customer.orders.rate', $order), ['rating' => 5])
            ->assertNotFound();
        $this->assertDatabaseCount('order_ratings', 0);
    }

    public static function forbiddenRoles(): array
    {
        return ['guest' => [null, 401], 'admin' => ['admin', 403], 'driver' => ['delivery', 403]];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_rating_route_is_exclusive_to_customers(?string $role, int $status): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $order = $this->order($owner);
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role]));
        }
        $this->postJson(route('customer.orders.rate', $order), ['rating' => 5])->assertStatus($status);
        $this->assertDatabaseCount('order_ratings', 0);
    }

    public static function invalidFeedback(): array
    {
        return [
            'below minimum' => [['rating' => 0], 'rating'],
            'above maximum' => [['rating' => 6], 'rating'],
            'fraction' => [['rating' => 1.5], 'rating'],
            'long comment' => [['rating' => 5, 'comment' => str_repeat('a', 1001)], 'comment'],
        ];
    }

    #[DataProvider('invalidFeedback')]
    public function test_invalid_feedback_does_not_create_a_rating(array $payload, string $field): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        $this->actingAs($customer)->postJson(route('customer.orders.rate', $order), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('order_ratings', 0);
    }


    public function test_failed_rating_restores_only_the_route_order_and_its_errors(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        $otherOrder = $this->order($customer);
        $comment = str_repeat('x', 1001);

        $this->actingAs($customer)->from(route('customer.account'))
            ->post(route('customer.orders.rate', $order), [
                'rating' => 4, 'comment' => $comment,
                'rating_order_id' => $otherOrder->id,
            ])->assertRedirect(route('customer.account'))
            ->assertSessionHasErrors(['comment'], null, 'orderRating'.$order->id)
            ->assertSessionHas('_old_input.rating_order_id', (string) $order->id);

        $html = $this->get(route('customer.account'))->assertOk()->getContent();
        $xpath = $this->ratingDom($html);
        $target = '//*[@id="account-order-'.$order->id.'"]';
        $other = '//*[@id="account-order-'.$otherOrder->id.'"]';
        $this->assertSame($comment, $xpath->query($target.'//textarea')->item(0)->textContent);
        $this->assertSame('', $xpath->query($other.'//textarea')->item(0)->textContent);
        $this->assertCount(1, $xpath->query($target.'//input[@name="rating" and @value="4" and @checked]'));
        $this->assertCount(0, $xpath->query($other.'//input[@checked]'));
        $this->assertCount(1, $xpath->query($target.'//*[@role="alert"]'));
        $this->assertCount(0, $xpath->query($other.'//*[@role="alert"]'));
        $this->assertDatabaseCount('order_ratings', 0);
    }

    public function test_array_feedback_renders_validation_errors_without_breaking_account(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        $this->actingAs($customer)->from(route('customer.account'))
            ->post(route('customer.orders.rate', $order), [
                'rating' => ['bad'], 'comment' => ['bad'],
            ])->assertSessionHasErrors(['rating', 'comment'], null, 'orderRating'.$order->id);
        $html = $this->get(route('customer.account'))->assertOk()->getContent();
        $xpath = $this->ratingDom($html);
        $this->assertSame('', $xpath->query('//textarea[@name="comment"]')->item(0)->textContent);
        $this->assertCount(0, $xpath->query('//input[@name="rating" and @checked]'));
        $this->assertDatabaseCount('order_ratings', 0);
    }

    public function test_rating_error_remains_visible_if_order_was_already_rated(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        OrderRating::create(['order_id' => $order->id, 'user_id' => $customer->id, 'rating' => 5]);
        $this->actingAs($customer)->from(route('customer.account'))
            ->post(route('customer.orders.rate', $order), ['rating' => 1])
            ->assertSessionHasErrors(['rating'], null, 'orderRating'.$order->id);
        $this->get(route('customer.account'))->assertOk()
            ->assertSee('Este pedido ya fue valorado.')
            ->assertSee('5 de 5 estrellas.');
        $this->assertDatabaseCount('order_ratings', 1);
    }

    public function test_comment_from_another_form_is_not_copied_to_ratings(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create(['role' => 'customer']);
        $this->order($customer);
        $html = $this->actingAs($customer)->withSession([
            '_old_input' => ['comment' => 'Texto de otro formulario', 'rating' => 5],
        ])->get(route('customer.account'))->assertOk()->getContent();
        $xpath = $this->ratingDom($html);
        $this->assertSame('', $xpath->query('//textarea[@name="comment"]')->item(0)->textContent);
        $this->assertCount(0, $xpath->query('//input[@name="rating" and @checked]'));
    }

    private function ratingDom(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($doc);
    }

    private function order(?User $customer, array $overrides = []): Order
    {
        return Order::create(array_replace([
            'user_id' => $customer?->id,
            'checkout_token' => (string) Str::uuid(),
            'tracking_token' => (string) Str::uuid(),
            'code' => 'CHF-'.Str::ulid(),
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'subtotal' => '20.00',
            'total' => '20.00',
            'payment_method' => 'cash',
            'payment_status' => 'verified',
            'status' => 'delivered',
        ], $overrides));
    }
}
