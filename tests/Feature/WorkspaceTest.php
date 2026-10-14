<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Memberships\WorkspaceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function createWorkspace(User $user, string $name = 'Meu espaço'): Workspace
    {
        return app(WorkspaceService::class)->create($user, ['name' => $name, 'timezone' => 'America/Sao_Paulo']);
    }

    public function test_guests_cannot_list_create_select_or_edit_workspaces(): void
    {
        $workspace = $this->createWorkspace(User::factory()->create());
        $this->get(route('workspaces.index'))->assertRedirect(route('login'));
        $this->post(route('workspaces.store'), ['name' => 'Novo', 'timezone' => 'UTC'])->assertRedirect(route('login'));
        $this->get(route('workspaces.show', $workspace))->assertRedirect(route('login'));
        $this->post(route('workspaces.activate', $workspace))->assertRedirect(route('login'));
        $this->patch(route('workspaces.update', $workspace), ['name' => 'Outro', 'timezone' => 'UTC'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('workspaces', 1);
    }

    public function test_creation_assigns_owner_membership_context_and_minimal_audit_atomically(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post(route('workspaces.store'), [
            'name' => '  Orçamento pessoal  ', 'timezone' => 'America/Sao_Paulo',
            'owner_id' => $other->id, 'created_by' => $other->id, 'role' => 'reader',
        ])->assertRedirect(route('dashboard'));

        $workspace = Workspace::sole();
        $this->assertSame('Orçamento pessoal', $workspace->name);
        $this->assertSame($user->id, $workspace->owner_id);
        $this->assertSame($user->id, $workspace->created_by);
        $this->assertSame($workspace->id, session('active_workspace_id'));
        $membership = WorkspaceMember::sole();
        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame(WorkspaceRole::Owner, $membership->role);
        $audit = AuditLog::sole();
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertSame($workspace->id, $audit->workspace_id);
        $this->assertSame('created', $audit->action);
        $this->assertSame(['name', 'timezone', 'owner_id', 'created_by'], $audit->changed_fields);
        $this->assertStringNotContainsString($workspace->name, json_encode($audit->getAttributes()));
    }

    public function test_only_active_memberships_appear_in_the_paginated_list(): void
    {
        $user = User::factory()->create();
        $mine = $this->createWorkspace($user, 'Meu orçamento');
        $inactive = $this->createWorkspace($user, 'Espaço inativo');
        $inactive->memberships()->update(['status' => 'inactive']);
        $foreign = $this->createWorkspace(User::factory()->create(), 'Orçamento alheio');

        $this->actingAs($user)->get(route('workspaces.index'))->assertOk()
            ->assertSee($mine->name)->assertDontSee($inactive->name)->assertDontSee($foreign->name)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_foreign_ids_cannot_be_viewed_selected_or_changed(): void
    {
        $user = User::factory()->create();
        $own = $this->createWorkspace($user);
        $foreign = $this->createWorkspace(User::factory()->create(), 'Espaço alheio');
        $this->actingAs($user)->withSession(['active_workspace_id' => $own->id]);

        $this->get(route('workspaces.show', $foreign))->assertNotFound();
        $this->post(route('workspaces.activate', $foreign))->assertNotFound();
        $this->patch(route('workspaces.update', $foreign), ['name' => 'Invadido', 'timezone' => 'UTC'])->assertNotFound();
        $this->assertSame($own->id, session('active_workspace_id'));
        $this->assertSame('Espaço alheio', $foreign->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_user_can_switch_between_their_workspaces_without_get_requests_mutating_context(): void
    {
        $user = User::factory()->create();
        $first = $this->createWorkspace($user, 'Pessoal');
        $second = $this->createWorkspace($user, 'Outro orçamento');
        $this->actingAs($user)->withSession(['active_workspace_id' => $first->id]);
        $this->get(route('workspaces.show', $second))->assertOk();
        $this->assertSame($first->id, session('active_workspace_id'));
        $this->post(route('workspaces.activate', $second))->assertRedirect(route('dashboard'));
        $this->assertSame($second->id, session('active_workspace_id'));
        $this->get(route('dashboard'))->assertSee($second->name)->assertDontSee('Espaço ativo: '.$first->name);
        $this->get(route('workspaces.activate', $first))->assertStatus(405);
    }

    public function test_forged_session_workspace_does_not_leak_another_workspace(): void
    {
        $foreign = $this->createWorkspace(User::factory()->create(), 'Segredo de outra família');
        $this->actingAs(User::factory()->create())->withSession(['active_workspace_id' => $foreign->id])
            ->get(route('dashboard'))->assertOk()->assertDontSee($foreign->name)
            ->assertSee('Escolher espaço financeiro');
        $this->assertNull(session('active_workspace_id'));
    }

    public function test_membership_revocation_is_checked_on_every_request(): void
    {
        $user = User::factory()->create();
        $workspace = $this->createWorkspace($user, 'Orçamento revogado');
        $this->actingAs($user)->withSession(['active_workspace_id' => $workspace->id])
            ->get(route('dashboard'))->assertSee($workspace->name);
        $workspace->memberships()->update(['status' => 'inactive']);
        $this->get(route('dashboard'))->assertDontSee($workspace->name);
        $this->assertNull(session('active_workspace_id'));
        $this->get(route('workspaces.show', $workspace))->assertNotFound();
        $this->post(route('workspaces.activate', $workspace))->assertNotFound();
        $this->patch(route('workspaces.update', $workspace), ['name' => 'Alterado', 'timezone' => 'UTC'])->assertNotFound();
    }

    public function test_owner_can_update_only_settings_and_audit_changed_fields(): void
    {
        $user = User::factory()->create();
        $workspace = $this->createWorkspace($user);
        $other = User::factory()->create();
        $data = ['name' => 'Renomeado', 'timezone' => 'UTC', 'owner_id' => $other->id, 'created_by' => $other->id];
        $this->actingAs($user)->patch(route('workspaces.update', $workspace), $data)
            ->assertRedirect(route('workspaces.show', $workspace));
        $workspace->refresh();
        $this->assertSame('Renomeado', $workspace->name);
        $this->assertSame('UTC', $workspace->timezone);
        $this->assertSame($user->id, $workspace->owner_id);
        $this->assertSame($user->id, $workspace->created_by);
        $audit = AuditLog::where('action', 'updated')->sole();
        $this->assertSame(['name', 'timezone'], $audit->changed_fields);
        $this->assertSame($user->id, $audit->actor_id);
        $this->patch(route('workspaces.update', $workspace), $data)->assertRedirect();
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_other_roles_can_select_the_workspace_but_cannot_edit_owner_settings(): void
    {
        $workspace = $this->createWorkspace(User::factory()->create());
        foreach ([WorkspaceRole::Administrator, WorkspaceRole::Member, WorkspaceRole::Reader] as $role) {
            $user = User::factory()->create();
            $workspace->memberships()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get(route('workspaces.show', $workspace))->assertOk()
                ->assertDontSee('Salvar configurações');
            $this->post(route('workspaces.activate', $workspace))->assertRedirect(route('dashboard'));
            $this->patch(route('workspaces.update', $workspace), ['name' => 'Alterado', 'timezone' => 'UTC'])->assertForbidden();
        }
        $this->assertSame('Meu espaço', $workspace->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_owner_identity_alone_does_not_bypass_membership_or_role_checks(): void
    {
        $user = User::factory()->create();
        $workspace = $this->createWorkspace($user);
        $workspace->memberships()->update(['role' => 'reader']);
        $this->actingAs($user)->patch(route('workspaces.update', $workspace), ['name' => 'Alterado', 'timezone' => 'UTC'])
            ->assertForbidden();
    }

    public function test_invalid_names_and_timezones_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('workspaces.store'), ['name' => '   ', 'timezone' => 'Invalid/Zone'])
            ->assertSessionHasErrors(['name', 'timezone']);
        $this->post(route('workspaces.store'), ['name' => str_repeat('a', 101), 'timezone' => 'UTC'])
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('workspace_members', 0);
    }

    public function test_creation_rolls_back_if_audit_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        AuditLog::creating(function (): void {
            throw new \RuntimeException('Falha simulada de auditoria');
        });
        try {
            $this->createWorkspace($user);
            $this->fail('Era esperado rollback da operação.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada de auditoria', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('workspace_members', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_database_prevents_duplicate_memberships(): void
    {
        $user = User::factory()->create();
        $workspace = $this->createWorkspace($user);
        $this->expectException(QueryException::class);
        $workspace->memberships()->create(['user_id' => $user->id, 'role' => WorkspaceRole::Member, 'status' => 'active']);
    }

    public function test_workspace_names_are_escaped_in_the_dashboard_and_list(): void
    {
        $user = User::factory()->create();
        $workspace = $this->createWorkspace($user, '<script>alert(1)</script>');
        $this->actingAs($user)->withSession(['active_workspace_id' => $workspace->id]);
        foreach (['dashboard', 'workspaces.index', 'workspaces.show'] as $route) {
            $this->get(route($route, $route === 'workspaces.show' ? $workspace : []))->assertOk()
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
                ->assertDontSee('<script>alert(1)</script>', false);
        }
    }
}
