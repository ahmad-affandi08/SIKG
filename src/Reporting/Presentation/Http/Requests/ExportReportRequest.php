<?php

declare(strict_types=1);

namespace Src\Reporting\Presentation\Http\Requests;

final class ExportReportRequest extends ReportFilterRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['required', 'in:pdf,xlsx'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'format.required' => 'Pilih format berkas.',
            'format.in' => 'Format berkas harus PDF atau Excel (XLSX).',
        ];
    }
}
