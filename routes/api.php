<?php

use App\Http\Controllers\InternalAudioSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/audio-sync')->middleware('throttle:10,1')->group(function (): void {
    Route::get('/avatars', [InternalAudioSyncController::class, 'avatars']);
    Route::post('/bundles', [InternalAudioSyncController::class, 'store']);
});
