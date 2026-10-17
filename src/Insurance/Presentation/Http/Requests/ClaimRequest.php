<?php

declare(strict_types=1);

namespace Src\Insurance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin invoice.edit dipasang di route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'string', 'size:26'],
            'insurer_id' => ['required', 'string', 'size:26'],
            'policy_id' => ['nullable', 'string', 'size:26'],
            'claimed_amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'insurer_id.required' => 'Penjamin wajib dipilih.',
            'claimed_amount.required' => 'Jumlah klaim wajib diisi.',
            'claimed_amount.integer' => 'Jumlah klaim berupa bilangan bulat rupiah.',
            'claimed_amount.min' => 'Jumlah klaim harus lebih dari nol.',
        ];
    }
}
