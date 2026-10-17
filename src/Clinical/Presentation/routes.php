<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.
