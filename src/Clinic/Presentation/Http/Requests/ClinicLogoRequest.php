<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hanya PNG atau JPG hingga 1 MB, dicek dari isi berkas (bukan sekadar nama). SVG sengaja tidak diterima.
 */
final class ClinicLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa middleware can:setting.manage pada route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['logo' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:1024']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Pilih berkas logo.',
            'logo.mimetypes' => 'Logo harus berformat PNG atau JPG.',
            'logo.max' => 'Ukuran logo maksimal 1 MB.',
        ];
    }
}
