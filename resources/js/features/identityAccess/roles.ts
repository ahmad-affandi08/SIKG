/** Nama peran untuk tampilan. Nilai (kunci) sama dengan yang dipakai server. */
export const ROLE_LABELS: Record<string, string> = {
  pemilik: 'Pemilik',
  admin: 'Admin',
  resepsionis: 'Resepsionis',
  dokter: 'Dokter gigi',
  perawat: 'Perawat',
  kasir: 'Kasir',
};

/** `doctorTitle` mengikuti profil layanan klinik (KLU-02): klinik umum menyebut "Dokter". */
export function roleLabel(role: string | null, doctorTitle?: string): string {
  if (role === null) {
    return '-';
  }
  if (role === 'dokter' && doctorTitle !== undefined) {
    return doctorTitle;
  }
  return ROLE_LABELS[role] ?? role;
}
