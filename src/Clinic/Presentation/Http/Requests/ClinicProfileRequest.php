<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClinicProfileRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'prescription_header' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.required' => 'Nama klinik wajib diisi.'];
    }
}
