<?php

use App\Actions\Certificates\GuardarFirmante;
use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\Signer;
use App\Models\TipoDeServicio;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentData;
use App\Services\Certificates\CertificatePdfService;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
    $this->equipo = ['current_team' => $this->gerente->currentTeam];
});

test('el gerente ve los certificados y cuáles sirven de base para uno nuevo', function () {
    $this->actingAs($this->gerente)
        ->get(route('gerente.configuracion.certificados.index', $this->equipo))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/configuracion/certificados')
            ->has('certificados', 6)
            ->where('certificados.0.puede_ser_base', fn ($valor) => is_bool($valor)));
});

test('crea un certificado nuevo a partir de un informe y conserva sus columnas', function () {
    $base = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();

    $this->actingAs($this->gerente)
        ->post(route('gerente.configuracion.certificados.store', $this->equipo), [
            'base_id' => $base->id,
            'nombre' => 'Operatividad de gabinetes contra incendio',
            'titulo' => 'CERTIFICADO DE OPERATIVIDAD',
            'subtitulo' => 'GABINETES CONTRA INCENDIO',
            'norma' => 'Conforme a la NFPA 14.',
            'declaracion' => 'Se certifica que los gabinetes fueron inspeccionados.',
            'prefijo' => 'GB',
            'vigencia_meses' => 12,
        ])
        ->assertSessionHasNoErrors();

    $nuevo = CertificateType::query()->where('prefijo', 'GB')->sole();
    expect($nuevo->codigo)->toBe('operatividad_de_gabinetes_contra_incendio')
        ->and($nuevo->columnas)->toBe($base->columnas)
        ->and($nuevo->familia)->toBe($base->familia)
        ->and($nuevo->generado_por_rol)->toBe($base->generado_por_rol);
});

test('no se usa de base un certificado de extintores', function () {
    $extintores = CertificateType::query()->where('codigo', 'operatividad_garantia')->sole();

    $this->actingAs($this->gerente)
        ->post(route('gerente.configuracion.certificados.store', $this->equipo), [
            'base_id' => $extintores->id, 'nombre' => 'Copia', 'titulo' => 'X', 'prefijo' => 'CP', 'vigencia_meses' => 12,
        ])
        ->assertSessionHasErrors('base_id');
});

test('editar los textos sube la versión: lo ya emitido conserva la suya', function () {
    $tipo = CertificateType::query()->where('codigo', 'operatividad_garantia')->sole();
    $version = $tipo->version ?? 1;

    $this->actingAs($this->gerente)
        ->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [
            'nombre' => $tipo->nombre,
            'titulo' => $tipo->titulo,
            'subtitulo' => $tipo->subtitulo,
            'norma' => 'Conforme a la NTP 350.043-1:2011.',
            'declaracion' => $tipo->declaracion,
            'responsabilidad' => $tipo->responsabilidad,
            'prefijo' => $tipo->prefijo,
            'vigencia_meses' => 12,
        ])
        ->assertSessionHasNoErrors();

    expect($tipo->fresh()->norma)->toBe('Conforme a la NTP 350.043-1:2011.')
        ->and($tipo->fresh()->version)->toBe($version + 1)
        ->and($tipo->fresh()->codigo)->toBe('operatividad_garantia');
});

test('el marco es fijo: no se aceptan posiciones manuales y al guardar se borran las de antes', function () {
    $tipo = CertificateType::query()->where('codigo', 'operatividad_garantia')->sole();
    $tipo->update(['diseno' => ['encabezado' => ['x' => 4, 'y' => 8]]]);
    $datos = $tipo->only(['nombre', 'titulo', 'prefijo', 'vigencia_meses']);
    $ruta = route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]);

    $this->actingAs($this->gerente)->put($ruta, [...$datos, 'diseno' => ['firmas' => ['x' => -5, 'y' => 3]]])
        ->assertSessionHasErrors('diseno');
    $this->put($ruta, $datos)->assertSessionHasNoErrors();

    expect($tipo->fresh()->diseno)->toBeNull();
    $this->get(route('gerente.configuracion.certificados.edit', [...$this->equipo, 'certificado' => $tipo]))
        ->assertInertia(fn (Assert $page) => $page->missing('certificado.diseno'));
});

