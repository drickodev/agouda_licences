<?php

namespace App\Console\Commands\Admin;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature(<<<'SIG'
    admin:create-user
        {--name= : Nom de l'administrateur}
        {--email= : Email (identifiant de connexion)}
        {--password= : Mot de passe (demandé en saisie masquée si omis)}
    SIG)]
#[Description("Crée un compte administrateur (accès à l'API admin, remplace l'ancien make:filament-user)")]
class CreateAdminUserCommand extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nom');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Mot de passe');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Compte administrateur créé : {$user->email} (id {$user->id}).");

        return self::SUCCESS;
    }
}
