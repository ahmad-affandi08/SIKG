<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SYS-02: catatan jalannya cadangan, uji pemulihan, salinan berkas, dan pemangkasan. Hanya bertambah; tanpa PHI.
// Tabel ini tidak ikut cadangan data (riwayat cadangan tidak perlu dipulihkan).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('kind', 10); // backup, verify, prune, mirror
            $table->boolean('ok');
            $table->string('file', 120)->nullable();
            $table->string('message', 500);
            $table->json('details')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finished_at');

            $table->index(['kind', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
