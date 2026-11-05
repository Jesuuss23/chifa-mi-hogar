<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSetting extends Model
{
    protected $fillable = [
        'legal_address',
        'privacy_hosting',
        'privacy_retention',
        'privacy_database',
        'legal_pages_published',
        'business_owner',
        'privacy_email',
        'website_url',
        'business_ruc',
        'logo_media_asset_id',
        'yape_number',
        'plin_number',
        'payment_holder',
        'payment_qr_media_asset_id',
        'orders_whatsapp',
        'free_delivery_km',
        'additional_cost_per_km',
        'kitchen_latitude',
        'kitchen_longitude',
        'accepting_orders',
        'closed_message',
        'whatsapp_group_url',
        'google_reviews_url',
    ];

    protected function casts(): array
    {
        return [
            'free_delivery_km' => 'decimal:2',
            'additional_cost_per_km' => 'decimal:2',
            'kitchen_latitude' => 'decimal:7',
            'kitchen_longitude' => 'decimal:7',
            'accepting_orders' => 'boolean',
            'legal_pages_published' => 'boolean',
        ];
    }

    public function closedMessage(): string
    {
        return trim((string) $this->closed_message)
            ?: 'Por el momento no estamos recibiendo pedidos.';
    }

    public function googleReviewsUrl(): ?string
    {
        $url = trim((string) $this->google_reviews_url);
        $parts = parse_url($url);

        // Validar también registros antiguos o editados fuera del formulario.
        if (strlen($url) > 500 || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    public function paymentQr(): BelongsTo
    {
        return $this->belongsTo(
            MediaAsset::class,
            'payment_qr_media_asset_id'
        );
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_asset_id');
    }

    public function logoUrl(): ?string
    {
        if ($this->logo) {
            return $this->logo->thumbnailUrl();
        }

        return is_file(public_path('images/brand/logo.jpeg'))
            ? asset('images/brand/logo.jpeg')
            : null;
    }

    public function missingLegalFields(): array
    {
        $fields = [
            'business_owner' => 'Titular del negocio',
            'privacy_email' => 'Correo de privacidad y contacto',
            'website_url' => 'Sitio web oficial',
            'legal_address' => 'Domicilio del responsable',
            'privacy_hosting' => 'Alojamiento y otros encargados',
            'privacy_retention' => 'Conservación y eliminación',
            'privacy_database' => 'Bancos de datos personales',
        ];

        return array_filter($fields, fn ($label, $field) => ! filled($this->$field), ARRAY_FILTER_USE_BOTH);
    }

    public function legalPagesAvailable(): bool
    {
        return (bool) $this->legal_pages_published && $this->missingLegalFields() === [];
    }

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'business_owner' => 'Rossi Rivadeneyra Jara',
                'privacy_email' => 'chifamihogar@gmail.com',
                'website_url' => 'https://chifamihogar.com',
                'free_delivery_km' => 3,
                'additional_cost_per_km' => 2,
                'accepting_orders' => true,
                'closed_message' =>
                    'Por el momento no estamos recibiendo pedidos.',
            ]
        );
    }
}