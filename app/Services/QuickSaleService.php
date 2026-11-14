<?php

namespace App\Services;

use App\Models\Extra;
use App\Models\Combo;
use App\Models\Product;
use App\Models\QuickSale;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuickSaleService
{
    public static function cents(string $value): int
    {
        if (! preg_match('/\A\d{1,5}(?:\.\d{1,2})?\z/', $value)) {
            throw ValidationException::withMessages(['total' => 'Introduce un importe entre S/0 y S/99,999.99, con hasta dos decimales.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function save(User $admin, array $data): QuickSale
    {
        abort_unless($admin->isAdmin(), 403);
        $payload = [
            'sold_on' => $data['sold_on'],
            'customer_name' => trim($data['customer_name'] ?? '') ?: null,
            'payment_method' => $data['payment_method'],
            'total' => isset($data['total']) ? self::cents((string) $data['total']) : null,
            'items' => array_map(fn ($row) => [
                'type' => $row['type'], 'id' => (int) $row['id'], 'quantity' => (int) $row['quantity'],
            ], array_values($data['items'])),
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($admin, $data, $payload, $hash) {
            // Serialize retries and share the established business mutex. The unique key is the final guard.
            $settings = StoreSetting::current();
            StoreSetting::whereKey($settings->id)->lockForUpdate()->firstOrFail();
            $existing = QuickSale::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless((int) $existing->created_by === (int) $admin->id && hash_equals($existing->payload_hash, $hash),
                    409, 'Este formulario ya se guardó con otros datos. Revisa el registro antes de crear otra venta.');
                return $existing;
            }
            $items = [];
            $subtotal = 0;
            foreach ($payload['items'] as $index => $row) {
                $model = match ($row['type']) {
                    'product' => Product::find($row['id']),
                    'combo' => Combo::find($row['id']),
                    'extra' => Extra::find($row['id']),
                    default => null,
                };
                if (! $model) {
                    throw ValidationException::withMessages(["items.{$index}.id" => 'Un plato, combo o adicional ya no existe. Revisa la selección.']);
                }
                $unit = self::cents((string) $model->price);
                $line = $unit * $row['quantity'];
                $subtotal += $line;
                if ($subtotal > 9999999) {
                    throw ValidationException::withMessages(['items' => 'El importe de la venta supera S/99,999.99.']);
                }
                $items[] = [...$row, 'name' => $model->name, 'unit_price_cents' => $unit, 'line_total_cents' => $line];
            }
            return QuickSale::create([
                'submission_key' => $data['submission_key'], 'payload_hash' => $hash,
                'created_by' => $admin->id, 'sold_on' => $payload['sold_on'],
                'customer_name' => $payload['customer_name'], 'payment_method' => $payload['payment_method'],
                'items' => $items, 'catalog_total_cents' => $subtotal,
                'total_cents' => $payload['total'] ?? $subtotal,
            ]);
        }, 3);
    }
}
