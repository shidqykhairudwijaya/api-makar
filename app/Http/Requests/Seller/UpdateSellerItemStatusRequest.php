<?php

namespace App\Http\Requests\Seller;

use App\Enums\PickupStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerItemStatusRequest extends FormRequest
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
            'pickup_status' => [
                'required',
                'string',
                Rule::enum(PickupStatus::class),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pickup_status.required' => 'Status komoditas wajib dipilih.',
            'pickup_status.enum' => 'Status komoditas tidak valid.',
        ];
    }
}
