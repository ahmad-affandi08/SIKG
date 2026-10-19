import { z } from 'zod';

import { api } from '@/lib/http';

export const patientSummarySchema = z.object({
  id: z.string(),
  medical_record_no: z.string(),
  name: z.string(),
  birth_date: z.string(),
  sex: z.string(),
  phone: z.string().nullable(),
  nik: z.string().nullable().optional(),
});

export type PatientSummary = z.infer<typeof patientSummarySchema>;

export const duplicateSchema = z.object({
  id: z.string(),
  medical_record_no: z.string(),
  name: z.string(),
  birth_date: z.string(),
  reason: z.enum(['nik', 'similar']),
});

export type DuplicateCandidate = z.infer<typeof duplicateSchema>;

/** POST, bukan GET: kata kunci bisa berupa nama, NIK, atau nomor HP dan tidak boleh berada di URL (M11). */
export async function searchPatients(q: string, signal?: AbortSignal): Promise<PatientSummary[]> {
  const options = signal ? { body: { q }, signal } : { body: { q } };
  const result = await api.post(
    '/internal-api/patients/search',
    z.array(patientSummarySchema),
    options,
  );
  return result.data;
}

export async function checkDuplicates(
  values: Record<string, unknown>,
  ignoreId?: string,
): Promise<DuplicateCandidate[]> {
  const body = ignoreId ? { ...values, ignore_id: ignoreId } : values;
  const result = await api.post(
    '/internal-api/patients/duplicate-check',
    z.array(duplicateSchema),
    {
      body,
    },
  );
  return result.data;
}
