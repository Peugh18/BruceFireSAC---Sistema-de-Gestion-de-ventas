<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\CertificateParticipant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncCertificateTraining
{
    /**
     * @param  array{
     *     modo?: string,
     *     fotos?: list<UploadedFile>,
     *     participantes?: list<array{id?: int|null, nombres: string, dni?: string|null, cargo?: string|null}>
     * }  $capacitacion
     */
    public function handle(Certificate $certificate, array $capacitacion): void
    {
        $modo = $capacitacion['modo'] ?? 'normal';
        $datos = $certificate->datos ?? [];
        $datos['modo'] = $modo;

        if ($modo === 'con_fotos' && ! empty($capacitacion['fotos'])) {
            $this->reemplazarFotos($certificate, $datos, $capacitacion['fotos']);
        }

        if ($modo !== 'con_fotos') {
            $datos['fotos'] = [];
        }

        $certificate->update(['datos' => $datos]);
        $this->sincronizarParticipantes($certificate, $modo === 'por_trabajador' ? ($capacitacion['participantes'] ?? []) : []);
    }

    /** @param list<UploadedFile> $fotos */
    protected function reemplazarFotos(Certificate $certificate, array &$datos, array $fotos): void
    {
        foreach ($datos['fotos'] ?? [] as $anterior) {
            Storage::disk('public')->delete($anterior);
        }

        $datos['fotos'] = collect($fotos)
            ->map(fn (UploadedFile $foto, int $indice) => $this->guardarFoto($certificate, $foto, $indice + 1))
            ->all();
    }

    protected function guardarFoto(Certificate $certificate, UploadedFile $foto, int $indice): string
    {
        $contenido = file_get_contents($foto->getRealPath());
        $origen = $contenido === false ? false : imagecreatefromstring($contenido);

        if ($origen === false) {
            throw ValidationException::withMessages(['fotos' => 'Una de las fotos no es una imagen válida.']);
        }

        // Recorte al centro en proporción 3:2 para que las fotos se vean parejas en el certificado.
        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $recorteAncho = (int) min($ancho, round($alto * 3 / 2));
        $recorteAlto = (int) min($alto, round($recorteAncho * 2 / 3));
        $escala = min(1, 1500 / $recorteAncho);
        $destino = imagecreatetruecolor((int) round($recorteAncho * $escala), (int) round($recorteAlto * $escala));
        imagecopyresampled(
            $destino, $origen, 0, 0,
            (int) (($ancho - $recorteAncho) / 2), (int) (($alto - $recorteAlto) / 2),
            imagesx($destino), imagesy($destino), $recorteAncho, $recorteAlto,
        );

        ob_start();
        imagejpeg($destino, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($origen);
        imagedestroy($destino);

        $ruta = "certificados/fotos/{$certificate->id}/{$indice}-".Str::uuid().'.jpg';
        Storage::disk('public')->put($ruta, $jpeg ?: '');

        return $ruta;
    }

    /** @param list<array{id?: int|null, nombres: string, dni?: string|null, cargo?: string|null}> $participantes */
    protected function sincronizarParticipantes(Certificate $certificate, array $participantes): void
    {
        $existentes = $certificate->participants()->lockForUpdate()->get()->keyBy('id');
        $conservados = [];
        $siguienteSufijo = (int) $existentes->max('sufijo');

        foreach (array_values($participantes) as $orden => $datos) {
            $participante = ! empty($datos['id']) ? $existentes->get((int) $datos['id']) : null;

            if (! $participante) {
                $participante = new CertificateParticipant([
                    'certificate_id' => $certificate->id,
                    'sufijo' => ++$siguienteSufijo,
                    'qr_token' => (string) Str::uuid(),
                ]);
            }

            $participante->fill([
                'orden' => $orden + 1,
                'nombres' => $datos['nombres'],
                'dni' => $datos['dni'] ?? null,
                'cargo' => $datos['cargo'] ?? null,
                'anulado_at' => null,
            ])->save();
            $conservados[] = $participante->id;
        }

        $certificate->participants()->whereNotIn('id', $conservados)->whereNull('anulado_at')->update(['anulado_at' => now()]);
    }
}
