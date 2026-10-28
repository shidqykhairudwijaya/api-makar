<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPaymentRequest extends FormRequest
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
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'payment_proof' => [
                Rule::requiredIf(fn () => $this->input('payment_method') === PaymentMethod::ManualTransfer->value),
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
            'external_reference_id' => ['nullable', 'string', 'max:100'],
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
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_proof.required_if' => 'Bukti pembayaran wajib diunggah untuk metode transfer manual.',
            'payment_proof.image' => 'Bukti pembayaran harus berupa gambar.',
            'payment_proof.max' => 'Ukuran file gambar bukti transfer maksimal 2MB.',
        ];
    }
}
