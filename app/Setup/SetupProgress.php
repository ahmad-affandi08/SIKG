<?php

declare(strict_types=1);

namespace App\Setup;

use Illuminate\Support\Facades\DB;

/**
 * MST-10: kemajuan penyiapan awal klinik, dibaca dari isi tabel master (tanpa menyimpan status sendiri, sehingga selalu
 * mengikuti keadaan sebenarnya). Langkah wajib menentukan "siap dipakai"; langkah lain hanya anjuran.
 */
final class SetupProgress
{
    /**
     * @return list<array{key: string, title: string, detail: string, done: bool, required: bool, summary: string, href: string, cta: string, import_href: string|null}>
     */
    public function steps(string $clinicId): array
    {
        $clinic = DB::table('clinics')->where('id', $clinicId)->first();
        $count = fn (string $table, bool $activeOnly = true) => (int) DB::table($table)->where('clinic_id', $clinicId)
            ->whereNull('deleted_at')->when($activeOnly, fn ($q) => $q->where('is_active', true))->count();

        $profileDone = $clinic !== null && trim((string) $clinic->address) !== '' && trim((string) $clinic->license_no) !== '';
        $methods = $count('payment_methods');
        $doctors = $count('doctors');
        $chairs = $count('chairs');
        $procedures = $count('procedures');
        $proceduresAll = $count('procedures', false);
        $pricedProcedures = (int) DB::table('procedures')->where('clinic_id', $clinicId)->whereNull('deleted_at')
            ->where('is_active', true)->where('default_price_amount', '>', 0)->count();
        $medicines = $count('medicines');
        $medicinesAll = $count('medicines', false);
        $categories = $count('expense_categories');
        $users = (int) DB::table('users')->where('clinic_id', $clinicId)->whereNull('deleted_at')->where('is_active', true)->count();

        return [
            [
                'key' => 'profile', 'title' => 'Profil klinik', 'required' => true, 'done' => $profileDone,
                'detail' => 'Nama, alamat, dan nomor izin tampil di kop kwitansi, resep, dan dokumen.',
                'summary' => $profileDone ? 'Alamat dan nomor izin sudah diisi.' : 'Alamat atau nomor izin belum diisi.',
                'href' => '/settings/profile', 'cta' => 'Isi profil', 'import_href' => null,
            ],
            [
                'key' => 'payment_methods', 'title' => 'Metode pembayaran', 'required' => true, 'done' => $methods > 0,
                'detail' => 'Kasir tidak dapat menerima pembayaran sebelum ada metode (tunai, transfer, QRIS, kartu).',
                'summary' => "{$methods} metode aktif.", 'href' => '/settings/master/payment-methods', 'cta' => 'Kelola metode', 'import_href' => null,
            ],
            [
                'key' => 'doctors', 'title' => 'Dokter', 'required' => true, 'done' => $doctors > 0,
                'detail' => 'Dokter dan jadwal praktik diperlukan untuk janji dan kunjungan.',
                'summary' => "{$doctors} dokter aktif.", 'href' => '/settings/doctors', 'cta' => 'Tambah dokter', 'import_href' => null,
            ],
            [
                'key' => 'chairs', 'title' => 'Kursi perawatan', 'required' => true, 'done' => $chairs > 0,
                'detail' => 'Kursi atau unit perawatan dipakai pada jadwal agar tidak bentrok.',
                'summary' => "{$chairs} kursi aktif.", 'href' => '/settings/master/chairs', 'cta' => 'Tambah kursi', 'import_href' => null,
            ],
            [
                'key' => 'procedures', 'title' => 'Tarif tindakan', 'required' => true, 'done' => $pricedProcedures > 0,
                'detail' => 'Isi tarif lalu aktifkan tindakan yang dipakai klinik. Data awal bisa diisi dari tombol di bawah, atau impor dari Excel.',
                'summary' => "{$procedures} aktif dari {$proceduresAll} tindakan; {$pricedProcedures} aktif dengan tarif terisi.",
                'href' => '/settings/master/procedures', 'cta' => 'Atur tarif', 'import_href' => '/settings/master/procedures/import',
            ],
            [
                'key' => 'medicines', 'title' => 'Obat dan bahan', 'required' => false, 'done' => $medicines > 0,
                'detail' => 'Isi harga jual lalu aktifkan obat yang dipakai agar dapat diresepkan dan ditagih.',
                'summary' => "{$medicines} aktif dari {$medicinesAll} obat dan bahan.",
                'href' => '/settings/master/medicines', 'cta' => 'Atur obat', 'import_href' => '/settings/master/medicines/import',
            ],
            [
                'key' => 'expense_categories', 'title' => 'Kategori biaya operasional', 'required' => false, 'done' => $categories > 0,
                'detail' => 'Diperlukan untuk mencatat biaya dan laporan laba rugi.',
                'summary' => "{$categories} kategori aktif.", 'href' => '/settings/master/expense-categories', 'cta' => 'Kelola kategori', 'import_href' => null,
            ],
            [
                'key' => 'users', 'title' => 'Pengguna', 'required' => false, 'done' => $users > 1,
                'detail' => 'Tambahkan resepsionis, dokter, perawat, dan kasir dengan peran masing-masing.',
                'summary' => "{$users} pengguna aktif.", 'href' => '/settings/users', 'cta' => 'Tambah pengguna', 'import_href' => null,
            ],
        ];
    }
}
