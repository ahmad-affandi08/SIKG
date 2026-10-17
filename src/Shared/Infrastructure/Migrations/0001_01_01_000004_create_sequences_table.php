<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nomor berurut dengan kunci baris (ADR-0004, docs/rules/database.md).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name', 50);
            $table->string('scope', 100)->default('');
            $table->unsignedBigInteger('next_value')->default(0);
            $table->primary(['name', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
