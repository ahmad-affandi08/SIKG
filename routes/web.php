<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Kerangka Fase 0. Rute terautentikasi dan izin ditambahkan bersama AUTH-01 dan AUTH-02.
Route::get('/', fn () => Inertia::render('Beranda'));
