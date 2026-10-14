<?php

namespace App\Services\Memberships;

use App\Models\Workspace;
use Illuminate\Http\Request;

class ActiveWorkspace
{
    public function resolve(Request $request): ?Workspace
    {
        $id = $request->session()->get('active_workspace_id');
        if ($id === null) {
            return null;
        }

        $workspace = $request->user()->workspaces()->where('workspaces.id', $id)->first();
        if ($workspace === null) {
            $request->session()->forget('active_workspace_id');
        }

        return $workspace;
    }
}
