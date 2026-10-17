import { describe, expect, it } from 'vitest';

import { QUEUE_POLL_MS } from './queries';
import { appointmentFormSchema, cancelSchema, rescheduleSchema, walkInSchema } from './schemas';
import { statusTone } from './status';

const valid = {
  patient_id: '01JABCDEFGHJKMNPQRSTVWXYZ0',
  doctor_id: '01JABCDEFGHJKMNPQRSTVWXYZ1',
  chair_id: '',
  date: '2025-06-16',
  time: '09:00',
  minutes: '30',
  complaint: '',
};

function messages(result: {
  success: boolean;
  error?: { issues: { message: string }[] };
}): string[] {
  return result.success ? [] : (result.error?.issues.map((i) => i.message) ?? []);
}

describe('JDW-01 formulir janji', () => {
  it('menerima isian yang benar dan mengubah durasi menjadi angka', () => {
    const result = appointmentFormSchema.safeParse(valid);
    expect(result.success).toBe(true);
    expect(result.success && result.data.minutes).toBe(30);
  });

  it('pasien dan dokter wajib dipilih', () => {
    const result = messages(
      appointmentFormSchema.safeParse({ ...valid, patient_id: '', doctor_id: '' }),
    );
    expect(result).toContain('Pasien wajib dipilih.');
    expect(result).toContain('Dokter wajib dipilih.');
  });

  it('jam harus berformat JJ:MM dan durasi 10 sampai 480 menit', () => {
    expect(messages(appointmentFormSchema.safeParse({ ...valid, time: '9:00' }))).toContain(
      'Jam mulai harus berformat JJ:MM.',
    );
    expect(messages(appointmentFormSchema.safeParse({ ...valid, minutes: '5' }))).toContain(
      'Durasi minimal 10 menit.',
    );
    expect(messages(appointmentFormSchema.safeParse({ ...valid, minutes: '500' }))).toContain(
      'Durasi maksimal 480 menit.',
    );
  });
});

describe('JDW-08 alasan wajib', () => {
  it('jadwal ulang wajib beralasan', () => {
    const base = {
      doctor_id: valid.doctor_id,
      chair_id: valid.chair_id,
      date: valid.date,
      time: valid.time,
      minutes: valid.minutes,
    };
    expect(messages(rescheduleSchema.safeParse({ ...base, reason: ' ' }))).toContain(
      'Alasan jadwal ulang wajib diisi.',
    );
    expect(rescheduleSchema.safeParse({ ...base, reason: 'Pasien minta diundur' }).success).toBe(
      true,
    );
  });

  it('pembatalan wajib beralasan', () => {
    expect(messages(cancelSchema.safeParse({ reason: '' }))).toContain(
      'Alasan pembatalan wajib diisi.',
    );
    expect(cancelSchema.safeParse({ reason: 'Sakit' }).success).toBe(true);
  });
});

describe('JDW-05 walk-in', () => {
  it('pasien dan dokter wajib, kursi opsional', () => {
    expect(
      walkInSchema.safeParse({ patient_id: '', doctor_id: '', chair_id: '', complaint: '' })
        .success,
    ).toBe(false);
    expect(
      walkInSchema.safeParse({ patient_id: 'P', doctor_id: 'D', chair_id: '', complaint: '' })
        .success,
    ).toBe(true);
  });
});

describe('JDW-04 dan JDW-06', () => {
  it('status tidak hanya lewat warna dan antrean di-poll tiap 10 detik', () => {
    expect(statusTone('completed')).toBe('success');
    expect(statusTone('cancelled')).toBe('danger');
    expect(statusTone('checked_in')).toBe('warning');
    expect(statusTone('scheduled')).toBe('neutral');
    expect(QUEUE_POLL_MS).toBe(10_000);
  });
});
