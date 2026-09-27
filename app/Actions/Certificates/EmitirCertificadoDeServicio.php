<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Collection;
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
    public static function tiposDeServicio(): Collection
    {
        return CertificateType::query()
            ->whereNotIn('codigo', self::TIPOS_DE_EXTINTORES)
            ->whereNotNull('columnas')
            ->orderBy('nombre')
            ->get()
            ->filter(fn (CertificateType $tipo) => ! empty($tipo->columnas))
            ->values();
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

        if (in_array($tipo->codigo, self::TIPOS_DE_EXTINTORES, true) || empty($tipo->columnas)) {
            throw ValidationException::withMessages(['tipo' => 'Este tipo de certificado no se llena con formulario.']);
        }

        $filas = $this->filas($tipo, $datos['filas'] ?? []);
        $pruebas = $this->pruebas($tipo, $datos['pruebas'] ?? []);

        $extra = [
            'referencia' => $datos['referencia'] ?? $sale->referencia,
            'direccion' => $datos['direccion'] ?? null,
            'tipo_atencion' => in_array($datos['tipo_atencion'] ?? null, self::ATENCIONES, true) ? $datos['tipo_atencion'] : 'mantenimiento',
            'datos' => [
                'filas' => $filas,
                'pruebas' => $pruebas,
                'observaciones' => trim((string) ($datos['observaciones'] ?? '')) ?: null,
            ],
        ];

        $existente = Certificate::query()
            ->where('sale_id', $sale->id)
            ->where('certificate_type_id', $tipo->id)
            ->where('estado', 'vigente')
            ->latest('id')
            ->first();

        if ($existente) {
            $existente->update(['tipo_atencion' => $extra['tipo_atencion']]);

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
    protected function filas(CertificateType $tipo, array $filas): array
    {
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
    protected function pruebas(CertificateType $tipo, array $pruebas): array
    {
        return collect($tipo->checklist ?? [])
            ->values()
            ->map(function (array $item, int $i) use ($pruebas) {
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
