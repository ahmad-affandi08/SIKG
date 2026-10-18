import { z } from 'zod';

import { api } from '@/lib/http';

export const conditionSchema = z.object({
  code: z.string(),
  name: z.string(),
  color: z.string(),
  scope: z.enum(['surface', 'tooth', 'both']),
  active: z.boolean(),
});

export type ToothCondition = z.infer<typeof conditionSchema>;

const entrySchema = z.object({
  condition_code: z.string(),
  set_at: z.string(),
  encounter_id: z.string(),
  in_this_encounter: z.boolean(),
});

export type ToothEntry = z.infer<typeof entrySchema>;

const toothSchema = z.object({
  whole: entrySchema.nullable(),
  // Jaring pengaman: PHP mengirim `[]` untuk peta kosong bila tidak dilemparkan menjadi objek.
  surfaces: z.preprocess(
    (value) => (Array.isArray(value) && value.length === 0 ? {} : value),
    z.record(z.string(), entrySchema),
  ),
});

export type ToothState = z.infer<typeof toothSchema>;

export const odontogramSchema = z.object({
  // Jaring pengaman: PHP mengirim `[]` untuk peta kosong (pasien belum punya data odontogram) bila tidak dilemparkan menjadi objek.
  teeth: z.preprocess(
    (value) => (Array.isArray(value) && value.length === 0 ? {} : value),
    z.record(z.string(), toothSchema),
  ),
  conditions: z.array(conditionSchema),
});

export type OdontogramData = z.infer<typeof odontogramSchema>;

export async function fetchOdontogram(
  patientId: string,
  encounterId: string,
  signal?: AbortSignal,
) {
  const options = { query: { encounter: encounterId }, ...(signal ? { signal } : {}) };
  const result = await api.get(
    `/internal-api/patients/${patientId}/odontogram`,
    odontogramSchema,
    options,
  );
  return result.data;
}

export async function saveCondition(
  encounterId: string,
  fdi: number,
  body: { surface: string; condition_code: string },
) {
  const result = await api.put(
    `/internal-api/encounters/${encounterId}/odontogram/teeth/${String(fdi)}`,
    z.object({ tooth_fdi: z.number(), surface: z.string(), condition_code: z.string() }),
    { body },
  );
  return result.data;
}
