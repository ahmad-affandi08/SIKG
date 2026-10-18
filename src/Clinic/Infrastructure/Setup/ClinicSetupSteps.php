<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Setup;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Setup\SetupStep;
use Src\Shared\Application\Setup\SetupStepProvider;

/**
 * MST-10: langkah Penyiapan awal yang datanya dimiliki konteks Clinic (profil, metode bayar, dokter, kursi, tarif tindakan).
 */
final class ClinicSetupSteps implements SetupStepProvider
{
    public function steps(string $clinicId): array
    {
        $clinic = DB::table('clinics')->where('id', $clinicId)->first();
        $count = fn (string $table, bool $activeOnly = true): int => (int) DB::table($table)->where('clinic_id', $clinicId)
            ->whereNull('deleted_at')->when($activeOnly, fn ($q) => $q->where('is_active', true))->count();

        $profileDone = $clinic !== null && trim((string) $clinic->address) !== '' && trim((string) $clinic->license_no) !== '';
        $methods = $count('payment_methods');
        $doctors = $count('doctors');
        $chairs = $count('chairs');
        $procedures = $count('procedures');
        $proceduresAll = $count('procedures', false);
        $priced = (int) DB::table('procedures')->where('clinic_id', $clinicId)->whereNull('deleted_at')
            ->where('is_active', true)->where('default_price_amount', '>', 0)->count();

        return [
            new SetupStep(10, 'profile', 'Profil klinik',
                'Nama, alamat, dan nomor izin tampil di kop kwitansi, resep, dan dokumen.', $profileDone, true,
                $profileDone ? 'Alamat dan nomor izin sudah diisi.' : 'Alamat atau nomor izin belum diisi.',
                '/settings/profile', 'Isi profil'),
            new SetupStep(20, 'payment_methods', 'Metode pembayaran',
                'Kasir tidak dapat menerima pembayaran sebelum ada metode (tunai, transfer, QRIS, kartu).', $methods > 0, true,
                "{$methods} metode aktif.", '/settings/master/payment-methods', 'Kelola metode'),
            new SetupStep(30, 'doctors', 'Dokter',
                'Dokter dan jadwal praktik diperlukan untuk janji dan kunjungan.', $doctors > 0, true,
                "{$doctors} dokter aktif.", '/settings/doctors', 'Tambah dokter'),
            new SetupStep(40, 'chairs', 'Kursi perawatan',
                'Kursi atau unit perawatan dipakai pada jadwal agar tidak bentrok.', $chairs > 0, true,
                "{$chairs} kursi aktif.", '/settings/master/chairs', 'Tambah kursi'),
            new SetupStep(50, 'procedures', 'Tarif tindakan',
                'Isi tarif lalu aktifkan tindakan yang dipakai klinik. Data awal bisa diisi dari tombol di bawah, atau impor dari Excel.',
                $priced > 0, true, "{$procedures} aktif dari {$proceduresAll} tindakan; {$priced} aktif dengan tarif terisi.",
                '/settings/master/procedures', 'Atur tarif', '/settings/master/procedures/import'),
        ];
    }
}
