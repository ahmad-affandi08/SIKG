import { can } from '@/lib/permissions';

export interface NavItem {
  label: string;
  href: string;
  /** Izin yang diperlukan untuk menampilkan menu. Kosong berarti untuk semua pengguna yang masuk. */
  permission?: string;
}

/**
 * Daftar menu utama. Setiap modul menambahkan itemnya BERSAMA route dan izin servernya, sehingga tidak ada
 * menu ke halaman yang belum ada. Menyembunyikan menu hanya kenyamanan; server tetap menolak aksi (M9).
 */
export const NAV_ITEMS: readonly NavItem[] = [
  { label: 'Beranda', href: '/dashboard' },
  { label: 'Pengguna', href: '/settings/users', permission: 'user.view' },
  {
    label: 'Pengaturan klinik',
    href: '/settings/clinic',
    permission: 'setting.manage',
  },
  {
    label: 'Kursi',
    href: '/settings/master/chairs',
    permission: 'setting.manage',
  },
  {
    label: 'Tindakan dan tarif',
    href: '/settings/master/procedures',
    permission: 'tariff.view',
  },
  {
    label: 'Metode pembayaran',
    href: '/settings/master/payment-methods',
    permission: 'setting.manage',
  },
  {
    label: 'Obat dan bahan',
    href: '/settings/master/medicines',
    permission: 'stock.view',
  },
  { label: 'Sistem', href: '/settings/system', permission: 'setting.manage' },
];

/** Bawaan aman: item berizin hanya tampil bila izinnya ada. */
export function visibleNavItems(
  items: readonly NavItem[],
  permissions: readonly string[],
): NavItem[] {
  return items.filter((item) => item.permission === undefined || can(permissions, item.permission));
}
