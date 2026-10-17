export const IMPORT_MAX_BYTES = 5 * 1024 * 1024;

/** Umpan balik cepat untuk berkas impor; server memeriksa isi berkas dan berwenang. */
export function importFileIssue(file: { name: string; size: number } | null): string | null {
  if (file === null) {
    return 'Pilih berkas Excel.';
  }
  if (!file.name.toLowerCase().endsWith('.xlsx')) {
    return 'Berkas harus berformat .xlsx (gunakan templat dari sistem).';
  }
  if (file.size > IMPORT_MAX_BYTES) {
    return 'Ukuran berkas maksimal 5 MB.';
  }
  return null;
}
