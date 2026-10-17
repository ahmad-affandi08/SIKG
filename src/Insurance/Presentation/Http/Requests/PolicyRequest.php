<?php

declare(strict_types=1);

namespace Src\Insurance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin patient.update dipasang di route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'insurer_id' => ['required', 'string', 'size:26'],
            'policy_no' => ['required', 'string', 'max:60'],
            'ceiling_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
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
            'policy_no.required' => 'Nomor polis atau kartu anggota wajib diisi.',
            'ceiling_amount.integer' => 'Plafon berupa bilangan bulat rupiah.',
            'valid_to.after_or_equal' => 'Masa berlaku akhir tidak boleh sebelum awal.',
        ];
    }
}
