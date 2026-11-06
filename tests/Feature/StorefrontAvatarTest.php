<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    public function test_header_uses_private_avatar_endpoint_without_exposing_file_path(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $path = 'customer-avatars/'.$user->id.'/12345678-1234-1234-1234-123456789abc.webp';
        Storage::disk('local')->put($path, 'private photo');
        $user->forceFill(['avatar_path' => $path])->save();
        $response = $this->actingAs($user)->get(route('storefront.index'))->assertOk();
        $response->assertSee('src="'.route('customer.avatar.show').'"', false)->assertDontSee($path, false);
        // Both desktop and mobile account entries use the same private source.
        $this->assertSame(2, substr_count($response->getContent(), 'src="'.route('customer.avatar.show').'"'));
        $this->get(route('customer.avatar.show'))->assertOk()->assertSee('private photo');
        $other = User::factory()->create(['role' => 'customer']);
        $this->actingAs($other)->get(route('storefront.index'))->assertOk()
            ->assertDontSee('src="'.route('customer.avatar.show').'"', false);
        $this->get(route('customer.avatar.show'))->assertNotFound();
    }

    public function test_missing_file_and_removed_photo_use_initial(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'name' => 'Zulema']);
        $user->forceFill(['avatar_path' => 'customer-avatars/'.$user->id.'/12345678-1234-1234-1234-123456789abc.webp'])->save();
        $this->actingAs($user)->get(route('storefront.index'))->assertOk()
            ->assertDontSee('src="'.route('customer.avatar.show').'"', false)->assertSee('Z</span>', false);
        $user->forceFill(['avatar_path' => null])->save();
        $this->actingAs($user->fresh())->get(route('storefront.index'))->assertOk()
            ->assertDontSee('src="'.route('customer.avatar.show').'"', false);
    }

    public function test_guests_and_staff_do_not_request_customer_avatar(): void
    {
        $this->get(route('storefront.index'))->assertOk()
            ->assertDontSee('src="'.route('customer.avatar.show').'"', false);
        foreach (['admin', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('storefront.index'))->assertOk()
                ->assertDontSee('src="'.route('customer.avatar.show').'"', false);
        }
    }
}
