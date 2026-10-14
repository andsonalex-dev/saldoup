<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdministrator extends Command
{
    protected $signature = 'saldoup:create-admin {email} {--name=Administrador local} {--generate-password}';

    protected $description = 'Cria administrador local separado, sem substituir usuários existentes.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Este comando é exclusivo do ambiente local.');

            return self::FAILURE;
        }
        $email = mb_strtolower(trim($this->argument('email')));
        $password = $this->option('generate-password') ? Str::password(24) : $this->secret('Senha (12 a 72 bytes)');
        $data = ['name' => $this->option('name'), 'email' => $email, 'password' => $password];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12|max:72']);
        if ($validator->fails() || strlen($password ?? '') > 72) {
            $this->error('Dados inválidos ou e-mail já cadastrado; nenhum usuário foi alterado.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($data): void {
            $user = new User($data);
            $user->forceFill(['is_platform_admin' => true, 'is_active' => true])->save();
            DB::table('platform_audit_logs')->insert(['actor_id' => $user->id, 'user_id' => $user->id, 'action' => 'local_admin_created', 'changed_fields' => json_encode(['is_platform_admin']), 'created_at' => now()]);
        });
        $this->info('Administrador local criado: '.$email);
        if ($this->option('generate-password')) {
            $this->line('Senha gerada: '.$password);
        }

        return self::SUCCESS;
    }
}
