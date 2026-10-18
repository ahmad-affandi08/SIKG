/**
 * Mendaftarkan service worker (P3-02) hanya pada build produksi. Service worker hanya menyimpan aset bundel statis dan halaman offline;
 * halaman atau data pasien tidak pernah disimpan di cache (M11).
 */
export function registerServiceWorker(): void {
  if (
    !import.meta.env.PROD ||
    typeof navigator === 'undefined' ||
    !('serviceWorker' in navigator)
  ) {
    return;
  }
  window.addEventListener('load', () => {
    void navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
      // Tanpa service worker aplikasi tetap berjalan; hanya halaman offline yang tidak tersedia.
    });
  });
}