test('el PDF ignora posiciones manuales guardadas antes y entra en una hoja', function (string $codigo) {
    $tipo = CertificateType::query()->where('codigo', $codigo)->sole();
    $tipo->update(['diseno' => ['encabezado' => ['x' => 4, 'y' => 8], 'firmas' => ['x' => -5, 'y' => 3]]]);
    $servicio = app(CertificatePdfService::class);
    $datos = app(CertificateDocumentData::class)->muestra($tipo);

    expect($servicio->html($datos))->not->toContain('translate(4pt, 8pt)')->not->toContain('left: 4pt; top: 8pt;')
        ->and(tap($servicio->muestra($tipo))->output()->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
})->with(['operatividad_garantia', 'luces_emergencia', 'capacitacion']);

test('un certificado emitido conserva el nombre CIP y firma aunque se actualice la persona', function () {
    Storage::fake('local');
    $tipo = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();
    $persona = Signer::create(['nombre' => 'Ingeniera Original', 'cargo' => 'Ingeniera', 'cip' => '12345', 'activo' => true, 'firma_path' => 'firmas/original.png']);
    Storage::disk('local')->put('firmas/original.png', base64_decode(explode(',', FIRMA_PNG)[1]));
    $tipo->signers()->sync([$persona->id => ['orden' => 1, 'cargo' => 'Responsable técnico']]);
    $emitido = app(IssueCertificate::class)->handle($tipo, Client::factory()->create(), [], extra: ['datos' => ['filas' => []]]);
    $antes = app(CertificateDocumentData::class)->desde($emitido)['firmantes'][0];

    app(GuardarFirmante::class)->handle($persona, ['nombre' => 'Ingeniera Actualizada', 'cargo' => 'Gerente', 'cip' => '99999', 'quitar_firma' => true]);
    $despues = app(CertificateDocumentData::class)->desde($emitido->fresh())['firmantes'][0];

    expect($despues)->toBe($antes);
    expect($despues['nombre'])->toBe('Ingeniera Original');
    expect($despues['cip'])->toBe('12345');
    expect($despues['firma'])->not->toBeNull();
    expect($tipo->fresh()->version)->toBeGreaterThan($emitido->typeVersion->version);
});

test('el editor abre para crear uno nuevo con los formatos base y para editar', function () {
    $fijo = CertificateType::query()->where('codigo', 'operatividad_garantia')->sole();

    $this->actingAs($this->gerente)
        ->get(route('gerente.configuracion.certificados.create', $this->equipo))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gerente/configuracion/certificado-editor')
            ->where('certificado', null)
            ->where('bases', fn ($bases) => collect($bases)->doesntContain('id', $fijo->id) && collect($bases)->isNotEmpty())
            ->where('bases.0.columnas', fn ($columnas) => collect($columnas)->doesntContain('clave', 'item')));

    $this->actingAs($this->gerente)
        ->get(route('gerente.configuracion.certificados.edit', [...$this->equipo, 'certificado' => $fijo]))
        ->assertInertia(fn (Assert $page) => $page->where('certificado.formato_fijo', true));
});

