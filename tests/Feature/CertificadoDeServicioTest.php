<?php

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentData;
use App\Services\Certificates\CertificatePdfService;
use App\Services\Tecnico\CertificadosDeCampo;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
    $this->luces = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
});

test('un cliente de recojo con documento pendiente no puede emitir certificados', function () {
    $cliente = Client::factory()->create(['numero_documento' => '']);
    expect(fn () => app(IssueCertificate::class)->handle($this->luces, $cliente, []))
        ->toThrow(ValidationException::class, 'Completa el DNI/RUC del cliente antes de emitir el certificado.');
    expect($cliente->certificates()->count())->toBe(0);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

function ventaDeServicio(?User $vendedor = null): Sale
{
    $sale = Sale::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'estado' => 'confirmada',
        'vendedor_id' => $vendedor?->id ?? User::factory()->create()->id,
    ]);
    $service = Service::factory()->create(['certificate_type_id' => CertificateType::where('codigo', 'luces_emergencia')->firstOrFail()->id]);
    SaleItem::factory()->forService($service)->create(['sale_id' => $sale->id]);

    return $sale;
}

function filaDeLuz(array $cambios = []): array
{
    return [
        'ubicacion' => 'Pasadizo 2do piso',
        'marca' => 'OPALUX',
        'modelo' => 'LED-200',
        'potencia' => '4',
        'autonomia' => '2',
        'resultado' => 'operativo',
        ...$cambios,
    ];
}

test('los tipos de servicio con formulario excluyen los de extintores y capacitacion', function () {
    $codigos = EmitirCertificadoDeServicio::tiposDeServicio()->pluck('codigo')->all();

    expect($codigos)->toContain('luces_emergencia', 'informe_deteccion', 'lamina_seguridad')
        ->not->toContain('operatividad_garantia', 'prueba_hidrostatica', 'capacitacion');
});

test('emite el certificado de luces con sus filas y pruebas y el pdf las pinta', function () {
    $sale = ventaDeServicio();

    $certificado = app(EmitirCertificadoDeServicio::class)->handle($sale, $this->luces, [
        'referencia' => 'SEDE: Tienda Centro',
        'tipo_atencion' => 'instalación',
        'filas' => [filaDeLuz(), filaDeLuz(['ubicacion' => 'Escalera', 'autonomia' => '1', 'resultado' => 'observado'])],
        'pruebas' => [['estado' => 'C'], ['estado' => 'C', 'valor' => '15']],
        'observaciones' => 'Se reemplazó una batería.',
    ]);

    expect($certificado->referencia)->toBe('SEDE: Tienda Centro')
        ->and($certificado->tipo_atencion)->toBe('instalación')
        ->and($certificado->datos['filas'][0]['item'])->toBe('01')
        ->and($certificado->datos['filas'][1]['item'])->toBe('02')
        ->and($certificado->datos['pruebas'])->toHaveCount(count($this->luces->checklist));

    $datos = app(CertificateDocumentData::class)->desde($certificado->fresh());

    // La activación tiene máximo 10 s: 15 s queda NC aunque se marcó C.
    expect($datos['pruebas'][1]['estado'])->toBe('NC')
        ->and($datos['resultado'])->toBe('observado')
        ->and($datos['observaciones'])->toBe('Se reemplazó una batería.');

    expect(app(CertificatePdfService::class)->generate($certificado->fresh())->output())->toStartWith('%PDF');
});

test('valida los obligatorios y los numeros segun las columnas del tipo', function () {
    $sale = ventaDeServicio();

    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $this->luces, [
        'filas' => [filaDeLuz(['marca' => '', 'autonomia' => 'dos horas'])],
    ]))->toThrow(function (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['filas.0.marca', 'filas.0.autonomia']);
    });
});

test('volver a guardar corrige el mismo certificado con una revision', function () {
    $sale = ventaDeServicio();
    $emitir = app(EmitirCertificadoDeServicio::class);

    $primero = $emitir->handle($sale, $this->luces, ['filas' => [filaDeLuz()]]);
    $corregido = $emitir->handle($sale, $this->luces, ['filas' => [filaDeLuz(), filaDeLuz(['ubicacion' => 'Almacén'])]]);

    expect($corregido->id)->toBe($primero->id)
        ->and($corregido->numero)->toBe($primero->numero)
        ->and($corregido->revision)->toBe($primero->revision + 1)
        ->and($corregido->datos['filas'])->toHaveCount(2);
});

