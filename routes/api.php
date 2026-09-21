<?php

use App\Http\Controllers\PublicChatController;
use App\Http\Controllers\PublicChatFeedbackController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:public-chat')->prefix('public/chat')->group(function () {
    Route::get('/widget-config', [PublicChatController::class, 'widgetConfig']);
    Route::post('/start', [PublicChatController::class, 'start']);
    Route::post('/message', [PublicChatController::class, 'message']);
    Route::post('/message/stream', [PublicChatController::class, 'stream']);
    Route::post('/contact', [PublicChatController::class, 'contact']);
    Route::post('/manual-messages', [PublicChatController::class, 'manualMessages']);
    Route::post('/feedback', [PublicChatFeedbackController::class, 'store']);
});
