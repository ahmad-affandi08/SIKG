<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Denyut scheduler dan worker antrean (SYS-07). Tanpa PHI.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->dateTime('beat_at');
            $table->json('meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
    }
};
