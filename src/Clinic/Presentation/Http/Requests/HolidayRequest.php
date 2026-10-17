<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class HolidayRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal wajib diisi.',
            'date.date_format' => 'Tanggal harus berformat YYYY-MM-DD.',
            'name.required' => 'Nama hari libur wajib diisi.',
        ];
    }
}
