<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// P2-05: stok lanjutan. Pemasok dan pesanan pembelian, batch dengan sisa per batch (FEFO), bahan habis pakai per tindakan, dan
// opname terjadwal. Uang BIGINT rupiah (M4). Mutasi stok tetap hanya bertambah; batch hanya menyimpan alokasi sisa.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('name', 150);
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->index(['clinic_id', 'name']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('po_no', 30);
            $table->char('supplier_id', 26);
            $table->string('status', 10); // draft, ordered, partial, received, cancelled
            $table->dateTime('ordered_at')->nullable();
            $table->date('expected_date')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->unique(['clinic_id', 'po_no']);
            $table->index(['status', 'supplier_id']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('purchase_order_id', 26);
            $table->char('medicine_id', 26);
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedBigInteger('unit_cost_amount'); // salinan harga beli saat dipesan
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
            $table->index('purchase_order_id');
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('medicine_id', 26);
            $table->string('batch_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('received_quantity');
            $table->unsignedInteger('remaining_quantity');
            $table->unsignedBigInteger('unit_cost_amount')->nullable();
            $table->char('supplier_id', 26)->nullable();
            $table->char('purchase_order_id', 26)->nullable();
            $table->dateTime('received_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete();
            $table->index(['medicine_id', 'expiry_date']);
        });

        Schema::create('procedure_materials', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('procedure_id', 26);
            $table->char('medicine_id', 26);
            $table->unsignedSmallInteger('quantity');
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('procedure_id')->references('id')->on('procedures')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
            $table->unique(['procedure_id', 'medicine_id']);
        });

        Schema::create('stock_count_schedules', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('interval_days')->default(30);
            $table->date('next_due_date')->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->unique('clinic_id');
        });

        Schema::create('stock_count_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->date('scheduled_for');
            $table->string('status', 10); // open, completed, cancelled
            $table->string('source', 10); // schedule, manual
            $table->string('note', 255)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('completed_by', 26)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('session_id', 26);
            $table->char('medicine_id', 26);
            $table->unsignedInteger('system_quantity');
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('session_id')->references('id')->on('stock_count_sessions')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
            $table->unique(['session_id', 'medicine_id']);
        });

        $this->backfillBatches();
    }

    /**
     * Saldo yang sudah ada sebelum pelacakan batch dibagi ke batch dari mutasi masuk terbaru (tanggal kedaluwarsa ikut), sisanya
     * ke satu batch tanpa tanggal, sehingga jumlah sisa batch sama dengan saldo.
     */
    private function backfillBatches(): void
    {
        $now = now();
        foreach (DB::table('stock_balances')->where('quantity', '>', 0)->get() as $balance) {
            $left = (int) $balance->quantity;
            $rows = [];
            $base = [
                'clinic_id' => $balance->clinic_id,
                'branch_id' => $balance->branch_id,
                'medicine_id' => $balance->medicine_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $movements = DB::table('stock_movements')->where('medicine_id', $balance->medicine_id)->where('type', 'in')->where('quantity', '>', 0)
                ->orderByDesc('created_at')->orderByDesc('id')->get();
            foreach ($movements as $movement) {
                if ($left <= 0) {
                    break;
                }
                $take = min($left, (int) $movement->quantity);
                $rows[] = [...$base, 'id' => (string) Str::ulid(), 'batch_no' => $movement->batch_no, 'expiry_date' => $movement->expiry_date, 'received_quantity' => (int) $movement->quantity, 'remaining_quantity' => $take, 'received_at' => $movement->created_at];
                $left -= $take;
            }
            if ($left > 0) {
                $rows[] = [...$base, 'id' => (string) Str::ulid(), 'batch_no' => null, 'expiry_date' => null, 'received_quantity' => $left, 'remaining_quantity' => $left, 'received_at' => $now];
            }
            if ($rows !== []) {
                DB::table('stock_batches')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_count_sessions');
        Schema::dropIfExists('stock_count_schedules');
        Schema::dropIfExists('procedure_materials');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
