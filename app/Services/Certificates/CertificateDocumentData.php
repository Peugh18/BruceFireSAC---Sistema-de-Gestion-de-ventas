<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateParticipant;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\ClientSite;
use App\Models\CompanySetting;
use App\Models\Signer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Arma los datos de un certificado para dibujarlo (PDF o Word) a partir de su
 * tipo configurable: textos y columnas de la versión de plantilla con que se
 * emitió, filas, checklist evaluado contra sus mínimos y máximos, resultado
 * global, firmantes con su firma, estado (vigente, vencido, anulado) y QR.
 */
class CertificateDocumentData
{
    private const MESES = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];

    public const RESULTADOS = [
        'operativo' => 'OPERATIVO',
        'observado' => 'OPERATIVO CON OBSERVACIÓN',
        'no_operativo' => 'NO OPERATIVO',
    ];

    public function __construct(protected CertificateNumberGenerator $numeros) {}

    /**
     * @return array<string, mixed>
     */
    public function desde(Certificate $certificate, ?CertificateParticipant $participant = null): array
    {
        $certificate->loadMissing(['certificateType.signers', 'typeVersion', 'client', 'serviceOrder', 'certificateUnits.equipment']);
        $participant?->loadMissing('certificate.client');
        $tipo = $this->plantilla($certificate);
        $datos = $certificate->datos ?? [];
        $client = $certificate->client;
        $pruebas = $this->pruebas($tipo['checklist'] ?? [], $datos['pruebas'] ?? []);
        $filas = ! empty($datos['filas']) ? array_values($datos['filas']) : $this->filasDeEquipos($certificate);
        $conNombreComercial = $client->certificado_con === 'nombre_comercial' && filled($client->nombre_comercial);
        $local = ClientSite::deReferencia($client, $certificate->referencia);

        return [
            'empresa' => $this->empresa(),
            'tipo' => $tipo,
            'certificado' => [
                'numero' => $participant?->numero() ?? $certificate->numero,
                'emision' => $certificate->fecha_emision?->format('d/m/Y'),
                'vencimiento' => $certificate->fecha_vigencia_hasta?->format('d/m/Y'),
                // La vigencia repite el vencimiento y la orden es interna: no se imprimen.
                'vigencia' => null,
                'orden' => null,
                // Sin orden, el certificado se une a la venta por su comprobante.
                'comprobante' => $certificate->serviceOrder === null ? $certificate->sale?->numeroComprobante() : null,
                'tipo_atencion' => $certificate->tipo_atencion ? Str::ucfirst($certificate->tipo_atencion) : null,
                'titulo_datos' => $certificate->tipo_atencion !== null || $certificate->service_order_id !== null ? 'Datos del servicio' : 'Datos del certificado',
                'estado' => $participant?->anulado_at
                    ? ['clave' => 'anulado', 'texto' => 'ANULADO', 'fecha' => $participant->anulado_at->format('d/m/Y')]
                    : $this->estado($certificate),
                'revision' => $certificate->revision,
                'revision_fecha' => $certificate->revision > 0 ? $certificate->updated_at?->format('d/m/Y') : null,
                'anulado_motivo' => $certificate->anulado_motivo,
            ],
            'cliente' => [
                'razon_social' => $participant?->nombres ?? ($conNombreComercial ? $client->nombre_comercial : $client?->razon_social),
                'documento_tipo' => $participant ? ($participant->dni ? 'DNI' : '') : strtoupper((string) ($client?->tipo_documento ?: 'RUC')),
                'documento' => $participant?->dni ?? $client?->numero_documento,
                'nombre_comercial' => $conNombreComercial ? null : $client?->nombre_comercial,
                // Un local elegido en la venta pone su dirección; su nombre ya no hace falta como referencia.
                'direccion' => $certificate->direccion ?: ($local?->direccion ?: $client?->direccion_fiscal),
                'referencia' => $local ? null : $this->referencia($certificate->referencia),
            ],
            'filas' => $filas,
            'pruebas' => $pruebas,
            'resultado' => $datos['resultado'] ?? $this->resultadoGlobal($filas, $pruebas),
            'observaciones' => $datos['observaciones'] ?? null,
            'firmantes' => $this->firmantes($tipo['firmantes'] ?? []),
            'capacitacion' => $this->capacitacion($datos, $certificate->fecha_emision, $participant, $client?->razon_social),
            'qr' => $this->qr(route('certificados.verificar', ['token' => $participant?->qr_token ?? $certificate->qr_token])),
        ];
    }

    /**
     * Certificado de ejemplo con los datos de un tipo, para la vista previa de
     * la plantilla (sin emitir nada ni reservar número).
     *
     * @return array<string, mixed>
     */
    public function muestra(CertificateType $tipo): array
    {
        $tipo->loadMissing('signers');
        $plantilla = $tipo->configuracionActual();
        $filas = [];
        foreach (range(1, 4) as $i) {
            $fila = [];
            foreach ($plantilla['columnas'] ?? [] as $columna) {
                $fila[$columna['clave']] = $this->valorDeEjemplo($columna['clave'], $i);
            }
            $filas[] = $fila;
        }
        $pruebas = $this->pruebas($plantilla['checklist'] ?? [], []);

        return [
            'empresa' => $this->empresa(),
            'tipo' => ['codigo' => $tipo->codigo, ...$plantilla],
            'certificado' => [
                'numero' => $this->numeros->proximo($tipo),
                'emision' => now()->format('d/m/Y'),
                'vencimiento' => $tipo->vigencia_meses > 0 ? now()->addMonths($tipo->vigencia_meses)->format('d/m/Y') : null,
                'vigencia' => null,
                'orden' => null,
                'comprobante' => null,
                'tipo_atencion' => null,
                'titulo_datos' => 'Datos del servicio',
                'estado' => $tipo->vigencia_meses > 0
                    ? ['clave' => 'vigente', 'texto' => 'VIGENTE HASTA', 'fecha' => now()->addMonths($tipo->vigencia_meses)->format('d/m/Y')]
                    : ['clave' => 'vigente', 'texto' => 'CERTIFICADO VÁLIDO', 'fecha' => null],
                'revision' => 0,
                'revision_fecha' => null,
                'anulado_motivo' => null,
            ],
            'cliente' => [
                'razon_social' => 'COMERCIAL LOS ANDES S.A.C.',
                'documento_tipo' => 'RUC',
                'documento' => '20481234567',
                'nombre_comercial' => 'Market Los Andes',
                'direccion' => 'Av. España 1245, Trujillo, La Libertad',
                'referencia' => null,
            ],
            'filas' => $filas,
            'pruebas' => $pruebas,
            'resultado' => 'operativo',
            'observaciones' => null,
            'firmantes' => $this->firmantes($plantilla['firmantes'] ?? []),
            'capacitacion' => $this->capacitacion([], now()),
            'qr' => $this->qr(url('/verificar-certificado/ejemplo')),
        ];
    }

    /**
     * Configuración de la plantilla con la que se emitió el certificado (la foto
     * de su versión) o, si es antiguo, la configuración actual del tipo.
     *
     * @return array<string, mixed>
     */
    protected function plantilla(Certificate $certificate): array
    {
        $configuracion = $certificate->typeVersion?->configuracion
            ?? $certificate->certificateType->configuracionActual();

        // El código BF-EQ es interno de la empresa: el cliente ve la serie de
        // fábrica. Si un formato antiguo lo tenía como columna, se retira.
        $configuracion['columnas'] = array_values(array_filter(
            $configuracion['columnas'] ?? [],
            fn (array $columna) => $columna['clave'] !== 'codigo_interno',
        ));

        // Nuevos y recargados en un mismo certificado: cada fila dice su atención.
        if (str_contains((string) $certificate->tipo_atencion, '/') && $configuracion['columnas'] !== []) {
            $configuracion['columnas'][] = ['clave' => 'atencion', 'titulo' => 'Atención', 'tipo' => 'texto', 'ancho' => 10];
        }

        if ($certificate->certificateType->codigo === 'operatividad_garantia') {
            $configuracion = $this->segunLaAtencion($configuracion, $certificate);
        }

        return ['codigo' => $certificate->certificateType->codigo, ...$configuracion];
    }

    /**
     * E-069: el texto fijo dice lo que de verdad se hizo. Una recarga no es solo
     * mantenimiento, un extintor nuevo no se recargó, y el párrafo de las
     * mangueras de CO2 solo va si hay algún CO2. Un texto que el Gerente
     * reescribió (sin la frase original) se respeta.
     *
     * @param  array<string, mixed>  $configuracion
     * @return array<string, mixed>
     */
    protected function segunLaAtencion(array $configuracion, Certificate $certificate): array
    {
        $atencion = mb_strtolower((string) $certificate->tipo_atencion);
        $nuevo = $atencion === 'nuevo' || ($certificate->service_order_id === null && $atencion === '');
        $agentes = $certificate->certificateUnits->map(fn (CertificateUnit $unit) => mb_strtoupper((string) $unit->equipment?->tipo_agente))->filter();
        $hayCo2 = $agentes->contains(fn (string $agente) => str_contains($agente, 'CO2'));

        $original = 'han sido sometidos a mantenimiento';
        $configuracion['declaracion'] = match (true) {
            $nuevo => str_replace($original, 'son nuevos, se encuentran operativos y cuentan con garantía de fábrica,', (string) ($configuracion['declaracion'] ?? '')),
            $atencion === 'recarga' => str_replace($original, 'han sido sometidos a recarga y mantenimiento', (string) ($configuracion['declaracion'] ?? '')),
            default => $configuracion['declaracion'] ?? null,
        };

        $configuracion['bloques'] = array_values(array_filter(
            $configuracion['bloques'] ?? [],
            fn (array $bloque) => match (mb_strtolower((string) ($bloque['titulo'] ?? ''))) {
                'prueba de conductividad' => $hayCo2,
                'porcentaje de carga pqs' => ! $nuevo,
                default => true,
            },
        ));

        return $configuracion;
    }

    /**
     * @return array{razon_social: string|null, ruc: string|null, direccion: string, ciudad: string|null, telefonos: list<string>, email: string|null}
     */
    protected function empresa(): array
    {
        $empresa = CompanySetting::current();
        $direccion = collect([$empresa->direccion, $empresa->distrito])->filter()
            ->map(fn (string $parte) => Str::of(Str::lower($parte))->title()->replace([' Y ', ' Nro. '], [' y ', ' '])->toString())
            ->implode(', ');

        return [
            'razon_social' => $empresa->razon_social,
            'ruc' => $empresa->ruc,
            'direccion' => $direccion,
            'ciudad' => $empresa->distrito ? Str::title(Str::lower($empresa->distrito)) : null,
            'telefonos' => collect(preg_split('/\s*[\/|,·]\s*/', (string) $empresa->telefono))->filter()->values()->all(),
            'email' => $empresa->email,
        ];
    }

    /**
     * Filas de la tabla para los certificados de extintores, que guardan sus
     * datos como unidades técnicas en lugar de filas libres.
     *
     * @return list<array<string, string|null>>
     */
    protected function filasDeEquipos(Certificate $certificate): array
    {
        return array_values($certificate->certificateUnits
            ->sortBy(fn (CertificateUnit $unit) => [$unit->orden ?? PHP_INT_MAX, $unit->id])
            ->values()
            ->map(fn (CertificateUnit $unit, int $indice) => [
                'item' => $unit->numero_cliente ?: str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT),
                'capacidad' => $unit->capacidad ?: $unit->equipment?->capacidad,
                'serie' => $unit->numero_serie_snapshot,
                'codigo_interno' => $unit->codigo_interno,
                'atencion' => $this->atencionDeLaFila($certificate, $unit),
                'marca' => $unit->marca ?: $unit->equipment?->marca,
                'tipo' => $unit->tipo_agente ?: '—',
                'anio' => (string) ($unit->anio_fabricacion ?: $unit->equipment?->anio_fabricacion),
                'proxima_ph' => $this->mesAnio($this->proximaPh($certificate, $unit)),
                'vencimiento' => $this->mesAnio($certificate->fecha_vigencia_hasta),
                'presion_ph' => $unit->presion_ph ?: '—',
                'tiempo_ph' => $unit->tiempo_ph ?: '—',
                'resultado' => 'operativo',
            ])
            ->all());
    }

    /**
     * Nuevo si el extintor se vendió en este certificado; si no, la atención
     * del servicio (recarga o mantenimiento).
     */
    protected function atencionDeLaFila(Certificate $certificate, CertificateUnit $unit): ?string
    {
        if (! str_contains((string) $certificate->tipo_atencion, '/')) {
            return null;
        }

        $nuevo = $certificate->sale?->items()->where('tipo_linea', 'unidad_nueva')->where('equipment_id', $unit->equipment_id)->exists();
        $servicio = collect(explode('/', (string) $certificate->tipo_atencion))->map(fn (string $parte) => trim($parte))->reject(fn (string $parte) => $parte === 'nuevo')->first();

        return Str::ucfirst($nuevo ? 'nuevo' : ($servicio ?? 'recarga'));
    }

    /**
     * Cruza el checklist de la plantilla con lo marcado en el certificado. Si
     * una prueba tiene un valor medido fuera de su mínimo o máximo, queda NC
     * aunque se haya marcado C.
     *
     * @param  list<array<string, mixed>>  $checklist
     * @param  list<array<string, mixed>>  $marcadas
     * @return list<array{texto: string, valor: string|null, estado: string}>
     */
    protected function pruebas(array $checklist, array $marcadas): array
    {
        $pruebas = [];
        foreach ($checklist as $indice => $item) {
            $marcada = $marcadas[$indice] ?? [];
            $valor = $marcada['valor'] ?? null;
            $estado = $marcada['estado'] ?? 'C';

            if (is_numeric($valor)) {
                $numero = (float) $valor;
                $fueraDeRango = (isset($item['minimo']) && $numero < (float) $item['minimo'])
                    || (isset($item['maximo']) && $numero > (float) $item['maximo']);
                $estado = $fueraDeRango ? 'NC' : $estado;
            }

            $pruebas[] = [
                'texto' => $item['texto'],
                'valor' => $valor !== null && $valor !== '' ? trim($valor.' '.($item['unidad'] ?? '')) : null,
                'estado' => $estado,
            ];
        }

        return $pruebas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{estado: string}>  $pruebas
     */
    protected function resultadoGlobal(array $filas, array $pruebas): string
    {
        $resultados = array_column($filas, 'resultado');

        if (in_array('no_operativo', $resultados, true)) {
            return 'no_operativo';
        }

        if (in_array('observado', $resultados, true) || in_array('NC', array_column($pruebas, 'estado'), true)) {
            return 'observado';
        }

        return 'operativo';
    }

    /**
     * Datos del diploma de capacitación: curso, horas, instructor y la fecha
     * escrita como en el documento ("26 de setiembre de 2026").
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function capacitacion(array $datos, CarbonInterface $fecha, ?CertificateParticipant $participant = null, ?string $empresaCliente = null): array
    {
        if (! empty($datos['fecha'])) {
            $fecha = Carbon::parse($datos['fecha']);
        }

        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre'];

        return [
            'curso' => mb_strtoupper((string) ($datos['curso'] ?? 'USO Y MANEJO DE EXTINTORES')),
            'horas' => (int) ($datos['horas'] ?? 4),
            'instructor' => $datos['instructor'] ?? null,
            'fecha_larga' => $fecha->day.' de '.$meses[$fecha->month - 1].' de '.$fecha->year,
            'modo' => $participant ? 'por_trabajador' : ($datos['modo'] ?? 'normal'),
            'fotos' => collect($datos['fotos'] ?? [])->map(fn (string $ruta) => $this->imagenDelDisco($ruta))->filter()->values()->all(),
            'personal_de' => $participant ? $empresaCliente : null,
            'dni' => $participant?->dni,
            'cargo' => $participant?->cargo,
        ];
    }

    /**
     * "PLACA: B32-928" → ['etiqueta' => 'Placa', 'valor' => 'B32-928'].
     *
     * @return array{etiqueta: string, valor: string}|null
     */
    protected function referencia(?string $referencia): ?array
    {
        if (! $referencia) {
            return null;
        }

        if (! str_contains($referencia, ':')) {
            return ['etiqueta' => 'Referencia', 'valor' => $referencia];
        }

        [$etiqueta, $valor] = explode(':', $referencia, 2);

        return ['etiqueta' => Str::ucfirst(Str::lower(trim($etiqueta))), 'valor' => trim($valor)];
    }

    /**
     * @return array{clave: string, texto: string, fecha: string|null}
     */
    protected function estado(Certificate $certificate): array
    {
        if ($certificate->estado === 'anulado') {
            return ['clave' => 'anulado', 'texto' => 'ANULADO', 'fecha' => $certificate->anulado_at?->format('d/m/Y')];
        }

        if ($certificate->estado === 'reemplazado') {
            return ['clave' => 'reemplazado', 'texto' => 'REEMPLAZADO', 'fecha' => null];
        }

        $vence = $certificate->fecha_vigencia_hasta;

        if ($vence && ($certificate->estado === 'vencido' || $vence->isBefore(today()))) {
            return ['clave' => 'vencido', 'texto' => 'VENCIDO EL', 'fecha' => $vence->format('d/m/Y')];
        }

        return $vence
            ? ['clave' => 'vigente', 'texto' => 'VIGENTE HASTA', 'fecha' => $vence->format('d/m/Y')]
            : ['clave' => 'vigente', 'texto' => 'CERTIFICADO VÁLIDO', 'fecha' => null];
    }

    /**
     * @param  list<array{id: int, nombre?: string, cargo?: string, cip?: string|null, firma?: string|null}>  $firmantes
     * @return list<array{nombre: string, cargo: string, cip: string|null, firma: string|null}>
     */
    protected function firmantes(array $firmantes): array
    {
        $modelos = Signer::query()->whereIn('id', array_column($firmantes, 'id'))->get()->keyBy('id');

        return collect($firmantes)->map(function (array $firmante) use ($modelos) {
            $signer = $modelos->get($firmante['id']);

            return [
                'nombre' => $firmante['nombre'] ?? $signer?->nombre ?? '',
                // El cargo es el del certificado cuando se emitió (la misma persona firma con distintos cargos).
                'cargo' => $firmante['cargo'] ?? $signer?->cargo ?? '',
                'cip' => array_key_exists('cip', $firmante) ? $firmante['cip'] : $signer?->cip,
                'firma' => array_key_exists('firma', $firmante) ? $firmante['firma'] : $this->firmaConSello($signer?->firma_path, $signer?->sello_path),
            ];
        })->values()->all();
    }

    /**
     * Si el firmante tiene sello propio (por ejemplo el del ingeniero con su
     * CIP), se pega a la derecha de la firma montándose un poco sobre ella:
     * una sola imagen que sirve igual para el PDF y el Word.
     */
    public function imagenDelFirmante(Signer $signer): ?string
    {
        return $this->firmaConSello($signer->firma_path, $signer->sello_path);
    }

    protected function firmaConSello(?string $firmaPath, ?string $selloPath): ?string
    {
        $disco = Storage::disk('local');
        $firma = $firmaPath && $disco->exists($firmaPath) ? @imagecreatefromstring($disco->get($firmaPath)) : null;
        $sello = $selloPath && $disco->exists($selloPath) ? @imagecreatefromstring($disco->get($selloPath)) : null;

        if (! $sello) {
            return $this->imagenDelDisco($firmaPath);
        }

        if (! $firma) {
            return $this->imagenDelDisco($selloPath);
        }

        $alto = imagesy($firma);
        $anchoSello = (int) round(imagesx($sello) * $alto / imagesy($sello));
        $montado = (int) round($anchoSello * 0.35);
        $ancho = imagesx($firma) + $anchoSello - $montado;

        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 255, 255, 255, 127));
        imagealphablending($lienzo, true);
        imagecopyresampled($lienzo, $sello, imagesx($firma) - $montado, 0, 0, 0, $anchoSello, $alto, imagesx($sello), imagesy($sello));
        imagecopy($lienzo, $firma, 0, 0, 0, 0, imagesx($firma), $alto);

        ob_start();
        imagepng($lienzo);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    protected function imagenDelDisco(?string $ruta): ?string
    {
        if (! $ruta || ! Storage::disk('local')->exists($ruta)) {
            return null;
        }

        $tipo = Storage::disk('local')->mimeType($ruta) ?: 'image/png';

        return 'data:'.$tipo.';base64,'.base64_encode(Storage::disk('local')->get($ruta));
    }

    /**
     * @return array{imagen: string, url: string}
     */
    protected function qr(string $url): array
    {
        return [
            'imagen' => 'data:image/png;base64,'.base64_encode((new PngWriter)->write(new QrCode($url))->getString()),
            'url' => $url,
        ];
    }

    protected function proximaPh(Certificate $certificate, CertificateUnit $unit): ?CarbonInterface
    {
        if ($unit->fechas_snapshot) {
            return $unit->proxima_prueba_hidrostatica;
        }
        if ($unit->fecha_ultima_ph) {
            return $unit->fecha_ultima_ph->copy()->addYears(5);
        }

        return $unit->equipment?->proxima_prueba_hidrostatica;
    }

    protected function mesAnio(?CarbonInterface $fecha): string
    {
        return $fecha ? self::MESES[$fecha->month - 1].' – '.$fecha->year : '—';
    }

    protected function valorDeEjemplo(string $clave, int $fila): string
    {
        $ejemplos = [
            'item' => str_pad((string) $fila, 2, '0', STR_PAD_LEFT),
            'ubicacion' => ['Recepción', 'Pasillo 1.er piso', 'Escalera principal', 'Área de oficinas'][$fila - 1],
            'marca' => ['OPALUX', 'HALUX', 'OPALUX', 'HALUX'][$fila - 1],
            'modelo' => ['9101-220LED Slim', 'HL-706LED', '9101-220LED Slim', 'HL-716LED'][$fila - 1],
            'potencia' => '4.4 W',
            'bateria' => '4 V – 2.5 Ah',
            'autonomia' => ['8 h (2 faros)', '8 h (2 faros)', '16 h (1 faro)', '8 h (2 faros)'][$fila - 1],
            'componente' => ['Detector de humo N.° 01', 'Detector de temperatura N.° 01', 'Sirena con luz estroboscópica N.° 01', 'Estación manual N.° 01'][$fila - 1],
            'prueba' => ['Activación con aerosol de prueba', 'Prueba con fuente de calor controlada', 'Prueba sonora y visual', 'Activación manual y restablecimiento'][$fila - 1],
            'capacidad' => ['6 Kg', '6 Kg', '9 Kg', '4 Kg'][$fila - 1],
            'serie' => ['00543', '000312', '00432', '0312321'][$fila - 1],
            'tipo' => 'PQS-ABC',
            'anio' => '2026',
            'proxima_ph' => 'SET – 2031',
            'vencimiento' => 'SET – 2027',
            'presion_ph' => '600 PSI',
            'tiempo_ph' => '60 SEG',
            'ambiente' => ['Vitrina principal', 'Mampara de ingreso', 'Ventanal 2.º piso', 'Puerta de oficina'][$fila - 1],
            'espesor' => '4 micras',
            'metros' => ['6.5 m²', '4.2 m²', '8.0 m²', '2.1 m²'][$fila - 1],
            'resultado' => 'operativo',
        ];

        return $ejemplos[$clave] ?? '—';
    }
}