test('no se usa el formulario para certificados de extintores', function () {
    $sale = ventaDeServicio();
    $operatividad = CertificateType::where('codigo', 'operatividad_garantia')->firstOrFail();

    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $operatividad, ['filas' => [filaDeLuz()]]))
        ->toThrow(ValidationException::class);
});

test('el vendedor abre el formulario y emite desde la venta', function () {
    $vendedor = vendedorUser();
    $sale = ventaDeServicio($vendedor);
    $ruta = ['current_team' => $vendedor->currentTeam, 'sale' => $sale, 'tipo' => 'luces_emergencia'];

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.certificado-servicio.create', $ruta))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/ventas/certificado-servicio')
            ->where('tipo.codigo', 'luces_emergencia')
            ->has('tipo.columnas', count($this->luces->columnas))
            ->where('existente', null));

    $this->actingAs($vendedor)
        ->post(route('vendedor.ventas.certificado-servicio.store', $ruta), [
            'tipo_atencion' => 'mantenimiento',
            'filas' => [filaDeLuz()],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('vendedor.ventas.show', ['current_team' => $vendedor->currentTeam, 'sale' => $sale]));

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.certificado-servicio.create', [...$ruta, 'tipo' => 'operatividad_garantia']))
        ->assertNotFound();
});

test('una plantilla de producto sin tabla se emite desde la venta sin filas ficticias', function () {
    $vendedor = vendedorUser();
    $sale = ventaDeServicio($vendedor);
    $tipo = CertificateType::factory()->create(['codigo' => 'garantia_manguera', 'columnas' => null, 'checklist' => null, 'composicion' => [['tipo' => 'datos'], ['tipo' => 'texto', 'titulo' => 'Garantía', 'texto' => 'Doce meses.']]]);
    $producto = Product::factory()->create(['certificate_type_id' => $tipo->id]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $producto->id]);
    $ruta = ['current_team' => $vendedor->currentTeam, 'sale' => $sale, 'tipo' => $tipo->codigo];

    $this->actingAs($vendedor)->get(route('vendedor.ventas.certificado-servicio.create', $ruta))
        ->assertInertia(fn ($page) => $page->where('tipo.requiere_tabla', false)->where('tipo.requiere_diploma', false));
    $this->post(route('vendedor.ventas.certificado-servicio.store', $ruta), [])->assertSessionHasNoErrors();

    $certificado = $sale->certificates()->where('certificate_type_id', $tipo->id)->sole();
    expect($certificado->datos['filas'])->toBe([])
        ->and($certificado->typeVersion->configuracion['composicion'])->toEqual($tipo->composicion);
});

test('una plantilla dinamica no se emite para ventas ajenas ni con plantilla inactiva', function (string $caso) {
    $sale = ventaDeServicio();
    $tipo = CertificateType::factory()->create(['codigo' => 'garantia_independiente', 'composicion' => [['tipo' => 'texto', 'texto' => 'Garantía']], 'columnas' => null, 'activo' => $caso !== 'inactiva']);
    if ($caso === 'inactiva') {
        $sale->items()->first()->service->update(['certificate_type_id' => $tipo->id]);
    }

    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $tipo, []))->toThrow(ValidationException::class);
    expect($sale->certificates()->count())->toBe(0);
})->with(['ajena', 'inactiva']);

test('una composicion con pruebas exige resultados reales y no asume conformidad', function () {
    $sale = ventaDeServicio();
    $this->luces->update(['composicion' => [['tipo' => 'pruebas']], 'columnas' => null]);

    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $this->luces, []))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('pruebas.0.estado');
        });
    expect($sale->certificates()->count())->toBe(0);
});

