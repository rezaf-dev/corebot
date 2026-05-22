<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeSourceJob;
use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Support\TenantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeSourceController extends Controller
{
    public function index(TenantAccess $access): Response
    {
        $user = auth()->user();

        return Inertia::render('Knowledge/Index', [
            'sources' => $access->scope(KnowledgeSource::query(), $user)->with('bot:id,name')->latest()->get(),
            'bots' => $access->scope(Bot::query(), $user)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, TenantAccess $access): RedirectResponse
    {
        $tenant = $access->ensureTenantAdmin(auth()->user());
        $data = $request->validate([
            'bot_id' => ['required', 'integer'],
            'type' => ['required', 'in:text,faq,pdf,docx'],
            'title' => ['required', 'string', 'max:255'],
            'raw_text' => ['nullable', 'string'],
            'question' => ['nullable', 'string'],
            'answer' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240', 'mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);

        $bot = $access->botForTenant(auth()->user(), $data['bot_id']);
        $attributes = [
            'tenant_id' => $tenant->id,
            'bot_id' => $bot->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'status' => 'draft',
        ];

        if ($data['type'] === 'faq') {
            $attributes['raw_text'] = "Question: {$data['question']}\nAnswer: {$data['answer']}";
        } elseif ($data['type'] === 'text') {
            $attributes['raw_text'] = $data['raw_text'];
        } else {
            $file = $request->file('file');
            $path = $file->store("tenants/{$tenant->id}/knowledge");
            $attributes += [
                'original_file_path' => $path,
                'original_file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        }

        $source = KnowledgeSource::create($attributes);
        ProcessKnowledgeSourceJob::dispatch($source->id);

        return back()->with('success', 'Knowledge source queued for processing.');
    }

    public function show(KnowledgeSource $knowledgeSource, TenantAccess $access): Response
    {
        $access->ensureCanAccess(auth()->user(), $knowledgeSource);

        return Inertia::render('Knowledge/Show', [
            'source' => $knowledgeSource->load('bot:id,name'),
            'chunks' => $knowledgeSource->chunks()->orderBy('chunk_index')->get(),
        ]);
    }

    public function reprocess(KnowledgeSource $knowledgeSource, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $knowledgeSource);
        ProcessKnowledgeSourceJob::dispatch($knowledgeSource->id);

        return back()->with('success', 'Knowledge source queued for reprocessing.');
    }

    public function destroy(KnowledgeSource $knowledgeSource, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $knowledgeSource);
        $knowledgeSource->delete();

        return back()->with('success', 'Knowledge source deleted.');
    }
}
