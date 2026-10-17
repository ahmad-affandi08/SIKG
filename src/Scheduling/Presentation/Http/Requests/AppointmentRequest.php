<?php

declare(strict_types=1);

namespace Src\Scheduling\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Scheduling\Domain\TimeSlot;

/**
 * JDW-01 dan JDW-08. Bentuk masukan diperiksa di sini; aturan jadwal (jam praktik, libur, bentrok) di Application.
 */
final class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa middleware can:appointment.manage pada route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'doctor_id' => ['required', 'string', 'size:26'],
            'chair_id' => ['nullable', 'string', 'size:26'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'minutes' => ['required', 'integer', 'between:'.TimeSlot::MIN_MINUTES.','.TimeSlot::MAX_MINUTES],
            'complaint' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->isMethod('PUT')) {
            $rules['reason'] = ['required', 'string', 'max:255'];
        } else {
            $rules['patient_id'] = ['required', 'string', 'size:26'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'Pasien wajib dipilih.',
            'doctor_id.required' => 'Dokter wajib dipilih.',
            'date.required' => 'Tanggal wajib diisi.',
            'date.date_format' => 'Tanggal harus berformat YYYY-MM-DD.',
            'time.required' => 'Jam mulai wajib diisi.',
            'time.regex' => 'Jam mulai harus berformat JJ:MM.',
            'minutes.required' => 'Durasi wajib diisi.',
            'minutes.between' => 'Durasi harus antara '.TimeSlot::MIN_MINUTES.' dan '.TimeSlot::MAX_MINUTES.' menit.',
            'reason.required' => 'Alasan jadwal ulang wajib diisi.',
        ];
    }

    public function chairId(): ?string
    {
        $chair = $this->input('chair_id');

        return is_string($chair) && $chair !== '' ? $chair : null;
    }
}
