<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Rute modul didefinisikan di Presentation/routes.php tiap konteks (dimuat ServiceProvider konteks).
Route::redirect('/', '/dashboard');
