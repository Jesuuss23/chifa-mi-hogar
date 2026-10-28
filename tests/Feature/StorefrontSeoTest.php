<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Storage::fake('public');
        Cache::flush();
        Http::preventStrayRequests();
        StoreSetting::current()->update(['website_url' => 'https://chifamihogar.com']);
    }

    private function head(TestResponse $response): \DOMXPath
    {
        $response->assertOk();
        preg_match('/<head>(.*?)<\/head>/s', $response->getContent(), $match);
        $this->assertArrayHasKey(1, $match);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><html><head>'.$match[1].'</head></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($document);
    }

    private function meta(\DOMXPath $head, string $attribute, string $name): string
    {
        $nodes = $head->query('//meta[@'.$attribute.'="'.$name.'"]');
        $this->assertCount(1, $nodes, 'Debe haber una sola etiqueta '.$name);
        return $nodes->item(0)->getAttribute('content');
    }

    private function canonical(\DOMXPath $head): string
    {
        $nodes = $head->query('//link[@rel="canonical"]');
        $this->assertCount(1, $nodes);
        $this->assertCount(1, $head->query('//title'));
        return $nodes->item(0)->getAttribute('href');
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        return Product::create(array_replace([
            'category_id' => $category->id, 'name' => 'Chaufa especial', 'slug' => 'chaufa-especial',
            'description' => 'Chaufa preparado al momento.', 'price' => 20, 'is_available' => true,
        ], $overrides));
    }

    private function media(string $name = 'plate'): MediaAsset
    {
        $path = 'catalog/details/'.$name.'.webp';
        Storage::disk('public')->put($path, 'test fixture');
        return MediaAsset::create([
            'original_path' => 'originals/'.$name.'.jpg',
            'thumbnail_path' => $path, 'card_path' => $path, 'detail_path' => $path,
            'original_name' => $name.'.jpg', 'mime_type' => 'image/jpeg',
            'original_size' => 100, 'width' => 100, 'height' => 100,
        ]);
    }

    public function test_home_has_one_complete_set_of_public_metadata(): void
    {
        $head = $this->head($this->get('/?utm_source=test&tracking_token=private'));
        $this->assertSame('https://chifamihogar.com/', $this->canonical($head));
        $this->assertSame('https://chifamihogar.com/', $this->meta($head, 'property', 'og:url'));
        $this->assertStringContainsString('Dark Kitchen', $this->meta($head, 'property', 'og:title'));
        $this->assertSame($this->meta($head, 'name', 'description'), $this->meta($head, 'property', 'og:description'));
        $this->assertSame('es_PE', $this->meta($head, 'property', 'og:locale'));
        $this->assertSame('summary_large_image', $this->meta($head, 'name', 'twitter:card'));
        $this->assertStringStartsWith('https://chifamihogar.com/api/brand/share-image.jpg?v=', $this->meta($head, 'property', 'og:image'));
        Http::assertNothingSent();
    }

    public function test_category_and_pagination_are_retained_but_tracking_parameters_are_removed(): void
    {
        $this->product();
        $head = $this->head($this->get('/?categoria=arroces&page=2&utm_campaign=ignored'));
        $this->assertSame('https://chifamihogar.com/?categoria=arroces&page=2', $this->canonical($head));
        $this->assertStringContainsString('Arroces', $this->meta($head, 'property', 'og:title'));
        $this->assertStringContainsString('Página 2', $this->meta($head, 'property', 'og:title'));
    }

    public function test_product_uses_own_image_description_and_slug(): void
    {
        $asset = $this->media();
        $product = $this->product(['media_asset_id' => $asset->id]);
        $head = $this->head($this->get(route('storefront.show', $product).'?utm_source=test'));
        $this->assertSame('https://chifamihogar.com/platos/chaufa-especial', $this->canonical($head));
        $this->assertSame('https://chifamihogar.com/storage/catalog/details/plate.webp', $this->meta($head, 'property', 'og:image'));
        $this->assertSame('Chaufa preparado al momento.', $this->meta($head, 'name', 'description'));
        $this->assertSame('Chaufa especial', $this->meta($head, 'property', 'og:image:alt'));
    }

    public function test_missing_product_file_uses_brand_image(): void
    {
        $asset = $this->media();
        Storage::disk('public')->delete($asset->detail_path);
        $product = $this->product(['media_asset_id' => $asset->id]);
        $head = $this->head($this->get(route('storefront.show', $product)));
        $this->assertStringContainsString('/api/brand/share-image.jpg?v=', $this->meta($head, 'property', 'og:image'));
    }

    public function test_combo_list_and_detail_have_distinct_metadata(): void
    {
        $combo = Combo::create([
            'name' => 'Combo familiar', 'slug' => 'combo-familiar', 'description' => 'Un combo para compartir.',
            'price' => 40, 'is_available' => true, 'show_in_menu' => true,
        ]);
        $list = $this->head($this->get('/combos?page=2&utm_source=test'));
        $this->assertSame('https://chifamihogar.com/combos?page=2', $this->canonical($list));
        $detail = $this->head($this->get(route('combos.show', $combo)));
        $this->assertSame('https://chifamihogar.com/combos/combo-familiar', $this->canonical($detail));
        $this->assertSame('Combo familiar | Chifa Mi Hogar', $this->meta($detail, 'property', 'og:title'));
        $this->assertSame('Un combo para compartir.', $this->meta($detail, 'property', 'og:description'));
    }

    public function test_metadata_escapes_markup_and_attribute_injection(): void
    {
        $product = $this->product([
            'name' => 'Chaufa " onload="alert(1)',
            'description' => '<b>Sabroso</b> &quot;><script>alert(1)</script>',
        ]);
        $head = $this->head($this->get(route('storefront.show', $product)));
        $this->meta($head, 'property', 'og:title');
        $this->meta($head, 'property', 'og:description');
        $this->assertCount(0, $head->query('//script'));
        $this->assertCount(0, $head->query('//*[@onload]'));
    }

    public function test_request_host_cannot_change_canonical_or_image_origin(): void
    {
        $head = $this->head($this->get('http://untrusted.example/'));
        $this->assertSame('https://chifamihogar.com/', $this->canonical($head));
        $this->assertStringStartsWith('https://chifamihogar.com/', $this->meta($head, 'property', 'og:image'));
    }

    public static function privateRoutes(): array
    {
        return [
            'cart' => ['cart.index'],
            'login' => ['login'],
            'registration' => ['customer.register'],
        ];
    }

    #[DataProvider('privateRoutes')]
    public function test_transactional_pages_do_not_get_catalog_metadata(string $name): void
    {
        $head = $this->head($this->get(route($name)));
        $this->assertCount(0, $head->query('//meta[starts-with(@property, "og:")]'));
        $this->assertCount(0, $head->query('//link[@rel="canonical"]'));
    }

    public function test_tracking_keeps_noindex_without_social_metadata(): void
    {
        $order = Order::create([
            'code' => 'CHF-'.Str::ulid(), 'tracking_token' => (string) Str::uuid(),
            'customer_name' => 'Cliente privado', 'customer_phone' => '999999999',
            'delivery_type' => 'delivery', 'subtotal' => 20, 'total' => 20,
            'payment_method' => 'cash', 'status' => 'pending',
        ]);
        $response = $this->get(route('orders.tracking.show', $order->tracking_token));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $head = $this->head($response);
        $this->assertSame('noindex, nofollow', $this->meta($head, 'name', 'robots'));
        $this->assertCount(0, $head->query('//meta[starts-with(@property, "og:")]'));
    }

    public function test_admin_preview_does_not_get_public_social_metadata(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $head = $this->head($this->get(route('admin.legal.preview', 'privacy')));
        $this->assertCount(0, $head->query('//meta[starts-with(@property, "og:")]'));
        $this->assertCount(0, $head->query('//link[@rel="canonical"]'));
    }
}
