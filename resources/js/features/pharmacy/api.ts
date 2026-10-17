import { z } from 'zod';

import { api } from '@/lib/http';

export const medicineOptionSchema = z.object({
  id: z.string(),
  name: z.string(),
  unit: z.string(),
  sell_price_amount: z.number().int().nonnegative(),
  drug_class: z.string().nullable(),
  available: z.number().int().nonnegative(),
});

export type MedicineOption = z.infer<typeof medicineOptionSchema>;

export async function searchMedicines(
  term: string,
  signal?: AbortSignal,
): Promise<MedicineOption[]> {
  const options = signal ? { query: { q: term }, signal } : { query: { q: term } };
  const result = await api.get(
    '/internal-api/medicines/search',
    z.array(medicineOptionSchema),
    options,
  );
  return result.data;
}
