/**
 * Hanya aksi yang aman diantrekan saat offline (P3-02): menulis ulang anamnesis dan catatan SOAP sebuah kunjungan. Pembayaran, stok,
 * penutupan kunjungan, dan semua aksi uang atau penguncian TIDAK boleh diantrekan karena hasilnya bergantung pada keadaan server saat itu (M7).
 */
const RULES: readonly { pattern: RegExp; label: string }[] = [
  { pattern: /^\/encounters\/[0-9A-Z]{26}\/anamnesis$/, label: 'Anamnesis' },
  { pattern: /^\/encounters\/[0-9A-Z]{26}\/notes$/, label: 'Catatan SOAP' },
];

export function queueLabel(method: string, url: string): string | null {
  if (method.toUpperCase() !== 'PUT') {
    return null;
  }
  const path = url.split('?')[0] ?? '';
  return RULES.find((rule) => rule.pattern.test(path))?.label ?? null;
}

export function isQueueable(method: string, url: string): boolean {
  return queueLabel(method, url) !== null;
}
