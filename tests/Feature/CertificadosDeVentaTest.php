<?php

use App\Actions\Certificates\EmitirCertificadosDeVenta;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\RevertSale;
use App\Models\Certificate;
use App\Models\CertificateParticipant;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Certificates\CertificatePdfService;
use App\Services\Certificates\CertificateWordService;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

/**
 * Venta confirmada con N extintores vendidos (cada uno ya es un equipo del cliente).
 *
 * @return array{0: Sale, 1: list<Equipment>}
 */
function ventaConExtintores(int $cantidad, string $destino = 'local_cliente'): array
{
    $client = Client::factory()->create(['razon_social' => 'FONPELL S.A.C.']);
    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'estado' => 'confirmada',
        'destino' => $destino,
        'referencia' => $destino === 'vehiculo' ? 'PLACA: B32-928' : null,
    ]);
    $product = Product::factory()->create();

    $equipos = [];
    for ($i = 1; $i <= $cantidad; $i++) {
        $equipo = Equipment::factory()->create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'capacidad' => '9 Kg',
            'marca' => 'IMPORTADO',
            'serie_fabricante' => (string) (450 + $i),
            'anio_fabricacion' => 2020,
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'equipment_id' => $equipo->id,
            'cantidad' => 1,
        ]);
        $equipos[] = $equipo;
    }

    return [$sale, $equipos];
}

test('por regla de negocio un local lleva operatividad y capacitacion y un vehiculo operatividad y prueba hidrostatica', function () {
    expect(EmitirCertificadosDeVenta::tiposPorDestino('local_cliente'))->toBe(['operatividad_garantia', 'capacitacion'])
        ->and(EmitirCertificadosDeVenta::tiposPorDestino('vehiculo'))->toBe(['operatividad_garantia', 'prueba_hidrostatica']);
});

test('una venta se reparte en dos locales con la numeracion y el orden del cliente', function () {
    [$sale, $equipos] = ventaConExtintores(4);

    $certificados = app(EmitirCertificadosDeVenta::class)->handle($sale, [
        [
            'referencia' => 'SEDE: Planta Chimbote',
            'direccion' => 'AV. INDUSTRIAL 123 CHIMBOTE',
            'tipos' => ['operatividad_garantia', 'capacitacion'],
            'unidades' => [
                ['equipment_id' => $equipos[2]->id, 'numero_cliente' => '01'],
                ['equipment_id' => $equipos[0]->id, 'numero_cliente' => '02'],
            ],
        ],
        [
            'referencia' => 'SEDE: Oficina Trujillo',
            'tipos' => ['operatividad_garantia'],
            'unidades' => [
                ['equipment_id' => $equipos[1]->id],
                ['equipment_id' => $equipos[3]->id],
            ],
        ],
    ]);

    expect($certificados)->toHaveCount(3);

    $chimbote = $certificados->first();
    $unidades = $chimbote->certificateUnits()->orderBy('orden')->get();

    expect($chimbote->referencia)->toBe('SEDE: Planta Chimbote')
        ->and($chimbote->direccion)->toBe('AV. INDUSTRIAL 123 CHIMBOTE')
        ->and($chimbote->tipo_atencion)->toBe('venta')
        ->and($unidades->pluck('numero_cliente')->all())->toBe(['01', '02'])
        ->and($unidades->first()->equipment_id)->toBe($equipos[2]->id)
        ->and($unidades->first()->capacidad)->toBe('9 Kg')
        ->and($equipos[2]->fresh()->numero_cliente)->toBe('01');

    $capacitacion = $certificados[1];

    expect($capacitacion->numero)->toStartWith('BF-UME-')
        ->and($capacitacion->fecha_vigencia_hasta)->toBeNull()
        ->and($capacitacion->datos['curso'])->toBe('USO Y MANEJO DE EXTINTORES')
        ->and($capacitacion->certificateUnits()->count())->toBe(0);
});

