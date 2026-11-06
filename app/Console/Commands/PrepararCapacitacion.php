<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CatalogoDemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('sistema:capacitacion')]
#[Description('Deja el servidor listo para capacitar: sedes, catálogo de ejemplo y un usuario por rol con contraseña aleatoria')]
class PrepararCapacitacion extends Command
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string|null}> correo, nombre, rol, sede */
    private const USUARIOS = [
        ['gerente@brucefire.pe', 'Gerente', 'Gerente', null],
        ['vendedor@brucefire.pe', 'Vendedor', 'Vendedor', 'tienda'],
        ['almacen@brucefire.pe', 'Almacén', 'Almacen', 'almacen'],
        ['planta@brucefire.pe', 'Técnico de planta', 'TecnicoPlanta', 'taller'],
        ['campo@brucefire.pe', 'Técnico de campo', 'TecnicoCampo', 'taller'],
    ];

    public function handle(CreateTeam $createTeam): int
    {
        $claves = DB::transaction(function () use ($createTeam): array {
            $almacen = Sede::firstOrCreate(['nombre' => 'Almacén Central Trujillo'], ['tipo' => 'mixta', 'ubigeo' => '130101', 'activo' => true]);
            $sedes = [
                'almacen' => $almacen,
                'tienda' => Sede::firstOrCreate(['nombre' => 'Tienda Trujillo Centro'], ['tipo' => 'tienda', 'ubigeo' => '130101', 'almacen_id' => $almacen->id, 'activo' => true]),
                'taller' => Sede::firstOrCreate(['nombre' => 'Taller de recargas'], ['tipo' => 'taller', 'ubigeo' => '130101', 'activo' => true]),
            ];

            $claves = [];
            $empresa = Team::query()->where('is_personal', false)->oldest('id')->first();

            foreach (self::USUARIOS as [$correo, $nombre, $rol, $sede]) {
                // Un usuario que ya existe conserva su contraseña: no se vuelve a mostrar.
                if (User::query()->where('email', $correo)->exists()) {
                    continue;
                }
                $clave = Str::password(12, symbols: false);
                $usuario = User::create(['name' => $nombre, 'email' => $correo, 'password' => Hash::make($clave)]);
                $usuario->forceFill(['email_verified_at' => now(), 'sede_id' => $sede ? $sedes[$sede]->id : null])->save();
                $usuario->syncRoles([$rol]);

                $empresa ??= $createTeam->handle($usuario, 'BRUCE FIRE');
                if (! $usuario->belongsToTeam($empresa)) {
                    $empresa->members()->attach($usuario, ['role' => TeamRole::Admin->value]);
                }
                $usuario->forceFill(['current_team_id' => $empresa->id])->save();
                $claves[] = [$rol, $correo, $clave];
            }

            app(CatalogoDemoSeeder::class)->sembrar();

            return $claves;
        });

        $this->info('Capacitación lista: sedes y catálogo de ejemplo cargados.');
        if ($claves === []) {
            $this->line('Los usuarios ya existían: se conservan sus contraseñas.');

            return self::SUCCESS;
        }
        $this->warn('Anota estas contraseñas (se muestran una sola vez):');
        $this->table(['Rol', 'Correo', 'Contraseña'], $claves);

        return self::SUCCESS;
    }
}
