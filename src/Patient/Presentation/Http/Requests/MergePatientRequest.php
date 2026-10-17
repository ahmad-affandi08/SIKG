<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MergePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa middleware can:patient.merge pada route
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'source_id' => ['required', 'string', 'size:26', Rule::notIn([(string) $this->route('patient')])],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_id.required' => 'Pilih pasien ganda yang akan digabung.',
            'source_id.size' => 'Pasien ganda tidak valid.',
            'source_id.not_in' => 'Pilih pasien ganda yang berbeda dari pasien utama.',
            'reason.required' => 'Alasan penggabungan wajib diisi.',
            'reason.min' => 'Alasan penggabungan minimal 5 karakter.',
            'reason.max' => 'Alasan penggabungan terlalu panjang.',
            'confirm.accepted' => 'Centang konfirmasi bahwa penggabungan tidak dapat dibatalkan lewat aplikasi.',
        ];
    }
}
