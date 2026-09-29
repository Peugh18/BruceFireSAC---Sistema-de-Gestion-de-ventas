<?php

namespace App\Services\Ml;

use App\Models\MlClienteHistorico;
use App\Models\MlComprobanteHistorico;
use App\Models\MlLineaHistorica;
use App\Models\MlProductoHistorico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Guarda el histórico limpio (una fila por línea vendida, como sale del ETL)
 * repartido en tercera forma normal: cliente, producto, comprobante y línea.
 * Reemplaza la carga anterior.
 */
class CargaHistorico
{
    /**
     * @param  list<array{fecha: string, tipo_doc: string, comprobante: string, documento_cliente: string, nombre_cliente: string, categoria: string, producto_original: string, cantidad: float|int|string, total: float|int|string, archivo_origen: string}>  $filas
     * @return int líneas guardadas
     */
    public function reemplazar(array $filas): int
    {
        $clientes = [];
        $productos = [];
        $comprobantes = [];

        foreach ($filas as $fila) {
            // El nombre vigente de un cliente es el de su compra más reciente.
            $cliente = $clientes[$fila['documento_cliente']] ?? null;
            if ($cliente === null || $fila['fecha'] >= $cliente['fecha']) {
                $clientes[$fila['documento_cliente']] = ['fecha' => $fila['fecha'], 'nombre' => $fila['nombre_cliente']];
            }

            $productos[$this->clave($fila['producto_original'])] ??= ['nombre' => $fila['producto_original'], 'categoria' => $fila['categoria']];
            $comprobantes[$fila['comprobante']] ??= [
                'comprobante' => $fila['comprobante'],
                'tipo_doc' => $fila['tipo_doc'],
                'fecha' => $fila['fecha'],
                'documento_cliente' => $fila['documento_cliente'],
                'archivo_origen' => $fila['archivo_origen'],
            ];
        }

        DB::transaction(function () use ($filas, $clientes, $productos, $comprobantes): void {
            MlLineaHistorica::query()->delete();
            MlComprobanteHistorico::query()->delete();
            MlProductoHistorico::query()->delete();
            MlClienteHistorico::query()->delete();

            $insertar = function (string $modelo, array $registros): void {
                foreach (array_chunk($registros, 1000) as $lote) {
                    $modelo::query()->insert($lote);
                }
            };

            $insertar(MlClienteHistorico::class, array_map(
                fn (int|string $documento, array $cliente) => ['documento' => (string) $documento, 'nombre' => $cliente['nombre']],
                array_keys($clientes), $clientes,
            ));
            $insertar(MlProductoHistorico::class, array_values($productos));
            $insertar(MlComprobanteHistorico::class, array_values($comprobantes));

            $idProducto = MlProductoHistorico::query()->pluck('id', 'nombre')->mapWithKeys(fn (mixed $id, mixed $nombre) => [$this->clave((string) $nombre) => (int) $id]);
            $insertar(MlLineaHistorica::class, array_map(fn (array $fila) => [
                'comprobante' => $fila['comprobante'],
                'ml_producto_id' => $idProducto[$this->clave($fila['producto_original'])],
                'cantidad' => $fila['cantidad'],
                'total' => $fila['total'],
            ], $filas));
        });

        return count($filas);
    }

    /**
     * MySQL compara los nombres sin distinguir mayúsculas ni tildes
     * (utf8mb4_0900_ai_ci): "Jabón" y "JABON" son el mismo producto.
     */
    protected function clave(string $nombre): string
    {
        return mb_strtoupper(Str::ascii(trim($nombre)));
    }
}
