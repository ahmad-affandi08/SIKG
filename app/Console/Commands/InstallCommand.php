<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\IdentityAccess\Infrastructure\Seeders\RolePermissionSeeder;

/**
 * Penyiapan awal di server: membuat satu klinik kosong dan satu pengguna Pemilik dengan kata sandi sementara
 * (wajib diganti saat masuk pertama). Menolak berjalan bila sudah ada klinik. Tidak menyentuh data lain.
 */
final class InstallCommand extends Command
{
    protected $signature = 'sikg:install
        {--clinic= : Nama klinik}
        {--name= : Nama lengkap Pemilik}
        {--username= : Nama pengguna Pemilik}
        {--email= : Email Pemilik}
        {--password= : Kata sandi sementara (minimal 12 karakter); bila kosong ditanyakan}';

    protected $description = 'Buat klinik pertama dan pengguna Pemilik (hanya bila belum ada klinik)';

    public function handle(): int
    {
        if (DB::table('clinics')->exists()) {
            $this->error('Sudah ada klinik di basis data ini. Penyiapan awal hanya dijalankan sekali.');

            return self::FAILURE;
        }

        $clinicName = trim((string) ($this->option('clinic') ?: $this->ask('Nama klinik')));
        $name = trim((string) ($this->option('name') ?: $this->ask('Nama lengkap Pemilik')));
        $username = trim((string) ($this->option('username') ?: $this->ask('Nama pengguna Pemilik')));
        $email = trim((string) ($this->option('email') ?: $this->ask('Email Pemilik')));
        $password = (string) ($this->option('password') ?: $this->secret('Kata sandi sementara (minimal 12 karakter)'));

        if ($clinicName === '' || $name === '' || $username === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Nama klinik, nama, nama pengguna, dan email yang valid wajib diisi.');

            return self::FAILURE;
        }
        if (mb_strlen($password) < 12) {
            $this->error('Kata sandi sementara minimal 12 karakter.');

            return self::FAILURE;
        }

        (new RolePermissionSeeder)->run();

        $clinic = new ClinicModel;
        $clinic->forceFill(['name' => $clinicName, 'timezone' => 'Asia/Jakarta'])->save();

        $owner = new User;
        $owner->forceFill([
            'clinic_id' => $clinic->id, 'branch_id' => null, 'name' => $name, 'email' => $email, 'username' => $username,
            'password' => $password, 'is_active' => true, 'must_change_password' => true,
        ])->save();
        $owner->assignRole(Role::Pemilik->value);

        $this->info("Klinik dan pengguna Pemilik '{$username}' dibuat. Kata sandi wajib diganti saat masuk pertama.");

        return self::SUCCESS;
    }
}
