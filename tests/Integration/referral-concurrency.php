<?php

// Opt-in integration check. Creates fixtures only in a NEW, EMPTY PostgreSQL database
// whose name ends in "_referral_concurrency_test". Never uses migrate:fresh.
use App\Models\{Category, Order, Product, ReferralCampaign, ReferralCoupon, StoreSetting, User};
use App\Services\{OrderService, ReferralService};
use Illuminate\Support\Facades\{Artisan, Bus, DB, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!app()->environment('testing') || DB::getDriverName() !== 'pgsql'
    || !str_ends_with(DB::connection()->getDatabaseName(), '_referral_concurrency_test')) {
    throw new RuntimeException('Requires APP_ENV=testing and a dedicated *_referral_concurrency_test PostgreSQL database.');
}
config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'session.driver' => 'array', 'cache.default' => 'array']);
Bus::fake();
DB::statement("SET lock_timeout = '15s'");

if (($argv[1] ?? '') === 'worker') {
    $mode = $argv[2];
    $data = json_decode(base64_decode($argv[3]), true, flags: JSON_THROW_ON_ERROR);
    auth('web')->loginUsingId($data['user_id']);
    echo "READY\n";
    flush();
    try {
        if ($mode === 'checkout') {
            $id = app(OrderService::class)->create($data['checkout'])->id;
        } else {
            $id = DB::transaction(function () use ($data, $mode) {
                $order = Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
                if ($mode === 'verify') {
                    $order->update(['payment_status' => 'verified', 'payment_verified_by' => $data['user_id'], 'payment_verified_at' => now()]);
                } elseif ($mode === 'cancel' && $order->payment_status !== 'verified') {
                    $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                }
                return $order->id;
            }, 3);
        }
        echo json_encode(['result' => 'ok', 'order_id' => $id], JSON_THROW_ON_ERROR);
    } catch (ValidationException $e) {
        echo json_encode(['result' => 'rejected', 'fields' => array_keys($e->errors())], JSON_THROW_ON_ERROR);
    }
    exit;
}

if (Schema::hasTable('migrations')) {
    throw new RuntimeException('Use a new empty test database. Existing tables will not be erased.');
}
Artisan::call('migrate', ['--force' => true]);

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function race(string $mode, array $payloads, ?int $orderId = null): array
{
    $processes = [];
    DB::beginTransaction();
    try {
        if ($orderId) Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
        else StoreSetting::whereKey(1)->lockForUpdate()->firstOrFail();
        foreach ($payloads as $payload) {
            $process = new Process([PHP_BINARY, __FILE__, 'worker', $mode, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))], base_path());
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 15;
        do {
            $ready = 0;
            foreach ($processes as $process) {
                if (str_contains($process->getOutput(), 'READY')) $ready++;
                if (!$process->isRunning() && !$process->isSuccessful()) throw new RuntimeException($process->getErrorOutput());
            }
            if ($ready === count($processes)) break;
            if (microtime(true) >= $deadline) throw new RuntimeException('Workers did not reach the lock barrier.');
            usleep(10000);
        } while (true);
        DB::commit();
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            check($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode(explode("READY\n", $process->getOutput(), 2)[1], true, flags: JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally {
        if (DB::transactionLevel()) DB::rollBack();
        foreach ($processes as $process) if ($process->isRunning()) $process->stop();
    }
}

StoreSetting::current()->update(['accepting_orders' => true, 'kitchen_latitude' => 0, 'kitchen_longitude' => 0,
    'free_delivery_km' => 0, 'additional_cost_per_km' => 2]);
$admin = User::factory()->create(['role' => 'admin']);
$owner = User::factory()->create(['role' => 'customer']);
$customer = User::factory()->create(['role' => 'customer']);
ReferralCampaign::create(['creation_key' => (string) Str::uuid(), 'name' => 'Concurrency test', 'status' => 'active',
    'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'created_by' => $admin->id,
    'guest_percent' => 10, 'inviter_percent' => 5, 'minimum_cents' => 1000,
    'maximum_discount_cents' => 500, 'coupon_days' => 30]);
app(ReferralService::class)->apply($customer, app(ReferralService::class)->codeFor($owner));
$category = Category::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
$product = Product::create(['category_id' => $category->id, 'name' => 'Chaufa', 'slug' => 'test', 'price' => 20, 'is_available' => true]);
$checkout = ['checkout_token' => (string) Str::uuid(), 'customer_name' => 'Test', 'customer_phone' => '999999999',
    'delivery_type' => 'delivery', 'delivery_address' => 'Test', 'delivery_latitude' => 0, 'delivery_longitude' => 0.01,
    'payment_method' => 'yape', 'items' => [['type' => 'product', 'id' => $product->id, 'quantity' => 1]]];

$results = race('checkout', array_fill(0, 4, ['user_id' => $customer->id, 'checkout' => $checkout]));
check(count(array_unique(array_column($results, 'order_id'))) === 1 && Order::count() === 1, 'Duplicate checkout');
$order = Order::firstOrFail();
check($order->discount_amount === '2.00', 'Incorrect invitation discount');
echo "PASS: four simultaneous retries create one discounted order.\n";

race('verify', array_fill(0, 4, ['user_id' => $admin->id, 'order_id' => $order->id]), $order->id);
check(ReferralCoupon::count() === 1, 'Duplicate reward');
echo "PASS: four simultaneous payment verifications create one coupon.\n";

$coupon = ReferralCoupon::firstOrFail();
$payloads = [];
for ($i = 0; $i < 4; $i++) {
    $payloads[] = ['user_id' => $owner->id, 'checkout' => array_replace($checkout,
        ['checkout_token' => (string) Str::uuid(), 'referral_coupon_id' => $coupon->id])];
}
$results = race('checkout', $payloads);
check(count(array_filter($results, fn ($r) => $r['result'] === 'ok')) === 1 && Order::count() === 2, 'Coupon reused concurrently');
check(count(array_filter($results, fn ($r) => $r['result'] === 'rejected')) === 3, 'Other redemptions must be rejected');
echo "PASS: four different checkouts redeem a coupon only once.\n";

$redeemed = Order::findOrFail($coupon->refresh()->reserved_order_id);
race('cancel', array_fill(0, 4, ['user_id' => $admin->id, 'order_id' => $redeemed->id]), $redeemed->id);
check($coupon->refresh()->reserved_order_id === null && $coupon->used_at === null, 'Cancellation did not release coupon');
check(DB::table('referral_order_discounts')->where('order_id', $redeemed->id)->whereNotNull('released_at')->count() === 1, 'Release audit missing');
echo "PASS: four simultaneous cancellations release one reservation.\n";

