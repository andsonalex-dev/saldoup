<?php

namespace App\Services\Memberships;

use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkspaceService
{
    public function create(User $actor, array $data): Workspace
    {
        return DB::transaction(function () use ($actor, $data): Workspace {
            $workspace = Workspace::create([
                'name' => $data['name'], 'timezone' => $data['timezone'],
                'owner_id' => $actor->id, 'created_by' => $actor->id,
            ]);
            $workspace->memberships()->create([
                'user_id' => $actor->id, 'role' => WorkspaceRole::Owner, 'status' => 'active',
            ]);
            $this->audit($actor, $workspace, 'created', ['name', 'timezone', 'owner_id', 'created_by']);

            return $workspace;
        });
    }

    public function update(User $actor, Workspace $workspace, array $data): void
    {
        DB::transaction(function () use ($actor, $workspace, $data): void {
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            Gate::forUser($actor)->authorize('update', $workspace);
            $workspace->fill(['name' => $data['name'], 'timezone' => $data['timezone']]);
            $changed = array_keys($workspace->getDirty());

            if ($changed !== []) {
                $workspace->save();
                $this->audit($actor, $workspace, 'updated', $changed);
            }
        });
    }

    private function audit(User $actor, Workspace $workspace, string $action, array $fields): void
    {
        AuditLog::create([
            'workspace_id' => $workspace->id, 'actor_id' => $actor->id,
            'entity_type' => 'workspace', 'entity_id' => $workspace->id,
            'action' => $action, 'changed_fields' => $fields, 'created_at' => now(),
        ]);
    }
}
