<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMobileOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id, 'code' => $this->code,
            'status' => $this->status, 'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'customer_name' => $this->customer_name,
            'delivery_type' => $this->delivery_type,
            'currency' => $this->currency,
            'total' => $this->total,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
        if ($this->resource->relationLoaded('items')) {
            $data += [
                'customer' => [
                    'name' => $this->customer_name, 'phone' => $this->customer_phone,
                    'email' => $this->customer_email,
                ],
                'delivery' => [
                    'address' => $this->address, 'reference' => $this->reference,
                    'latitude' => $this->latitude, 'longitude' => $this->longitude,
                    'driver' => $this->deliveryUser
                        ? ['id' => $this->deliveryUser->id, 'name' => $this->deliveryUser->name] : null,
                ],
                'subtotal' => $this->subtotal, 'delivery_fee' => $this->delivery_fee,
                'discount_amount' => $this->discount_amount,
                'customer_notes' => $this->customer_notes,
                'has_payment_receipt' => $this->payment_receipt_media_asset_id !== null,
                'items' => $this->items->map(fn ($item) => [
                    'id' => $item->id, 'type' => $item->item_type,
                    'name' => $item->item_name, 'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price, 'extras_unit_total' => $item->extras_unit_total,
                    'line_total' => $item->line_total, 'notes' => $item->notes,
                    'extras' => $item->extras->map(fn ($extra) => [
                        'name' => $extra->extra_name, 'quantity' => $extra->quantity,
                        'unit_price' => $extra->unit_price, 'line_total' => $extra->line_total,
                    ])->values(),
                    'combo_components' => $item->comboComponents->map(fn ($component) => [
                        'type' => $component->component_type, 'name' => $component->component_name,
                        'included_quantity' => $component->included_quantity,
                        'selected_quantity' => $component->selected_quantity,
                        'additional_quantity' => $component->additional_quantity,
                        'unit_price' => $component->unit_price,
                        'additional_total' => $component->additional_total,
                    ])->values(),
                ])->values(),
            ];
        }
        return $data;
    }
}
