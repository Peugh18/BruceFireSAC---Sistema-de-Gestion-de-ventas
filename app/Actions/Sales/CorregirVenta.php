<?php

namespace App\Actions\Sales;

use App\Actions\Certificates\IssueCertificate;
use App\Models\Certificate;
use App\Models\CertificateUnit;
use App\Models\Equipment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrige un comprobante equivocado sin perder lo que no cambió. La nota de
 * crédito anula la venta y la deja "en corrección": sus certificados y fichas
 * siguen vivos. Al emitir la venta nueva se compara extintor por extintor; lo
 * que sigue pasa a la venta nueva tal como estaba y lo quitado sale. Si cambia
 * el cliente o nadie rehace la venta, se cierra como una anulación normal.
 */
class CorregirVenta
{
    public function __construct(
        protected IssueCertificate $issueCertificate,
        protected RevertSale $revertSale,
    ) {}

    /**
     * La venta a corregir tiene que verla el usuario, seguir en corrección y no
     * tener ya otra venta viva que la corrija.
     */
    public function validar(int $saleId, User $user): void
    {
        $original = Sale::query()->visiblePara($user)->whereKey($saleId)->whereNotNull('correccion_desde')->first();

        if (! $original || $original->correccion()->where('estado', '!=', 'anulada')->exists()) {
            throw ValidationException::withMessages(['corrige_sale_id' => 'Esta venta ya no está pendiente de corrección.']);
        }
    }

    /**
     * Si la venta nueva es de otro cliente, lo del anterior se cierra antes de
     * vender las series: su historia no pasa al cliente nuevo.
     */
    public function prepararCliente(Sale $nueva): void
    {
        $original = $this->originalEnCorreccion($nueva);

        if ($original && (int) $original->client_id !== (int) $nueva->client_id) {
            $this->cerrar($original, "Venta {$original->numero_interno} corregida a otro cliente.");
        }
    }

    /**
     * Cierra la corrección como una anulación: los certificados se anulan y las
     * fichas de los extintores que no se volvieron a vender quedan de baja.
     */
    public function cerrar(Sale $original, string $motivo): void
    {
        DB::transaction(function () use ($original, $motivo): void {
            $this->revertSale->anularCertificados($original, $motivo);
            $this->darDeBaja($this->equiposDe($original)->diff($this->equiposVendidosDeNuevo($original)->all())->all());
            $original->forceFill(['correccion_desde' => null])->save();
        });
    }

    /**
     * La venta nueva hereda lo de la anterior. Corre al emitirla, antes de los
     * certificados automáticos: esos encuentran los heredados y agregan los
     * extintores nuevos conservando el número.
     */
    public function trasladar(Sale $nueva): void
    {
        $original = $this->originalEnCorreccion($nueva);

        if (! $original) {
            return;
        }

        DB::transaction(function () use ($original, $nueva): void {
            Certificate::query()->where('sale_id', $original->id)->whereIn('estado', ['vigente', 'vencido'])->update(['sale_id' => $nueva->id]);
            DB::table('certificate_sale')->where('sale_id', $original->id)->update(['sale_id' => $nueva->id]);
            ServiceOrder::query()->where('sale_id', $original->id)->update(['sale_id' => $nueva->id]);

            if ($original->quote_id) {
                $nueva->forceFill(['quote_id' => $original->quote_id])->save();
                $original->forceFill(['quote_id' => null])->save();
            }

            $quitados = $this->equiposDe($original)->diff($this->equiposVendidosDeNuevo($original)->all());
            $this->retirarDeCertificados($nueva, $quitados->all(), "Extintor retirado al corregir la venta {$original->numero_interno}.");
            $this->darDeBaja($quitados->all());

            $original->forceFill(['correccion_desde' => null])->save();

            AuditLogger::log(
                action: 'venta.corregida',
                entity: $nueva,
                newValues: ['numero_interno' => $nueva->numero_interno, 'corrige' => $original->numero_interno],
                userId: $this->usuario(),
            );
        });
    }

    protected function usuario(): ?int
    {
        return is_int($id = auth()->id()) ? $id : null;
    }

    protected function originalEnCorreccion(Sale $nueva): ?Sale
    {
        return $nueva->corrige_sale_id
            ? Sale::query()->lockForUpdate()->whereKey($nueva->corrige_sale_id)->whereNotNull('correccion_desde')->first()
            : null;
    }

    /**
     * @param  array<int, int>  $equipos
     */
    public function retirarDeCertificados(Sale $nueva, array $equipos, string $motivo): void
    {
        if ($equipos === []) {
            return;
        }

        Certificate::query()
            ->where('sale_id', $nueva->id)
            ->whereHas('certificateUnits', fn ($query) => $query->whereIn('equipment_id', $equipos))
            ->with('certificateUnits')
            ->get()
            ->each(function (Certificate $certificado) use ($equipos, $motivo): void {
                $quedan = $certificado->certificateUnits->reject(fn (CertificateUnit $unidad) => in_array($unidad->equipment_id, $equipos, true));

                if ($quedan->isEmpty()) {
                    $certificado->update(['estado' => 'anulado', 'anulado_motivo' => $motivo, 'anulado_at' => now(), 'anulado_por' => auth()->id()]);

                    return;
                }

                $this->issueCertificate->corregir($certificado, $quedan->map(fn (CertificateUnit $unidad) => collect($unidad->getAttributes())->except(['id', 'certificate_id', 'orden', 'created_at', 'updated_at'])->all())->values()->all(), [], $motivo, $this->usuario());
            });
    }

    /** @return Collection<int, int> */
    protected function equiposDe(Sale $sale): Collection
    {
        return $sale->items()->where('tipo_linea', 'unidad_nueva')->whereNotNull('equipment_id')->pluck('equipment_id');
    }

    /** @return Collection<int, int> */
    protected function equiposVendidosDeNuevo(Sale $original): Collection
    {
        return SaleItem::query()
            ->whereIn('sale_id', $original->correccion()->where('estado', '!=', 'anulada')->pluck('id'))
            ->where('tipo_linea', 'unidad_nueva')
            ->whereNotNull('equipment_id')
            ->pluck('equipment_id');
    }

    /** @param array<int, int> $equipos */
    protected function darDeBaja(array $equipos): void
    {
        Equipment::query()->whereIn('id', $equipos)->update(['estado' => 'baja']);
    }
}
