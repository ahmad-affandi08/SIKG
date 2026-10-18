<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// P3-05: klinik yang sempat memakai Midtrans tidak boleh membawa kredensial lama sebagai kredensial DOKU. Pengaturan lama
// dinonaktifkan dan dikosongkan (tidak dihapus), dan niat bayar yang masih menunggu ditutup agar tidak menggantung.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('online_payment_settings')->where('provider', '!=', 'doku')->update([
            'enabled' => false, 'provider' => 'doku', 'client_id' => null, 'secret_key_encrypted' => null, 'updated_at' => now(),
        ]);
        DB::table('payment_intents')->where('provider', '!=', 'doku')->where('status', 'pending')->update([
            'status' => 'cancelled', 'active_slot' => null, 'note' => 'Ditutup saat pergantian penyedia ke DOKU.', 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Data lama sengaja tidak dipulihkan.
    }
};
