<?php

namespace App\Models;

use App\Concerns\TieneUbigeo;
use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string $ruc
 * @property string|null $direccion
 * @property string|null $ubigeo
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $logo_path
 * @property string $color_marca
 * @property string|null $sitio_web
 * @property string|null $leyenda_pie
 * @property string|null $mensaje_agradecimiento
 * @property string|null $condiciones_comprobante
 * @property string|null $cuenta_detraccion
 */
#[Fillable([
    'razon_social', 'nombre_comercial', 'ruc', 'direccion', 'ubigeo',
    'telefono', 'email', 'sitio_web', 'logo_path', 'color_marca', 'leyenda_pie',
    'mensaje_agradecimiento', 'condiciones_comprobante', 'cuenta_detraccion',
    'instructor_capacitacion',
])]
class CompanySetting extends Model
{
    public const COLOR_MARCA = '#D2232A';

    /**
     * Caja donde entra el logo en el comprobante: se escala sin deformarlo
     * hasta tocar el ancho o el alto máximo, y nunca baja del alto mínimo
     * (un logo muy chico se vería pixelado; uno enorme se comería la
     * cabecera).
     */
    public const LOGO_PDF_MAX_ANCHO = 190;

    public const LOGO_PDF_MAX_ALTO = 80;

    public const LOGO_PDF_MIN_ALTO = 45;

    public const LOGO_PDF_MAX_ANCHO_ALARGADO = 230;

    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory;

    use TieneUbigeo;

    /**
     * Configuración de empresa como fila única (singleton). Se crea con los
     * valores de config/billing.php la primera vez que se necesita; a partir
     * de ahí esta fila en base de datos es la fuente de verdad editable
     * desde Gerente, no el .env.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'razon_social' => config('billing.company.razon_social', 'BRUCE FIRE S.A.C.'),
            'nombre_comercial' => config('billing.company.nombre_comercial', 'BRUCE FIRE'),
            'ruc' => (string) config('billing.company.ruc'),
            'direccion' => config('billing.company.direccion'),
            // Un BILLING_COMPANY_UBIGEO vacío (.env recién copiado del
            // ejemplo) es "sin ubigeo", no el código '' que no existe.
            'ubigeo' => config('billing.company.ubigeo') ?: null,
            'instructor_capacitacion' => 'Edgar Guevara Cabrera',
        ]);
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function colorMarca(): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->color_marca) === 1
            ? strtoupper($this->color_marca)
            : self::COLOR_MARCA;
    }

    /**
     * Blanco o casi negro, el que se lea mejor encima del color de la marca.
     */
    public function colorTextoSobreMarca(): string
    {
        [$r, $g, $b] = $this->rgbMarca();
        $luminancia = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminancia > 0.6 ? '#1A1A1A' : '#FFFFFF';
    }

    /**
     * El color de la marca muy aclarado, para fondos suaves.
     */
    public function colorMarcaSuave(float $mezcla = 0.92): string
    {
        return sprintf('#%02X%02X%02X', ...array_map(
            fn (int $canal) => (int) round($canal + (255 - $canal) * $mezcla),
            $this->rgbMarca(),
        ));
    }

    /**
     * Logo listo para el PDF (data URI) con el tamaño en que debe dibujarse.
     *
     * @return array{src: string, ancho: int, alto: int}|null
     */
    public function logoParaPdf(): ?array
    {
        if (! $this->logo_path || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $contenido = Storage::disk('public')->get($this->logo_path);
        $medidas = $contenido ? @getimagesizefromstring($contenido) : false;

        if (! $contenido || ! $medidas || $medidas[0] < 1 || $medidas[1] < 1) {
            return null;
        }

        [$ancho, $alto] = [$medidas[0], $medidas[1]];
        $escala = min(self::LOGO_PDF_MAX_ANCHO / $ancho, self::LOGO_PDF_MAX_ALTO / $alto);

        // Un logo muy alargado quedaría como una tira: se le deja crecer un
        // poco más a lo ancho para que llegue al alto mínimo.
        if ($alto * $escala < self::LOGO_PDF_MIN_ALTO) {
            $escala = min(self::LOGO_PDF_MIN_ALTO / $alto, self::LOGO_PDF_MAX_ANCHO_ALARGADO / $ancho);
        }

        return [
            'src' => 'data:'.$medidas['mime'].';base64,'.base64_encode($contenido),
            'ancho' => (int) round($ancho * $escala),
            'alto' => (int) round($alto * $escala),
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function rgbMarca(): array
    {
        $hex = ltrim($this->colorMarca(), '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
