<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandShareImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Cache::flush();
        Http::preventStrayRequests();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    private function logo(int $red, int $blue): MediaAsset
    {
        $image = imagecreatetruecolor(200, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, 0, $blue));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        $path = 'catalog/details/logo-test.png';
        Storage::disk('public')->put($path, $bytes);
        return MediaAsset::firstOrCreate(['detail_path' => $path], [
            'original_path' => 'originals/logo-test.png',
            'thumbnail_path' => $path, 'card_path' => $path,
            'original_name' => 'logo.png', 'mime_type' => 'image/png',
            'original_size' => strlen($bytes), 'width' => 200, 'height' => 120,
        ]);
    }

    public function test_brand_image_is_a_public_jpeg_without_session_cookies(): void
    {
        $response = $this->get(route('brand.share-image'));
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertEmpty($response->headers->getCookies());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $size = getimagesizefromstring($response->getContent());
        $this->assertSame(1200, $size[0]);
        $this->assertSame(630, $size[1]);
        $this->assertSame('image/jpeg', $size['mime']);
        $this->assertLessThan(300 * 1024, strlen($response->getContent()));
        Http::assertNothingSent();
    }

    public function test_configured_logo_is_drawn_and_changes_refresh_the_image_url(): void
    {
        $asset = $this->logo(0, 255);
        StoreSetting::current()->update(['logo_media_asset_id' => $asset->id]);
        $firstPage = $this->get('/')->assertOk()->getContent();
        preg_match('/property="og:image" content="([^"]+)"/', $firstPage, $firstUrl);
        $this->assertArrayHasKey(1, $firstUrl);

        $first = $this->get(route('brand.share-image'))->assertOk()->getContent();
        $decoded = imagecreatefromstring($first);
        $color = imagecolorsforindex($decoded, imagecolorat($decoded, 600, 240));
        imagedestroy($decoded);
        $this->assertGreaterThan(200, $color['blue']);
        $this->assertLessThan(50, $color['red']);

        $this->logo(255, 0);
        $secondPage = $this->get('/')->assertOk()->getContent();
        preg_match('/property="og:image" content="([^"]+)"/', $secondPage, $secondUrl);
        $this->assertNotSame($firstUrl[1], $secondUrl[1]);
        $second = $this->get(route('brand.share-image'))->assertOk()->getContent();
        $this->assertNotSame($first, $second);
    }

    public function test_corrupt_logo_falls_back_to_a_valid_brand_card(): void
    {
        $asset = $this->logo(0, 255);
        StoreSetting::current()->update(['logo_media_asset_id' => $asset->id]);
        Storage::disk('public')->put($asset->detail_path, 'invalid image');
        $response = $this->get(route('brand.share-image'))->assertOk();
        $this->assertSame('image/jpeg', getimagesizefromstring($response->getContent())['mime']);
    }

    public function test_visitor_cannot_select_a_file_or_remote_image(): void
    {
        $first = $this->get(route('brand.share-image'))->assertOk()->getContent();
        $second = $this->get(route('brand.share-image').'?file=../../.env&url=https://untrusted.example/x&v=anything')
            ->assertOk()->getContent();
        $this->assertSame($first, $second);
        Http::assertNothingSent();
    }
}
