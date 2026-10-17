import { z } from 'zod';

import { queueItemSchema } from '@/features/scheduling/schemas';
import { api } from '@/lib/http';

export async function fetchQueue(date?: string, signal?: AbortSignal) {
  const options = { query: { date }, ...(signal ? { signal } : {}) };
  const result = await api.get('/internal-api/queue', z.array(queueItemSchema), options);
  return result.data;
}

export async function fetchAvailability(
  params: { doctor: string; date: string; minutes: number; chair?: string },
  signal?: AbortSignal,
) {
  const options = { query: params, ...(signal ? { signal } : {}) };
  const result = await api.get(
    '/internal-api/appointments/availability',
    z.array(z.string()),
    options,
  );
  return result.data;
}
