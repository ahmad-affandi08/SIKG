import { z } from 'zod';

export const FILE_MAX_BYTES = 10 * 1024 * 1024;
export const FILE_TYPES: readonly string[] = ['image/jpeg', 'image/png', 'application/pdf'];

export const FILE_KINDS = [
  { value: 'xray', label: 'Rontgen' },
  { value: 'photo', label: 'Foto klinis' },
  { value: 'document', label: 'Dokumen' },
] as const;

/** Umpan balik cepat; server memeriksa isi berkas yang sebenarnya dan berwenang (RM-08). */
export function fileIssue(file: { type: string; size: number } | null): string | null {
  if (file === null) {
    return 'Pilih berkas.';
  }
  if (!FILE_TYPES.includes(file.type)) {
    return 'Berkas harus berformat JPG, PNG, atau PDF.';
  }
  if (file.size === 0 || file.size > FILE_MAX_BYTES) {
    return 'Ukuran berkas maksimal 10 MB.';
  }
  return null;
}

export const fileMetaSchema = z.object({
  kind: z.enum(['xray', 'photo', 'document']),
  tooth_fdi: z.string().trim(),
  caption: z.string().trim().max(255, 'Terlalu panjang.'),
});

export function formatBytes(bytes: number): string {
  if (bytes < 1024) {
    return `${String(bytes)} B`;
  }
  if (bytes < 1024 * 1024) {
    return `${String(Math.round(bytes / 1024))} KB`;
  }
  const tenths = Math.round((bytes * 10) / (1024 * 1024));
  return `${String(Math.trunc(tenths / 10))}.${String(tenths % 10)} MB`;
}
