<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KnowledgeSourceStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeSourceJob;
use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Support\TenantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeSourceController extends Controller
{
    public function index(Request $request, TenantAccess $access): Response
    {
        $user = auth()->user();
        $search = $request->string('search')->trim()->toString();

        $baseQuery = $access->scope(KnowledgeSource::query(), $user);

        $statsQuery = clone $baseQuery;

        $sources = (clone $baseQuery)
            ->with('bot:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term) {
                    $query->where('title', 'like', $term)
                        ->orWhere('original_file_name', 'like', $term)
                        ->orWhere('type', 'like', $term)
                        ->orWhere('status', 'like', $term)
                        ->orWhereHas('bot', fn ($botQuery) => $botQuery->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Knowledge/Index', [
            'sources' => $sources,
            'bots' => $access->scope(Bot::query(), $user)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
            ],
            'stats' => [
                'total' => $statsQuery->count(),
                'ready' => (clone $statsQuery)->where('status', KnowledgeSourceStatus::Ready->value)->count(),
                'processing' => (clone $statsQuery)->whereIn('status', [
                    KnowledgeSourceStatus::Queued->value,
                    KnowledgeSourceStatus::Processing->value,
                    'draft',
                ])->count(),
                'failed' => (clone $statsQuery)->where('status', KnowledgeSourceStatus::Failed->value)->count(),
                'chunks' => (int) (clone $statsQuery)->sum('chunks_count'),
            ],
            'hasActiveSources' => (clone $statsQuery)->whereIn('status', [
                KnowledgeSourceStatus::Queued->value,
                KnowledgeSourceStatus::Processing->value,
                'draft',
            ])->exists(),
            'research' => [
                'search_provider' => filled(config('corebot.knowledge_research.tavily_api_key')) ? 'tavily' : 'duckduckgo',
            ],
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
            'status' => KnowledgeSourceStatus::Queued->value,
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
        $this->queueProcessing($source);

        return back()->with('success', 'Knowledge source queued for processing.');
    }

    public function show(KnowledgeSource $knowledgeSource, TenantAccess $access): Response
    {
        $access->ensureCanAccess(auth()->user(), $knowledgeSource);

        $source = $knowledgeSource->load('bot:id,name');
        $faq = $source->type === 'faq' ? $this->parseFaqRawText($source->raw_text) : null;

        return Inertia::render('Knowledge/Show', [
            'source' => $source,
            'faq' => $faq,
            'chunks' => $knowledgeSource->chunks()->orderBy('chunk_index')->get(),
        ]);
    }

    public function update(Request $request, KnowledgeSource $knowledgeSource, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $knowledgeSource);

        $status = KnowledgeSourceStatus::fromStored($knowledgeSource->status);

        if (! $status->canEdit()) {
            throw ValidationException::withMessages([
                'title' => 'This source cannot be edited while it is queued or processing.',
            ]);
        }

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'raw_text' => ['nullable', 'string'],
            'question' => ['nullable', 'string'],
            'answer' => ['nullable', 'string'],
        ];

        $data = $request->validate($rules);

        $attributes = ['title' => $data['title']];

        if ($knowledgeSource->type === 'faq') {
            $attributes['raw_text'] = "Question: {$data['question']}\nAnswer: {$data['answer']}";
        } elseif ($knowledgeSource->type === 'text') {
            $attributes['raw_text'] = $data['raw_text'];
        }

        $knowledgeSource->update($attributes);
        $this->queueProcessing($knowledgeSource);

        return back()->with('success', 'Knowledge source updated and queued for reprocessing.');
    }

    public function reprocess(KnowledgeSource $knowledgeSource, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $knowledgeSource);

        $status = KnowledgeSourceStatus::fromStored($knowledgeSource->status);

        if ($status->isInProgress()) {
            return back()->with('error', 'This source is already queued or processing.');
        }

        $this->queueProcessing($knowledgeSource);

        return back()->with('success', 'Knowledge source queued for reprocessing.');
    }

    public function cancel(KnowledgeSource $knowledgeSource, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $knowledgeSource);

        $status = KnowledgeSourceStatus::fromStored($knowledgeSource->status);

        if (! $status->canCancel()) {
            return back()->with('error', 'Only queued or processing sources can be cancelled.');
        }

        $knowledgeSource->update([
            'status' => KnowledgeSourceStatus::Cancelled->value,
            'error_message' => null,
        ]);

        return back()->with('success', 'Processing cancelled.');
    }

    public function destroy(KnowledgeSource $knowledgeSource, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $knowledgeSource);
        $knowledgeSource->delete();

        return back()->with('success', 'Knowledge source deleted.');
    }

    private function queueProcessing(KnowledgeSource $source): void
    {
        $source->update([
            'status' => KnowledgeSourceStatus::Queued->value,
            'error_message' => null,
        ]);

        ProcessKnowledgeSourceJob::dispatch($source->id);
    }

    /**
     * @return array{question: string, answer: string}
     */
    private function parseFaqRawText(?string $rawText): array
    {
        if (preg_match('/^Question:\s*(.*?)\nAnswer:\s*(.*)$/s', (string) $rawText, $matches)) {
            return [
                'question' => $matches[1],
                'answer' => $matches[2],
            ];
        }

        return [
            'question' => '',
            'answer' => (string) $rawText,
        ];
    }
}
