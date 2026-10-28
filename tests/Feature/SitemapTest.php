<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        StoreSetting::current()->update(['website_url' => 'https://chifamihogar.com']);
    }

    private function urls(string $path = '/sitemap.xml'): array
    {
        $response = $this->get($path)->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $xml = new \DOMDocument();
        $this->assertTrue($xml->loadXML($response->getContent()));
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $xml->documentElement->namespaceURI);
        return array_map(fn ($node) => $node->textContent, iterator_to_array($xml->getElementsByTagName('loc')));
    }

    public function test_empty_catalog_lists_only_public_entry_points(): void
    {
        $this->assertSame(['https://chifamihogar.com/', 'https://chifamihogar.com/combos'], $this->urls());
    }

    public function test_product_visibility_matches_the_storefront(): void
    {
        $active = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        $inactive = Category::create(['name' => 'Ocultos', 'slug' => 'ocultos', 'is_active' => false]);
        foreach ([
            ['visible', $active->id, true],
            ['unavailable', $active->id, false],
            ['inactive-category', $inactive->id, true],
            ['deleted', $active->id, true],
        ] as [$slug, $category, $available]) {
            $product = Product::create(['name' => $slug, 'slug' => $slug, 'category_id' => $category,
                'price' => 20, 'is_available' => $available]);
            if ($slug === 'deleted') {
                $product->delete();
            }
        }
        $this->assertSame([
            'https://chifamihogar.com/', 'https://chifamihogar.com/combos',
            'https://chifamihogar.com/platos/visible',
        ], $this->urls());
    }

    public function test_combo_visibility_matches_the_storefront(): void
    {
        foreach ([
            ['visible', true, true], ['unavailable', false, true],
            ['hidden', true, false], ['deleted', true, true],
        ] as [$slug, $available, $menu]) {
            $combo = Combo::create(['name' => $slug, 'slug' => $slug, 'price' => 40,
                'is_available' => $available, 'show_in_menu' => $menu]);
            if ($slug === 'deleted') {
                $combo->delete();
            }
        }
        $this->assertSame([
            'https://chifamihogar.com/', 'https://chifamihogar.com/combos',
            'https://chifamihogar.com/combos/visible',
        ], $this->urls());
    }

    public function test_request_host_and_query_cannot_inject_urls(): void
    {
        $this->assertSame(['https://chifamihogar.com/', 'https://chifamihogar.com/combos'],
            $this->urls('http://untrusted.example/sitemap.xml?tracking_token=secret&url=https://evil.example'));
    }

    public function test_unpublishing_a_combo_removes_it_on_the_next_request(): void
    {
        $combo = Combo::create(['name' => 'Combo', 'slug' => 'combo', 'price' => 40,
            'is_available' => true, 'show_in_menu' => true]);
        $this->assertContains('https://chifamihogar.com/combos/combo', $this->urls());
        $combo->update(['show_in_menu' => false]);
        $this->assertNotContains('https://chifamihogar.com/combos/combo', $this->urls());
    }
}
