<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Throwable;

class GoogleReviewController extends Controller
{
    public function index(
        GoogleReviewService $googleReviews
    ): View {
        $business = null;
        $error = null;

        try {
            $business = $googleReviews->getBusinessReviews();
        } catch (Throwable $exception) {
            report($exception);

            $error = 'No se pudieron cargar las reseñas de Google.';
        }

        return view('admin.reviews.google', compact(
            'business',
            'error'
        ));
    }

    public function data(
        GoogleReviewService $googleReviews
    ): JsonResponse {
        try {
            return response()->json([
                'success' => true,
                'data' => $googleReviews->getBusinessReviews(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar Google.',
            ], 502);
        }
    }
}