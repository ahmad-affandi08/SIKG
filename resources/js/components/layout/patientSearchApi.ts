import { z } from 'zod';

import { api } from '@/lib/http';

/** Bagian hasil PAS-02 yang dipakai kotak cari cepat; layout tidak boleh mengimpor features/, jadi skemanya sendiri. */
const quickResultSchema = z.object({
  id: z.string(),
  medical_record_no: z.string(),
  name: z.string(),
  birth_date: z.string(),
  nik: z.string().nullable().optional(),
});

export type QuickPatient = z.infer<typeof quickResultSchema>;

/** POST, bukan GET: kata kunci bisa berupa nama atau NIK dan tidak boleh berada di URL (M11). */
export async function quickSearchPatients(
  q: string,
  signal?: AbortSignal,
): Promise<QuickPatient[]> {
  const options = signal ? { body: { q }, signal } : { body: { q } };
  const result = await api.post(
    '/internal-api/patients/search',
    z.array(quickResultSchema),
    options,
  );
  return result.data;
}
