<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'buyer_id' => $this->buyer_id,
            'courier_id' => $this->courier_id,
            'subtotal_products' => (int) $this->subtotal_products,
            'formatted_subtotal_products' => 'Rp '.number_format((float) $this->subtotal_products, 0, ',', '.'),
            'delivery_fee' => (int) $this->delivery_fee,
            'formatted_delivery_fee' => 'Rp '.number_format((float) $this->delivery_fee, 0, ',', '.'),
            'total_amount' => (int) $this->total_amount,
            'formatted_total_amount' => 'Rp '.number_format((float) $this->total_amount, 0, ',', '.'),
            'order_status' => $this->order_status?->value ?? $this->order_status,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'delivery_address_snapshot' => $this->delivery_address_snapshot,
            'notes' => $this->notes,
            'ordered_at' => $this->ordered_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'buyer' => new UserResource($this->whenLoaded('buyer')),
            'courier' => new UserResource($this->whenLoaded('courier')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
