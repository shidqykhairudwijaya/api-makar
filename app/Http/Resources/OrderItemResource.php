<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'seller_id' => $this->seller_id,
            'product_name_snapshot' => $this->product_name_snapshot,
            'unit_snapshot' => $this->unit_snapshot,
            'price_at_purchase' => (int) $this->price_at_purchase,
            'formatted_price_at_purchase' => 'Rp '.number_format((float) $this->price_at_purchase, 0, ',', '.'),
            'quantity' => (int) $this->quantity,
            'subtotal' => (int) $this->subtotal,
            'formatted_subtotal' => 'Rp '.number_format((float) $this->subtotal, 0, ',', '.'),
            'pickup_status' => $this->pickup_status?->value ?? $this->pickup_status,
            'seller' => new UserResource($this->whenLoaded('seller')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
