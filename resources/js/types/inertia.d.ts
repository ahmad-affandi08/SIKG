import '@inertiajs/core';

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: {
      auth: {
        user: { id: string; name: string; roles: string[] } | null;
        // Hanya untuk kenyamanan UI; server tetap menolak aksi tanpa izin (M9).
        permissions: string[];
      };
      /** Profil layanan (KLU-01): `dental` false pada klinik umum; menu dan istilah gigi disembunyikan. */
      clinic: {
        name: string;
        timezone: string;
        profile: string;
        dental: boolean;
        doctor_title: string;
      } | null;
      /** Cabang aktif dan pilihannya (P3-04); `options` kosong bila klinik hanya punya satu cabang. */
      branch: {
        active_id: string | null;
        active_name: string | null;
        locked: boolean;
        options: { id: string; name: string }[];
      } | null;
      /** Isian wajib menurut aturan validasi server; dikirim per halaman formulir. */
      required?: string[];
      /** Kemajuan Penyiapan awal untuk banner Beranda (MST-10); null bila selesai atau bukan halaman Beranda. */
      setup?: { done: number; total: number } | null;
      flash: { success: string | null; error: string | null; error_code?: string | null };
    };
  }
}
