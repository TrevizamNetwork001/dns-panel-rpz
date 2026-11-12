<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreatePanelAdmin extends Command
{
    protected $signature = 'panel:admin-create {email?} {--name=Administrador} {--password-stdin : Ler senha pela entrada padrão} {--if-missing : Não alterar um administrador existente}';

    protected $description = 'Cria o primeiro administrador sem senha padrão e sem redefinir usuários existentes';

    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            if ($this->option('if-missing')) {
                $this->info('Administrador já cadastrado; acesso existente preservado.');

                return self::SUCCESS;
            }
            $this->error('Já existe um administrador. Use a gestão de usuários do painel.');

            return self::FAILURE;
        }

        $email = $this->argument('email') ?? $this->ask('E-mail do administrador');
        $name = $this->option('name');
        $validator = Validator::make(compact('email', 'name'), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if ($this->option('password-stdin')) {
            // Consume a single line; never accept passwords in process arguments.
            $password = rtrim((string) fgets(STDIN, 4098), "\r\n");
        } else {
            $password = $this->secret('Senha do administrador (mínimo 12 caracteres)');
            if ($password !== $this->secret('Confirme a senha')) {
                $this->error('As senhas não conferem.');

                return self::FAILURE;
            }
        }
        $validator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:12', 'max:1024'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'admin']);
        $this->info('Administrador criado. Entre no painel com o e-mail e a senha informados.');

        return self::SUCCESS;
    }
}
