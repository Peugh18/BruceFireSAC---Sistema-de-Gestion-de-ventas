<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Catálogo 25 de SUNAT, si ya se convirtió el archivo oficial a CSV.
        if (is_readable(database_path('data/sunat_codigos_producto.csv'))) {
            Artisan::call('sunat:importar-codigos-producto');
        }

        // En producción no se crean usuarios de demostración (su contraseña es
        // conocida): el primer Gerente se crea con sistema:crear-gerente y él
        // registra las sedes y al personal.
        if (app()->environment('production')) {
            $this->call([CertificateTypeSeeder::class, TipoDeServicioSeeder::class]);

            return;
        }

        $almacen = Sede::firstOrCreate(
            ['nombre' => 'Almacén Central Trujillo'],
            [
                'tipo' => 'mixta',
                'ubigeo' => '130101',
                'activo' => true,
            ]
        );

        $tienda = Sede::firstOrCreate(
            ['nombre' => 'Tienda Trujillo Centro'],
            [
                'tipo' => 'tienda',
                'ubigeo' => '130101',
                'almacen_id' => $almacen->id,
                'activo' => true,
            ]
        );

        $taller = Sede::firstOrCreate(
            ['nombre' => 'Taller de recargas'],
            ['tipo' => 'taller', 'ubigeo' => '130101', 'activo' => true],
        );

        $vendedor = User::firstOrCreate(
            ['email' => 'vendedor@brucefire.pe'],
            [
                'name' => 'Vendedor Demo',
                'password' => bcrypt('password'),
            ]
        );
        $vendedor->syncRoles(['Vendedor']);
        // Cada trabajador pertenece a una sede: la venta toma su stock de ahí.
        $vendedor->forceFill(['sede_id' => $tienda->id])->save();

        $team = $vendedor->currentTeam ?? app(CreateTeam::class)->handle($vendedor, 'BRUCE FIRE', isPersonal: false);

        $demoUsers = [
            ['name' => 'Gerente Demo', 'email' => 'gerente@brucefire.pe', 'role' => 'Gerente', 'sede_id' => null],
            ['name' => 'Almacén Demo', 'email' => 'almacen@brucefire.pe', 'role' => 'Almacen', 'sede_id' => $almacen->id],
            ['name' => 'Técnico Planta Demo', 'email' => 'tecnico.planta@brucefire.pe', 'role' => 'TecnicoPlanta', 'sede_id' => $taller->id],
            ['name' => 'Técnico Campo Demo', 'email' => 'tecnico.campo@brucefire.pe', 'role' => 'TecnicoCampo', 'sede_id' => $taller->id],
        ];

        foreach ($demoUsers as $demo) {
            $user = User::firstOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => bcrypt('password'),
                ]
            );
            $user->syncRoles([$demo['role']]);
            if (! $user->belongsToTeam($team)) {
                $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
            }
            $user->forceFill(['current_team_id' => $team->id, 'sede_id' => $demo['sede_id']])->save();
        }

        $this->call([CertificateTypeSeeder::class, TipoDeServicioSeeder::class]);
    }
}
