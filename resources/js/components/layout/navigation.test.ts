import { describe, expect, it } from 'vitest';

import { NAV_ITEMS, visibleNavItems } from '@/components/layout/navigation';
import type { NavItem } from '@/components/layout/navigation';

const MENU: readonly NavItem[] = [
  { label: 'Beranda', href: '/dashboard' },
  { label: 'Pasien', href: '/patients', permission: 'patient.view' },
  { label: 'Kasir', href: '/invoices', permission: 'invoice.view' },
  { label: 'Stok', href: '/stock', permission: 'stock.view' },
  { label: 'Pengaturan', href: '/settings', permission: 'setting.manage' },
];

const labels = (items: readonly NavItem[]) => items.map((item) => item.label);

describe('AUTH-02 menu disaring menurut izin (kenyamanan UI, bukan keamanan)', () => {
  it('item tanpa izin tampil untuk semua pengguna yang masuk', () => {
    expect(labels(visibleNavItems(MENU, []))).toEqual(['Beranda']);
  });

  it('bawaan aman: tanpa izin, menu berizin disembunyikan', () => {
    expect(labels(visibleNavItems(MENU, []))).not.toContain('Pasien');
  });

  it('kasir hanya melihat menu yang izinnya dimiliki', () => {
    const kasir = ['patient.view', 'invoice.view', 'payment.create'];

    expect(labels(visibleNavItems(MENU, kasir))).toEqual(['Beranda', 'Pasien', 'Kasir']);
  });

  it('admin melihat pengaturan, resepsionis tidak', () => {
    expect(labels(visibleNavItems(MENU, ['setting.manage']))).toContain('Pengaturan');
    expect(labels(visibleNavItems(MENU, ['patient.view']))).not.toContain('Pengaturan');
  });

  it('LAP menu Laporan tampil bagi pemegang report.view atau stock.manage (Perawat), tidak bagi lainnya', () => {
    const laporan = NAV_ITEMS.filter((item) => item.href === '/reports');

    expect(labels(visibleNavItems(laporan, ['report.view']))).toEqual(['Laporan']);
    expect(labels(visibleNavItems(laporan, ['stock.manage']))).toEqual(['Laporan']);
    expect(labels(visibleNavItems(laporan, ['patient.view', 'invoice.view']))).toEqual([]);
  });

  it('daftar menu bawaan aplikasi hanya memuat halaman yang sudah ada', () => {
    expect(NAV_ITEMS.map((item) => item.href)).toEqual([
      '/dashboard',
      '/patients',
      '/schedule',
      '/queue',
      '/reminders',
      '/stock',
      '/invoices',
      '/cash-closings',
      '/insurance/claims',
      '/reports',
      '/settings/users',
      '/settings/roles',
      '/settings/profile',
      '/settings/clinic',
      '/settings/doctors',
      '/settings/master/chairs',
      '/settings/master/procedures',
      '/settings/master/tooth-conditions',
      '/settings/master/payment-methods',
      '/settings/master/insurers',
      '/settings/master/medicines',
      '/settings/master/document-templates',
      '/settings/satusehat',
      '/settings/reminders',
      '/audit',
      '/audit/access',
      '/settings/system',
      '/account/password',
    ]);
  });
});
