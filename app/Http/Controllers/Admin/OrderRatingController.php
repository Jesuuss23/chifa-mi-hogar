<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderRating;
use Illuminate\View\View;

class OrderRatingController extends Controller
{
    public function index(): View
    {
        $total = OrderRating::count();

        $average = round((float) OrderRating::avg('rating'), 2);

        $customers = OrderRating::distinct()
            ->count('user_id');

        $distribution = OrderRating::query()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $ratings = OrderRating::with(['user', 'order'])
            ->latest()
            ->paginate(15);

        return view('admin.order-ratings.index', compact(
            'total',
            'average',
            'customers',
            'distribution',
            'ratings'
        ));
    }
}