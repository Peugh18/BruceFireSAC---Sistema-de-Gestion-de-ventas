<?php

namespace App\Actions\Tecnico;

use App\Models\Deficiency;
use App\Models\Evidencia;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda una evidencia de la orden (§33) en el disco privado. Las fotos se
 * enderezan y se comprimen; los audios y archivos se guardan tal cual.
 */
class GuardarEvidencia
{
    /** Lado mayor de una foto guardada, en píxeles. */
    private const LADO_MAXIMO = 1600;

    private const CALIDAD_JPEG = 78;

    /** Extensiones que el navegador usa al grabar o subir audio. */
    private const EXTENSIONES_AUDIO = ['webm', 'ogg', 'oga', 'm4a', 'mp3', 'wav', 'aac'];

    public function archivo(
        ServiceOrder $orden,
        UploadedFile $archivo,
        string $etapa,
        User $user,
        ?int $equipmentId = null,
        ?ServiceOrderEvent $evento = null,
        ?Deficiency $deficiencia = null,
    ): Evidencia {
        $mime = (string) $archivo->getMimeType();
        $extension = strtolower($archivo->extension() ?: $archivo->getClientOriginalExtension());
        $esAudio = str_starts_with($mime, 'audio/') || in_array($extension, self::EXTENSIONES_AUDIO, true);
        $comprimida = str_starts_with($mime, 'image/') ? $this->comprimir($archivo) : null;

        if ($comprimida !== null) {
            $tipo = 'foto';
            $contenido = $comprimida;
            $mime = 'image/jpeg';
            $extension = 'jpg';
        } else {
            $tipo = $esAudio ? 'audio' : (str_starts_with($mime, 'image/') ? 'foto' : 'archivo');
            $contenido = (string) file_get_contents($archivo->getRealPath());
        }

        return $this->crear($orden, $tipo, $etapa, $user, $contenido, $mime, $extension ?: 'bin', $archivo->getClientOriginalName(), $equipmentId, $evento, $deficiencia);
    }

    /**
     * La firma táctil llega como imagen PNG en base64 (data URL).
     */
    public function firma(ServiceOrder $orden, string $dataUrl, string $etapa, User $user): Evidencia
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $partes)) {
            throw ValidationException::withMessages(['firma' => 'La firma no es válida. Vuelve a firmar.']);
        }

        $png = (string) base64_decode($partes[1], true);
        $imagen = @imagecreatefromstring($png);

        if (! $imagen instanceof GdImage || strlen($png) > 1_500_000) {
            throw ValidationException::withMessages(['firma' => 'La firma no es válida. Vuelve a firmar.']);
        }

        return $this->crear($orden, 'firma', $etapa, $user, $png, 'image/png', 'png', "firma-{$etapa}.png");
    }

    protected function crear(
        ServiceOrder $orden,
        string $tipo,
        string $etapa,
        User $user,
        string $contenido,
        string $mime,
        string $extension,
        ?string $nombreOriginal,
        ?int $equipmentId = null,
        ?ServiceOrderEvent $evento = null,
        ?Deficiency $deficiencia = null,
    ): Evidencia {
        $ruta = "evidencias/{$orden->id}/".Str::uuid().'.'.preg_replace('/[^a-z0-9]/', '', $extension);
        Storage::disk('local')->put($ruta, $contenido);

        return Evidencia::create([
            'service_order_id' => $orden->id,
            'equipment_id' => $equipmentId,
            'service_order_event_id' => $evento?->id,
            'deficiency_id' => $deficiencia?->id,
            'user_id' => $user->id,
            'tipo' => $tipo,
            'etapa' => $etapa,
            'path' => $ruta,
            'nombre_original' => $nombreOriginal !== null ? Str::limit($nombreOriginal, 200, '') : null,
            'mime' => $mime,
            'tamano' => strlen($contenido),
            'created_at' => now(),
        ]);
    }

    /**
     * JPEG enderezado según su dato EXIF y reducido; null si no se puede
     * leer (por ejemplo HEIC), y entonces se guarda el original.
     */
    protected function comprimir(UploadedFile $archivo): ?string
    {
        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if (! $imagen instanceof GdImage) {
            return null;
        }

        if (function_exists('exif_read_data') && $archivo->getMimeType() === 'image/jpeg') {
            $exif = @exif_read_data($archivo->getRealPath());
            $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            $grados = [3 => 180, 6 => -90, 8 => 90][$orientacion] ?? 0;
            $imagen = $grados === 0 ? $imagen : (imagerotate($imagen, $grados, 0) ?: $imagen);
        }

        $escala = min(1, self::LADO_MAXIMO / max(imagesx($imagen), imagesy($imagen)));

        if ($escala < 1) {
            $imagen = imagescale($imagen, (int) round(imagesx($imagen) * $escala)) ?: $imagen;
        }

        ob_start();
        imagejpeg($imagen, null, self::CALIDAD_JPEG);

        return (string) ob_get_clean();
    }
}
