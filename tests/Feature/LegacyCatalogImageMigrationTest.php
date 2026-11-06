<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\LegacyCatalogImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LegacyCatalogImageMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function product(string $path = 'legacy/chaufa.jpg'): Product
    {
        $category = Category::create(['name' => 'Carta', 'slug' => 'carta-'.Str::uuid()]);
        return Product::create([
            'category_id' => $category->id, 'name' => 'Chaufa',
            'slug' => 'chaufa-'.Str::uuid(), 'price' => 20, 'image_path' => $path,
        ]);
    }

    private function image(string $path = 'legacy/chaufa.jpg'): void
    {
        $file = UploadedFile::fake()->image('chaufa.jpg', 20, 10);
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
    }

    public function test_default_review_does_not_convert_or_write(): void
    {
        $this->image();
        $product = $this->product();
        $before = $product->fresh()->getAttributes();
        $this->mock(ImageService::class)->shouldNotReceive('upload');
        $this->artisan('catalog:migrate-images')->expectsOutputToContain('LISTO')->assertExitCode(0);
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_apply_generates_webp_variants_and_keeps_historical_source(): void
    {
        $this->image();
        $original = Storage::disk('public')->get('legacy/chaufa.jpg');
        $product = $this->product('/storage/legacy/chaufa.jpg');
        $this->artisan('catalog:migrate-images', ['--apply' => true])->assertExitCode(0);
        $product->refresh();
        $asset = $product->mediaAsset;
        $this->assertNotNull($asset);
        $this->assertSame('/storage/legacy/chaufa.jpg', $product->image_path);
        $this->assertSame($original, Storage::disk('public')->get('legacy/chaufa.jpg'));
        Storage::disk('local')->assertExists($asset->original_path);
        foreach ([$asset->thumbnail_path, $asset->card_path, $asset->detail_path] as $path) {
            Storage::disk('public')->assertExists($path);
            $info = getimagesize(Storage::disk('public')->path($path));
            $this->assertSame(IMAGETYPE_WEBP, $info[2]);
            $this->assertSame(20, $info[0]);
        }
        $this->artisan('catalog:migrate-images', ['--apply' => true])->assertExitCode(0);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertCount(4, Storage::disk('public')->allFiles());
    }

    public function test_missing_source_does_not_stop_later_valid_records(): void
    {
        $missing = $this->product('missing.jpg');
        $this->image();
        $valid = $this->product();
        $this->artisan('catalog:migrate-images', ['--apply' => true])->assertExitCode(1);
        $this->assertNull($missing->fresh()->media_asset_id);
        $this->assertNotNull($valid->fresh()->media_asset_id);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_corrupt_and_external_sources_are_not_converted(): void
    {
        Storage::disk('public')->put('bad.jpg', 'not an image');
        $this->product('bad.jpg');
        $this->product('https://example.com/image.jpg');
        $this->product('../private.jpg');
        $this->mock(ImageService::class)->shouldNotReceive('upload');
        $this->artisan('catalog:migrate-images', ['--apply' => true])->assertExitCode(1);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_existing_asset_and_soft_deleted_records_are_skipped(): void
    {
        $this->image();
        $asset = app(ImageService::class)->upload(UploadedFile::fake()->image('new.jpg', 10, 10));
        $product = $this->product();
        $product->update(['media_asset_id' => $asset->id]);
        $deleted = $this->product();
        $deleted->delete();
        $this->mock(ImageService::class)->shouldNotReceive('upload');
        $this->artisan('catalog:migrate-images', ['--apply' => true])->assertExitCode(0);
        $this->assertSame($asset->id, $product->fresh()->media_asset_id);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_batch_limit_and_after_id_allow_progress(): void
    {
        $this->image();
        $first = $this->product();
        $second = $this->product();
        $this->artisan('catalog:migrate-images', ['--apply' => true, '--limit' => 1])->assertExitCode(0);
        $this->assertNotNull($first->fresh()->media_asset_id);
        $this->assertNull($second->fresh()->media_asset_id);
        $this->artisan('catalog:migrate-images', [
            '--apply' => true, '--after-id' => $first->id, '--limit' => 1,
        ])->assertExitCode(0);
        $this->assertNotNull($second->fresh()->media_asset_id);
    }

    public function test_stale_snapshot_cannot_replace_new_image(): void
    {
        $this->image();
        $product = $this->product();
        Product::whereKey($product->id)->update(['image_path' => 'replacement.jpg']);
        $this->mock(ImageService::class)->shouldNotReceive('upload');
        $this->assertFalse(app(LegacyCatalogImageService::class)->migrate($product));
        $this->assertSame('replacement.jpg', $product->fresh()->image_path);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_link_failure_rolls_back_asset_and_removes_generated_files(): void
    {
        $this->image();
        $product = $this->product();
        $dispatcher = Product::getEventDispatcher();
        Product::setEventDispatcher(clone $dispatcher);
        Product::updating(function () {
            throw new RuntimeException('Simulated link failure');
        });
        try {
            try {
                app(LegacyCatalogImageService::class)->migrate($product);
                $this->fail('Expected link failure.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Simulated link failure', $exception->getMessage());
            }
        } finally {
            Product::setEventDispatcher($dispatcher);
        }
        $this->assertNull($product->fresh()->media_asset_id);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame(['legacy/chaufa.jpg'], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_invalid_options_cannot_apply_changes(): void
    {
        $this->product();
        foreach ([['--type' => 'users'], ['--limit' => 0], ['--limit' => 501], ['--after-id' => -1]] as $options) {
            $this->artisan('catalog:migrate-images', $options + ['--apply' => true])->assertExitCode(1);
        }
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_image_dimensions_are_bounded_before_upload(): void
    {
        $file = UploadedFile::fake()->image('wide.jpg', 4097, 1);
        Storage::disk('public')->put('wide.jpg', file_get_contents($file->getRealPath()));
        $this->product('wide.jpg');
        $this->mock(ImageService::class)->shouldNotReceive('upload');
        $this->artisan('catalog:migrate-images', ['--apply' => true])->assertExitCode(1);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_type_filter_converts_only_selected_catalog_table(): void
    {
        $this->image();
        $product = $this->product();
        $combo = Combo::create([
            'name' => 'Combo', 'slug' => 'combo', 'price' => 30,
            'image_path' => 'legacy/chaufa.jpg',
        ]);
        $this->artisan('catalog:migrate-images', ['--type' => 'combos', '--apply' => true])->assertExitCode(0);
        $this->assertNotNull($combo->fresh()->media_asset_id);
        $this->assertNull($product->fresh()->media_asset_id);
    }

    public function test_oversized_file_is_rejected_before_decoding(): void
    {
        Storage::disk('public')->put('large.jpg', str_repeat('x', 5 * 1024 * 1024 + 1));
        $this->product('large.jpg');
        $this->mock(ImageService::class)->shouldNotReceive('upload');
        $this->artisan('catalog:migrate-images', ['--apply' => true])->assertExitCode(1);
        $this->assertDatabaseCount('media_assets', 0);
    }


    public function test_symlink_outside_public_disk_cannot_be_imported(): void
    {
        $file = UploadedFile::fake()->image('private.jpg', 10, 10);
        Storage::disk('local')->put('private.jpg', file_get_contents($file->getRealPath()));
        $link = Storage::disk('public')->path('outside.jpg');
        if (! function_exists('symlink') || ! @symlink(Storage::disk('local')->path('private.jpg'), $link)) {
            $this->markTestSkipped('El entorno no permite crear enlaces simbólicos.');
        }
        try {
            $this->product('outside.jpg');
            $this->mock(ImageService::class)->shouldNotReceive('upload');
            $this->artisan('catalog:migrate-images', ['--apply' => true])->assertExitCode(1);
            $this->assertDatabaseCount('media_assets', 0);
            Storage::disk('local')->assertExists('private.jpg');
        } finally {
            unlink($link);
        }
    }

}
