<?php

use App\Actions\Certificates\GuardarFirmante;
use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Signer;
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

test('la distribucion visual se guarda versionada tambien en formatos fijos y conserva el formato anterior', function () {
    $tipo = CertificateType::query()->where('codigo', 'operatividad_garantia')->sole();
    $anterior = $tipo->versions()->create(['version' => $tipo->version, 'configuracion' => $tipo->configuracionActual()]);
    $diseno = ['encabezado' => ['x' => 4, 'y' => 8], 'firmas' => ['x' => -5, 'y' => 3]];
    $datos = $tipo->only(['nombre', 'titulo', 'prefijo', 'vigencia_meses']);

    $this->actingAs($this->gerente)->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [...$datos, 'diseno' => $diseno])
        ->assertSessionHasNoErrors();

    expect($tipo->fresh()->diseno)->toEqual($diseno)
        ->and($tipo->fresh()->configuracionActual()['diseno'])->toEqual($diseno)
        ->and($tipo->fresh()->version)->toBe($anterior->version + 1)
        ->and($anterior->fresh()->configuracion['diseno'] ?? null)->toBeNull();
    $this->get(route('gerente.configuracion.certificados.edit', [...$this->equipo, 'certificado' => $tipo]))
        ->assertInertia(fn (Assert $page) => $page->where('certificado.diseno.encabezado.x', 4)->where('certificado.diseno.firmas.x', -5));
});

test('el PDF usa la distribucion guardada en cada familia sin alterar versiones emitidas', function (string $codigo) {
    $tipo = CertificateType::query()->where('codigo', $codigo)->sole();
    $tipo->update(['diseno' => ['encabezado' => ['x' => 4, 'y' => 8], 'firmas' => ['x' => -5, 'y' => 3]]]);
    $pdf = app(CertificatePdfService::class)->muestra($tipo);
    $html = $pdf->getDomPDF()->getDom()->saveHTML();
    $pdf->output();
    expect($html)->toContain($codigo === 'capacitacion' ? 'translate(4pt, 8pt)' : 'left: 4pt; top: 8pt;');
    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
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

test('la distribucion visual rechaza posiciones fuera de la hoja y zonas desconocidas', function () {
    $tipo = CertificateType::query()->where('codigo', 'luces_emergencia')->sole();
    $datos = $tipo->only(['nombre', 'titulo', 'prefijo', 'vigencia_meses']);

    $this->actingAs($this->gerente)->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [...$datos, 'diseno' => ['firmas' => ['x' => 9999, 'y' => 0]]])
        ->assertSessionHasErrors('diseno.firmas.x');
    $this->put(route('gerente.configuracion.certificados.update', [...$this->equipo, 'certificado' => $tipo]), [...$datos, 'diseno' => ['script' => ['x' => 0, 'y' => 0]]])
        ->assertSessionHasErrors('diseno');
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
