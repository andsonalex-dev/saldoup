<?php

namespace App\Services\Memberships;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

class Permissions
{
    public const LABELS = ['accounts.adjust' => 'Informar saldo atual e registrar ajuste manual', 'cards.view' => 'Visualizar cartões autorizados', 'cards.create' => 'Criar cartões', 'cards.purchase' => 'Registrar compras em cartões autorizados', 'cards.pay' => 'Pagar faturas autorizadas', 'accounts.view' => 'Visualizar contas autorizadas', 'accounts.create' => 'Criar contas', 'accounts.update' => 'Editar contas autorizadas', 'transactions.view' => 'Visualizar lançamentos autorizados', 'transactions.create' => 'Criar lançamentos', 'transactions.reverse' => 'Efetivar, cancelar e estornar lançamentos', 'members.manage' => 'Gerenciar membros e convites'];

    public function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        $query = $workspace->memberships()->where('user_id', $user->id)->where('status', 'active');
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function snapshot(User $user, Workspace $workspace): array
    {
        $member = $this->membership($user, $workspace);
        $allowed = array_fill_keys(array_keys(self::LABELS), false);
        if (! $member || ! $user->is_active) {
            return $allowed;
        }
        foreach ($allowed as $permission => $_) {
            if ($member->role === WorkspaceRole::Owner && $workspace->owner_id === $user->id) {
                $allowed[$permission] = true;

                continue;
            }
            if ($member->role === WorkspaceRole::Reader && ! in_array($permission, ['accounts.view', 'transactions.view', 'cards.view'], true)) {
                continue;
            }
            if ($permission === 'members.manage' && $member->role !== WorkspaceRole::Administrator) {
                continue;
            }
            $allowed[$permission] = in_array($permission, $member->permissions ?? [], true);
        }

        return $allowed;
    }

    public function allows(User $user, Workspace $workspace, string $permission): bool
    {
        return $this->snapshot($user, $workspace)[$permission] ?? false;
    }

    public function authorize(User $user, Workspace $workspace, string $permission): void
    {
        abort_unless($this->allows($user, $workspace, $permission), 403);
    }
}
