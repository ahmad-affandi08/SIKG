<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Patient\Application\PatientDraft;
use Src\Patient\Domain\Sex;

/**
 * PAS-01 dan PAS-06. Validasi bentuk di sini; aturan domain (NIK wajib, wali untuk di bawah 18 tahun) di PatientProfile.
 */
final class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa middleware can:... pada route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:30'],
            'nik_missing_reason' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'emergency_name' => ['nullable', 'string', 'max:255'],
            'emergency_relation' => ['nullable', 'string', 'max:50'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_relation' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'consent' => ['sometimes', 'boolean'],
            'acknowledge_duplicates' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pasien wajib diisi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date_format' => 'Tanggal lahir harus berformat YYYY-MM-DD.',
            'sex.required' => 'Jenis kelamin wajib dipilih.',
        ];
    }

    public function toDraft(): PatientDraft
    {
        return new PatientDraft(
            name: $this->string('name')->toString(),
            nik: $this->nullable('nik'),
            nikMissingReason: $this->nullable('nik_missing_reason'),
            birthDate: $this->string('birth_date')->toString(),
            sex: $this->string('sex')->toString(),
            address: $this->nullable('address'),
            phone: $this->nullable('phone'),
            occupation: $this->nullable('occupation'),
            emergencyName: $this->nullable('emergency_name'),
            emergencyRelation: $this->nullable('emergency_relation'),
            emergencyPhone: $this->nullable('emergency_phone'),
            guardianName: $this->nullable('guardian_name'),
            guardianRelation: $this->nullable('guardian_relation'),
            guardianPhone: $this->nullable('guardian_phone'),
        );
    }

    private function nullable(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
