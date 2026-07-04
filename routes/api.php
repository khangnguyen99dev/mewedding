<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Public\PublicInteractionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public, unauthenticated interaction endpoints (rate-limited + sanitized)
|--------------------------------------------------------------------------
*/
Route::prefix('public/{slug}')->middleware('throttle:20,1')->group(function () {
    Route::get('/guestbook', [PublicInteractionController::class, 'guestbookIndex']);
    Route::post('/guestbook', [PublicInteractionController::class, 'guestbookStore']);
    Route::post('/rsvp', [PublicInteractionController::class, 'rsvp']);
    Route::get('/stats', [PublicInteractionController::class, 'stats']);
});

/*
|--------------------------------------------------------------------------
| API Routes (Sanctum SPA cookie auth)
|--------------------------------------------------------------------------
*/

// ---- Authentication ----
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ---- Templates (read-only registry) ----
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::get('/templates/{key}', [TemplateController::class, 'show']);

    // ---- Invitations ----
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::get('/invitations/{invitation}', [InvitationController::class, 'show']);
    Route::match(['put', 'patch'], '/invitations/{invitation}', [InvitationController::class, 'update']);
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy']);
    Route::post('/invitations/{invitation}/duplicate', [InvitationController::class, 'duplicate']);
    Route::post('/invitations/{invitation}/publish', [InvitationController::class, 'publish']);
    Route::post('/invitations/{invitation}/unpublish', [InvitationController::class, 'unpublish']);
    Route::post('/invitations/{invitation}/preview', [InvitationController::class, 'storeDraft']);

    // ---- Media ----
    Route::post('/invitations/{invitation}/media', [MediaController::class, 'store']);
    Route::delete('/invitations/{invitation}/media/{media}', [MediaController::class, 'destroy']);

    // ---- Guests: RSVP + guestbook moderation ----
    Route::get('/invitations/{invitation}/rsvps', [GuestController::class, 'rsvps']);
    Route::get('/invitations/{invitation}/guestbook', [GuestController::class, 'guestbook']);
    Route::patch('/invitations/{invitation}/guestbook/{message}', [GuestController::class, 'moderate']);
    Route::delete('/invitations/{invitation}/guestbook/{message}', [GuestController::class, 'destroyGuestbook']);
});
