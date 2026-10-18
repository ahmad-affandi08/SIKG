import { z } from 'zod';

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P3-03). */
export const importSchema = z.object({
  name: z.string().min(1, 'Pilih berkas.'),
  encounter_id: z.string().min(1, 'Pilih kunjungan tujuan.'),
  tooth_fdi: z.string().refine((value) => value === '' || /^\d{2}$/.test(value), {
    message: 'Nomor gigi FDI dua angka.',
  }),
});

export const dicomUploadSchema = z.object({
  encounter_id: z.string().min(1, 'Pilih kunjungan tujuan.'),
  tooth_fdi: z.string().refine((value) => value === '' || /^\d{2}$/.test(value), {
    message: 'Nomor gigi FDI dua angka.',
  }),
  caption: z.string().max(255, 'Keterangan maksimal 255 karakter.'),
});

/** Ukuran berkas dalam teks pendek (KB atau MB). */
export function formatBytes(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${String(Math.max(1, Math.round(bytes / 1024)))} KB`;
  }
  return `${String(Math.round((bytes / (1024 * 1024)) * 10) / 10).replace('.', ',')} MB`;
}