test('la tabla y las pruebas se editan: renombrar conserva la clave, lo nuevo recibe una e ítem y resultado se quedan', function () {
    $base = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();

    $this->actingAs($this->gerente)
        ->post(route('gerente.configuracion.certificados.store', $this->equipo), [
            'base_id' => $base->id,
            'nombre' => 'Operatividad de gabinetes',
            'titulo' => 'CERTIFICADO DE OPERATIVIDAD',
            'prefijo' => 'GCI',
            'vigencia_meses' => 12,
            'orientacion' => 'horizontal',
            'columnas' => [
                ['clave' => 'ubicacion', 'titulo' => 'Ubicación del gabinete', 'tipo' => 'texto', 'obligatorio' => true],
                ['titulo' => 'Presión', 'tipo' => 'numero', 'unidad' => 'psi'],
            ],
            'checklist' => [
                ['texto' => 'Manguera sin cortes'],
                ['texto' => 'Autonomía verificada', 'unidad' => 'h', 'minimo' => 1.5],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('gerente.configuracion.certificados.index', $this->equipo));

    $nuevo = CertificateType::query()->where('prefijo', 'GCI')->sole();
    expect(array_column($nuevo->columnas, 'clave'))->toBe(['item', 'ubicacion', 'presion', 'resultado'])
        ->and($nuevo->columnas[1]['titulo'])->toBe('Ubicación del gabinete')
        ->and($nuevo->columnas[2])->toMatchArray(['tipo' => 'numero', 'unidad' => 'psi'])
        ->and($nuevo->orientacion)->toBe('horizontal')
        ->and($nuevo->checklist[1])->toEqualCanonicalizing(['texto' => 'Autonomía verificada', 'unidad' => 'h', 'minimo' => 1.5])
        ->and(app(CertificatePdfService::class)->muestra($nuevo)->getDomPDF()->getPaperOrientation())->toBe('landscape');
});

test('en hoja vertical no entran más columnas que el máximo', function () {
    $base = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();
    $columnas = array_map(fn (int $n) => ['titulo' => "Dato {$n}", 'tipo' => 'texto'], range(1, 8));

    $this->actingAs($this->gerente)
        ->post(route('gerente.configuracion.certificados.store', $this->equipo), [
            'base_id' => $base->id, 'nombre' => 'Muchas columnas', 'titulo' => 'X', 'prefijo' => 'MC', 'vigencia_meses' => 12,
            'orientacion' => 'vertical', 'columnas' => $columnas,
        ])
        ->assertSessionHasErrors('columnas');
});

test('un formato fijo solo cambia sus textos aunque lleguen columnas', function () {
    $tipo = CertificateType::query()->where('codigo', 'operatividad_garantia')->sole();
    $columnas = $tipo->columnas;

    $this->actingAs($this->gerente)
        ->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [
            'nombre' => $tipo->nombre, 'titulo' => $tipo->titulo, 'prefijo' => $tipo->prefijo, 'vigencia_meses' => 12,
            'orientacion' => 'horizontal', 'columnas' => [['titulo' => 'Otra', 'tipo' => 'texto']],
        ])
        ->assertSessionHasNoErrors();

    expect($tipo->fresh()->columnas)->toBe($columnas)
        ->and($tipo->fresh()->orientacion)->toBe('vertical');
});

test('quién firma y en qué orden se elige en el editor y sube la versión', function () {
    $tipo = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();
    $version = $tipo->version ?? 1;
    $otro = Signer::query()->create(['nombre' => 'Ana Ríos', 'cargo' => 'Técnica', 'activo' => true]);
    $orden = [$otro->id, ...$tipo->signers->pluck('id')->reverse()->values()->all()];
    $firmas = array_map(fn (int $id) => ['id' => $id, 'cargo' => $id === $otro->id ? 'Supervisora de campo' : null], $orden);
    $datos = ['nombre' => $tipo->nombre, 'titulo' => $tipo->titulo, 'prefijo' => $tipo->prefijo, 'vigencia_meses' => 12];

    $this->actingAs($this->gerente)
        ->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [...$datos, 'firmantes' => $firmas])
        ->assertSessionHasNoErrors();

    expect($tipo->fresh()->signers->pluck('id')->all())->toBe($orden)
        ->and($tipo->fresh()->configuracionActual()['firmantes'][0]['cargo'])->toBe('Supervisora de campo')
        ->and($tipo->fresh()->configuracionActual()['firmantes'][1]['cargo'])->toBe($tipo->signers->last()->cargo)
        ->and($tipo->fresh()->version)->toBe($version + 1);

    $this->actingAs($this->gerente)
        ->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [...$datos, 'firmantes' => []])
        ->assertSessionHasErrors('firmantes');
});

test('creador dinamico crea desde cero y relaciona productos y tipos de servicio', function () {
    $producto = Product::factory()->create();
    $tipoServicio = TipoDeServicio::create(['nombre' => 'Garantía de mangueras', 'activo' => true]);
    $servicio = Service::factory()->create(['tipo_de_servicio_id' => $tipoServicio->id]);
    $firmante = Signer::query()->where('activo', true)->firstOrFail();
    $composicion = [['tipo' => 'datos'], ['tipo' => 'texto', 'titulo' => 'Garantía', 'texto' => 'Cobertura de doce meses.']];

    $this->actingAs($this->gerente)->post(route('gerente.configuracion.certificados.store', $this->equipo), [
        'nombre' => 'Garantía dinámica', 'titulo' => 'CERTIFICADO', 'prefijo' => 'GDM', 'vigencia_meses' => 12,
        'composicion' => $composicion, 'columnas' => [], 'firmantes' => [['id' => $firmante->id, 'cargo' => 'Responsable']],
        'product_ids' => [$producto->id], 'tipo_de_servicio_ids' => [$tipoServicio->id],
    ])->assertSessionHasNoErrors();

    $nuevo = CertificateType::query()->where('prefijo', 'GDM')->sole();
    expect($nuevo->composicion)->toEqual($composicion)
        ->and($nuevo->familia)->toBe('operatividad')
        ->and($producto->fresh()->certificate_type_id)->toBe($nuevo->id)
        ->and($tipoServicio->fresh()->certificate_type_id)->toBe($nuevo->id)
        ->and($servicio->fresh()->certificate_type_id)->toBe($nuevo->id);
    $emitido = app(IssueCertificate::class)->handle($nuevo, Client::factory()->create(), []);
    $this->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $nuevo]), [
        'nombre' => $nuevo->nombre, 'titulo' => $nuevo->titulo, 'prefijo' => $nuevo->prefijo, 'vigencia_meses' => 12,
        'composicion' => [['tipo' => 'texto', 'texto' => 'Nueva cobertura']], 'product_ids' => [], 'tipo_de_servicio_ids' => [],
    ])->assertSessionHasNoErrors();
    expect($emitido->fresh()->typeVersion->configuracion['composicion'])->toEqual($composicion)
        ->and($producto->fresh()->certificate_type_id)->toBeNull()
        ->and($servicio->fresh()->certificate_type_id)->toBeNull();
});

