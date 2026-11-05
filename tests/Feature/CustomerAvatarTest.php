<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CustomerAvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    public function test_photo_is_optional_and_account_shows_fallback(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->get(route('customer.account'))->assertOk()
            ->assertSee('Foto de perfil (opcional)')->assertSee('Todavía no has agregado una foto.');
        $this->get(route('customer.avatar.show'))->assertNotFound();
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_upload_is_private_small_webp_and_leaves_user_data_unchanged(): void
    {
        $user = $this->customer();
        $before = $user->getAttributes();
        $this->actingAs($user)->put(route('customer.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('portrait.jpg', 400, 800),
            'name' => 'Injected', 'role' => 'admin', 'avatar_path' => '../other.webp',
        ])->assertRedirect(route('customer.account').'#account-avatar')->assertSessionHasNoErrors();
        $path = $user->fresh()->avatar_path;
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $size = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertLessThanOrEqual(256, $size[0]);
        $this->assertLessThanOrEqual(256, $size[1]);
        $this->assertSame('image/webp', $size['mime']);
        foreach (['name', 'email', 'password', 'role'] as $field) {
            $this->assertSame($before[$field], $user->fresh()->getAttributes()[$field]);
        }
        $this->assertArrayNotHasKey('avatar_path', $user->fresh()->toArray());
        $this->actingAs($user->fresh())->get(route('customer.avatar.show'))->assertOk()
            ->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
        Http::assertNothingSent();
    }

    public function test_replacement_removes_only_previous_photo(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $this->upload();
        $old = $user->fresh()->avatar_path;
        Storage::disk('local')->put('unrelated.txt', 'keep');
        $this->upload();
        $new = $user->fresh()->avatar_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
        Storage::disk('local')->assertExists('unrelated.txt');
    }

    public function test_remove_is_idempotent_and_keeps_account(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $this->upload();
        $path = $user->fresh()->avatar_path;
        $this->delete(route('customer.avatar.destroy'))->assertRedirect(route('customer.account').'#account-avatar');
        $this->delete(route('customer.avatar.destroy'))->assertRedirect();
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('invalidImages')]
    public function test_invalid_upload_preserves_previous_photo(string $kind): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $this->upload();
        $path = $user->fresh()->avatar_path;
        $file = match ($kind) {
            'fake' => UploadedFile::fake()->createWithContent('avatar.jpg', '<?php echo "bad";'),
            'svg' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'large' => UploadedFile::fake()->image('avatar.jpg')->size(2049),
            'dimensions' => UploadedFile::fake()->image('avatar.jpg', 2049, 32),
            'tiny' => UploadedFile::fake()->image('avatar.jpg', 16, 16),
        };
        $this->put(route('customer.avatar.update'), ['avatar' => $file])
            ->assertSessionHasErrors(['avatar'], null, 'avatar');
        $this->assertSame($path, $user->fresh()->avatar_path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public static function invalidImages(): array
    {
        return [['fake'], ['svg'], ['large'], ['dimensions'], ['tiny']];
    }

    public function test_failed_storage_does_not_replace_current_photo(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $this->upload();
        $path = $user->fresh()->avatar_path;
        $originalDisk = Storage::disk('local');
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->with(Mockery::on(fn ($candidate) => $candidate !== $path))->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->put(route('customer.avatar.update'), ['avatar' => UploadedFile::fake()->image('new.jpg', 64, 64)])
            ->assertSessionHasErrors(['avatar'], null, 'avatar');
        $this->assertSame($path, $user->fresh()->avatar_path);
        $this->assertTrue($originalDisk->exists($path));
    }

    public function test_other_customer_cannot_read_or_delete_owners_file(): void
    {
        $owner = $this->customer();
        $this->actingAs($owner);
        $this->upload();
        $path = $owner->fresh()->avatar_path;
        $other = $this->customer();
        $this->actingAs($other)->get(route('customer.avatar.show', ['user_id' => $owner->id, 'path' => $path]))->assertNotFound();
        $this->delete(route('customer.avatar.destroy'), ['user_id' => $owner->id, 'avatar_path' => $path])->assertRedirect();
        $this->assertSame($path, $owner->fresh()->avatar_path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_guest_and_staff_cannot_manage_customer_photo(): void
    {
        $this->get(route('customer.avatar.show'))->assertRedirect();
        $this->put(route('customer.avatar.update'))->assertRedirect();
        $this->delete(route('customer.avatar.destroy'))->assertRedirect();
        foreach (['admin', 'delivery'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('customer.avatar.show'))->assertForbidden();
            $this->put(route('customer.avatar.update'))->assertForbidden();
            $this->delete(route('customer.avatar.destroy'))->assertForbidden();
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cleanup_never_deletes_paths_outside_users_avatar_directory(): void
    {
        Storage::disk('local')->put('originals/keep.jpg', 'keep');
        app(CustomerAvatarService::class)->discard('originals/keep.jpg', 1);
        app(CustomerAvatarService::class)->discard('customer-avatars/1/../../originals/keep.jpg', 1);
        Storage::disk('local')->assertExists('originals/keep.jpg');
    }

    public function test_upload_rate_limit(): void
    {
        $this->actingAs($this->customer());
        for ($i = 0; $i < 5; $i++) {
            $this->put(route('customer.avatar.update'), [])->assertSessionHasErrors(['avatar'], null, 'avatar');
        }
        $this->put(route('customer.avatar.update'), [])->assertTooManyRequests();
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    private function upload(): void
    {
        $this->put(route('customer.avatar.update'), ['avatar' => UploadedFile::fake()->image('photo.png', 64, 64)])
            ->assertSessionHasNoErrors();
    }
}
