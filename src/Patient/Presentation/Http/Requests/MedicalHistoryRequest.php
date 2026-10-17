<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Patient\Domain\MedicalHistoryEntry;
use Src\Patient\Domain\MedicalHistoryType;
use Src\Patient\Domain\Severity;

final class MedicalHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa middleware can:patient.history.manage pada route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MedicalHistoryType::class)],
            'name' => ['required', 'string', 'max:255'],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['type.required' => 'Jenis riwayat wajib dipilih.', 'name.required' => 'Nama riwayat wajib diisi.'];
    }

    public function toEntry(): MedicalHistoryEntry
    {
        $severity = $this->input('severity');
        $notes = $this->input('notes');

        return new MedicalHistoryEntry(
            MedicalHistoryType::from($this->string('type')->toString()),
            $this->string('name')->toString(),
            is_string($severity) && $severity !== '' ? Severity::from($severity) : null,
            is_string($notes) ? $notes : null,
        );
    }
}
