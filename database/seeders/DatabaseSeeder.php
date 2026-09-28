<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

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
            ['name' => 'Técnico Planta Demo', 'email' => 'tecnico.planta@brucefire.pe', 'role' => 'TecnicoPlanta', 'sede_id' => $almacen->id],
            ['name' => 'Técnico Campo Demo', 'email' => 'tecnico.campo@brucefire.pe', 'role' => 'TecnicoCampo', 'sede_id' => $almacen->id],
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

        $this->call(CertificateTypeSeeder::class);
    }
}
