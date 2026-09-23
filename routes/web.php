<?php

use App\Http\Controllers\Admin\AvatarController;
use App\Http\Controllers\Admin\ConversationVersionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\PublicAvatarController;
use Illuminate\Support\Facades\Route;

Route::middleware('avatar.host')->group(function (): void {
    Route::get('/', [PublicAvatarController::class, 'landing'])->name('avatar.landing');
    Route::get('/llamada', [PublicAvatarController::class, 'call'])->name('avatar.call');
    Route::prefix('asistente')->controller(PublicAvatarController::class)->group(function (): void {
        Route::get('/estado', 'status')->name('avatar.status');
        Route::post('/saludo', 'greeting')->name('avatar.greeting');
        Route::post('/mensaje', 'message')->middleware('throttle:20,1')->name('avatar.message');
        Route::get('/respuestas/{ticket}', 'poll')->middleware('throttle:60,1')->name('avatar.poll');
    });
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function (): void {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::resource('avatars', AvatarController::class);
        Route::get('avatars/{avatar}/versions/create', [ConversationVersionController::class, 'create'])->name('versions.create');
        Route::post('avatars/{avatar}/versions', [ConversationVersionController::class, 'store'])->name('versions.store');
        Route::get('avatars/{avatar}/versions/{version}', [ConversationVersionController::class, 'show'])->name('versions.show');
        Route::post('avatars/{avatar}/versions/{version}/publish', [ConversationVersionController::class, 'publish'])->name('versions.publish');
    });
});
