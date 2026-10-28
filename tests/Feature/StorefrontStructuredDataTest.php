<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Storage::fake('public');
        StoreSetting::current()->update(['website_url' => 'https://chifamihogar.com']);
    }

    private function graph(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('~<script type="application/ld\\+json">(.*?)</script>~s', $html, $matches);
        $this->assertCount(1, $matches[1]);
        $data = json_decode($matches[1][0], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $data['@context']);
        return $data['@graph'];
    }

    public function test_home_identifies_the_site_and_catalog_without_invented_business_data(): void
    {
        $graph = $this->graph('/?utm_source=test');
        $this->assertSame(['WebSite', 'CollectionPage'], array_column($graph, '@type'));
        $this->assertSame('Chifa Mi Hogar', $graph[0]['name']);
        $this->assertSame('https://chifamihogar.com/', $graph[0]['url']);
        $this->assertSame($graph[0]['@id'], $graph[1]['isPartOf']['@id']);
        $this->assertSame('https://chifamihogar.com/', $graph[1]['url']);
        foreach (['aggregateRating', 'address', 'openingHours', 'taxID'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', json_encode($graph));
        }
    }

    public function test_product_has_canonical_breadcrumbs_and_safe_json(): void
    {
        $category = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Chaufa "</script><script>alert(1)</script>',
            'slug' => 'chaufa', 'description' => 'Sabor & tradición', 'price' => 20, 'is_available' => true,
        ]);
        $graph = $this->graph(route('storefront.show', $product).'?tracking_token=secret');
        $this->assertSame('WebPage', $graph[1]['@type']);
        $this->assertSame('https://chifamihogar.com/platos/chaufa', $graph[1]['url']);
        $items = $graph[2]['itemListElement'];
        $this->assertSame([1, 2], array_column($items, 'position'));
        $this->assertSame('https://chifamihogar.com/', $items[0]['item']);
        $this->assertSame($graph[1]['url'], $items[1]['item']);
        $this->assertSame($graph[2]['@id'], $graph[1]['breadcrumb']['@id']);
    }

    public function test_combo_links_to_the_combo_catalog_in_breadcrumbs(): void
    {
        $combo = Combo::create(['name' => 'Familiar', 'slug' => 'familiar', 'price' => 40,
            'is_available' => true, 'show_in_menu' => true]);
        $graph = $this->graph(route('combos.show', $combo));
        $this->assertSame([1, 2, 3], array_column($graph[2]['itemListElement'], 'position'));
        $this->assertSame('https://chifamihogar.com/combos', $graph[2]['itemListElement'][1]['item']);
        $list = $this->graph('/combos?page=2');
        $this->assertSame('CollectionPage', $list[1]['@type']);
        $this->assertSame('https://chifamihogar.com/combos?page=2', $list[1]['url']);
    }

    public function test_json_encoding_prevents_script_breakout_even_for_raw_values(): void
    {
        $seo = app(\App\Services\StorefrontSeoService::class)->forView('storefront.index', [
            'products' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 12),
        ]);
        $value = '</script><script>alert("x")</script>&';
        $seo['structured_data']['@graph'][0]['name'] = $value;
        $html = view('components.storefront-seo', compact('seo'))->render();
        $this->assertStringNotContainsString('<script>alert', $html);
        preg_match('~<script type="application/ld\\+json">(.*?)</script>~s', $html, $match);
        $this->assertSame($value, json_decode($match[1], true, 512, JSON_THROW_ON_ERROR)['@graph'][0]['name']);
    }

    public function test_private_pages_do_not_receive_public_structured_data(): void
    {
        foreach (['cart.index', 'login', 'customer.register'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('application/ld+json', false);
        }
    }
}
