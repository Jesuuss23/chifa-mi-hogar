<?php

namespace Tests\Feature;

use App\Models\CustomerSocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'customer', ...$attributes]);
    }

    public function test_guest_cannot_read_list_details_or_photo(): void
    {
        $customer = $this->customer();
        foreach (['admin.customers.index', 'admin.customers.show', 'admin.customers.avatar'] as $name) {
            $this->get(route($name, $name === 'admin.customers.index' ? [] : $customer->id))->assertRedirect();
        }
    }

    public static function nonAdminRoles(): array
    {
        return [['customer'], ['delivery']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_other_roles_cannot_read_customer_data(string $role): void
    {
        $customer = $this->customer();
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('admin.customers.index'))->assertForbidden();
        $this->get(route('admin.customers.show', $customer->id))->assertForbidden();
        $this->get(route('admin.customers.avatar', $customer->id))->assertForbidden();
    }

    public function test_directory_is_paginated_and_excludes_staff(): void
    {
        $admin = $this->admin();
        User::factory()->count(26)->create(['role' => 'customer']);
        User::factory()->create(['role' => 'delivery', 'email' => 'driver-private@example.test']);
        $response = $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->total() === 26 && $rows->count() === 25)
            ->assertDontSee('driver-private@example.test')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->get(route('admin.customers.index', ['page' => 2]))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->count() === 1);
    }

    public function test_search_matches_name_email_and_phone_without_returning_staff(): void
    {
        $customer = $this->customer(['name' => 'Rosa Cliente', 'email' => 'rosa@example.test']);
        $customer->customerProfile()->create(['phone' => '987654321']);
        $this->customer(['name' => 'Otra persona']);
        $admin = $this->admin();
        User::factory()->create(['role' => 'delivery', 'name' => 'Rosa Repartidor', 'email' => 'rosa-driver@example.test']);
        $this->actingAs($admin);
        foreach (['ROSA', 'rosa@example.test', '987654'] as $q) {
            $this->get(route('admin.customers.index', ['q' => $q]))->assertOk()
                ->assertViewHas('customers', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $customer->id);
        }
        $this->get(route('admin.customers.index', ['q' => '%']))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->total() === 0);
        $this->getJson(route('admin.customers.index', ['q' => ['bad']]))->assertUnprocessable();
        $this->getJson(route('admin.customers.index', ['q' => str_repeat('a', 101)]))->assertUnprocessable();
    }

    public function test_details_are_escaped_and_do_not_expose_credentials_or_provider_ids(): void
    {
        $customer = $this->customer(['name' => '<script>alert(1)</script>', 'remember_token' => 'private-remember-secret']);
        $customer->customerProfile()->create([
            'phone' => '987654321', 'delivery_address' => '<img src=x onerror=alert(2)>',
            'delivery_reference' => 'Puerta azul', 'delivery_latitude' => 0, 'delivery_longitude' => 0,
        ]);
        (new CustomerSocialAccount())->forceFill([
            'user_id' => $customer->id, 'provider' => 'google', 'provider_id' => 'private-provider-secret',
        ])->save();
        $before = $customer->fresh()->getAttributes();
        $this->actingAs($this->admin());
        foreach ([[], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
            $response = $this->get(route('admin.customers.show', $customer->id), $headers)->assertOk()
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertDontSee('<img src=x onerror=alert(2)>', false)
                ->assertSee('Puerta azul')->assertSee('Google')->assertSee('Ver ubicación en Google Maps')
                ->assertDontSee('private-provider-secret')->assertDontSee('private-remember-secret')
                ->assertDontSee($customer->password, false)
                ->assertHeader('X-Customer-Details', '1')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        }
        $this->assertSame($before, $customer->fresh()->getAttributes());
    }

    public function test_missing_profile_has_clear_empty_states(): void
    {
        $customer = $this->customer();
        $this->actingAs($this->admin())->get(route('admin.customers.show', $customer->id))
            ->assertOk()->assertSee('No indicada')->assertSee('Ubicación no configurada o incompleta.')
            ->assertDontSee('Ver ubicación en Google Maps');
    }

    public function test_staff_and_missing_ids_are_not_customer_records(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create(['role' => 'delivery']);
        $this->actingAs($admin);
        foreach ([$admin->id, $driver->id, 999999] as $id) {
            $this->get(route('admin.customers.show', $id))->assertNotFound();
            $this->get(route('admin.customers.avatar', $id))->assertNotFound();
        }
    }

    public function test_avatar_requires_owned_private_path_and_existing_file(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $path = 'customer-avatars/'.$customer->id.'/11111111-1111-1111-1111-111111111111.webp';
        Storage::disk('local')->put($path, 'private-webp-test-bytes');
        $customer->forceFill(['avatar_path' => $path])->save();
        $this->actingAs($this->admin());
        $response = $this->get(route('admin.customers.avatar', $customer->id))->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $other->forceFill(['avatar_path' => $path])->save();
        $this->get(route('admin.customers.avatar', $other->id))->assertNotFound();
        $customer->forceFill(['avatar_path' => '../outside.webp'])->save();
        $this->get(route('admin.customers.avatar', $customer->id))->assertNotFound();
        $customer->forceFill(['avatar_path' => $path])->save();
        Storage::disk('local')->delete($path);
        $this->get(route('admin.customers.avatar', $customer->id))->assertNotFound();
    }
}
