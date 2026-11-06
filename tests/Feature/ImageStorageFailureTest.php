<?php

namespace Tests\Feature;

use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ImageStorageFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_original_write_never_creates_asset(): void
    {
        $local = Mockery::mock();
        $public = Mockery::mock();
        $local->shouldReceive('putFileAs')->once()->andReturn(false);
        $local->shouldReceive('delete')->once()->andReturn(true);
        $public->shouldReceive('delete')->once()->with([])->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        Storage::shouldReceive('disk')->with('public')->andReturn($public);
        try {
            app(ImageService::class)->upload(UploadedFile::fake()->image('test.jpg', 10, 10));
            $this->fail('Expected failed storage write.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No se pudo guardar el original de la imagen.', $exception->getMessage());
        }
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_failed_variant_write_cleans_up_previous_variants_and_original(): void
    {
        $local = Mockery::mock();
        $public = Mockery::mock();
        $local->shouldReceive('putFileAs')->once()->andReturn('originals/test.jpg');
        $local->shouldReceive('delete')->once()->andReturn(true);
        $public->shouldReceive('put')->twice()->andReturn(true, false);
        $public->shouldReceive('delete')->once()->with(Mockery::on(fn ($paths) =>
            count($paths) === 2
            && str_starts_with($paths[0], 'catalog/thumbnails/')
            && str_starts_with($paths[1], 'catalog/cards/')
        ))->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        Storage::shouldReceive('disk')->with('public')->andReturn($public);
        try {
            app(ImageService::class)->upload(UploadedFile::fake()->image('test.jpg', 10, 10));
            $this->fail('Expected failed storage write.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No se pudo guardar una variante de la imagen.', $exception->getMessage());
        }
        $this->assertDatabaseCount('media_assets', 0);
    }
}
