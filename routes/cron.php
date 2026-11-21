<?php

use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

// Stateless (no session / CSRF) and protected by a bearer token (CRON_SECRET).
Route::get('/internal/cron/{group}', CronController::class)
    ->where('group', 'frequent|hourly|daily');
