<?php

namespace App\Actions\Billing;

use App\Actions\Sales\CorregirVenta;
use App\Actions\Sales\RevertSale;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nota de crédito por devolución parcial (motivo 07): no deja devolver más de
 * lo vendido y, cuando SUNAT la acepta, regresa al stock lo devuelto. Los
 * extintores se eligen por serie: cada uno tiene su ficha y su certificado.
 */
class DevolucionParcial
{
    public const MOTIVO = '07';

    /**
     * Lo que ya se devolvió por cada línea y las series que aún se pueden elegir.
     *
     * @return array<int, array{devuelta: float, series: list<string>}>
     */
    public function porLinea(Sale $sale, ElectronicDocument $original): array
    {
        $previas = $this->seleccionesPrevias($original);
        $devueltas = $previas->pluck('series')->filter()->flatten()->all();
        $resultado = [];

        foreach ($sale->gruposDeLinea() as $indice => $grupo) {
            $resultado[$indice] = [
                'devuelta' => (float) $previas->where('indice', $indice)->sum('cantidad'),
                'series' => array_values(array_diff($this->series($grupo), $devueltas)),
            ];
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $seleccion
     */
    public function validar(Sale $sale, ElectronicDocument $original, array $seleccion): void
    {
        $lineas = $this->porLinea($sale, $original);
        $grupos = $sale->gruposDeLinea();

        foreach ($seleccion as $elegida) {
            $indice = (int) ($elegida['indice'] ?? -1);
            $grupo = $grupos->get($indice);

            if (! $grupo) {
                continue;
            }

            $cantidad = (float) ($elegida['cantidad'] ?? 0);
            if ($cantidad + $lineas[$indice]['devuelta'] > (float) $grupo->sum('cantidad') + 0.0001) {
                throw ValidationException::withMessages(['importe' => 'No se puede devolver más de lo vendido: parte de esa línea ya se devolvió.']);
            }

            if ($this->series($grupo) !== []) {
                $series = array_values(array_unique($elegida['series'] ?? []));
                if (count($series) !== (int) $cantidad || array_diff($series, $lineas[$indice]['series']) !== []) {
                    throw ValidationException::withMessages(['importe' => 'Elige qué extintores devuelve, por su serie.']);
                }
            }
        }
    }

    /**
     * Regresa al stock lo devuelto. Corre una sola vez, al aceptarla SUNAT.
     */
    public function aplicar(ElectronicDocument $nota): void
    {
        if ($nota->tipo !== 'nota_credito' || $nota->motivo_catalogo !== self::MOTIVO
            || ! in_array($nota->sunat_estado, ['aceptado', 'observado'], true)) {
            return;
        }

        DB::transaction(function () use ($nota): void {
            $nota = ElectronicDocument::query()->lockForUpdate()->findOrFail($nota->id);
            $sale = Sale::query()->lockForUpdate()->findOrFail($nota->sale_id);

            if (($nota->nota_datos['stock_devuelto'] ?? false) || $sale->estado !== 'confirmada') {
                return;
            }

            $grupos = $sale->gruposDeLinea();
            $motivo = "por nota de crédito {$nota->serie}-{$nota->correlativo}";
            $equipos = [];

            foreach ($nota->nota_datos['opciones']['lineas'] ?? [] as $elegida) {
                $grupo = $grupos->get((int) $elegida['indice'], collect());

                foreach ($grupo as $item) {
                    /** @var SaleItem $item */
                    if ($item->tipo_linea === 'unidad_nueva' && in_array($item->inventoryUnit?->numero_serie, $elegida['series'] ?? [], true)) {
                        app(RevertSale::class)->devolverUnidad($sale, $item, $motivo);
                        $equipos[] = (int) $item->equipment_id;
                    }
                }

                $producto = $grupo->first(fn (SaleItem $item) => $item->tipo_linea === 'producto' && $item->deficiency_id === null);
                if ($producto) {
                    app(RevertSale::class)->devolverProducto($sale, $producto, $motivo, (int) $elegida['cantidad']);
                }
            }

            app(CorregirVenta::class)->retirarDeCertificados($sale, $equipos, "Extintor devuelto {$motivo}.");
            $nota->forceFill(['nota_datos' => [...$nota->nota_datos, 'stock_devuelto' => true]])->save();
        });
    }

    /** @return Collection<int, mixed> */
    protected function seleccionesPrevias(ElectronicDocument $original): Collection
    {
        return ElectronicDocument::query()
            ->where('cpe_afectado_id', $original->id)
            ->where('tipo', 'nota_credito')
            ->where('motivo_catalogo', self::MOTIVO)
            ->where('sunat_estado', '!=', 'rechazado')
            ->get()
            ->map(fn (ElectronicDocument $nota) => $nota->nota_datos['opciones']['lineas'] ?? [])
            ->collapse()
            ->values();
    }

    /**
     * @param  Collection<int, SaleItem>  $grupo
     * @return list<string>
     */
    protected function series(Collection $grupo): array
    {
        return array_values(array_filter($grupo->where('tipo_linea', 'unidad_nueva')->map(fn (SaleItem $item) => (string) $item->inventoryUnit?->numero_serie)->all()));
    }
}