test('al confirmar la venta solo sale solo el de operatividad, en el orden escaneado', function (string $destino, array $tipos) {
    [$sale, $equipos] = ventaConExtintores(3, $destino);
    $sale->update(['estado' => 'borrador', 'comprobante_tipo' => 'nota_venta']);

    app(ConfirmSale::class)->handle($sale);

    $certificados = Certificate::where('sale_id', $sale->id)->with('certificateType', 'certificateUnits')->orderBy('id')->get();

    expect($certificados->pluck('certificateType.codigo')->all())->toBe($tipos)
        ->and($certificados->first()->certificateUnits->sortBy('orden')->pluck('equipment_id')->all())
        ->toBe(array_map(fn ($e) => $e->id, $equipos));
})->with([
    'local' => ['local_cliente', ['operatividad_garantia']],
    'vehiculo' => ['vehiculo', ['operatividad_garantia']],
]);

test('una venta sin extintores no emite certificados al confirmarse', function () {
    $sale = Sale::factory()->create(['estado' => 'confirmada']);

    expect(app(EmitirCertificadosDeVenta::class)->automaticos($sale))->toBeEmpty();
});

test('ajustar el orden conserva el numero del certificado y deja una revision con el motivo', function () {
    [$sale, $equipos] = ventaConExtintores(2);
    $user = vendedorUser();
    $emitir = app(EmitirCertificadosDeVenta::class);
    [$operatividad, $capacitacion] = $emitir->handle($sale, [[
        'tipos' => ['operatividad_garantia', 'capacitacion'],
        'unidades' => array_map(fn ($e) => ['equipment_id' => $e->id], $equipos),
    ]])->all();

    $ajustados = $emitir->handle($sale, [[
        'tipos' => ['operatividad_garantia', 'capacitacion'],
        'unidades' => [
            ['equipment_id' => $equipos[1]->id, 'numero_cliente' => '01'],
            ['equipment_id' => $equipos[0]->id, 'numero_cliente' => '02'],
        ],
    ]], 'El cliente pidió su propia numeración', $user->id);

    $corregido = $ajustados->first()->fresh(['revisions', 'certificateUnits']);

    expect($ajustados->pluck('numero')->all())->toBe([$operatividad->numero, $capacitacion->numero])
        ->and($corregido->revision)->toBe(1)
        ->and($corregido->estado)->toBe('vigente')
        ->and($corregido->revisions->first()->motivo)->toBe('El cliente pidió su propia numeración')
        ->and($corregido->revisions->first()->user_id)->toBe($user->id)
        ->and($corregido->certificateUnits->sortBy('orden')->pluck('numero_cliente')->all())->toBe(['01', '02'])
        ->and($capacitacion->fresh()->revision)->toBe(0)
        ->and(Certificate::where('sale_id', $sale->id)->count())->toBe(2);
});

test('al reagrupar solo el certificado nuevo pide numero y el que sobra se anula', function () {
    [$sale, $equipos] = ventaConExtintores(2);
    $emitir = app(EmitirCertificadosDeVenta::class);
    [$operatividad, $capacitacion] = $emitir->handle($sale, [[
        'tipos' => ['operatividad_garantia', 'capacitacion'],
        'unidades' => array_map(fn ($e) => ['equipment_id' => $e->id], $equipos),
    ]])->all();

    $ajustados = $emitir->handle($sale, [
        ['referencia' => 'SEDE: Chimbote', 'tipos' => ['operatividad_garantia'], 'unidades' => [['equipment_id' => $equipos[0]->id]]],
        ['referencia' => 'SEDE: Trujillo', 'tipos' => ['operatividad_garantia'], 'unidades' => [['equipment_id' => $equipos[1]->id]]],
    ]);

    expect($ajustados->first()->numero)->toBe($operatividad->numero)
        ->and($ajustados->last()->numero)->not->toBe($operatividad->numero)
        ->and($capacitacion->fresh()->estado)->toBe('anulado');
});

test('anular la venta anula sus certificados y el qr deja de mostrarlos como validos', function () {
    [$sale] = ventaConExtintores(1);
    $certificados = app(EmitirCertificadosDeVenta::class)->automaticos($sale);

    app(RevertSale::class)->handle($sale, 'con la nota de crédito FC01-00000003');

    $anulado = $certificados->first()->fresh();

    expect($anulado->estado)->toBe('anulado')
        ->and($anulado->anulado_motivo)->toContain('FC01-00000003');

    $this->getJson(route('certificados.verificar', ['token' => $anulado->qr_token]))
        ->assertOk()
        ->assertJsonPath('estado', 'anulado');
});

