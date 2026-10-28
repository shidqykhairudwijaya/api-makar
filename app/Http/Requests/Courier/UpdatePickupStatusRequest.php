<?php

namespace App\Http\Requests\Courier;

use App\Enums\PickupStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePickupStatusRequest extends FormRequest
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
                Rule::in([PickupStatus::PickedUp->value, PickupStatus::OutOfStock->value]),
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
            'pickup_status.required' => 'Status penjemputan barang wajib dipilih.',
            'pickup_status.in' => 'Status penjemputan harus berupa picked_up atau out_of_stock.',
        ];
    }
}
