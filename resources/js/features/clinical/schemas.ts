import { z } from 'zod';

import { isValidFdi } from '@/features/clinical/tooth';

const optionalInt = (label: string, min: number, max: number) =>
  z
    .string()
    .trim()
    .refine((value) => value === '' || /^\d+$/.test(value), `${label} harus berupa angka bulat.`)
    .refine(
      (value) => value === '' || (Number(value) >= min && Number(value) <= max),
      `${label} di luar rentang yang wajar (${String(min)} sampai ${String(max)}).`,
    );

/** Suhu diketik dalam derajat (contoh 36.8) dan dikirim sebagai persepuluh derajat (368), tanpa bilangan pecahan. */
export function temperatureToTenths(text: string): number | null {
  const value = text.trim().replace(',', '.');
  if (value === '') {
    return null;
  }
  const match = /^(\d{2})(?:\.(\d))?$/.exec(value);
  if (!match) {
    return Number.NaN;
  }
  return Number(match[1]) * 10 + Number(match[2] ?? '0');
}

export function tenthsToTemperature(tenths: number | null): string {
  if (tenths === null) {
    return '';
  }
  return `${String(Math.trunc(tenths / 10))}.${String(tenths % 10)}`;
}

export const anamnesisSchema = z
  .object({
    chief_complaint: z.string().trim().max(2000, 'Terlalu panjang.'),
    complaint_history: z.string().trim().max(4000, 'Terlalu panjang.'),
    systolic: optionalInt('Tekanan sistolik', 40, 300),
    diastolic: optionalInt('Tekanan diastolik', 20, 200),
    pulse: optionalInt('Nadi', 20, 300),
    temperature: z.string().trim(),
  })
  .superRefine((v, ctx) => {
    if ((v.systolic === '') !== (v.diastolic === '')) {
      ctx.addIssue({
        code: 'custom',
        path: [v.systolic === '' ? 'systolic' : 'diastolic'],
        message: 'Tekanan darah diisi sistolik dan diastolik sekaligus.',
      });
    } else if (v.systolic !== '' && Number(v.diastolic) >= Number(v.systolic)) {
      ctx.addIssue({
        code: 'custom',
        path: ['diastolic'],
        message: 'Tekanan diastolik harus lebih rendah dari sistolik.',
      });
    }
    const tenths = temperatureToTenths(v.temperature);
    if (tenths !== null && (Number.isNaN(tenths) || tenths < 300 || tenths > 450)) {
      ctx.addIssue({
        code: 'custom',
        path: ['temperature'],
        message: 'Suhu tidak wajar (contoh 36.8, rentang 30 sampai 45).',
      });
    }
  });

export type AnamnesisValues = z.infer<typeof anamnesisSchema>;

export const SOAP_FIELDS = [
  { key: 'subjective', label: 'Subjektif (S)' },
  { key: 'extra_oral', label: 'Objektif: pemeriksaan ekstra oral (O)' },
  { key: 'intra_oral', label: 'Objektif: pemeriksaan intra oral (O)' },
  { key: 'assessment', label: 'Asesmen (A)' },
  { key: 'plan', label: 'Rencana (P)' },
] as const;

export type SoapKey = (typeof SOAP_FIELDS)[number]['key'];

export const soapSchema = z.object({
  subjective: z.string().max(8000, 'Terlalu panjang (maksimal 8000 karakter).'),
  extra_oral: z.string().max(8000, 'Terlalu panjang (maksimal 8000 karakter).'),
  intra_oral: z.string().max(8000, 'Terlalu panjang (maksimal 8000 karakter).'),
  assessment: z.string().max(8000, 'Terlalu panjang (maksimal 8000 karakter).'),
  plan: z.string().max(8000, 'Terlalu panjang (maksimal 8000 karakter).'),
});

export type SoapValues = z.infer<typeof soapSchema>;

export const diagnosisEntrySchema = z.object({
  diagnosis_id: z.string().min(1, 'Diagnosa wajib dipilih.'),
  tooth_fdi: z
    .string()
    .trim()
    .refine((v) => v === '' || isValidFdi(Number(v)), 'Nomor gigi tidak valid menurut FDI.'),
  notes: z.string().trim().max(500, 'Terlalu panjang.'),
});

export type DiagnosisEntryValues = z.infer<typeof diagnosisEntrySchema>;

export type ProcedureScope = 'tooth' | 'surface' | 'jaw' | 'visit';

export function procedureEntrySchema(scope: ProcedureScope | null) {
  return z
    .object({
      procedure_id: z.string().min(1, 'Tindakan wajib dipilih.'),
      tooth_fdi: z.string().trim(),
      surfaces: z.array(z.string()),
      jaw: z.string(),
      quantity: z.coerce
        .number()
        .int('Jumlah harus bilangan bulat.')
        .min(1, 'Jumlah minimal 1.')
        .max(99, 'Jumlah maksimal 99.'),
      notes: z.string().trim().max(500, 'Terlalu panjang.'),
    })
    .superRefine((v, ctx) => {
      const needsTooth = scope === 'tooth' || scope === 'surface';
      if (needsTooth && !isValidFdi(Number(v.tooth_fdi))) {
        ctx.addIssue({
          code: 'custom',
          path: ['tooth_fdi'],
          message: 'Tindakan ini wajib menyebut nomor gigi yang valid.',
        });
      }
      if (scope === 'surface' && v.surfaces.length === 0) {
        ctx.addIssue({
          code: 'custom',
          path: ['surfaces'],
          message: 'Pilih minimal satu permukaan.',
        });
      }
      if (scope === 'jaw' && v.jaw === '') {
        ctx.addIssue({ code: 'custom', path: ['jaw'], message: 'Pilih rahang.' });
      }
    });
}

export const addendumSchema = z.object({
  body: z.string().trim().min(1, 'Isi addendum wajib diisi.').max(8000, 'Terlalu panjang.'),
  reason: z.string().trim().min(1, 'Alasan addendum wajib diisi.').max(255, 'Terlalu panjang.'),
});
