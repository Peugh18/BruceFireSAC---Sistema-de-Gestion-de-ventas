<?php

namespace App\Actions\Certificates;

use App\Models\CertificateType;
use App\Models\Signer;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Crea o actualiza a un firmante con su firma y sello, y lo pone o lo quita
 * de los tipos de certificado que firma. Cada tipo que cambia de firmantes
 * sube de versión, así los certificados ya emitidos conservan los suyos.
 */
class GuardarFirmante
{
    public function __construct(protected GuardarImagenDeFirma $guardarImagen) {}

    /**
     * @param  array{nombre: string, cargo: string, cip?: string|null, activo?: bool, tipos?: list<string>, quitar_firma?: bool, quitar_sello?: bool}  $datos
     */
    public function handle(?Signer $signer, array $datos, ?UploadedFile $firma = null, ?UploadedFile $sello = null, ?int $userId = null): Signer
    {
        $firmaNueva = $firma ? $this->guardarImagen->handle($firma, 'firmas') : null;
        $selloNuevo = $sello ? $this->guardarImagen->handle($sello, 'sellos') : null;

        return DB::transaction(function () use ($signer, $datos, $firmaNueva, $selloNuevo, $userId) {
            $signer ??= new Signer(['activo' => true]);
            $antes = $signer->exists ? $signer->only(['nombre', 'cargo', 'cip', 'activo', 'firma_path', 'sello_path']) : null;
            $tiposAnteriores = $signer->exists ? $signer->certificateTypes()->pluck('certificate_types.id')->all() : [];

            $signer->fill([
                'nombre' => trim($datos['nombre']),
                'cargo' => trim($datos['cargo']),
                'cip' => trim((string) ($datos['cip'] ?? '')) ?: null,
                'activo' => $datos['activo'] ?? $signer->activo ?? true,
            ]);

            $signer->firma_path = $this->reemplazar($signer->firma_path, $firmaNueva, $datos['quitar_firma'] ?? false);
            $signer->sello_path = $this->reemplazar($signer->sello_path, $selloNuevo, $datos['quitar_sello'] ?? false);
            $signer->save();

            if (array_key_exists('tipos', $datos)) {
                $this->sincronizarTipos($signer, $datos['tipos'] ?? []);
            }

            if ($antes !== null && $antes !== $signer->only(['nombre', 'cargo', 'cip', 'activo', 'firma_path', 'sello_path'])) {
                $siguen = $signer->certificateTypes()->whereIn('certificate_types.id', $tiposAnteriores)->pluck('certificate_types.id');
                CertificateType::query()->whereIn('id', $siguen)->increment('version');
            }

            AuditLogger::log(
                action: $antes ? 'configuracion.firmante_actualizado' : 'configuracion.firmante_creado',
                entity: $signer,
                oldValues: $antes,
                newValues: $signer->only(['nombre', 'cargo', 'cip', 'activo', 'firma_path', 'sello_path']),
                context: array_key_exists('tipos', $datos) ? ['tipos' => $datos['tipos']] : null,
                userId: $userId,
            );

            return $signer->refresh();
        });
    }

    protected function reemplazar(?string $actual, ?string $nueva, bool $quitar): ?string
    {
        if (($nueva || $quitar) && $actual) {
            Storage::disk('local')->delete($actual);
        }

        return $nueva ?? ($quitar ? null : $actual);
    }

    /**
     * @param  list<string>  $codigos
     */
    protected function sincronizarTipos(Signer $signer, array $codigos): void
    {
        $actuales = $signer->certificateTypes()->pluck('codigo')->all();

        foreach (CertificateType::query()->whereIn('codigo', array_diff($codigos, $actuales))->get() as $tipo) {
            $orden = (int) DB::table('certificate_type_signer')->where('certificate_type_id', $tipo->id)->max('orden') + 1;
            $tipo->signers()->attach($signer->id, ['orden' => $orden]);
            $tipo->increment('version');
        }

        foreach (CertificateType::query()->whereIn('codigo', array_diff($actuales, $codigos))->get() as $tipo) {
            $tipo->signers()->detach($signer->id);
            $tipo->increment('version');
        }
    }
}