test('creador dinamico rechaza bloques desconocidos repetidos y marco desplazado', function (array $extra, string $error) {
    $tipo = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();
    $datos = $tipo->only(['nombre', 'titulo', 'prefijo', 'vigencia_meses']);
    $this->actingAs($this->gerente)->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [
        ...$datos, 'composicion' => [['tipo' => 'datos']], ...$extra,
    ])->assertSessionHasErrors($error);
})->with([
    'tipo desconocido' => [['composicion' => [['tipo' => 'script']]], 'composicion.0.tipo'],
    'datos repetidos' => [['composicion' => [['tipo' => 'datos'], ['tipo' => 'datos']]], 'composicion'],
    'clave desconocida' => [['composicion' => [['tipo' => 'texto', 'html' => '<script>']]], 'composicion.0'],
    'marco protegido' => [['diseno' => ['encabezado' => ['x' => 2, 'y' => 0]]], 'diseno'],
]);

test('creador dinamico no roba relaciones de otra plantilla y no guarda cambios parciales', function () {
    $tipo = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();
    $otro = CertificateType::query()->where('codigo', 'lamina_seguridad')->first() ?? CertificateType::query()->where('id', '!=', $tipo->id)->firstOrFail();
    $producto = Product::factory()->create(['certificate_type_id' => $otro->id]);
    $datos = $tipo->only(['nombre', 'titulo', 'prefijo', 'vigencia_meses']);

    $this->actingAs($this->gerente)->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [
        ...$datos, 'titulo' => 'Cambio que no debe guardarse', 'composicion' => [['tipo' => 'datos']], 'product_ids' => [$producto->id],
    ])->assertSessionHasErrors('product_ids');
    expect($tipo->fresh()->titulo)->toBe($datos['titulo'])
        ->and($producto->fresh()->certificate_type_id)->toBe($otro->id);
});

test('creador dinamico exige diploma horizontal y mantiene fijos los formatos originales', function () {
    $firmante = Signer::query()->where('activo', true)->firstOrFail();
    $datos = ['nombre' => 'Reconocimiento', 'titulo' => 'CERTIFICADO', 'prefijo' => 'RDM', 'vigencia_meses' => 0,
        'familia' => 'diploma', 'orientacion' => 'vertical', 'composicion' => [['tipo' => 'diploma']],
        'firmantes' => [['id' => $firmante->id]]];
    $this->actingAs($this->gerente)->post(route('gerente.configuracion.certificados.store', $this->equipo), $datos)
        ->assertSessionHasErrors('orientacion');
    $this->post(route('gerente.configuracion.certificados.store', $this->equipo), [...$datos, 'orientacion' => 'horizontal', 'composicion' => [['tipo' => 'datos']]])
        ->assertSessionHasErrors('composicion');
    $this->post(route('gerente.configuracion.certificados.store', $this->equipo), [...$datos, 'orientacion' => 'horizontal'])
        ->assertSessionHasNoErrors();
    expect(CertificateType::query()->where('prefijo', 'RDM')->sole()->familia)->toBe('diploma');

    $fijo = CertificateType::query()->where('codigo', 'capacitacion')->sole();
    $this->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $fijo]), [
        ...$fijo->only(['nombre', 'titulo', 'vigencia_meses']), 'prefijo' => 'FDM', 'composicion' => [['tipo' => 'texto', 'texto' => 'Otro formato']],
    ])->assertSessionHasErrors('composicion');
});

