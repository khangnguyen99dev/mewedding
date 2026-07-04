<?php

use App\Http\Controllers\Public\InvitationPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| - /admin/*  -> Vue SPA shell (admin)
| - /{slug}   -> public invitation page (must stay LAST: it is a catch-all)
*/

// Authenticated invitation preview for the admin editor iframe (any status, uncached).
// Registered before the SPA + slug catch-alls.
Route::get('/preview/{invitation}', [InvitationPageController::class, 'preview'])
    ->middleware('auth')
    ->name('invitation.preview');

Route::view('/admin/{any?}', 'admin')->where('any', '.*')->name('admin');

// Route::redirect (not a closure) so `php artisan route:cache` works in production.
Route::redirect('/', '/admin');

// Public invitation — single lowercase slug segment. Registered last so it never
// shadows /admin, /api, /storage, /up, /build, etc.
Route::get('/{slug}', [InvitationPageController::class, 'show'])
    ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
    ->name('invitation.show');
