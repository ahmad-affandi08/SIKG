import { can } from '@/lib/permissions';

export interface NavItem {
  label: string;
  href: string;
  /** Izin yang diperlukan untuk menampilkan menu. Kosong berarti untuk semua pengguna yang masuk. */
  permission?: string;
  /** Judul kelompok menu; item berurutan dengan kelompok sama ditampilkan di bawah satu judul. */
  group?: string;
}

/**
 * Daftar menu utama. Setiap modul menambahkan itemnya BERSAMA route dan izin servernya, sehingga tidak ada
 * menu ke halaman yang belum ada. Menyembunyikan menu hanya kenyamanan; server tetap menolak aksi (M9).
 */
export const NAV_ITEMS: readonly NavItem[] = [
  { label: 'Beranda', href: '/dashboard' },
  {
    label: 'Pasien',
    href: '/patients',
    permission: 'patient.view',
    group: 'Layanan',
  },
  {
    label: 'Jadwal',
    href: '/schedule',
    permission: 'appointment.view',
    group: 'Layanan',
  },
  {
    label: 'Antrean',
    href: '/queue',
    permission: 'appointment.view',
    group: 'Layanan',
  },
  { label: 'Stok obat', href: '/stock', permission: 'stock.view', group: 'Farmasi' },
  { label: 'Tagihan', href: '/invoices', permission: 'invoice.view', group: 'Kasir' },
  { label: 'Tutup kas', href: '/cash-closings', permission: 'cash.close', group: 'Kasir' },
  {
    label: 'Pengguna',
    href: '/settings/users',
    permission: 'user.view',
    group: 'Pengaturan',
  },
  {
    label: 'Profil klinik',
    href: '/settings/profile',
    permission: 'setting.manage',
    group: 'Pengaturan',
  },
  {
    label: 'Pengaturan klinik',
    href: '/settings/clinic',
    permission: 'setting.manage',
    group: 'Pengaturan',
  },
  {
    label: 'Dokter',
    href: '/settings/doctors',
    permission: 'setting.manage',
    group: 'Pengaturan',
  },
  {
    label: 'Kursi',
    href: '/settings/master/chairs',
    permission: 'setting.manage',
    group: 'Pengaturan',
  },
  {
    label: 'Tindakan dan tarif',
    href: '/settings/master/procedures',
    permission: 'tariff.view',
    group: 'Pengaturan',
  },
  {
    label: 'Kondisi gigi',
    href: '/settings/master/tooth-conditions',
    permission: 'setting.manage',
    group: 'Pengaturan',
  },
  {
    label: 'Metode pembayaran',
    href: '/settings/master/payment-methods',
    permission: 'setting.manage',
    group: 'Pengaturan',
  },
  {
    label: 'Obat dan bahan',
    href: '/settings/master/medicines',
    permission: 'stock.view',
    group: 'Pengaturan',
  },
  {
    label: 'Sistem',
    href: '/settings/system',
    permission: 'setting.manage',
    group: 'Pengaturan',
  },
];

/**
 * Halaman yang bukan menu sendiri dikaitkan ke menu induknya untuk jejak halaman (awalan URL ke href menu).
 */
export const NAV_ALIASES: Readonly<Record<string, string>> = {
  '/appointments': '/schedule',
  '/encounters': '/patients',
  '/prescriptions': '/patients',
  '/clinical-files': '/patients',
  '/settings/holidays': '/settings/clinic',
};

/** Bawaan aman: item berizin hanya tampil bila izinnya ada. */
export function visibleNavItems(
  items: readonly NavItem[],
  permissions: readonly string[],
): NavItem[] {
  return items.filter((item) => item.permission === undefined || can(permissions, item.permission));
}
