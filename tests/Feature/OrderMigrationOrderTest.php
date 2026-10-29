<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderMigrationOrderTest extends TestCase
{
    private string $originalConnection;
    private ?string $testSchema = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        // Never use the application's database, including when running the full suite.
        $url = getenv('ORDER_MIGRATION_TEST_PGSQL_URL');
        if ($url && ! in_array(parse_url($url, PHP_URL_SCHEME), ['pgsql', 'postgres', 'postgresql'], true)) {
            throw new \InvalidArgumentException('ORDER_MIGRATION_TEST_PGSQL_URL must be a PostgreSQL URL.');
        }

        $connection = $url ? [
            'driver' => 'pgsql',
            'url' => $url,
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ] : [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
        config(['database.connections.order_migration_test' => $connection]);
        DB::purge('order_migration_test');

        if ($url) {
            $this->testSchema = 'order_migration_test_'.bin2hex(random_bytes(8));
            DB::connection('order_migration_test')->statement('CREATE SCHEMA "'.$this->testSchema.'"');
            // Set on this connection only; exclude public from table lookup.
            DB::connection('order_migration_test')->statement('SET search_path TO "'.$this->testSchema.'"');
        }

        DB::setDefaultConnection('order_migration_test');
        Schema::swap(DB::connection('order_migration_test')->getSchemaBuilder());

        // Minimal prerequisites; the real order migrations are loaded unchanged.
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('media_assets', fn (Blueprint $table) => $table->id());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testSchema !== null) {
                // Only the randomly generated schema owned by this test.
                DB::connection('order_migration_test')->statement('DROP SCHEMA "'.$this->testSchema.'" CASCADE');
            }
        } finally {
            if (isset($this->originalConnection)) {
                DB::setDefaultConnection($this->originalConnection);
            }
            DB::purge('order_migration_test');
            Schema::clearResolvedInstance('db.schema');
            $this->app->forgetInstance('db.schema');
            parent::tearDown();
        }
    }

    public function test_items_can_be_created_before_orders_and_the_final_fk_cascades(): void
    {
        $this->itemsMigration()->up();

        $this->assertFalse(Schema::hasTable('orders'));
        $this->assertSame([], Schema::getForeignKeys('order_items'));

        $this->ordersMigration()->up();
        $foreignKeys = Schema::getForeignKeys('order_items');
        $this->assertCount(1, $foreignKeys);
        $this->assertSame(['order_id'], $foreignKeys[0]['columns']);
        $this->assertSame('orders', $foreignKeys[0]['foreign_table']);
        $this->assertSame('cascade', strtolower($foreignKeys[0]['on_delete']));

        [$orderId, $itemId] = $this->insertOrderWithItem();
        DB::table('orders')->where('id', $orderId)->delete();
        $this->assertFalse(DB::table('order_items')->where('id', $itemId)->exists());
    }

    public function test_final_schema_rejects_an_item_without_an_order(): void
    {
        $this->itemsMigration()->up();
        $this->ordersMigration()->up();

        $this->expectException(QueryException::class);
        DB::table('order_items')->insert($this->itemAttributes(999999));
    }

    public function test_reverse_order_works_with_rows_and_can_be_migrated_again(): void
    {
        $this->itemsMigration()->up();
        $this->ordersMigration()->up();
        $this->insertOrderWithItem();

        // Laravel reverses the filenames: orders before order_items.
        $this->ordersMigration()->down();
        $this->assertFalse(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('order_items'));
        $this->assertSame([], Schema::getForeignKeys('order_items'));
        $this->itemsMigration()->down();
        $this->assertFalse(Schema::hasTable('order_items'));

        $this->itemsMigration()->up();
        $this->ordersMigration()->up();
        $this->assertCount(1, Schema::getForeignKeys('order_items'));
    }

    public function test_rollback_also_handles_the_legacy_inline_foreign_key(): void
    {
        $this->itemsMigration()->up();
        $this->ordersMigration()->up();
        $this->itemsMigration()->down();

        // Reproduce the original inline FK definition from an installed database.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
        });

        $this->ordersMigration()->down();
        $this->itemsMigration()->down();
        $this->assertFalse(Schema::hasTable('orders'));
        $this->assertFalse(Schema::hasTable('order_items'));
    }

    public function test_recorded_migrations_are_not_replayed_and_keep_existing_rows(): void
    {
        $this->itemsMigration()->up();
        $this->ordersMigration()->up();
        [$orderId, $itemId] = $this->insertOrderWithItem();

        $repository = $this->app['migration.repository'];
        $repository->createRepository();
        $paths = $this->migrationPaths();
        foreach ($paths as $path) {
            $repository->log(basename($path, '.php'), 1);
        }

        $this->app['migrator']->run($paths);
        $this->assertCount(2, $repository->getRan());
        $this->assertTrue(DB::table('orders')->where('id', $orderId)->exists());
        $this->assertTrue(DB::table('order_items')->where('id', $itemId)->exists());
        $this->assertCount(1, Schema::getForeignKeys('order_items'));
    }

    private function migrationPaths(): array
    {
        $paths = [
            database_path('migrations/2026_10_24_085832_create_order_items_table.php'),
            database_path('migrations/2026_10_24_085832_create_orders_table.php'),
        ];
        sort($paths);

        return $paths;
    }

    private function itemsMigration(): \Illuminate\Database\Migrations\Migration
    {
        return require $this->migrationPaths()[0];
    }

    private function ordersMigration(): \Illuminate\Database\Migrations\Migration
    {
        return require $this->migrationPaths()[1];
    }

    private function insertOrderWithItem(): array
    {
        $orderId = DB::table('orders')->insertGetId([
            'code' => 'MIGRATION-TEST',
            'customer_name' => 'Cliente',
            'customer_phone' => '999999999',
            'subtotal' => 20,
            'total' => 20,
            'payment_method' => 'cash',
        ]);
        $itemId = DB::table('order_items')->insertGetId($this->itemAttributes($orderId));

        return [$orderId, $itemId];
    }

    private function itemAttributes(int $orderId): array
    {
        return [
            'order_id' => $orderId,
            'item_type' => 'product',
            'item_name' => 'Chaufa',
            'quantity' => 1,
            'unit_price' => 20,
            'line_total' => 20,
        ];
    }
}
