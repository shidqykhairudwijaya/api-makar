<?php

namespace App\Http\Requests\Order;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_fee' => ['nullable', 'integer', 'min:0'],
            'delivery_address' => ['nullable', 'array'],
            'delivery_address.recipient_name' => ['required_with:delivery_address', 'string', 'max:255'],
            'delivery_address.phone' => ['required_with:delivery_address', 'string', 'max:20'],
            'delivery_address.store_name' => ['nullable', 'string', 'max:255'],
            'delivery_address.full_address' => ['required_with:delivery_address', 'string', 'max:1000'],
            'delivery_address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
