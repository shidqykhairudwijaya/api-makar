<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
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
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'amount_paid' => (int) $this->amount_paid,
            'formatted_amount_paid' => 'Rp '.number_format((float) $this->amount_paid, 0, ',', '.'),
            'payment_proof_url' => $this->payment_proof_url,
            'verified_by' => $this->verified_by,
            'verifier' => new UserResource($this->whenLoaded('verifier')),
            'paid_at' => $this->paid_at?->toISOString(),
            'external_reference_id' => $this->external_reference_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
