<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function settings(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/Workspace', ['section' => 'settings', 'tenants' => $request->user()->isSuperAdmin() ? Tenant::query()->orderBy('name')->get(['id', 'name']) : []]);
    }

    public function admin(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return Inertia::render('Admin/Workspace', ['section' => 'admin']);
    }
}
