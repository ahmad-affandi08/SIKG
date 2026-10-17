<?php

declare(strict_types=1);

namespace Src\Reporting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Penyaring laporan dari query string atau badan permintaan. Otorisasi laporan dan cakupan ada di Application.
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'doctor_id' => ['nullable', 'string', 'size:26'],
            'method_id' => ['nullable', 'string', 'size:26'],
            'grain' => ['nullable', 'in:day,month'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date_format' => 'Tanggal awal harus berformat tanggal yang sah.',
            'to.date_format' => 'Tanggal akhir harus berformat tanggal yang sah.',
            'doctor_id.size' => 'Dokter tidak valid.',
            'method_id.size' => 'Metode bayar tidak valid.',
            'grain.in' => 'Penyajian harus harian atau bulanan.',
        ];
    }
}