test('no se certifican extintores de otra venta ni una venta sin confirmar', function () {
    [$sale] = ventaConExtintores(1);
    [, $ajenos] = ventaConExtintores(1);

    expect(fn () => app(EmitirCertificadosDeVenta::class)->handle($sale, [
        ['tipos' => ['operatividad_garantia'], 'unidades' => [['equipment_id' => $ajenos[0]->id]]],
    ]))->toThrow(ValidationException::class, 'no pertenecen a esta venta');

    $sale->update(['estado' => 'borrador']);

    expect(fn () => app(EmitirCertificadosDeVenta::class)->handle($sale, [
        ['tipos' => ['capacitacion'], 'unidades' => []],
    ]))->toThrow(ValidationException::class, 'venta confirmada');
});

test('con muchos extintores la tabla continua en mas hojas', function (int $extintores, bool $variasHojas) {
    [$sale, $equipos] = ventaConExtintores($extintores);

    $certificado = app(EmitirCertificadosDeVenta::class)->handle($sale, [[
        'tipos' => ['operatividad_garantia'],
        'unidades' => array_map(fn ($e) => ['equipment_id' => $e->id], $equipos),
    ]])[0];

    $pdf = app(CertificatePdfService::class)->generate($certificado)->output();

    expect(preg_match_all('#/Type\s*/Page(?!s)#', $pdf) > 1)->toBe($variasHojas);
})->with([
    'pocos extintores en una hoja' => [3, false],
    '50 extintores en varias hojas' => [50, true],
]);

test('los tres certificados generan su pdf con qr', function (string $destino, int $extintores) {
    [$sale, $equipos] = ventaConExtintores($extintores, $destino);
    $tipos = EmitirCertificadosDeVenta::tiposPorDestino($destino);

    $certificados = app(EmitirCertificadosDeVenta::class)->handle($sale, [[
        'referencia' => $sale->referencia,
        'tipos' => $tipos,
        'unidades' => array_map(fn ($e) => [
            'equipment_id' => $e->id,
            'fecha_ultima_ph' => today()->toDateString(),
            'presion_ph' => '600 PSI',
            'tiempo_ph' => '60 SEG',
            'resultado_ph' => 'aprobado',
        ], $equipos),
    ]]);

    foreach ($certificados as $certificado) {
        $pdf = app(CertificatePdfService::class)->generate($certificado)->output();

        expect($pdf)->toStartWith('%PDF');

        if ($ruta = env('CERTIFICADOS_MUESTRA_DIR')) {
            file_put_contents("{$ruta}/{$certificado->certificateType->codigo}-{$extintores}.pdf", $pdf);
        }
    }
})->with([
    'local con 3 extintores' => ['local_cliente', 3],
    'vehiculo con 1 extintor' => ['vehiculo', 1],
    'local con 20 extintores' => ['local_cliente', 20],
    'local con 50 extintores' => ['local_cliente', 50],
]);

