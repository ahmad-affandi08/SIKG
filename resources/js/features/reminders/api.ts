import { z } from 'zod';

import { api } from '@/lib/http';

const linkSchema = z.object({ url: z.string().startsWith('https://wa.me/') });

/** Tautan WhatsApp untuk satu pengingat; nomor HP baru keluar dari server saat petugas menekan tombol. */
export async function fetchWhatsAppLink(reminderId: string): Promise<string> {
  const result = await api.post(`/internal-api/reminders/${reminderId}/link`, linkSchema);
  return result.data.url;
}
