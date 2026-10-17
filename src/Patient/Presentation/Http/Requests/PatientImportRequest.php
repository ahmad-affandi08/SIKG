<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PatientImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa middleware can:patient.import pada route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas Excel.',
            'file.mimes' => 'Berkas harus berformat .xlsx (gunakan templat dari sistem).',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ];
    }
}
