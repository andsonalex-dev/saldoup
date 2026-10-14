<?php

namespace App\Http\Controllers\Memberships;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Memberships\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvitationController extends Controller
{
    private function invitation(Request $r, string $token, bool $lock = false): WorkspaceInvitation
    {
        $q = WorkspaceInvitation::where('token_hash', hash('sha256', $token));
        if ($lock) {
            $q->lockForUpdate();
        } $i = $q->firstOrFail();
        abort_if($i->accepted_at || $i->cancelled_at || $i->expires_at->isPast(), 410, 'Convite expirado ou indisponível.');
        abort_unless(hash_equals($i->email, $r->user()->email), 403, 'Entre com o e-mail destinatário do convite.');

        return $i;
    }

    public function show(Request $r, string $token)
    {
        $i = $this->invitation($r, $token);

        return view('workspaces.invitation', ['invitation' => $i, 'workspace' => Workspace::findOrFail($i->workspace_id), 'token' => $token]);
    }

    public function accept(Request $r, string $token)
    {
        $id = DB::transaction(function () use ($r, $token) {
            $i = $this->invitation($r, $token, true);
            $w = Workspace::query()->lockForUpdate()->findOrFail($i->workspace_id);
            $grantor = User::find($i->invited_by);
            abort_unless($grantor && $grantor->is_active && app(Permissions::class)->allows($grantor, $w, 'members.manage'), 410, 'O responsável pelo convite não tem mais autorização.');
            foreach ($i->permissions as $permission) {
                abort_unless(app(Permissions::class)->allows($grantor, $w, $permission), 410, 'As permissões do responsável pelo convite mudaram.');
            }
            abort_if($i->role === 'administrator' && $w->owner_id !== $grantor->id, 410);
            $member = $w->memberships()->where('user_id', $r->user()->id)->first();
            if ($member) {
                abort_if($member->status === 'active', 422, 'Você já é membro deste espaço.');
                $member->update(['role' => $i->role, 'permissions' => $i->permissions, 'status' => 'active']);
            } else {
                $member = $w->memberships()->create(['user_id' => $r->user()->id, 'role' => $i->role, 'permissions' => $i->permissions, 'status' => 'active']);
            }
            $i->update(['accepted_at' => now()]);
            AuditLog::create(['workspace_id' => $w->id, 'actor_id' => $r->user()->id, 'entity_type' => 'membership', 'entity_id' => $member->id, 'action' => 'invite_accepted', 'changed_fields' => ['status', 'role', 'permissions'], 'created_at' => now()]);

            return $w->id;
        });
        $r->session()->put('active_workspace_id', $id);

        return redirect()->route('dashboard')->with('status', 'Convite aceito.');
    }
}
