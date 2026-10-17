import { z } from 'zod';

const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;

export const MIN_MINUTES = 10;
export const MAX_MINUTES = 480;
export const DURATIONS = [15, 30, 45, 60, 90, 120] as const;

export const appointmentFormSchema = z.object({
  patient_id: z.string().min(1, 'Pasien wajib dipilih.'),
  doctor_id: z.string().min(1, 'Dokter wajib dipilih.'),
  chair_id: z.string(),
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Tanggal wajib diisi.'),
  time: z.string().regex(TIME, 'Jam mulai harus berformat JJ:MM.'),
  minutes: z.coerce
    .number()
    .int('Durasi harus bilangan bulat.')
    .min(MIN_MINUTES, `Durasi minimal ${String(MIN_MINUTES)} menit.`)
    .max(MAX_MINUTES, `Durasi maksimal ${String(MAX_MINUTES)} menit.`),
  complaint: z.string().trim().max(500, 'Terlalu panjang.'),
});

export type AppointmentFormValues = z.input<typeof appointmentFormSchema>;

export const rescheduleSchema = appointmentFormSchema
  .omit({ patient_id: true, complaint: true })
  .extend({ reason: z.string().trim().min(1, 'Alasan jadwal ulang wajib diisi.').max(255) });

export const cancelSchema = z.object({
  reason: z.string().trim().min(1, 'Alasan pembatalan wajib diisi.').max(255, 'Terlalu panjang.'),
});

export const walkInSchema = z.object({
  patient_id: z.string().min(1, 'Pasien wajib dipilih.'),
  doctor_id: z.string().min(1, 'Dokter wajib dipilih.'),
  chair_id: z.string(),
  complaint: z.string().trim().max(500, 'Terlalu panjang.'),
});

export type WalkInValues = z.infer<typeof walkInSchema>;

export const queueItemSchema = z.object({
  id: z.string(),
  date: z.string(),
  start: z.string(),
  end: z.string(),
  minutes: z.number(),
  status: z.string(),
  status_label: z.string(),
  source: z.string(),
  queue_no: z.number().nullable(),
  complaint: z.string().nullable(),
  patient_id: z.string(),
  patient_name: z.string().nullable(),
  medical_record_no: z.string().nullable(),
  doctor_id: z.string(),
  doctor_name: z.string().nullable(),
  chair_id: z.string().nullable(),
  chair_name: z.string().nullable(),
});

export type QueueItem = z.infer<typeof queueItemSchema>;
