<?php

namespace App\Services\Memberships;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OwnershipTransfer
{
    public function transfer(User $actor, int $workspace, int $user, array $permissions): void
    {
        DB::transaction(function () use ($actor, $workspace, $user, $permissions) {
            $w = Workspace::query()->lockForUpdate()->findOrFail($workspace);
            Gate::forUser($actor)->authorize('update', $w);
            abort_if($user === $actor->id, 422, 'Escolha outro membro.');
            User::where('is_active', true)->lockForUpdate()->findOrFail($user);
            $members = $w->memberships()->whereIn('user_id', [$actor->id, $user])->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id');
            $target = $members->get($user);
            $current = $members->get($actor->id);
            abort_unless($target && $target->status === 'active', 404);
            abort_if($target->role->value === 'owner', 422);
            $permissions = array_values(array_diff(array_unique($permissions), ['members.manage']));
            $current->update(['role' => 'member', 'permissions' => $permissions]);
            $target->update(['role' => 'owner', 'permissions' => []]);
            $w->update(['owner_id' => $user]);
            $ledger = app(Ledger::class);
            $ledger->audit($actor, $w->id, 'workspace', $w->id, 'ownership_transferred', ['owner_id']);
            $ledger->audit($actor, $w->id, 'membership', $current->id, 'ownership_relinquished', ['role', 'permissions']);
            $ledger->audit($actor, $w->id, 'membership', $target->id, 'ownership_received', ['role', 'permissions']);
        }, 3);
    }
}
