<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    private function validData(array $changes = []): array
    {
        return array_replace([
            'name' => 'Cliente actualizado', 'phone' => '999999999',
            'delivery_address' => 'Calle de prueba 123', 'delivery_reference' => 'Puerta verde',
            'delivery_latitude' => -12.0464, 'delivery_longitude' => -77.0428,
        ], $changes);
    }

    public function test_customer_saves_only_own_profile_and_cannot_escalate_privileges(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $other->customerProfile()->create(['phone' => '988888888']);
        $email = $user->email;
        $password = $user->password;
        $this->actingAs($user)->put(route('customer.profile.update'), $this->validData([
            'user_id' => $other->id, 'role' => 'admin', 'email' => 'changed@example.com', 'password' => 'changed',
        ]))->assertRedirect(route('customer.account'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_profiles', ['user_id' => $user->id, 'phone' => '999999999']);
        $this->assertSame('988888888', $other->fresh()->customerProfile->phone);
        $this->assertSame('customer', $user->fresh()->role);
        $this->assertSame($email, $user->fresh()->email);
        $this->assertSame($password, $user->fresh()->password);
        $this->get(route('customer.account'))->assertOk()->assertSee('Calle de prueba 123')->assertDontSee('988888888');
    }

    public function test_repeated_save_updates_one_profile_and_can_clear_address(): void
    {
        $this->actingAs($user = User::factory()->create(['role' => 'customer']));
        $this->put(route('customer.profile.update'), $this->validData())->assertSessionHasNoErrors();
        $this->put(route('customer.profile.update'), $this->validData([
            'delivery_address' => '', 'delivery_reference' => '',
            'delivery_latitude' => '', 'delivery_longitude' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_profiles', 1);
        $this->assertNull($user->fresh()->customerProfile->delivery_latitude);
        $this->assertNull($user->fresh()->customerProfile->delivery_address);
    }

    public static function invalidData(): array
    {
        return [
            'bad phone' => [['phone' => '123'], 'phone'],
            'missing latitude' => [['delivery_latitude' => null], 'delivery_latitude'],
            'missing longitude' => [['delivery_longitude' => null], 'delivery_longitude'],
            'no address' => [['delivery_address' => null], 'delivery_address'],
            'latitude range' => [['delivery_latitude' => 91], 'delivery_latitude'],
            'longitude range' => [['delivery_longitude' => -181], 'delivery_longitude'],
        ];
    }

    #[DataProvider('invalidData')]
    public function test_invalid_profile_is_not_saved(array $changes, string $field): void
    {
        $user = User::factory()->create(['role' => 'customer', 'name' => 'Original']);
        $this->actingAs($user)->put(route('customer.profile.update'), $this->validData($changes))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('customer_profiles', 0);
        $this->assertSame('Original', $user->fresh()->name);
    }

    public function test_zero_coordinates_are_valid(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->put(route('customer.profile.update'), $this->validData([
                'delivery_latitude' => 0, 'delivery_longitude' => 0,
            ]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_profiles', 1);
    }

    public function test_guests_and_other_roles_cannot_write_profiles(): void
    {
        $this->put(route('customer.profile.update'), $this->validData())->assertRedirect(route('login'));
        foreach (['admin', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->put(route('customer.profile.update'), $this->validData())->assertForbidden();
        }
        $this->assertDatabaseCount('customer_profiles', 0);
    }
}
