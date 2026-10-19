/**
 * Nama merek yang tampil ke pengguna. Cerminan `Src\Shared\Domain\Brand` di backend; berkas statis di `public/`
 * (manifest, halaman offline) tidak dapat mengimpor ini dan harus diubah bersamaan (ADR-0014).
 */
export const BRAND = {
  name: 'SIKLIN',
  tagline: 'Sistem Informasi Klinik',
} as const;
