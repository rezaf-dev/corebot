<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Knowledge\KnowledgeResearchService;
use App\Support\TenantAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class KnowledgeResearchController extends Controller
{
    public function store(Request $request, TenantAccess $access, KnowledgeResearchService $research): JsonResponse
    {
        $access->ensureTenantAdmin(auth()->user());

        $data = $request->validate([
            'mode' => ['required', 'in:url,search'],
            'query' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $data['mode'] === 'url'
                ? $research->fromUrl($data['query'])
                : $research->fromSearch($data['query']);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Could not fetch content. Please try again.',
            ], 422);
        }

        return response()->json($result);
    }
}
