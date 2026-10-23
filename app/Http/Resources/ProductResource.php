<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'seller_id' => $this->seller_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (int) $this->price,
            'formatted_price' => 'Rp '.number_format((float) $this->price, 0, ',', '.'),
            'unit' => $this->unit?->value ?? $this->unit,
            'min_order' => (int) $this->min_order,
            'stock' => (int) $this->stock,
            'image_url' => $this->image_url,
            'is_available' => (bool) $this->is_available,
            'seller' => new UserResource($this->whenLoaded('seller')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
