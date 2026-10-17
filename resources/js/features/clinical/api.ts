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
