<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminCustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_guests_customers_and_delivery_cannot_read_directory_or_details(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        foreach ([route('admin.customers.index'), route('admin.customers.show', $customer->id)] as $url) {
            $this->get($url)->assertRedirect();
        }
        foreach (['customer', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.customers.index'))->assertForbidden();
            $this->get(route('admin.customers.show', $customer->id))->assertForbidden();
        }
    }

    public function test_directory_is_paginated_and_excludes_staff_and_sensitive_attributes(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => 'delivery', 'name' => 'Staff secreto']);
        User::factory()->count(21)->create(['role' => 'customer']);
        $response = $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk()
            ->assertDontSee('Staff secreto')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertViewHas('customers', function ($customers) {
            $this->assertSame(21, $customers->total());
            $this->assertCount(20, $customers);
            foreach ($customers as $customer) {
                foreach (['password', 'remember_token', 'avatar_path'] as $field) {
                    $this->assertArrayNotHasKey($field, $customer->getAttributes());
                }
            }
            return true;
        });
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_search_matches_customer_contact_without_leaking_other_roles(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Rosa Pérez', 'email' => 'rosa@example.test']);
        $customer->customerProfile()->create(['phone' => '987654321']);
        User::factory()->create(['role' => 'delivery', 'name' => 'Rosa Staff', 'email' => 'rosa.staff@example.test']);
        User::factory()->create(['role' => 'customer', 'name' => 'Otro', 'email' => 'otro@example.test']);
        $this->actingAs($admin);
        foreach (['ROSA', 'rosa@example.test', '987654321'] as $term) {
            $this->get(route('admin.customers.index', ['q' => $term]))->assertOk()
                ->assertViewHas('customers', fn ($customers) => $customers->pluck('id')->all() === [$customer->id]);
        }
        // Search wildcards are treated literally, not as a way to match all accounts.
        $this->get(route('admin.customers.index', ['q' => '%']))->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 0);
    }

    public function test_detail_shows_saved_profile_without_modifying_it_and_escapes_html(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'name' => '<script>alert(1)</script>']);
        $profile = $customer->customerProfile()->create([
            'phone' => '987654321', 'delivery_address' => '<img src=x onerror=alert(1)>',
            'delivery_reference' => 'Puerta azul', 'delivery_latitude' => -12.0123456,
            'delivery_longitude' => -77.0123456,
        ]);
        $before = $profile->fresh()->getAttributes();
        $response = $this->actingAs($this->admin())->get(route('admin.customers.show', $customer->id))->assertOk()
            ->assertSee('987654321')->assertSee('Puerta azul')
            ->assertSee('-12.0123456')->assertSee('-77.0123456')
            ->assertSee(e($customer->name), false)->assertDontSee('<script>', false)
            ->assertSee(e($profile->delivery_address), false)->assertDontSee('<img src=x', false)
            ->assertSee('consulta siempre los datos del pedido');
        $this->assertSame($before, $profile->fresh()->getAttributes());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertNothingSent();
    }

    public function test_uncompleted_profile_has_clear_empty_state(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($this->admin())->get(route('admin.customers.show', $customer->id))->assertOk()
            ->assertSee('todavía no ha completado sus datos')->assertSee('Sin registrar');
        $this->assertDatabaseCount('customer_profiles', 0);
    }

    public function test_staff_ids_and_unknown_ids_cannot_be_opened_as_customer(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create(['role' => 'delivery']);
        $this->actingAs($admin);
        foreach ([$admin->id, $driver->id, 999999] as $id) {
            $this->get(route('admin.customers.show', $id))->assertNotFound();
        }
    }
}
