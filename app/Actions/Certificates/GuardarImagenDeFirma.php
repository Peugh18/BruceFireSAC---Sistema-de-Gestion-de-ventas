<?php

namespace App\Actions\Certificates;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Convierte la foto de una firma o de un sello (tomada con el celular sobre
 * papel blanco, o un PNG ya recortado) en un PNG transparente listo para los
 * certificados: endereza la foto, quita el fondo del papel, recorta lo que
 * sobra y la deja de un tamaño liviano.
 */
class GuardarImagenDeFirma
{
    private const LADO_MAXIMO_DE_TRABAJO = 1400;

    private const ANCHO_FINAL = 900;

    private const ALTO_FINAL = 450;

    public function handle(UploadedFile $archivo, string $carpeta = 'firmas'): string
    {
        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if (! $imagen instanceof GdImage) {
            throw ValidationException::withMessages(['imagen' => 'No se pudo leer la imagen. Sube una foto JPG o PNG.']);
        }

        imagepalettetotruecolor($imagen);
        $imagen = $this->enderezar($imagen, $archivo);
        $imagen = $this->reducir($imagen, self::LADO_MAXIMO_DE_TRABAJO, self::LADO_MAXIMO_DE_TRABAJO);

        if (! $this->yaEsTransparente($imagen)) {
            $imagen = $this->quitarFondo($imagen);
        }

        $imagen = $this->recortar($imagen);
        $imagen = $this->reducir($imagen, self::ANCHO_FINAL, self::ALTO_FINAL);

        ob_start();
        imagepng($imagen, null, 9);
        $png = (string) ob_get_clean();

        $ruta = trim($carpeta, '/').'/'.Str::uuid().'.png';
        Storage::disk('public')->put($ruta, $png);

        return $ruta;
    }

    /**
     * Las fotos del celular vienen giradas según su dato EXIF.
     */
    protected function enderezar(GdImage $imagen, UploadedFile $archivo): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($archivo->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $imagen;
        }

        $orientacion = (int) (@exif_read_data($archivo->getRealPath())['Orientation'] ?? 1);
        $grados = [3 => 180, 6 => -90, 8 => 90][$orientacion] ?? 0;

        return $grados === 0 ? $imagen : (imagerotate($imagen, $grados, 0) ?: $imagen);
    }

    protected function reducir(GdImage $imagen, int $anchoMaximo, int $altoMaximo): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1, $anchoMaximo / $ancho, $altoMaximo / $alto);

        if ($escala >= 1) {
            return $this->conTransparencia($imagen);
        }

        $nueva = $this->lienzo((int) max(1, round($ancho * $escala)), (int) max(1, round($alto * $escala)));
        imagecopyresampled($nueva, $imagen, 0, 0, 0, 0, imagesx($nueva), imagesy($nueva), $ancho, $alto);

        return $nueva;
    }

    /**
     * Un PNG que ya trae bastante transparencia se respeta tal cual.
     */
    protected function yaEsTransparente(GdImage $imagen): bool
    {
        $transparentes = 0;
        $muestras = 0;
        $paso = max(1, (int) (min(imagesx($imagen), imagesy($imagen)) / 40));

        for ($y = 0; $y < imagesy($imagen); $y += $paso) {
            for ($x = 0; $x < imagesx($imagen); $x += $paso) {
                $muestras++;
                if (((imagecolorat($imagen, $x, $y) >> 24) & 0x7F) > 100) {
                    $transparentes++;
                }
            }
        }

        return $muestras > 0 && $transparentes / $muestras > 0.2;
    }

    /**
     * El papel se vuelve transparente y la tinta conserva su color, con un
     * borde suave. El nivel del papel se mide en la propia foto, así una hoja
     * con sombra o poca luz también queda limpia.
     */
    protected function quitarFondo(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $luces = [];
        $paso = max(1, (int) (min($ancho, $alto) / 60));

        for ($y = 0; $y < $alto; $y += $paso) {
            for ($x = 0; $x < $ancho; $x += $paso) {
                $luces[] = $this->luz(imagecolorat($imagen, $x, $y));
            }
        }

        sort($luces);
        $papel = max(90, $luces[(int) (count($luces) * 0.6)] ?? 255);
        $tinta = min($papel - 60, $luces[(int) (count($luces) * 0.01)] ?? 0);
        $corte = $papel - 0.18 * ($papel - $tinta);

        $salida = $this->lienzo($ancho, $alto);

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                $color = imagecolorat($imagen, $x, $y);
                $luz = $this->luz($color);

                if ($luz >= $corte) {
                    continue;
                }

                $opacidad = min(1, ($corte - $luz) / max(1, $corte - $tinta) * 1.6);
                $alfa = (int) round(127 * (1 - $opacidad));
                imagesetpixel($salida, $x, $y, imagecolorallocatealpha(
                    $salida,
                    ($color >> 16) & 0xFF,
                    ($color >> 8) & 0xFF,
                    $color & 0xFF,
                    $alfa,
                ));
            }
        }

        return $salida;
    }

    /**
     * Deja solo el trazo con un pequeño margen.
     */
    protected function recortar(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        [$x1, $y1, $x2, $y2] = [$ancho, $alto, -1, -1];

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                if (((imagecolorat($imagen, $x, $y) >> 24) & 0x7F) < 110) {
                    $x1 = min($x1, $x);
                    $y1 = min($y1, $y);
                    $x2 = max($x2, $x);
                    $y2 = max($y2, $y);
                }
            }
        }

        if ($x2 < 0) {
            throw ValidationException::withMessages(['imagen' => 'No se encontró la firma en la foto. Tómala sobre papel blanco y con buena luz.']);
        }

        $margen = 6;
        $x1 = max(0, $x1 - $margen);
        $y1 = max(0, $y1 - $margen);
        $x2 = min($ancho - 1, $x2 + $margen);
        $y2 = min($alto - 1, $y2 + $margen);

        $recorte = $this->lienzo($x2 - $x1 + 1, $y2 - $y1 + 1);
        imagecopy($recorte, $imagen, 0, 0, $x1, $y1, $x2 - $x1 + 1, $y2 - $y1 + 1);

        return $recorte;
    }

    protected function conTransparencia(GdImage $imagen): GdImage
    {
        if (imageistruecolor($imagen)) {
            imagealphablending($imagen, false);
            imagesavealpha($imagen, true);

            return $imagen;
        }

        $copia = $this->lienzo(imagesx($imagen), imagesy($imagen));
        imagecopy($copia, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));

        return $copia;
    }

    protected function lienzo(int $ancho, int $alto): GdImage
    {
        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 255, 255, 255, 127));

        return $lienzo;
    }

    protected function luz(int $color): float
    {
        return 0.299 * (($color >> 16) & 0xFF) + 0.587 * (($color >> 8) & 0xFF) + 0.114 * ($color & 0xFF);
    }
}
