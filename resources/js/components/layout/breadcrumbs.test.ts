import { describe, expect, it } from 'vitest';

import { buildCrumbs } from '@/components/layout/breadcrumbs';
import { NAV_ITEMS } from '@/components/layout/navigation';

const labels = (url: string, title: string | null) =>
  buildCrumbs(url, NAV_ITEMS, title).map((c) => c.label);

describe('UI-06 jejak halaman dari URL dan menu', () => {
  it('beranda hanya satu ruas tanpa tautan', () => {
    expect(buildCrumbs('/dashboard', NAV_ITEMS, 'Beranda')).toEqual([{ label: 'Beranda' }]);
  });

  it('halaman menu: Beranda, kelompok, menu; ruas terakhir bukan tautan', () => {
    const crumbs = buildCrumbs('/patients', NAV_ITEMS, 'Pasien');
    expect(crumbs.map((c) => c.label)).toEqual(['Beranda', 'Layanan', 'Pasien']);
    expect(crumbs[0]?.href).toBe('/dashboard');
    expect(crumbs[1]?.href).toBeUndefined();
    expect(crumbs[2]?.href).toBeUndefined();
  });

  it('halaman di dalam menu menambah judul halaman dan menu menjadi tautan', () => {
    const crumbs = buildCrumbs('/patients/01ABC/edit', NAV_ITEMS, 'Rekam pasien');
    expect(crumbs.map((c) => c.label)).toEqual(['Beranda', 'Layanan', 'Pasien', 'Rekam pasien']);
    expect(crumbs[2]?.href).toBe('/patients');
    expect(crumbs[3]?.href).toBeUndefined();
  });

  it('menu terpanjang yang cocok dipakai untuk pengaturan bertingkat', () => {
    expect(labels('/settings/master/medicines/create', 'Tambah katalog obat dan bahan')).toEqual([
      'Beranda',
      'Pengaturan',
      'Obat dan bahan',
      'Tambah katalog obat dan bahan',
    ]);
  });

  it('halaman yang bukan menu dikaitkan ke menu induk lewat alias', () => {
    expect(labels('/appointments/create', 'Buat janji')).toEqual([
      'Beranda',
      'Layanan',
      'Jadwal',
      'Buat janji',
    ]);
    expect(labels('/encounters/01ABC', 'Pemeriksaan')).toEqual([
      'Beranda',
      'Layanan',
      'Pasien',
      'Pemeriksaan',
    ]);
    expect(labels('/settings/holidays/create', 'Tambah hari libur')).toEqual([
      'Beranda',
      'Pengaturan',
      'Pengaturan klinik',
      'Tambah hari libur',
    ]);
  });

  it('parameter query diabaikan dan judul yang sama dengan menu tidak diulang', () => {
    expect(labels('/stock?medicine=01ABC', 'Stok obat')).toEqual([
      'Beranda',
      'Farmasi',
      'Stok obat',
    ]);
  });

  it('tanpa menu yang cocok memakai judul halaman saja', () => {
    expect(labels('/entah/halaman', 'Halaman Uji')).toEqual(['Beranda', 'Halaman Uji']);
  });
});
