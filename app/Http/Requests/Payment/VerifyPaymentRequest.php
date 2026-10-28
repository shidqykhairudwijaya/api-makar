<?php

namespace App\Http\Requests\Payment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyPaymentRequest extends FormRequest
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
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => [
                Rule::requiredIf(fn () => $this->input('action') === 'reject'),
                'nullable',
                'string',
                'max:500',
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
            'action.required' => 'Aksi verifikasi (approve/reject) wajib ditentukan.',
            'action.in' => 'Aksi verifikasi harus berupa approve atau reject.',
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi jika pembayaran ditolak.',
        ];
    }
}
