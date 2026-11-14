<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Extra;
use App\Models\Combo;
use App\Models\Product;
use App\Models\QuickSale;
use App\Services\QuickSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuickSaleController extends Controller
{
    public function create()
    {
        $catalog = collect(['product' => Product::class, 'combo' => Combo::class, 'extra' => Extra::class])->flatMap(
            fn ($class, $type) => $class::orderBy('name')->get(['id', 'name', 'price'])->map(fn ($item) => [
                'type' => $type, 'id' => $item->id, 'name' => $item->name,
                'price_cents' => QuickSaleService::cents((string) $item->price),
            ])
        )->values();
        return view('admin.quick-sales.create', compact('catalog'));
    }

    public function store(Request $request, QuickSaleService $service)
    {
        $data = $request->validate([
            'submission_key' => ['required', 'uuid'],
            'sold_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:'.now('America/Lima')->toDateString()],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['required', Rule::in(['cash', 'yape'])],
            'total' => ['nullable', 'regex:/\A\d{1,5}(?:\.\d{1,2})?\z/'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.type' => ['required', Rule::in(['product', 'combo', 'extra'])],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'between:1,99'],
        ]);
        $sale = $service->save($request->user(), $data);
        return redirect()->route('admin.quick-sales.show', $sale)->with('success', 'Venta guardada.');
    }

    public function show(QuickSale $quickSale)
    {
        $quickSale->load('creator');
        return view('admin.quick-sales.show', ['sale' => $quickSale]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'period' => ['sometimes', Rule::in(['day', 'month', 'year', 'custom'])],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'payment_method' => ['nullable', Rule::in(['cash', 'yape'])],
            'search' => ['nullable', 'string', 'max:120'],
            'group' => ['nullable', Rule::in(['day', 'month', 'year'])],
        ]);
        $period = $data['period'] ?? 'day';
        $date = Carbon::parse($data['date'] ?? now('America/Lima')->toDateString(), 'America/Lima');
        [$from, $to] = match ($period) {
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            'year' => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
            'custom' => [Carbon::parse($data['from'], 'America/Lima'), Carbon::parse($data['to'], 'America/Lima')],
            default => [$date->copy(), $date->copy()],
        };
        if ($from->diffInDays($to) > 3660) {
            throw ValidationException::withMessages(['to' => 'Selecciona un rango de hasta diez años.']);
        }
        $query = QuickSale::whereBetween('sold_on', [$from->toDateString(), $to->toDateString()]);
        if (! empty($data['payment_method'])) {
            $query->where('payment_method', $data['payment_method']);
        }
        if (! empty($data['search'])) {
            $query->whereLike('customer_name', '%'.$data['search'].'%');
        }
        $totals = (clone $query)->selectRaw('payment_method, count(*) as sale_count, sum(total_cents) as amount')->groupBy('payment_method')->get();
        $group = $data['group'] ?? ($period === 'year' ? 'month' : 'day');
        // Fixed expressions only: request data never becomes SQL. PostgreSQL and SQLite both support this form.
        $length = ['day' => 10, 'month' => 7, 'year' => 4][$group];
        $expression = "SUBSTR(CAST(sold_on AS VARCHAR), 1, {$length})";
        $summary = (clone $query)->selectRaw("{$expression} as period_label, count(*) as sale_count, sum(total_cents) as amount")
            ->groupByRaw($expression)->orderBy('period_label', 'desc')->paginate(24, ['*'], 'summary_page')->withQueryString();
        $sales = (clone $query)->orderByDesc('sold_on')->orderByDesc('id')->paginate(20)->withQueryString();
        return view('admin.quick-sales.index', compact('sales', 'totals', 'summary', 'from', 'to', 'date', 'period', 'group'));
    }
}
