import { z } from 'zod';

import { api } from '@/lib/http';

export async function fetchPublicSlots(
  slug: string,
  params: { doctor_id: string; date: string },
  signal?: AbortSignal,
): Promise<string[]> {
  const options = { query: params, ...(signal ? { signal } : {}) };
  const result = await api.get(
    `/internal-api/book/${slug}/slots`,
    z.array(z.string().regex(/^\d{2}:\d{2}$/)),
    options,
  );
  return result.data;
}

/** Nomor HP penuh dibuka petugas atas permintaan; pembukaannya tercatat di audit (M10). */
export async function fetchBookingPhone(id: string): Promise<string> {
  const result = await api.post(
    `/internal-api/booking-requests/${id}/phone`,
    z.object({ phone: z.string() }),
  );
  return result.data.phone;
}
