<?php

use App\Http\Controllers\Gerente\CertificateTypeController;
use App\Models\CertificateType;
use App\Models\SunatCodigoProducto;
use App\Models\TipoDeServicio;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->equipo = ['current_team' => $this->gerente->currentTeam];
});

test('el catálogo 25 se importa del csv, salta filas malas y repetirlo no duplica', function () {
    $archivo = base_path('tests/Fixtures/sunat_codigos_producto.csv');

    $this->artisan('sunat:importar-codigos-producto', ['archivo' => $archivo])->assertSuccessful();
    $this->artisan('sunat:importar-codigos-producto', ['archivo' => $archivo])->assertSuccessful();

    expect(SunatCodigoProducto::query()->count())->toBe(2)
        ->and(SunatCodigoProducto::query()->find('72101516')->descripcion)->toContain('extinguidores')
        ->and(SunatCodigoProducto::query()->find('46191601')->clase)->toBe('Equipo contra incendios');
});

test('se busca por palabras o por el inicio del código', function () {
    $this->artisan('sunat:importar-codigos-producto', ['archivo' => base_path('tests/Fixtures/sunat_codigos_producto.csv')]);

    $this->actingAs($this->gerente)
        ->getJson(route('gerente.sunat.codigos-producto', [...$this->equipo, 'buscar' => 'mantenimiento extinguidores']))
        ->assertOk()
        ->assertJsonPath('codigos.0.codigo', '72101516')
        ->assertJsonCount(1, 'codigos');

    $this->actingAs($this->gerente)
        ->getJson(route('gerente.sunat.codigos-producto', [...$this->equipo, 'buscar' => '4619']))
        ->assertJsonPath('codigos.0.codigo', '46191601');

    // En servicios solo salen servicios (segmentos 70–94), no el bien "Extintores".
    $this->actingAs($this->gerente)
        ->getJson(route('gerente.sunat.codigos-producto', [...$this->equipo, 'buscar' => 'extin', 'tipo' => 'servicio']))
        ->assertJsonCount(1, 'codigos')
        ->assertJsonPath('codigos.0.codigo', '72101516');

    // Por la clase: "contra incendios" no está en "Extintores", pero sí en su clase.
    $this->actingAs($this->gerente)
        ->getJson(route('gerente.sunat.codigos-producto', [...$this->equipo, 'buscar' => 'contra incendios']))
        ->assertJsonPath('codigos.0.codigo', '46191601')
        ->assertJsonPath('codigos.0.clase', 'Equipo contra incendios');
});

test('con el catálogo cargado, un código que no existe no se guarda en el tipo', function () {
    $this->artisan('sunat:importar-codigos-producto', ['archivo' => base_path('tests/Fixtures/sunat_codigos_producto.csv')]);

    $this->actingAs($this->gerente)
        ->post(route('gerente.tipos-de-servicio.store', $this->equipo), ['nombre' => 'Gabinetes', 'codigo_sunat' => '12345678'])
        ->assertSessionHasErrors('codigo_sunat');

    $this->actingAs($this->gerente)
        ->post(route('gerente.tipos-de-servicio.store', $this->equipo), ['nombre' => 'Gabinetes', 'codigo_sunat' => '72101516'])
        ->assertSessionHasNoErrors();

    expect(TipoDeServicio::query()->where('nombre', 'Gabinetes')->value('codigo_sunat'))->toBe('72101516');
});

test('un certificado nuevo creado copiando un informe aparece para elegirlo en los tipos de servicio', function () {
    $base = CertificateType::query()->get()->first(fn (CertificateType $tipo) => CertificateTypeController::puedeSerBase($tipo));

    $this->actingAs($this->gerente)
        ->from(route('gerente.servicios.index', $this->equipo))
        ->post(route('gerente.configuracion.certificados.store', $this->equipo), [
            'nombre' => 'Gabinetes contra incendio',
            'base_id' => $base->id,
            'titulo' => 'CERTIFICADO DE GABINETES',
            'prefijo' => 'GCI',
            'vigencia_meses' => 12,
        ])
        ->assertRedirect(route('gerente.configuracion.certificados.index', $this->equipo));

    $this->actingAs($this->gerente)
        ->get(route('gerente.servicios.index', $this->equipo))
        ->assertInertia(fn ($page) => $page
            ->where('tiposCertificado', fn ($lista) => collect($lista)->contains('nombre', 'Gabinetes contra incendio')));
});
