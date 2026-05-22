<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\OpenAIService;
use App\Support\TenantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiSettingsController extends Controller
{
    public function edit(TenantAccess $access): Response
    {
        $tenant = $access->ensureTenantAdmin(auth()->user());
        $settings = $tenant->aiSetting()->firstOrCreate([], [
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
        ]);

        return Inertia::render('AiSettings/Edit', [
            'settings' => [
                'provider' => $settings->provider,
                'masked_api_key' => $settings->maskedApiKey(),
                'base_url' => $settings->base_url,
                'chat_model' => $settings->chat_model,
                'embedding_model' => $settings->embedding_model,
                'embedding_dimensions' => $settings->embedding_dimensions,
                'is_active' => $settings->is_active,
                'last_tested_at' => $settings->last_tested_at,
                'last_test_status' => $settings->last_test_status,
                'last_test_error' => $settings->last_test_error,
            ],
        ]);
    }

    public function update(Request $request, TenantAccess $access): RedirectResponse
    {
        $tenant = $access->ensureTenantAdmin(auth()->user());
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['required', 'url', 'max:255'],
            'chat_model' => ['required', 'string', 'max:100'],
            'embedding_model' => ['required', 'string', 'max:100'],
        ]);

        $settings = $tenant->aiSetting()->firstOrNew();
        $settings->fill([
            'provider' => 'openai',
            'base_url' => $data['base_url'],
            'chat_model' => $data['chat_model'],
            'embedding_model' => $data['embedding_model'],
            'embedding_dimensions' => 1536,
        ]);

        if (filled($data['api_key'] ?? null)) {
            $settings->api_key = $data['api_key'];
            $settings->is_active = false;
            $settings->last_test_status = null;
            $settings->last_test_error = null;
        }

        $settings->save();

        return back()->with('success', 'AI settings saved.');
    }

    public function test(TenantAccess $access, OpenAIService $openAI): RedirectResponse
    {
        $tenant = $access->ensureTenantAdmin(auth()->user());
        $settings = $tenant->aiSetting()->firstOrCreate();

        try {
            $openAI->testApiKey($tenant->fresh('aiSetting'));
            $settings->update([
                'is_active' => true,
                'last_tested_at' => now(),
                'last_test_status' => 'success',
                'last_test_error' => null,
            ]);
        } catch (Throwable $e) {
            $settings->update([
                'is_active' => false,
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_error' => str($e->getMessage())->limit(500)->toString(),
            ]);
        }

        return back();
    }
}
