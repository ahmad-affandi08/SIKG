<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Requests;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateClinicSettingsRequest extends FormRequest
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
        $max = (int) config('sikg.session_max_minutes');

        return [
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'session_timeout_minutes' => ['required', 'integer', 'min:5', "max:{$max}"],
            'medical_record_pattern' => ['required', 'string', 'max:40'],
            'invoice_pattern' => ['required', 'string', 'max:40'],
            'tax_percent' => ['required', 'integer', 'between:0,100'],
            'notify_whatsapp' => ['nullable', 'array', 'max:5'],
            'notify_whatsapp.*' => ['nullable', 'string', 'max:200'],
            'reminders_enabled' => ['sometimes', 'boolean'],
            'discount_limits' => ['required', 'array'],
            'discount_limits.pemilik' => ['required', 'integer', 'between:0,100'],
            'discount_limits.admin' => ['required', 'integer', 'between:0,100'],
            'discount_limits.kasir' => ['required', 'integer', 'between:0,100'],
            'operating_hours' => ['required', 'array', 'size:7'],
            'operating_hours.*' => ['nullable', 'array'],
            'operating_hours.*.open' => ['required_with:operating_hours.*', 'date_format:H:i'],
            'operating_hours.*.close' => ['required_with:operating_hours.*', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timezone.in' => 'Zona waktu tidak dikenal.',
            'session_timeout_minutes.min' => 'Batas sesi minimal 5 menit.',
            'session_timeout_minutes.max' => 'Batas sesi melebihi batas tertinggi server (:max menit).',
            'notify_whatsapp.max' => 'Nomor WhatsApp staf maksimal 5.',
            'tax_percent.between' => 'Tarif pajak harus antara 0 dan 100 persen.',
            'discount_limits.*.between' => 'Batas diskon harus antara 0 dan 100 persen.',
            'operating_hours.*.open.date_format' => 'Jam buka harus berformat HH:MM.',
            'operating_hours.*.close.date_format' => 'Jam tutup harus berformat HH:MM.',
        ];
    }
}
