<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('sistema:crear-gerente {email : Correo con el que entrará} {nombre : Nombre completo}')]
#[Description('Crea el primer Gerente al instalar el sistema en producción, con una contraseña aleatoria que se muestra una sola vez')]
class CrearGerente extends Command
{
    public function handle(CreateTeam $createTeam): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        $nombre = trim((string) $this->argument('nombre'));

        $validacion = Validator::make(['email' => $email, 'nombre' => $nombre], [
            'email' => ['required', 'email', 'unique:users,email'],
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $clave = Str::password(14, symbols: false);

        DB::transaction(function () use ($createTeam, $email, $nombre, $clave): void {
            $gerente = User::create(['name' => $nombre, 'email' => $email, 'password' => Hash::make($clave)]);
            $gerente->forceFill(['email_verified_at' => now()])->save();
            $gerente->syncRoles(['Gerente']);

            // La empresa (equipo) se crea con el primer Gerente; los siguientes se suman a ella.
            $empresa = Team::query()->where('is_personal', false)->oldest('id')->first();

            if ($empresa === null) {
                $empresa = $createTeam->handle($gerente, 'BRUCE FIRE');
            } elseif (! $gerente->belongsToTeam($empresa)) {
                $empresa->members()->attach($gerente, ['role' => TeamRole::Admin->value]);
            }

            $gerente->forceFill(['current_team_id' => $empresa->id])->save();
        });

        $this->info("Gerente creado: {$email}");
        $this->warn("Contraseña inicial (se muestra una sola vez): {$clave}");
        $this->line('Pídele que la cambie al entrar, en Configuración > Contraseña.');

        return self::SUCCESS;
    }
}
