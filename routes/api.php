<?php

use App\Http\Controllers\PublicChatController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:public-chat')->prefix('public/chat')->group(function () {
    Route::post('/start', [PublicChatController::class, 'start']);
    Route::post('/message', [PublicChatController::class, 'message']);
    Route::post('/message/stream', [PublicChatController::class, 'stream']);
    Route::post('/contact', [PublicChatController::class, 'contact']);
});
