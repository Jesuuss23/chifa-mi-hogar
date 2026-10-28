<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use App\Services\BrandShareImageService;
use Illuminate\Http\Response;

class BrandShareImageController extends Controller
{
    public function __invoke(BrandShareImageService $images): Response
    {
        return response($images->jpeg(StoreSetting::current()->load('logo')), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
