import { z } from 'zod';

/** RM-09 dan RM-10: jenis dokumen klinis. Nilai sama dengan `DocumentType` di server. */
export type DocumentKind = 'consent' | 'certificate' | 'referral';

export interface DocumentVariables {
  pasien: string;
  no_rm: string;
  tanggal_lahir: string;
  umur: string;
  klinik: string;
  tanggal: string;
  dokter: string;
  sip: string;
  tanggal_kunjungan: string;
  diagnosa: string;
  tindakan: string;
  keperluan: string;
  istirahat: string;
  tujuan: string;
  alasan: string;
  keluhan: string;
  terapi: string;
}

const PLACEHOLDER = /\{\{\s*([a-z_]+)\s*\}\}/g;

/** Mengisi penanda `{{nama}}` dari isian; penanda yang nilainya kosong dibiarkan agar terlihat belum terisi. */
export function renderTemplate(body: string, variables: Partial<DocumentVariables>): string {
  return body.replace(PLACEHOLDER, (whole: string, name: string) => {
    const value = (variables as Record<string, string | undefined>)[name];
    return value !== undefined && value.trim() !== '' ? value.trim() : whole;
  });
}

/** Penanda yang masih tersisa pada teks (belum terisi atau salah ketik). */
export function unresolvedPlaceholders(body: string): string[] {
  const found = new Set<string>();
  for (const match of body.matchAll(/\{\{[^}]*\}\}/g)) {
    found.add(match[0]);
  }
  return [...found];
}

/** Kalimat anjuran istirahat untuk surat keterangan; kosong berarti tidak memerlukan istirahat khusus. */
export function restSentence(days: string): string {
  const count = Number(days);
  if (days.trim() === '' || !Number.isInteger(count) || count <= 0) {
    return 'tidak memerlukan istirahat khusus';
  }
  return `disarankan beristirahat selama ${String(count)} hari`;
}

/** Tanggal YYYY-MM-DD menjadi DD/MM/YYYY. */
export function toDisplayDate(iso: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);
  return match ? `${match[3] ?? ''}/${match[2] ?? ''}/${match[1] ?? ''}` : iso;
}

export const documentFormSchema = z.object({
  doctor_id: z.string().min(1, 'Dokter wajib dipilih.'),
  body: z
    .string()
    .trim()
    .min(20, 'Isi dokumen minimal 20 karakter.')
    .max(8000, 'Isi dokumen terlalu panjang.')
    .refine((text) => unresolvedPlaceholders(text).length === 0, {
      message:
        'Masih ada penanda {{...}} yang belum terisi. Lengkapi isiannya atau hapus kalimatnya.',
    }),
});
export type DocumentFormValues = z.infer<typeof documentFormSchema>;

/** Isian tambahan yang wajib untuk tiap jenis dokumen. */
export function requiredExtras(kind: DocumentKind): string[] {
  switch (kind) {
    case 'consent':
      return ['tindakan'];
    case 'certificate':
      return ['keperluan'];
    case 'referral':
      return ['tujuan', 'keluhan', 'terapi', 'alasan'];
  }
}
