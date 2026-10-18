<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Clinic\Infrastructure\Eloquent\ChairModel;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Clinic\Infrastructure\Eloquent\DoctorModel;
use Src\Clinic\Infrastructure\Eloquent\DoctorScheduleModel;
use Src\Clinic\Infrastructure\Eloquent\ProcedureModel;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\IdentityAccess\Infrastructure\Seeders\RolePermissionSeeder;
use Src\Pharmacy\Application\StockService;
use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Shared\Application\TenantContext;

/**
 * Data contoh untuk mencoba aplikasi di komputer lokal: satu klinik, pengguna tiap peran, dokter, kursi, tarif,
 * obat, dan tiga pasien sintetis (M12). Tidak pernah dijalankan di produksi. Kata sandi dibuat acak dan dicetak sekali.
 */
class DemoSeeder extends Seeder
{
    private function makeUser(ClinicModel $clinic, string $password, Role $role, string $name, string $username): User
    {
        $user = new User;
        $user->forceFill([
            'clinic_id' => $clinic->id, 'branch_id' => null, 'name' => $name, 'email' => "{$username}@contoh.test",
            'username' => $username, 'password' => $password, 'is_active' => true,
        ])->save();
        $user->assignRole($role->value);

        return $user;
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoSeeder tidak boleh dijalankan di produksi.');

            return;
        }
        if (DB::table('clinics')->exists()) {
            $this->command->warn('Sudah ada klinik di basis data ini; data contoh tidak ditambahkan.');

            return;
        }

        $this->call(RolePermissionSeeder::class);
        $password = Str::password(14, symbols: false);

        $clinic = new ClinicModel;
        $clinic->forceFill(['name' => 'Klinik Gigi Senyum Sehat', 'timezone' => 'Asia/Jakarta'])->save();

        $admin = $this->makeUser($clinic, $password, Role::Admin, 'Admin Contoh', 'admin');
        foreach ([
            [Role::Pemilik, 'Pemilik Contoh', 'pemilik'],
            [Role::Resepsionis, 'Rina Resepsionis', 'rina'],
            [Role::Dokter, 'drg. Sari Wulandari', 'sari'],
            [Role::Perawat, 'Nia Perawat', 'nia'],
            [Role::Kasir, 'Kiki Kasir', 'kasir'],
        ] as [$role, $name, $username]) {
            $this->makeUser($clinic, $password, $role, $name, $username);
        }

        app(TenantContext::class)->set($clinic->id);

        $doctor = new DoctorModel;
        $doctor->forceFill(['name' => 'drg. Sari Wulandari', 'is_active' => true, 'fee_scheme' => null])->save();
        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            (new DoctorScheduleModel)->forceFill(['doctor_id' => $doctor->id, 'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '17:00:00'])->save();
        }
        foreach (['Kursi 1', 'Kursi 2'] as $name) {
            (new ChairModel)->forceFill(['name' => $name, 'is_active' => true])->save();
        }

        foreach ([['TMB', 'Tambalan komposit', 'tooth', 350000], ['SCL', 'Scaling', 'jaw', 250000], ['CAB', 'Cabut gigi dewasa', 'tooth', 300000]] as [$code, $name, $scope, $price]) {
            (new ProcedureModel)->forceFill(['code' => $code, 'name' => $name, 'scope' => $scope, 'default_price_amount' => $price, 'is_active' => true])->save();
        }

        $stock = app(StockService::class);
        foreach ([
            ['Amoksisilin 500 mg', 'kapsul', 2000, 20, 'Penisilin', 120],
            ['Parasetamol 500 mg', 'tablet', 1000, 50, null, 200],
            ['Asam mefenamat 500 mg', 'tablet', 1500, 30, null, 90],
            ['Povidone iodine 10%', 'botol', 25000, 5, null, 12],
        ] as [$name, $unit, $price, $min, $class, $qty]) {
            $medicine = new MedicineModel;
            $medicine->forceFill(['name' => $name, 'unit' => $unit, 'sell_price_amount' => $price, 'min_stock' => $min, 'drug_class' => $class, 'is_active' => true])->save();
            $stock->receive($admin->id, $medicine->id, $qty, null, null, 'Stok awal contoh');
        }

        foreach (['Budi Santoso' => '1990-01-01', 'Siti Rahmawati' => '1985-02-02', 'Andi Pratama' => '2000-03-03'] as $name => $born) {
            static $n = 0;
            $n++;
            DB::table('patients')->insert([
                'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $clinic->id, 'medical_record_no' => sprintf('CTH-%05d', $n),
                'name' => $name, 'name_search' => mb_strtolower($name), 'birth_date' => $born, 'sex' => $n === 2 ? 'female' : 'male',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->command->info("Data contoh dibuat. Masuk dengan nama pengguna admin, pemilik, rina, sari, nia, atau kasir. Kata sandi (semua pengguna): {$password}");
    }
}
