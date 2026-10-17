import { z } from 'zod';

import { api } from '@/lib/http';

export const diagnosisSchema = z.object({
  id: z.string(),
  code: z.string(),
  name: z.string(),
});

export type Diagnosis = z.infer<typeof diagnosisSchema>;

export async function searchDiagnoses(term: string, signal?: AbortSignal): Promise<Diagnosis[]> {
  const options = signal ? { query: { q: term }, signal } : { query: { q: term } };
  const result = await api.get('/internal-api/diagnoses/search', z.array(diagnosisSchema), options);
  return result.data;
}

export const procedureSchema = z.object({
  id: z.string(),
  code: z.string(),
  name: z.string(),
  scope: z.enum(['tooth', 'surface', 'jaw', 'visit']),
  price_amount: z.number().int().nonnegative(),
});

export type ProcedureOption = z.infer<typeof procedureSchema>;

export async function searchProcedures(
  term: string,
  signal?: AbortSignal,
): Promise<ProcedureOption[]> {
  const options = signal ? { query: { q: term }, signal } : { query: { q: term } };
  const result = await api.get(
    '/internal-api/procedures/search',
    z.array(procedureSchema),
    options,
  );
  return result.data;
}
