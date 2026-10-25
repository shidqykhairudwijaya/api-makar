<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subtotal = $this->resource->relationLoaded('product') && $this->product
            ? (int) ($this->quantity * $this->product->price)
            : null;

        return [
            'id' => $this->id,
            'buyer_id' => $this->buyer_id,
            'product_id' => $this->product_id,
            'quantity' => (int) $this->quantity,
            'subtotal' => $subtotal,
            'formatted_subtotal' => $subtotal !== null ? 'Rp '.number_format((float) $subtotal, 0, ',', '.') : null,
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
