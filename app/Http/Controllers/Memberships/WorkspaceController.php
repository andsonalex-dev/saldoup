<?php

namespace App\Http\Controllers\Memberships;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memberships\WorkspaceRequest;
use App\Models\Workspace;
use App\Services\Memberships\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        return view('workspaces.index', [
            'workspaces' => $request->user()->workspaces()->orderBy('workspaces.name')->paginate(10),
        ]);
    }

    public function store(WorkspaceRequest $request, WorkspaceService $service): RedirectResponse
    {
        $workspace = $service->create($request->user(), $request->validated());
        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('dashboard')->with('status', 'Espaço financeiro criado.');
    }

    public function show(Request $request, int $workspace): View
    {
        $workspace = $this->accessible($request, $workspace);
        Gate::authorize('view', $workspace);

        return view('workspaces.show', compact('workspace'));
    }

    public function update(WorkspaceRequest $request, int $workspace, WorkspaceService $service): RedirectResponse
    {
        $workspace = $this->accessible($request, $workspace);
        $service->update($request->user(), $workspace, $request->validated());

        return redirect()->route('workspaces.show', $workspace)->with('status', 'Espaço financeiro atualizado.');
    }

    public function activate(Request $request, int $workspace): RedirectResponse
    {
        $workspace = $this->accessible($request, $workspace);
        Gate::authorize('view', $workspace);
        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('dashboard')->with('status', 'Espaço financeiro selecionado.');
    }

    private function accessible(Request $request, int $id): Workspace
    {
        return $request->user()->workspaces()->where('workspaces.id', $id)->firstOrFail();
    }
}
