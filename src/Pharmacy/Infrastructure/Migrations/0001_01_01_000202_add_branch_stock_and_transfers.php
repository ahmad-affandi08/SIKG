<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P3-04: stok per cabang. Saldo dan batch kini milik satu cabang (branch_id kosong = gudang pusat/klinik tanpa cabang). Keunikan saldo
// per obat berubah menjadi per obat dan cabang lewat kolom turunan `branch_key` (kolom biasa, bukan trigger; M21). Transfer antar
// cabang tercatat hanya-tambah di stock_transfers dan menghasilkan dua mutasi (keluar dan masuk) serta batch tujuan bertanda transfer_id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable(); // cabang asal
            $table->char('from_branch_id', 26)->nullable();
            $table->char('to_branch_id', 26)->nullable();
            $table->char('medicine_id', 26);
            $table->unsignedInteger('quantity');
            $table->string('reason', 255)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('from_branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('to_branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
            $table->index(['clinic_id', 'created_at']);
        });

        // Indeks baru lebih dulu agar kunci asing medicine_id selalu punya indeks pendukung ketika yang lama dihapus.
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->char('branch_key', 26)->storedAs("COALESCE(branch_id, '00000000000000000000000000')")->after('branch_id');
            $table->unique(['medicine_id', 'branch_key'], 'stock_balances_medicine_branch_unique');
        });
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropUnique('stock_balances_medicine_id_unique');
        });

        Schema::table('stock_batches', function (Blueprint $table) {
            $table->char('transfer_id', 26)->nullable()->after('purchase_order_id');
            $table->foreign('transfer_id')->references('id')->on('stock_transfers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->dropForeign(['transfer_id']);
            $table->dropColumn('transfer_id');
        });
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->unique('medicine_id', 'stock_balances_medicine_id_unique');
        });
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropUnique('stock_balances_medicine_branch_unique');
            $table->dropColumn('branch_key');
        });
        Schema::dropIfExists('stock_transfers');
    }
};