test('una composicion con tabla sigue exigiendo filas reales', function () {
    $sale = ventaDeServicio();
    $this->luces->update(['composicion' => [['tipo' => 'tabla']]]);

    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $this->luces, []))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('filas');
        });
    expect($sale->certificates()->count())->toBe(0);
});

test('una composicion de resultado no declara operativo cuando ninguna prueba aplica', function () {
    $sale = ventaDeServicio();
    $this->luces->update(['composicion' => [['tipo' => 'pruebas'], ['tipo' => 'resultado']], 'columnas' => null]);
    $pruebas = array_fill(0, count($this->luces->checklist), ['estado' => 'NA']);

    expect(fn () => app(EmitirCertificadoDeServicio::class)->handle($sale, $this->luces, ['pruebas' => $pruebas]))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('pruebas');
        });
    expect($sale->certificates()->count())->toBe(0);
});

test('un diploma dinamico guarda los datos reales del curso sin tabla', function () {
    $this->freezeTime();
    $vendedor = vendedorUser();
    $sale = ventaDeServicio($vendedor);
    $tipo = CertificateType::factory()->create(['codigo' => 'curso_seguridad', 'familia' => 'diploma', 'composicion' => [['tipo' => 'diploma']], 'columnas' => null, 'checklist' => null]);
    $sale->items()->first()->service->update(['certificate_type_id' => $tipo->id]);
    $ruta = ['current_team' => $vendedor->currentTeam, 'sale' => $sale, 'tipo' => $tipo->codigo];

    $this->actingAs($vendedor)->post(route('vendedor.ventas.certificado-servicio.store', $ruta), [])
        ->assertSessionHasErrors(['capacitacion.curso', 'capacitacion.horas', 'capacitacion.instructor', 'capacitacion.fecha']);
    $this->post(route('vendedor.ventas.certificado-servicio.store', $ruta), ['capacitacion' => ['curso' => 'Seguridad en obra', 'horas' => 6, 'instructor' => 'Ana Pérez', 'fecha' => today()->toDateString()]])->assertSessionHasNoErrors();

    $certificado = $sale->certificates()->sole();
    expect($certificado->datos['curso'])->toBe('Seguridad en obra')
        ->and($certificado->datos['horas'])->toBe(6)
        ->and($certificado->datos['instructor'])->toBe('Ana Pérez')
        ->and($certificado->datos['fecha'])->toBe(today()->toDateString());
});

test('corregir usa las columnas historicas aunque la plantilla actual haya cambiado', function () {
    $sale = ventaDeServicio();
    $emitir = app(EmitirCertificadoDeServicio::class);
    $certificado = $emitir->handle($sale, $this->luces, ['filas' => [filaDeLuz()]]);
    $version = $certificado->certificate_type_version_id;
    $this->luces->update(['columnas' => [['clave' => 'otra', 'titulo' => 'Otra', 'obligatorio' => true]], 'version' => 2]);

    $corregido = $emitir->handle($sale, $this->luces->fresh(), ['filas' => [filaDeLuz(['marca' => 'NUEVA'])]]);

    expect($corregido->certificate_type_version_id)->toBe($version)
        ->and($corregido->datos['filas'][0]['marca'])->toBe('NUEVA');
});

test('campo certifica una composicion sin tabla y omite los diplomas del formulario tecnico', function () {
    $tipo = CertificateType::factory()->create(['codigo' => 'garantia_instalacion', 'columnas' => null, 'checklist' => null, 'composicion' => [['tipo' => 'datos'], ['tipo' => 'texto', 'texto' => 'Instalación registrada.']]]);
    $servicio = Service::factory()->create(['certificate_type_id' => $tipo->id]);
    $orden = ServiceOrder::factory()->create(['service_id' => $servicio->id, 'sale_id' => null]);
    $campo = app(CertificadosDeCampo::class);

    $campo->emitir($orden, [['tipo_id' => $tipo->id, 'version' => 1, 'filas' => [], 'pruebas' => []]], 'instalación');

    expect($orden->certificates()->sole()->datos['filas'])->toBe([]);
    $tipo->update(['familia' => 'diploma', 'composicion' => [['tipo' => 'diploma']]]);
    expect($campo->tipos($orden))->toHaveCount(0);
});
