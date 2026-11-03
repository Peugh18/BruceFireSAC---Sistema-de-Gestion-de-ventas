<?php

namespace App\Actions\Certificates;

use App\Models\CertificateType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El certificado de un producto (garantía de mangueras, luces…) nadie lo
 * llena: la venta pone cliente, referencia, producto y cantidad, y la ficha
 * técnica que Almacén llenó una vez pone el resto. Si la ficha está
 * incompleta, la venta se confirma igual y el certificado queda en espera
 * hasta que Almacén la complete.
 */
class EmitirCertificadosDeProducto
{
    /** Columnas de la tabla que llena la venta, no la ficha. */
    public const COLUMNAS_DE_LA_VENTA = ['item', 'producto', 'cantidad'];

    /** Bloques que necesitan a una persona: con ellos el certificado no sale solo. */
    public const BLOQUES_REVISADOS = ['pruebas', 'resultado', 'diploma'];

    public function __construct(
        protected EmitirCertificadoDeServicio $emitir,
    ) {}

    public static function seLlenaSolo(CertificateType $tipo): bool
    {
        return $tipo->composicion !== null
            && EmitirCertificadoDeServicio::esFormulario($tipo)
            && ! collect(self::BLOQUES_REVISADOS)->contains(fn (string $bloque) => EmitirCertificadoDeServicio::usaBloque($tipo, $bloque));
    }

    /**
     * Regla del formulario del producto: solo una plantilla activa que pueda
     * salir sola con la venta.
     *
     * @return list<mixed>
     */
    public static function reglaDePlantilla(): array
    {
        return ['sometimes', 'nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail): void {
            $tipo = is_numeric($value) ? CertificateType::query()->where('activo', true)->find((int) $value) : null;
            if ($tipo === null) {
                $fail('Elige un certificado activo.');
            } elseif (! self::seLlenaSolo($tipo)) {
                $fail('Ese certificado lleva pruebas o un resultado que revisa una persona, así que no sale solo con la venta. Elige otro o crea uno por bloques.');
            }
        }];
    }

    /**
     * Campos que Almacén llena en la ficha: las columnas de la tabla que no
     * salen de la venta.
     *
     * @return list<array{clave: string, titulo: string, tipo?: string, unidad?: string|null, obligatorio?: bool}>
     */
    public static function camposDeFicha(CertificateType $tipo): array
    {
        if (! EmitirCertificadoDeServicio::usaBloque($tipo, 'tabla')) {
            return [];
        }

        return array_values(array_filter(
            $tipo->columnas ?? [],
            fn (array $columna) => ! in_array($columna['clave'], self::COLUMNAS_DE_LA_VENTA, true) && ($columna['tipo'] ?? null) !== 'resultado',
        ));
    }

    public static function fichaIncompleta(Product $producto): bool
    {
        $campos = $producto->certificateType ? self::camposDeFicha($producto->certificateType) : [];
        if ($campos !== [] && $producto->ficha_tecnica === null) {
            return true;
        }

        return collect($campos)->contains(fn (array $campo) => ($campo['obligatorio'] ?? false) && blank($producto->ficha_tecnica[$campo['clave']] ?? null));
    }

    /**
     * Emite, una sola vez por venta, el certificado de cada plantilla ligada
     * a sus productos: una fila por producto.
     */
    public function handle(Sale $sale): void
    {
        if ($sale->estado !== 'confirmada') {
            return;
        }

        $enEspera = [];
        $lineas = $sale->items()->whereHas('product', fn ($query) => $query->whereNotNull('certificate_type_id'))
            ->with('product.certificateType')->orderBy('id')->get();

        foreach ($lineas->groupBy('product.certificate_type_id') as $tipoId => $delTipo) {
            $tipo = $delTipo->first()->product->certificateType;
            if (! $tipo->activo || ! self::seLlenaSolo($tipo) || EmitirCertificadoDeServicio::existente($sale, $tipo)) {
                continue;
            }

            $porProducto = $delTipo->groupBy('product_id');
            if ($porProducto->contains(fn ($lineasDelProducto) => self::fichaIncompleta($lineasDelProducto->first()->product))) {
                $enEspera[] = (int) $tipoId;

                continue;
            }

            try {
                $this->emitir->handle($sale, $tipo, [
                    'tipo_atencion' => null,
                    'filas' => array_values($porProducto->map(fn ($lineasDelProducto) => [
                        ...($lineasDelProducto->first()->product->ficha_tecnica ?? []),
                        'producto' => $lineasDelProducto->first()->product->nombre,
                        'cantidad' => (string) $lineasDelProducto->sum(fn (SaleItem $linea) => $linea->cantidad),
                    ])->all()),
                ]);
            } catch (ValidationException) {
                // Un dato que no pasa la plantilla no frena la venta: espera a que se corrija la ficha.
                $enEspera[] = (int) $tipoId;
            }
        }

        $sale->update(['certificados_en_espera' => $enEspera ?: null]);
    }

    /**
     * Al completar la ficha salen los certificados de las ventas que la esperaban.
     */
    public function alCompletarFicha(Product $producto): void
    {
        if ($producto->certificate_type_id === null || self::fichaIncompleta($producto)) {
            return;
        }

        Sale::query()->whereJsonContains('certificados_en_espera', $producto->certificate_type_id)->pluck('id')
            ->each(fn (int $id) => DB::transaction(fn () => $this->handle(Sale::query()->lockForUpdate()->findOrFail($id))));
    }
}
