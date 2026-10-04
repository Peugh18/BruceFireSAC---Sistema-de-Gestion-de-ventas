<?php

namespace App\Http\Requests\Gerente;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingRequest extends FormRequest
{
    public const LOGO_MIN_LADO = 150;

    public const LOGO_MIN_ALTO = 60;

    public const LOGO_MAX_LADO = 3000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'ruc' => ['required', 'digits:11'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', 'size:6', 'exists:ubigeos,codigo'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'sitio_web' => ['nullable', 'string', 'max:255'],
            'color_marca' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'leyenda_pie' => ['nullable', 'string', 'max:500'],
            'mensaje_agradecimiento' => ['nullable', 'string', 'max:150'],
            'condiciones_comprobante' => ['nullable', 'string', 'max:1000'],
            'cuenta_detraccion' => ['nullable', 'string', 'max:50'],
            // Muy chico se ve pixelado en el comprobante; enorme no aporta y
            // pesa en cada PDF.
            'logo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg',
                'max:2048',
                'dimensions:min_width='.self::LOGO_MIN_LADO.',min_height='.self::LOGO_MIN_ALTO.',max_width='.self::LOGO_MAX_LADO.',max_height='.self::LOGO_MAX_LADO,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.dimensions' => 'El logo debe medir al menos '.self::LOGO_MIN_LADO.' px de ancho y '.self::LOGO_MIN_ALTO.' px de alto, y como máximo '.self::LOGO_MAX_LADO.' px por lado.',
            'logo.mimes' => 'Sube el logo en PNG (ideal, con fondo transparente) o JPG.',
            'color_marca.regex' => 'Elige un color válido (formato #RRGGBB).',
        ];
    }
}
