/** Nama peran untuk tampilan. Nilai (kunci) sama dengan yang dipakai server. */
export const ROLE_LABELS: Record<string, string> = {
  pemilik: 'Pemilik',
  admin: 'Admin',
  resepsionis: 'Resepsionis',
  dokter: 'Dokter gigi',
  perawat: 'Perawat',
  kasir: 'Kasir',
};

export function roleLabel(role: string | null): string {
  if (role === null) {
    return '-';
  }
  return ROLE_LABELS[role] ?? role;
}
