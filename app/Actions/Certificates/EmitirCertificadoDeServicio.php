<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmitirCertificadoDeServicio
{
    /**
     * Tipos que se certifican por extintor o por curso, no con un formulario.
     */
    public const TIPOS_DE_EXTINTORES = ['operatividad_garantia', 'prueba_hidrostatica', 'capacitacion'];

    public const RESULTADOS = ['operativo', 'observado', 'no_operativo'];

    public const ESTADOS_PRUEBA = ['C', 'NC', 'NA'];

    public const ATENCIONES = ['instalación', 'mantenimiento', 'inspección'];

    public function __construct(
        protected IssueCertificate $issueCertificate,
    ) {}

    /**
     * Tipos de servicio con formulario propio (luces de emergencia, sistema
     * de detección, lámina y los que agregue el Gerente con columnas).
     *
     * @return Collection<int, CertificateType>
     */
    public static function tiposDeServicio(?Sale $sale = null): Collection
    {
        return CertificateType::query()
            ->whereNotIn('codigo', self::TIPOS_DE_EXTINTORES)
            ->where('activo', true)
            ->where(fn ($query) => $query->whereNotNull('columnas')->orWhereNotNull('composicion'))
            ->when($sale !== null, fn ($query) => $query->where(fn ($catalogo) => $catalogo
                ->whereHas('services.saleItems', fn ($items) => $items->where('sale_id', $sale->id))
                ->orWhereHas('products.saleItems', fn ($items) => $items->where('sale_id', $sale->id))))
            ->orderBy('nombre')
            ->get()
            ->filter(fn (CertificateType $tipo) => self::esFormulario($tipo))
            ->values();
    }

    public static function esFormulario(CertificateType $tipo): bool
    {
        return ! in_array($tipo->codigo, self::TIPOS_DE_EXTINTORES, true)
            && ($tipo->composicion !== null || ! empty($tipo->columnas));
    }

    public static function usaBloque(CertificateType $tipo, string $bloque): bool
    {
        return $tipo->composicion === null
            ? in_array($bloque, ['tabla', 'pruebas'], true)
            : in_array($bloque, array_column($tipo->composicion, 'tipo'), true);
    }

    public static function correspondeAVenta(Sale $sale, CertificateType $tipo): bool
    {
        return $sale->items()->where(fn ($items) => $items
            ->whereHas('service', fn ($query) => $query->where('certificate_type_id', $tipo->id))
            ->orWhereHas('product', fn ($query) => $query->where('certificate_type_id', $tipo->id)))->exists();
    }

    public static function existente(Sale $sale, CertificateType $tipo): ?Certificate
    {
        return $sale->certificates()->with('typeVersion')
            ->where('certificate_type_id', $tipo->id)->where('estado', 'vigente')->latest('id')->first();
    }

    public static function plantillaParaVenta(Sale $sale, CertificateType $tipo): CertificateType
    {
        $configuracion = self::existente($sale, $tipo)?->typeVersion?->configuracion;
        if ($configuracion === null) {
            return $tipo;
        }

        $historica = clone $tipo;
        $historica->fill(array_intersect_key($configuracion, array_flip(CertificateType::CAMPOS_PLANTILLA)));
        $historica->composicion = $configuracion['composicion'] ?? null;

        return $historica;
    }

    /**
     * Emite (o corrige, si ya existe uno vigente de ese tipo en la venta) el
     * certificado de un servicio con las filas y pruebas llenadas según la
     * configuración del tipo.
     *
     * @param  array{referencia?: string|null, direccion?: string|null, tipo_atencion?: string|null, filas?: list<array<string, mixed>>, pruebas?: list<array{estado?: string, valor?: string|null}>, observaciones?: string|null}  $datos
     */
    public function handle(Sale $sale, CertificateType $tipo, array $datos, ?int $userId = null): Certificate
    {
        if ($sale->estado !== 'confirmada') {
            throw ValidationException::withMessages(['filas' => 'Solo se certifica un servicio de una venta confirmada.']);
        }

        $existente = self::existente($sale, $tipo);
        $plantilla = self::plantillaParaVenta($sale, $tipo);
        if (! self::esFormulario($plantilla) || (! $tipo->activo && $existente === null)) {
            throw ValidationException::withMessages(['tipo' => 'Este tipo de certificado no se llena con formulario.']);
        }

        if (! self::correspondeAVenta($sale, $tipo)) {
            throw ValidationException::withMessages(['tipo' => 'Esta venta no incluye el producto o servicio correspondiente a este certificado.']);
        }

        $filas = $this->filas($plantilla, $datos['filas'] ?? []);
        $pruebas = $this->pruebas($plantilla, $datos['pruebas'] ?? []);
        // Un resultado sin nada evaluado no puede declarar "operativo".
        if (self::usaBloque($plantilla, 'resultado') && $filas === []
            && $pruebas !== [] && collect($pruebas)->every(fn (array $prueba) => $prueba['estado'] === 'NA')) {
            throw ValidationException::withMessages(['pruebas' => 'Marca al menos una prueba como conforme o no conforme: con todas en «No aplica» no hay resultado que certificar.']);
        }
        $capacitacion = [];
        if (self::usaBloque($plantilla, 'diploma')) {
            $validado = Validator::make($datos, [
                'capacitacion.curso' => ['required', 'string', 'max:255'],
                'capacitacion.horas' => ['required', 'integer', 'min:1', 'max:10000'],
                'capacitacion.instructor' => ['required', 'string', 'max:255'],
                'capacitacion.fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            ])->validate();
            $capacitacion = [...$validado['capacitacion'], 'horas' => (int) $validado['capacitacion']['horas'], 'fotos' => $existente?->datos['fotos'] ?? []];
        }

        $extra = [
            'referencia' => $datos['referencia'] ?? $sale->referencia,
            'direccion' => $datos['direccion'] ?? null,
            // Un producto vendido no es una atención: se pide null explícito.
            'tipo_atencion' => array_key_exists('tipo_atencion', $datos) && $datos['tipo_atencion'] === null
                ? null
                : (in_array($datos['tipo_atencion'] ?? null, self::ATENCIONES, true) ? $datos['tipo_atencion'] : 'mantenimiento'),
            'datos' => [
                ...$capacitacion,
                'filas' => $filas,
                'pruebas' => $pruebas,
                'observaciones' => trim((string) ($datos['observaciones'] ?? '')) ?: null,
            ],
        ];

        if ($existente) {
            return $this->issueCertificate->corregir($existente, [], $extra, 'Datos del servicio corregidos', $userId);
        }

        return $this->issueCertificate->handle($tipo, $sale->client, [], saleId: $sale->id, extra: $extra);
    }

    /**
     * Deja en cada fila solo las columnas del tipo y valida obligatorios,
     * números y resultados. El ítem se numera solo si viene vacío.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function filas(CertificateType $tipo, array $filas): array
    {
        if (! self::usaBloque($tipo, 'tabla')) {
            return [];
        }
        if ($filas === []) {
            throw ValidationException::withMessages(['filas' => 'Agrega al menos una fila al certificado.']);
        }

        $errores = [];
        $limpias = [];

        foreach (array_values($filas) as $i => $fila) {
            $limpia = [];

            foreach ($tipo->columnas as $columna) {
                $clave = $columna['clave'];
                $valor = trim((string) ($fila[$clave] ?? ''));
                $titulo = $columna['titulo'];
                $tipoColumna = $columna['tipo'] ?? 'texto';

                if ($clave === 'item' && $valor === '') {
                    $valor = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                }

                if ($tipoColumna === 'resultado') {
                    $valor = in_array($valor, self::RESULTADOS, true) ? $valor : '';
                }

                if (($columna['obligatorio'] ?? false) && $valor === '') {
                    $errores["filas.{$i}.{$clave}"] = 'Fila '.($i + 1).": falta «{$titulo}».";
                }

                if ($tipoColumna === 'numero' && $valor !== '' && ! is_numeric(str_replace(',', '.', $valor))) {
                    $errores["filas.{$i}.{$clave}"] = 'Fila '.($i + 1).": «{$titulo}» debe ser un número.";
                }

                $limpia[$clave] = $tipoColumna === 'numero' && $valor !== '' ? str_replace(',', '.', $valor) : $valor;
            }

            $limpias[] = $limpia;
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $limpias;
    }

    /**
     * Una prueba por cada punto del checklist del tipo, en el mismo orden.
     *
     * @param  list<array{estado?: string, valor?: string|null}>  $pruebas
     * @return list<array{estado: string, valor: string|null}>
     */
    public function pruebas(CertificateType $tipo, array $pruebas): array
    {
        if (! self::usaBloque($tipo, 'pruebas')) {
            return [];
        }

        return collect($tipo->checklist ?? [])
            ->values()
            ->map(function (array $item, int $i) use ($pruebas, $tipo) {
                if ($tipo->composicion !== null) {
                    if (! in_array($pruebas[$i]['estado'] ?? null, self::ESTADOS_PRUEBA, true)) {
                        throw ValidationException::withMessages(["pruebas.{$i}.estado" => 'Registra el resultado real de esta prueba.']);
                    }
                    if (! empty($item['unidad']) && $pruebas[$i]['estado'] !== 'NA' && ! is_numeric(str_replace(',', '.', (string) ($pruebas[$i]['valor'] ?? '')))) {
                        throw ValidationException::withMessages(["pruebas.{$i}.valor" => 'Registra la medición numérica de esta prueba.']);
                    }
                }
                $estado = $pruebas[$i]['estado'] ?? 'C';
                $valor = trim((string) ($pruebas[$i]['valor'] ?? ''));

                return [
                    'estado' => in_array($estado, self::ESTADOS_PRUEBA, true) ? $estado : 'C',
                    'valor' => $valor === '' ? null : str_replace(',', '.', $valor),
                ];
            })
            ->all();
    }
}
