<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAdministrationController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($r->user()->is_platform_admin, 403);

        return view('admin.users', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function update(Request $r, User $user)
    {
        abort_unless($r->user()->is_platform_admin, 403);
        $data = $r->validate(['is_active' => 'required|boolean', 'is_platform_admin' => 'sometimes|boolean', 'current_password' => 'required|string|current_password']);
        abort_if($user->id === $r->user()->id, 403, 'Seu próprio acesso administrativo não pode ser alterado neste painel.');
        DB::transaction(function () use ($r, $user, $data): void {
            $administrators = User::where('is_platform_admin', true)->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            abort_unless($administrators->contains('id', $r->user()->id), 403);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->forceFill(['is_active' => $data['is_active'], 'is_platform_admin' => $data['is_platform_admin'] ?? $user->is_platform_admin]);
            $fields = array_keys($user->getDirty());
            if ($fields !== []) {
                $user->save();
                DB::table('platform_audit_logs')->insert(['actor_id' => $r->user()->id, 'user_id' => $user->id, 'action' => 'access_updated', 'changed_fields' => json_encode($fields), 'created_at' => now()]);
            }
        });

        return back()->with('status', 'Status de acesso atualizado.');
    }
}
