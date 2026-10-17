<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PatientContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa middleware can:patient.update pada route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'reminders_opt_in' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Alamat email tidak valid.',
            'email.max' => 'Alamat email terlalu panjang.',
            'reminders_opt_in.required' => 'Pilihan persetujuan pengingat wajib ada.',
        ];
    }
}
