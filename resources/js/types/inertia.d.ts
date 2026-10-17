import '@inertiajs/core';

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: {
      auth: {
        user: { id: string; name: string; roles: string[] } | null;
        // Hanya untuk kenyamanan UI; server tetap menolak aksi tanpa izin (M9).
        permissions: string[];
      };
      clinic: { name: string; timezone: string } | null;
      /** Isian wajib menurut aturan validasi server; dikirim per halaman formulir. */
      required?: string[];
      flash: { success: string | null; error: string | null; error_code?: string | null };
    };
  }
}
