<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleReviewService
{
    public function getBusinessReviews(): array
    {
        $apiKey = config('services.google_places.key');
        $placeId = config('services.google_places.place_id');

        if (!$apiKey || !$placeId) {
            throw new RuntimeException(
                'Google Places no está configurado.'
            );
        }

        try {
            $response = Http::timeout(12)
                ->retry(2, 500)
                ->withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' =>
                        'id,displayName,rating,userRatingCount,reviews,googleMapsUri',
                ])
                ->get(
                    'https://places.googleapis.com/v1/places/'
                    . rawurlencode($placeId),
                    ['languageCode' => 'es']
                );

            $response->throw();

            $data = $response->json();

            return [
                'place_id' => $data['id'] ?? $placeId,
                'name' => data_get($data, 'displayName.text', 'ChifaMiHogar'),
                'rating' => $data['rating'] ?? null,
                'total_ratings' => $data['userRatingCount'] ?? 0,
                'google_maps_url' => $data['googleMapsUri'] ?? null,
                'write_review_url' =>
                    'https://g.page/r/CZ2X6MInF3OfEBM/review',
                'reviews' => collect($data['reviews'] ?? [])
                    ->map(fn ($review) => [
                        'author' => data_get(
                            $review,
                            'authorAttribution.displayName'
                        ),
                        'author_url' => data_get(
                            $review,
                            'authorAttribution.uri'
                        ),
                        'rating' => $review['rating'] ?? null,
                        'comment' => data_get(
                            $review,
                            'text.text'
                        ) ?? data_get(
                            $review,
                            'originalText.text'
                        ),
                        'published_at' =>
                            $review['publishTime'] ?? null,
                        'relative_time' =>
                            $review['relativePublishTimeDescription'] ?? null,
                        'google_maps_url' =>
                            $review['googleMapsUri'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ];
        } catch (\Throwable $exception) {
            Log::error('Error consultando Google Places', [
                'message' => $exception->getMessage(),
                'place_id' => $placeId,
            ]);

            throw new RuntimeException(
                'No se pudieron consultar las reseñas de Google.',
                0,
                $exception
            );
        }
    }
}
