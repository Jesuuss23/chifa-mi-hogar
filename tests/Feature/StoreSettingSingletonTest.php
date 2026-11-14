<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingSingletonTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_keeps_the_mutex_key_when_the_sequence_has_advanced(): void
    {
        StoreSetting::forceCreate(['id' => 7, 'accepting_orders' => false]);
        $settings = StoreSetting::current();
        $this->assertSame(1, $settings->id);
        $settings->update(['kitchen_latitude' => 0, 'kitchen_longitude' => 0]);
        $this->assertSame(1, StoreSetting::current()->id);
        $this->assertSame('0.0000000', StoreSetting::current()->kitchen_latitude);
        $this->assertSame(2, StoreSetting::count());
    }
}
