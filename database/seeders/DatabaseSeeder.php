<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
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

        $almacen = Sede::create([
            'nombre' => 'Almacén Central Trujillo',
            'tipo' => 'mixta',
            'ciudad' => 'Trujillo',
            'activo' => true,
        ]);

        Sede::create([
            'nombre' => 'Tienda Trujillo Centro',
            'tipo' => 'tienda',
            'ciudad' => 'Trujillo',
            'almacen_id' => $almacen->id,
            'activo' => true,
        ]);

        $vendedor = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $vendedor->assignRole('Vendedor');

        app(CreateTeam::class)->handle($vendedor, 'BRUCE FIRE', isPersonal: false);

        $this->call(CertificateTypeSeeder::class);
    }
}
