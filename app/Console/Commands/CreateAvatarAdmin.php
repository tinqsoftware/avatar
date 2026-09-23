<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('avatars:create-admin {email : Correo de la persona administradora} {--name= : Nombre visible}')]
#[Description('Crea o promueve una cuenta administradora del panel de avatares')]
class CreateAvatarAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = mb_strtolower($this->argument('email'));
        $name = $this->option('name') ?: $this->ask('Nombre', 'Administrador');
        $password = $this->secret('Contraseña');
        $confirmation = $this->secret('Repite la contraseña');

        if (! is_string($password) || $password === '' || $password !== $confirmation) {
            $this->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $this->info("Administrador listo: {$email}");

        return self::SUCCESS;
    }
}
