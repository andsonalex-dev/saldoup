<?php

namespace App\Http\Controllers\Memberships;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvite;
use App\Services\Memberships\OwnershipTransfer;
use App\Services\Memberships\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MemberController extends Controller
{
    private function workspace(Request $r, int $id)
    {
        $w = $r->user()->workspaces()->findOrFail($id);
        app(Permissions::class)->authorize($r->user(), $w, 'members.manage');

        return $w;
    }

    private function audit(Request $r, int $workspace, int $entity, string $action, array $fields): void
    {
        AuditLog::create(['workspace_id' => $workspace, 'actor_id' => $r->user()->id, 'entity_type' => str_contains($action, 'invite') ? 'invitation' : 'membership', 'entity_id' => $entity, 'action' => $action, 'changed_fields' => $fields, 'created_at' => now()]);
    }

    private function permittedData(Request $r, $w): array
    {
        $data = $r->validate(['role' => ['required', Rule::in(['administrator', 'member', 'reader'])], 'permissions' => 'array', 'permissions.*' => Rule::in(array_keys(Permissions::LABELS)), 'current_password' => 'required|string|current_password']);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        if ($data['role'] === 'reader') {
            $data['permissions'] = array_values(array_intersect($data['permissions'], ['accounts.view', 'transactions.view', 'cards.view']));
        }
        if ($data['role'] !== 'administrator') {
            $data['permissions'] = array_values(array_diff($data['permissions'], ['members.manage']));
        }
        if ($w->owner_id !== $r->user()->id) {
            abort_if($data['role'] === 'administrator', 403);
            foreach ($data['permissions'] as $permission) {
                app(Permissions::class)->authorize($r->user(), $w, $permission);
            }
        }

        return $data;
    }

    public function index(Request $r, int $workspace)
    {
        $w = $this->workspace($r, $workspace);
        $members = DB::table('workspace_members')->join('users', 'users.id', '=', 'workspace_members.user_id')->where('workspace_id', $w->id)->select('workspace_members.*', 'users.name', 'users.email', 'users.is_active')->orderBy('workspace_members.id')->get();

        return view('workspaces.members', ['workspace' => $w, 'members' => $members, 'invitations' => WorkspaceInvitation::where('workspace_id', $w->id)->latest()->paginate(10)]);
    }

    public function update(Request $r, int $workspace, int $member)
    {
        $w = $this->workspace($r, $workspace);
        $data = $this->permittedData($r, $w);
        DB::transaction(function () use ($r, $w, $member, $data) {
            $m = $w->memberships()->lockForUpdate()->findOrFail($member);
            app(Permissions::class)->authorize($r->user(), $w, 'members.manage');
            abort_if($m->role === WorkspaceRole::Owner || ($w->owner_id !== $r->user()->id && $m->role === WorkspaceRole::Administrator), 403);
            $m->fill(['role' => $data['role'], 'permissions' => $data['permissions']])->save();
            $this->audit($r, $w->id, $m->id, 'permissions_updated', ['role', 'permissions']);
        });

        return back()->with('status', 'Permissões atualizadas.');
    }

    public function remove(Request $r, int $workspace, int $member)
    {
        $w = $this->workspace($r, $workspace);
        $r->validate(['current_password' => 'required|string|current_password']);
        DB::transaction(function () use ($r, $w, $member) {
            $m = $w->memberships()->lockForUpdate()->findOrFail($member);
            abort_if($m->role === WorkspaceRole::Owner || ($w->owner_id !== $r->user()->id && $m->role === WorkspaceRole::Administrator), 403);
            app(Permissions::class)->authorize($r->user(), $w, 'members.manage');
            $m->update(['status' => 'inactive']);
            $this->audit($r, $w->id, $m->id, 'removed', ['status']);
        });

        return back()->with('status', 'Membership desativada; histórico preservado.');
    }

    public function invite(Request $r, int $workspace)
    {
        $w = $this->workspace($r, $workspace);
        $data = $this->permittedData($r, $w);
        $r->validate(['email' => 'required|string|email|max:255']);
        $email = mb_strtolower(trim($r->input('email')));
        $r->merge(['email' => $email]);
        $r->validate(['email' => 'required|email|max:255']);
        $token = Str::random(64);
        $invite = DB::transaction(function () use ($r, $w, $data, $email, $token) {
            DB::table('workspaces')->where('id', $w->id)->lockForUpdate()->first();
            app(Permissions::class)->authorize($r->user(), $w, 'members.manage');
            abort_if($w->memberships()->where('status', 'active')->whereHas('user', fn ($q) => $q->where('email', $email))->exists(), 422, 'Este usuário já é membro.');
            WorkspaceInvitation::where('workspace_id', $w->id)->where('email', $email)->whereNull('accepted_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
            $invite = WorkspaceInvitation::create(['workspace_id' => $w->id, 'email' => $email, 'token_hash' => hash('sha256', $token), 'role' => $data['role'], 'permissions' => $data['permissions'], 'invited_by' => $r->user()->id, 'expires_at' => now()->addDays(7)]);
            $this->audit($r, $w->id, $invite->id, 'invited', ['role', 'permissions']);

            return $invite;
        });
        try {
            Notification::route('mail', $email)->notify(new WorkspaceInvite($token, $w->name));
        } catch (TransportExceptionInterface) {
            return back()->with('status', 'Convite registrado, mas a entrega falhou. Confira o SMTP e envie outro convite.');
        }

        return back()->with('status', 'Convite enviado.');
    }

    public function cancel(Request $r, int $workspace, int $invitation)
    {
        $w = $this->workspace($r, $workspace);
        $r->validate(['current_password' => 'required|string|current_password']);
        DB::transaction(function () use ($r, $w, $invitation) {
            $i = WorkspaceInvitation::where('workspace_id', $w->id)->lockForUpdate()->findOrFail($invitation);
            abort_if($i->accepted_at, 422);
            $i->update(['cancelled_at' => now()]);
            $this->audit($r, $w->id, $i->id, 'invite_cancelled', ['cancelled_at']);
        });

        return back()->with('status', 'Convite cancelado.');
    }

    public function transferOwnership(Request $r, int $workspace)
    {
        $w = $r->user()->workspaces()->findOrFail($workspace);
        $data = $r->validate(['user_id' => 'required|integer', 'current_password' => 'required|string|current_password', 'confirm_transfer' => 'required|accepted', 'former_owner_permissions' => 'array', 'former_owner_permissions.*' => Rule::in(array_keys(Permissions::LABELS))]);
        app(OwnershipTransfer::class)->transfer($r->user(), $w->id, (int) $data['user_id'], $data['former_owner_permissions'] ?? []);

        return redirect()->route('dashboard')->with('status', 'Propriedade transferida; registros privados preservam seus donos e autorizações.');
    }
}
