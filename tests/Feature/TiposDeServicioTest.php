<?php

use App\Models\CertificateType;
use App\Models\Service;
use App\Models\TipoDeServicio;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TipoDeServicioSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class, TipoDeServicioSeeder::class]);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->equipo = ['current_team' => $this->gerente->currentTeam];
});

test('un servicio se crea con tipo, nombre y precio y hereda certificado, detraccion y codigo sunat', function () {
    $recarga = TipoDeServicio::query()->where('nombre', 'Recarga de extintor')->sole();

    $this->actingAs($this->gerente)
        ->post(route('gerente.servicios.store', $this->equipo), [
            'tipo_de_servicio_id' => $recarga->id,
            'nombre' => 'Recarga PQS 6 kg',
            'precio_venta' => 45,
            'agente' => 'PQS ABC',
            'capacidad' => '6 kg',
        ])
        ->assertSessionHasNoErrors();

    $servicio = Service::query()->where('nombre', 'Recarga PQS 6 kg')->sole();
    expect($servicio->unidad_medida)->toBe('ZZ')
        ->and($servicio->tipo_afectacion_igv)->toBe('10')
        ->and($servicio->estado_detraccion)->toBe('pendiente')
        ->and($servicio->codigo_detraccion)->toBeNull()
        ->and($servicio->certificateType->codigo)->toBe('operatividad_garantia')
        ->and($servicio->codigoSunat())->toBe('72101516');
});

test('un servicio nuevo sin tipo no se guarda', function () {
    $this->actingAs($this->gerente)
        ->post(route('gerente.servicios.store', $this->equipo), ['nombre' => 'Servicio suelto', 'precio_venta' => 45])
        ->assertSessionHasErrors(['tipo_de_servicio_id' => 'Elige el tipo de servicio.']);
});

test('el gerente administra los tipos y el cambio llega a sus servicios', function () {
    $this->actingAs($this->gerente)
        ->post(route('gerente.tipos-de-servicio.store', $this->equipo), [
            'nombre' => 'Señalización',
            'estado_detraccion' => 'sujeto',
            'codigo_detraccion' => '037',
            'certificate_type_id' => null,
            'pide_extintor' => false,
        ])
        ->assertSessionHasNoErrors();
    $tipo = TipoDeServicio::query()->where('nombre', 'Señalización')->sole();
    $servicio = Service::factory()->create(['tipo_de_servicio_id' => $tipo->id]);
    $ph = CertificateType::query()->where('codigo', 'prueba_hidrostatica')->sole();

    $this->put(route('gerente.tipos-de-servicio.update', [...$this->equipo, 'tipo_de_servicio' => $tipo]), [
        'nombre' => 'Señalización y PH',
        'estado_detraccion' => 'sujeto',
        'codigo_detraccion' => '020',
        'certificate_type_id' => $ph->id,
        'pide_extintor' => true,
    ])->assertSessionHasNoErrors();

    expect($servicio->fresh()->codigo_detraccion)->toBe('020')
        ->and($servicio->fresh()->certificate_type_id)->toBe($ph->id);
});

test('un tipo con servicios no se borra: se desactiva', function () {
    $tipo = TipoDeServicio::query()->first();
    Service::factory()->create(['tipo_de_servicio_id' => $tipo->id]);

    $this->actingAs($this->gerente)
        ->delete(route('gerente.tipos-de-servicio.destroy', [...$this->equipo, 'tipo_de_servicio' => $tipo]))
        ->assertSessionHasNoErrors();

    expect($tipo->fresh())->not->toBeNull()->and($tipo->fresh()->activo)->toBeFalse();
});

test('la pantalla de servicios trae los tipos con su detraccion y certificado en palabras', function () {
    $this->actingAs($this->gerente)
        ->get(route('gerente.servicios.index', $this->equipo))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tiposDeServicio.0.nombre', 'Recarga de extintor')
            ->where('tiposDeServicio.0.estado_detraccion', 'pendiente')
            ->where('detracciones.0.codigo', '012')
            ->where('tiposDeServicio.0.certificado', 'Operatividad y Garantía'));
});
