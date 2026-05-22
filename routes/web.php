<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\BotController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KnowledgeSourceController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\WidgetInstallController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::get('/demo', DemoController::class)->name('demo');

Route::post('/support-requests', [SupportRequestController::class, 'store'])
    ->middleware('throttle:support-request')
    ->name('support-requests.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');

    Route::get('/ai-settings', [AiSettingsController::class, 'edit'])->name('ai-settings.edit');
    Route::put('/ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
    Route::post('/ai-settings/test', [AiSettingsController::class, 'test'])->name('ai-settings.test');

    Route::resource('bots', BotController::class)->except(['show']);

    Route::resource('knowledge-sources', KnowledgeSourceController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('/knowledge-sources/{knowledgeSource}/reprocess', [KnowledgeSourceController::class, 'reprocess'])->name('knowledge-sources.reprocess');

    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');

    Route::get('/widget-install', WidgetInstallController::class)->name('widget.install');
    Route::put('/widget-install/{bot}', [WidgetInstallController::class, 'update'])->name('widget.install.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
