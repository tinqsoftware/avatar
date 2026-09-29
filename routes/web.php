<?php

use App\Http\Controllers\Admin\AudioStudioController;
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
    Route::prefix('prueba')->controller(PublicAvatarController::class)->group(function (): void {
        Route::get('/llamada', 'previewCall')->name('avatar.preview.call');
        Route::get('/asistente/estado', 'previewStatus')->name('avatar.preview.status');
        Route::post('/asistente/saludo', 'previewGreeting')->name('avatar.preview.greeting');
        Route::post('/asistente/mensaje', 'previewMessage')->middleware('throttle:20,1')->name('avatar.preview.message');
        Route::get('/asistente/respuestas/{ticket}', 'previewPoll')->middleware('throttle:60,1')->name('avatar.preview.poll');
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
        Route::get('audio-studio', [AudioStudioController::class, 'index'])->name('audio-studio.index');
        Route::post('audio-studio/import', [AudioStudioController::class, 'import'])->name('audio-studio.import');
        Route::get('avatars/{avatar}/audio-studio', [AudioStudioController::class, 'show'])->name('audio-studio.show');
        Route::post('avatars/{avatar}/audio-studio/samples', [AudioStudioController::class, 'storeSamples'])->name('audio-studio.samples.store');
        Route::post('avatars/{avatar}/audio-studio/rive', [AudioStudioController::class, 'storeRive'])->name('audio-studio.rive.store');
        Route::post('avatars/{avatar}/audio-studio/destination', [AudioStudioController::class, 'destination'])->name('audio-studio.destination');
        Route::get('avatars/{avatar}/versions/{version}/audio-preview', [AudioStudioController::class, 'preview'])->name('audio-studio.preview');
        Route::patch('avatars/{avatar}/versions/{version}/audio-line', [AudioStudioController::class, 'updateLine'])->name('audio-studio.line.update');
        Route::post('avatars/{avatar}/versions/{version}/approve-preview', [AudioStudioController::class, 'approve'])->name('audio-studio.approve');
        Route::post('avatars/{avatar}/versions/{version}/upload', [AudioStudioController::class, 'upload'])->name('audio-studio.upload');
        Route::get('avatars/{avatar}/versions/create', [ConversationVersionController::class, 'create'])->name('versions.create');
        Route::post('avatars/{avatar}/versions', [ConversationVersionController::class, 'store'])->name('versions.store');
        Route::get('avatars/{avatar}/versions/{version}', [ConversationVersionController::class, 'show'])->name('versions.show');
        Route::post('avatars/{avatar}/versions/{version}/publish', [ConversationVersionController::class, 'publish'])->name('versions.publish');
        Route::post('avatars/{avatar}/versions/{version}/resume', [ConversationVersionController::class, 'resume'])->name('versions.resume');
    });
});
