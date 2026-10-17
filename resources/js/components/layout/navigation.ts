import type { IconName } from '@/components/layout/icons';
import { can } from '@/lib/permissions';

export interface NavItem {
  label: string;
  href: string;
  /** Izin yang diperlukan untuk menampilkan menu. Kosong berarti untuk semua pengguna yang masuk. */
  permission?: string;
  /** Judul kelompok menu; item berurutan dengan kelompok sama ditampilkan di bawah satu judul. */
  group?: string;
  icon?: IconName;
}

/**
 * Daftar menu utama. Setiap modul menambahkan itemnya BERSAMA route dan izin servernya, sehingga tidak ada
 * menu ke halaman yang belum ada. Menyembunyikan menu hanya kenyamanan; server tetap menolak aksi (M9).
 */
export const NAV_ITEMS: readonly NavItem[] = [
  { label: 'Beranda', href: '/dashboard', icon: 'home' },
  {
    label: 'Pasien',
    href: '/patients',
    permission: 'patient.view',
    group: 'Layanan',
    icon: 'patients',
  },
  {
    label: 'Jadwal',
    href: '/schedule',
    permission: 'appointment.view',
    group: 'Layanan',
    icon: 'calendar',
  },
  {
    label: 'Antrean',
    href: '/queue',
    permission: 'appointment.view',
    group: 'Layanan',
    icon: 'queue',
  },
  { label: 'Stok obat', href: '/stock', permission: 'stock.view', group: 'Farmasi', icon: 'pill' },
  {
    label: 'Pengguna',
    href: '/settings/users',
    permission: 'user.view',
    group: 'Pengaturan',
    icon: 'users',
  },
  {
    label: 'Profil klinik',
    href: '/settings/profile',
    permission: 'setting.manage',
    group: 'Pengaturan',
    icon: 'building',
  },
  {
    label: 'Pengaturan klinik',
    href: '/settings/clinic',
    permission: 'setting.manage',
    group: 'Pengaturan',
    icon: 'cog',
  },
  {
    label: 'Dokter',
    href: '/settings/doctors',
    permission: 'setting.manage',
    group: 'Pengaturan',
    icon: 'doctor',
  },
  {
    label: 'Kursi',
    href: '/settings/master/chairs',
    permission: 'setting.manage',
    group: 'Pengaturan',
    icon: 'chair',
  },
  {
    label: 'Tindakan dan tarif',
    href: '/settings/master/procedures',
    permission: 'tariff.view',
    group: 'Pengaturan',
    icon: 'tag',
  },
  {
    label: 'Kondisi gigi',
    href: '/settings/master/tooth-conditions',
    permission: 'setting.manage',
    group: 'Pengaturan',
    icon: 'tooth',
  },
  {
    label: 'Metode pembayaran',
    href: '/settings/master/payment-methods',
    permission: 'setting.manage',
    group: 'Pengaturan',
    icon: 'card',
  },
  {
    label: 'Obat dan bahan',
    href: '/settings/master/medicines',
    permission: 'stock.view',
    group: 'Pengaturan',
    icon: 'pill',
  },
  {
    label: 'Sistem',
    href: '/settings/system',
    permission: 'setting.manage',
    group: 'Pengaturan',
    icon: 'shield',
  },
];

/** Bawaan aman: item berizin hanya tampil bila izinnya ada. */
export function visibleNavItems(
  items: readonly NavItem[],
  permissions: readonly string[],
): NavItem[] {
  return items.filter((item) => item.permission === undefined || can(permissions, item.permission));
}