test('el vendedor abre la pantalla de armar certificados y los emite', function () {
    $user = vendedorUser();
    [$sale, $equipos] = ventaConExtintores(2);
    $sale->update(['vendedor_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.certificados.create', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/ventas/certificados')
            ->has('unidades', 2)
            ->where('tiposSugeridos', ['operatividad_garantia', 'capacitacion']));

    $this->actingAs($user)
        ->post(route('vendedor.ventas.certificados.store', ['current_team' => $user->currentTeam, 'sale' => $sale]), [
            'grupos' => [[
                'tipos' => ['operatividad_garantia'],
                'unidades' => [['equipment_id' => $equipos[0]->id], ['equipment_id' => $equipos[0]->id]],
            ]],
        ])
        ->assertSessionHasErrors();

    $this->actingAs($user)
        ->post(route('vendedor.ventas.certificados.store', ['current_team' => $user->currentTeam, 'sale' => $sale]), [
            'grupos' => [[
                'tipos' => ['operatividad_garantia'],
                'unidades' => array_map(fn ($e) => ['equipment_id' => $e->id], $equipos),
            ]],
        ])
        ->assertRedirect(route('vendedor.ventas.show', ['current_team' => $user->currentTeam, 'sale' => $sale]));

    expect(Certificate::where('sale_id', $sale->id)->count())->toBe(1);
});

test('el modo normal conserva el certificado de capacitacion a nombre del cliente', function () {
    [$sale] = ventaConExtintores(1);

    $certificate = app(EmitirCertificadosDeVenta::class)->handle($sale, [[
        'tipos' => ['capacitacion'],
        'unidades' => [],
        'capacitacion' => ['modo' => 'normal'],
    ]])->first();

    expect($certificate->datos['modo'])->toBe('normal')
        ->and($certificate->participants()->count())->toBe(0)
        ->and(app(CertificatePdfService::class)->generate($certificate)->output())->toStartWith('%PDF');
});

test('el modo con fotos guarda hasta tres imagenes dentro del certificado', function () {
    Storage::fake('local');
    [$sale] = ventaConExtintores(1);

    $certificate = app(EmitirCertificadosDeVenta::class)->handle($sale, [[
        'tipos' => ['capacitacion'],
        'unidades' => [],
        'capacitacion' => [
            'modo' => 'con_fotos',
            'fotos' => [UploadedFile::fake()->image('capacitacion-1.png', 1800, 1200)],
        ],
    ]])->first()->fresh();

    expect($certificate->datos['modo'])->toBe('con_fotos')
        ->and($certificate->datos['fotos'])->toHaveCount(1);
    Storage::disk('local')->assertExists($certificate->datos['fotos'][0]);
    expect(app(CertificatePdfService::class)->generate($certificate)->output())->toStartWith('%PDF');
});

test('participantes reciben sufijos correlativos que no se reutilizan y qr propio', function () {
    [$sale] = ventaConExtintores(1);
    $emitir = app(EmitirCertificadosDeVenta::class);

    $certificate = $emitir->handle($sale, [[
        'tipos' => ['capacitacion'],
        'unidades' => [],
        'capacitacion' => [
            'modo' => 'por_trabajador',
            'participantes' => [
                ['nombres' => 'Ana Torres', 'dni' => '75359392'],
                ['nombres' => 'Luis Pérez', 'dni' => null],
            ],
        ],
    ]])->first()->fresh('participants');

    expect($certificate->participants->pluck('sufijo')->all())->toBe([1, 2])
        ->and($certificate->participants->map->numero()->all())->toBe(["{$certificate->numero}-01", "{$certificate->numero}-02"]);

    $luis = $certificate->participants->last();
    $anaToken = $certificate->participants->first()->qr_token;

    $emitir->handle($sale, [[
        'tipos' => ['capacitacion'],
        'unidades' => [],
        'capacitacion' => [
            'modo' => 'por_trabajador',
            'participantes' => [
                ['id' => $luis->id, 'nombres' => $luis->nombres],
                ['nombres' => 'María Ruiz', 'dni' => '70112233'],
            ],
        ],
    ]]);

    $participantes = CertificateParticipant::where('certificate_id', $certificate->id)->orderBy('sufijo')->get();

    expect($participantes->pluck('sufijo')->all())->toBe([1, 2, 3])
        ->and($participantes[0]->anulado_at)->not->toBeNull()
        ->and($participantes[2]->numero())->toBe("{$certificate->numero}-03");

    $this->getJson(route('certificados.verificar', ['token' => $participantes[2]->qr_token]))
        ->assertOk()
        ->assertJsonPath('numero', "{$certificate->numero}-03")
        ->assertJsonPath('participante.nombres', 'María Ruiz');

    $this->getJson(route('certificados.verificar', ['token' => $anaToken]))
        ->assertOk()
        ->assertJsonPath('estado', 'anulado');
});

test('el word del certificado es valido aunque la razon social tenga & o <', function () {
    [$sale, $equipos] = ventaConExtintores(1);
    $sale->client->update(['razon_social' => 'PANADERÍA & PASTELERÍA <ANYLI> S.A.C.']);

    $certificados = app(EmitirCertificadosDeVenta::class)->handle($sale->fresh(), [[
        'tipos' => ['operatividad_garantia', 'capacitacion'],
        'unidades' => [['equipment_id' => $equipos[0]->id]],
    ]]);

    foreach ($certificados as $certificado) {
        $ruta = app(CertificateWordService::class)->generate($certificado->fresh());
        $zip = new ZipArchive;

        expect($zip->open($ruta))->toBeTrue();
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect(@simplexml_load_string($xml))->not->toBeFalse()
            ->and($xml)->toContain('PANADERÍA &amp; PASTELERÍA &lt;ANYLI&gt; S.A.C.');
    }
});

test('la prueba hidrostatica y la capacitacion quedan pendientes al cobrar y la P.H. pide sus datos reales', function () {
    [$sale, $equipos] = ventaConExtintores(1, 'vehiculo');
    $emitir = app(EmitirCertificadosDeVenta::class);

    expect($emitir->automaticos($sale)->map(fn (Certificate $c) => $c->certificateType->codigo)->all())->toBe(['operatividad_garantia']);

    expect(fn () => $emitir->handle($sale, [[
        'tipos' => ['prueba_hidrostatica'],
        'unidades' => [['equipment_id' => $equipos[0]->id]],
    ]]))->toThrow(ValidationException::class, 'fecha, la presión, el tiempo y el resultado');

    expect(fn () => $emitir->handle($sale, [[
        'tipos' => ['prueba_hidrostatica'],
        'unidades' => [['equipment_id' => $equipos[0]->id, 'fecha_ultima_ph' => '2026-09-30', 'presion_ph' => '600 PSI', 'tiempo_ph' => '30 SEG', 'resultado_ph' => 'desaprobado']],
    ]]))->toThrow(ValidationException::class, 'no aprobó');

    $ph = $emitir->handle($sale, [[
        'tipos' => ['prueba_hidrostatica'],
        'unidades' => [['equipment_id' => $equipos[0]->id, 'fecha_ultima_ph' => '2026-09-30', 'presion_ph' => '610 PSI', 'tiempo_ph' => '30 SEG', 'resultado_ph' => 'aprobado']],
    ]])->first()->certificateUnits->first();

    expect($ph->fecha_ultima_ph->toDateString())->toBe('2026-09-30')
        ->and($ph->presion_ph)->toBe('610 PSI')
        ->and($ph->tiempo_ph)->toBe('30 SEG')
        ->and($ph->presion_trabajo)->toBe('195 PSI');
});

test('la presion de trabajo de la P.H. sale del agente real: un CO2 no lleva la de PQS', function () {
    [$sale, $equipos] = ventaConExtintores(1, 'vehiculo');
    $equipos[0]->update(['tipo_agente' => 'CO2']);

    $unidad = app(EmitirCertificadosDeVenta::class)->handle($sale, [[
        'tipos' => ['prueba_hidrostatica'],
        'unidades' => [['equipment_id' => $equipos[0]->id, 'fecha_ultima_ph' => '2026-09-30', 'presion_ph' => '3000 PSI', 'tiempo_ph' => '30 SEG', 'resultado_ph' => 'aprobado']],
    ]])->first()->certificateUnits->first();

    expect($unidad->tipo_agente)->toBe('CO2')
        ->and($unidad->presion_trabajo)->toBe('850 PSI');
});

test('sin agente no se emite el certificado del extintor y la venta no se bloquea', function () {
    [$sale, $equipos] = ventaConExtintores(1);
    $equipos[0]->update(['tipo_agente' => 'No legible']);
    $emitir = app(EmitirCertificadosDeVenta::class);

    expect($emitir->automaticos($sale))->toBeEmpty()
        ->and(fn () => $emitir->handle($sale, [[
            'tipos' => ['operatividad_garantia'],
            'unidades' => [['equipment_id' => $equipos[0]->id]],
        ]]))->toThrow(ValidationException::class, 'Falta el agente extintor');

    expect(Certificate::where('sale_id', $sale->id)->count())->toBe(0);
});