test('creador dinamico expone relaciones y bloques del editor sin ofrecer recursos inactivos', function () {
    $tipo = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();
    $producto = Product::factory()->create(['certificate_type_id' => $tipo->id]);
    Product::factory()->create(['activo' => false]);
    $servicio = TipoDeServicio::create(['nombre' => 'Inspección dinámica', 'certificate_type_id' => $tipo->id, 'activo' => true]);
    TipoDeServicio::create(['nombre' => 'Inspección anterior', 'activo' => false]);

    $this->actingAs($this->gerente)->get(route('gerente.configuracion.certificados.edit', [...$this->equipo, 'certificado' => $tipo]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificado.composicion', null)
            ->where('certificado.product_ids', [$producto->id])
            ->where('certificado.tipo_de_servicio_ids', [$servicio->id])
            ->where('productosDisponibles', fn ($items) => collect($items)->pluck('id')->all() === [$producto->id])
            ->where('tiposDeServicioDisponibles', fn ($items) => collect($items)->pluck('id')->all() === [$servicio->id]));
});

test('creador dinamico valida contenido necesario y limites de mediciones', function (array $extra, string $error) {
    $firmante = Signer::query()->where('activo', true)->firstOrFail();
    $this->actingAs($this->gerente)->post(route('gerente.configuracion.certificados.store', $this->equipo), [
        'nombre' => 'Contenido dinámico', 'titulo' => 'CERTIFICADO', 'prefijo' => 'CDM', 'vigencia_meses' => 12,
        'composicion' => [['tipo' => 'datos']], 'firmantes' => [['id' => $firmante->id]], ...$extra,
    ])->assertSessionHasErrors($error);
})->with([
    'tabla sin columnas' => [['composicion' => [['tipo' => 'tabla']]], 'columnas'],
    'texto en blanco' => [['composicion' => [['tipo' => 'texto', 'texto' => '   ']]], 'composicion.0.texto'],
    'pruebas sin puntos' => [['composicion' => [['tipo' => 'pruebas']]], 'checklist'],
    'resultado sin evaluacion' => [['composicion' => [['tipo' => 'resultado']]], 'composicion'],
    'limites invertidos' => [['checklist' => [['texto' => 'Presión', 'minimo' => 10, 'maximo' => 2]]], 'checklist.0.maximo'],
]);

test('creador dinamico desde cero exige composicion y firmantes', function () {
    $this->actingAs($this->gerente)->post(route('gerente.configuracion.certificados.store', $this->equipo), [
        'nombre' => 'Plantilla incompleta', 'titulo' => 'CERTIFICADO', 'prefijo' => 'INC', 'vigencia_meses' => 12,
    ])->assertSessionHasErrors(['composicion', 'firmantes']);
});

test('creador dinamico incluye resultado obligatorio en la tabla evaluada', function () {
    $firmante = Signer::query()->where('activo', true)->firstOrFail();
    $this->actingAs($this->gerente)->post(route('gerente.configuracion.certificados.store', $this->equipo), [
        'nombre' => 'Evaluación dinámica', 'titulo' => 'CERTIFICADO', 'prefijo' => 'EDM', 'vigencia_meses' => 12,
        'composicion' => [['tipo' => 'tabla'], ['tipo' => 'resultado']], 'firmantes' => [['id' => $firmante->id]],
        'columnas' => [['titulo' => 'Equipo', 'tipo' => 'texto', 'obligatorio' => true]],
    ])->assertSessionHasNoErrors();

    $tipo = CertificateType::query()->where('prefijo', 'EDM')->sole();
    expect(collect($tipo->columnas)->firstWhere('clave', 'resultado'))
        ->toMatchArray(['tipo' => 'resultado', 'obligatorio' => true]);
});

test('el diploma original conserva el prefijo BF-UME al editar sus textos', function () {
    $tipo = CertificateType::query()->where('codigo', 'capacitacion')->sole();
    $this->actingAs($this->gerente)->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [
        ...$tipo->only(['nombre', 'titulo', 'prefijo', 'vigencia_meses']), 'declaracion' => 'Constancia de participación actualizada.',
    ])->assertSessionHasNoErrors();
    expect($tipo->fresh()->prefijo)->toBe('BF-UME')
        ->and($tipo->fresh()->declaracion)->toBe('Constancia de participación actualizada.');
});
