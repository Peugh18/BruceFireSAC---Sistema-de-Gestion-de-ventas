<?php

namespace App\Http\Controllers\Gerente;

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Certificates\EmitirCertificadosDeProducto;
use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use App\Models\Product;
use App\Models\Signer;
use App\Models\Team;
use App\Models\TipoDeServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Certificados que emite la empresa. Todos usan la misma plantilla general;
 * el gerente arma uno nuevo copiando un informe de servicio y cambia sus
 * textos, su tabla de equipos, sus pruebas y la orientación de la hoja. Los
 * de extintores y la capacitación tienen reglas propias: solo cambian textos.
 */
class CertificateTypeController extends Controller
{
    /** Firmas que entran al pie de la hoja (el sello va al centro). */
    public const MAXIMO_FIRMANTES = 4;

    /** Columnas editables por hoja, sin contar «Ítem» ni «Resultado». */
    public const MAXIMO_COLUMNAS = ['vertical' => 7, 'horizontal' => 10];

    public const BLOQUES = ['datos', 'declaracion', 'tabla', 'pruebas', 'texto', 'resultado', 'responsabilidad', 'diploma'];

    public function index(Team $current_team): Response
    {
        return Inertia::render('gerente/configuracion/certificados', [
            'certificados' => CertificateType::query()->orderBy('id')->get()->map(fn (CertificateType $tipo) => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
                'prefijo' => $tipo->prefijo,
                'vigencia_meses' => $tipo->vigencia_meses,
                'orientacion' => $tipo->orientacion,
                'activo' => (bool) $tipo->activo,
                'puede_ser_base' => self::puedeSerBase($tipo),
                'vista_previa' => route('gerente.configuracion.firmas.vista-previa', ['current_team' => $current_team, 'tipo' => $tipo->codigo]),
            ]),
        ]);
    }

    public function create(Request $request, Team $current_team): Response
    {
        // Desde el producto: "Crear certificado nuevo" llega con el producto ya elegido.
        return $this->editor($current_team, null, $request->integer('producto') ?: null);
    }

    public function edit(Team $current_team, CertificateType $certificado): Response
    {
        return $this->editor($current_team, $certificado);
    }

    public function store(Request $request, Team $current_team): RedirectResponse
    {
        $datos = $this->validar($request, null);
        $base = isset($datos['base_id']) ? CertificateType::query()->findOrFail((int) $datos['base_id']) : null;

        $nuevo = DB::transaction(function () use ($datos, $base): CertificateType {

            $codigo = Str::slug($datos['nombre'], '_');
            $sufijo = 2;
            while (CertificateType::query()->where('codigo', $codigo)->exists()) {
                $codigo = Str::slug($datos['nombre'], '_').'_'.$sufijo++;
            }

            $nuevo = $base?->replicate(['codigo', 'version']) ?? new CertificateType([
                'familia' => 'operatividad', 'orientacion' => 'vertical', 'generado_por_rol' => 'vendedor',
                'digitos' => 6, 'incluye_anio' => true, 'fotos_minimas' => 0, 'requiere_cip' => false,
            ]);
            $nuevo->fill([...$this->textos($datos), 'codigo' => $codigo, 'version' => 1, 'activo' => true, 'diseno' => null]);
            if (isset($datos['familia'])) {
                $nuevo->familia = $datos['familia'];
            }
            $this->aplicarFormato($nuevo, $datos);
            $nuevo->save();
            if (isset($datos['firmantes'])) {
                $this->firmar($nuevo, $datos['firmantes']);
            } elseif ($base) {
                $firmas = DB::table('certificate_type_signer')->where('certificate_type_id', $base->id)->get(['signer_id', 'orden', 'cargo']);
                $nuevo->signers()->sync($firmas->mapWithKeys(fn (object $firma) => [$firma->signer_id => ['orden' => $firma->orden, 'cargo' => $firma->cargo]])->all());
            }

            $this->relacionar($nuevo, $datos);

            return $nuevo;
        });

        return to_route('gerente.configuracion.certificados.index', $current_team)
            ->with('success', "Certificado «{$nuevo->nombre}» creado.");
    }

    public function update(Request $request, Team $current_team, CertificateType $certificado): RedirectResponse
    {
        $datos = $this->validar($request, $certificado);
        DB::transaction(function () use ($certificado, $datos): void {
            $certificado->refreshForUpdate();
            $certificado->fill($this->textos($datos));
            // El marco es fijo: al guardar se borran las posiciones manuales de antes.
            $certificado->diseno = null;
            if (self::puedeSerBase($certificado)) {
                $this->aplicarFormato($certificado, $datos);
                if (isset($datos['familia']) && $certificado->composicion !== null) {
                    $certificado->familia = $datos['familia'];
                }
            }

            $cambioFirmas = isset($datos['firmantes']) && $this->firmar($certificado, $datos['firmantes']);

            // Lo ya emitido guarda la versión con la que salió: un cambio sube la versión.
            if ($certificado->isDirty() || $cambioFirmas) {
                $certificado->version = ($certificado->version ?? 1) + 1;
                $certificado->save();
            }
            $this->relacionar($certificado, $datos);
        });

        return to_route('gerente.configuracion.certificados.index', $current_team)
            ->with('success', "Certificado «{$certificado->nombre}» actualizado.");
    }

    /**
     * Quién firma, con qué cargo y en qué orden. Devuelve si cambió algo.
     *
     * @param  array<int, mixed>  $firmantes  [{id, cargo}]
     */
    private function firmar(CertificateType $tipo, array $firmantes): bool
    {
        $nuevos = [];
        foreach ($firmantes as $firmante) {
            $id = (int) $firmante['id'];
            $nuevos[$id] ??= ['orden' => count($nuevos) + 1, 'cargo' => trim((string) ($firmante['cargo'] ?? '')) ?: null];
        }

        $actuales = [];
        foreach ($tipo->signers()->get() as $signer) {
            $actuales[(int) $signer->id] = ['orden' => $signer->pivot->orden, 'cargo' => $signer->pivot->cargo];
        }

        if ($nuevos === $actuales) {
            return false;
        }

        $tipo->signers()->sync($nuevos);

        return true;
    }

    public static function puedeSerBase(CertificateType $tipo): bool
    {
        return ! in_array($tipo->codigo, EmitirCertificadoDeServicio::TIPOS_DE_EXTINTORES, true);
    }

    /**
     * Pantalla del editor: a la izquierda las partes del certificado y a la
     * derecha la hoja como va quedando.
     */
    private function editor(Team $current_team, ?CertificateType $certificado, ?int $productoInicial = null): Response
    {
        $bases = CertificateType::query()->with('signers')->orderBy('id')->get()
            ->filter(fn (CertificateType $tipo) => self::puedeSerBase($tipo))
            ->map(fn (CertificateType $tipo) => $this->formato($tipo))
            ->values();

        return Inertia::render('gerente/configuracion/certificado-editor', [
            'certificado' => $certificado ? [
                'id' => $certificado->id,
                'nombre' => $certificado->nombre,
                'formato_fijo' => ! self::puedeSerBase($certificado),
                'vista_previa' => route('gerente.configuracion.firmas.vista-previa', ['current_team' => $current_team, 'tipo' => $certificado->codigo]),
                ...$this->formato($certificado->load('signers')),
            ] : null,
            'bases' => $bases,
            'maximoColumnas' => self::MAXIMO_COLUMNAS,
            'maximoFirmantes' => self::MAXIMO_FIRMANTES,
            'bloquesDisponibles' => self::BLOQUES,
            'productoInicial' => $productoInicial,
            'productosDisponibles' => Product::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'certificate_type_id']),
            'tiposDeServicioDisponibles' => TipoDeServicio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'certificate_type_id']),
            'firmantesDisponibles' => Signer::query()->where('activo', true)->orderBy('nombre')->get()->map(fn (Signer $signer) => [
                ...$this->firmante($signer, $current_team),
            ])->values(),
        ]);
    }

    /** @return array{id: int, nombre: string, cargo: string, cip: string|null, firma_url: string|null, sello_url: string|null} */
    private function firmante(Signer $signer, Team $equipo): array
    {
        $imagen = fn (string $tipo, ?string $ruta) => $ruta ? route('gerente.configuracion.firmas.imagen', ['current_team' => $equipo, 'firmante' => $signer, 'tipo' => $tipo, 'v' => md5($ruta)]) : null;

        return [
            'id' => $signer->id,
            'nombre' => $signer->nombre,
            'cargo' => $signer->cargo,
            'cip' => $signer->cip,
            'firma_url' => $imagen('firma', $signer->firma_path),
            'sello_url' => $imagen('sello', $signer->sello_path),
        ];
    }

    /** @return array<string, mixed> */
    private function formato(CertificateType $tipo): array
    {
        $columnas = $tipo->columnas ?? [];

        return [
            'id' => $tipo->id,
            'nombre' => $tipo->nombre,
            'titulo' => $tipo->titulo,
            'subtitulo' => $tipo->subtitulo,
            'norma' => $tipo->norma,
            'declaracion' => $tipo->declaracion,
            'responsabilidad' => $tipo->responsabilidad,
            'prefijo' => $tipo->prefijo,
            'vigencia_meses' => $tipo->vigencia_meses,
            'orientacion' => $tipo->orientacion ?? 'vertical',
            'familia' => $tipo->familia,
            'composicion' => self::puedeSerBase($tipo) ? $tipo->composicion : null,
            'bloques' => $tipo->bloques ?? [],
            'product_ids' => $tipo->products()->pluck('id')->all(),
            'tipo_de_servicio_ids' => $tipo->tiposDeServicio()->pluck('id')->all(),
            'columnas' => array_values(array_filter($columnas, fn (array $columna) => ! self::esColumnaFija($columna))),
            'tiene_item' => collect($columnas)->contains(fn (array $columna) => $columna['clave'] === 'item'),
            'tiene_resultado' => collect($columnas)->contains(fn (array $columna) => ($columna['tipo'] ?? null) === 'resultado'),
            'checklist' => $tipo->checklist ?? [],
            'firmantes' => $tipo->signers->map(fn (Signer $signer) => [
                'id' => $signer->id,
                'cargo' => $signer->pivot->cargo ?: $signer->cargo,
            ])->values(),
        ];
    }

    /**
     * «Ítem» y las columnas de resultado las pone el sistema, no se editan.
     *
     * @param  array<string, mixed>  $columna
     */
    private static function esColumnaFija(array $columna): bool
    {
        return ($columna['clave'] ?? null) === 'item' || ($columna['tipo'] ?? null) === 'resultado';
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function textos(array $datos): array
    {
        return collect($datos)->only(['nombre', 'titulo', 'subtitulo', 'norma', 'declaracion', 'responsabilidad', 'prefijo', 'vigencia_meses', 'activo'])->all();
    }

    /**
     * Tabla, pruebas y hoja. Una columna renombrada conserva su clave (con ella
     * se guardan los datos que llena el técnico); las nuevas reciben una.
     *
     * @param  array<string, mixed>  $datos
     */
    private function aplicarFormato(CertificateType $tipo, array $datos): void
    {
        if (array_key_exists('composicion', $datos)) {
            $tipo->composicion = $datos['composicion'];
        }
        if (isset($datos['orientacion'])) {
            $tipo->orientacion = $datos['orientacion'];
        }

        if (isset($datos['checklist']) && is_array($datos['checklist'])) {
            $pruebas = [];
            foreach ($datos['checklist'] as $punto) {
                $prueba = ['texto' => trim((string) $punto['texto'])];
                if (filled($punto['unidad'] ?? null)) {
                    $prueba['unidad'] = (string) $punto['unidad'];
                }
                // Los mínimos y máximos que ya tenía la prueba siguen evaluándose.
                if (is_numeric($punto['minimo'] ?? null)) {
                    $prueba['minimo'] = (float) $punto['minimo'];
                }
                if (is_numeric($punto['maximo'] ?? null)) {
                    $prueba['maximo'] = (float) $punto['maximo'];
                }
                $pruebas[] = $prueba;
            }
            $tipo->checklist = $pruebas;
        }

        if (! isset($datos['columnas']) || ! is_array($datos['columnas'])) {
            return;
        }

        $actuales = $tipo->columnas ?? [];
        $porClave = array_column($actuales, null, 'clave');
        $usadas = array_column($actuales, 'clave');
        $inicio = [];
        $centro = [];
        $final = [];

        foreach ($actuales as $columna) {
            if ($columna['clave'] === 'item') {
                $inicio[] = $columna;
            } elseif (($columna['tipo'] ?? null) === 'resultado') {
                $final[] = $columna;
            }
        }

        foreach ($datos['columnas'] as $enviada) {
            $clave = (string) ($enviada['clave'] ?? '');
            if (! isset($porClave[$clave]) || self::esColumnaFija($porClave[$clave])) {
                $clave = $this->claveNueva((string) $enviada['titulo'], $usadas);
                $usadas[] = $clave;
            }

            // Lo que la columna ya tenía (mínimos, ancho) se conserva.
            $columna = [
                ...($porClave[$clave] ?? []),
                'clave' => $clave,
                'titulo' => trim((string) $enviada['titulo']),
                'tipo' => (string) $enviada['tipo'],
                'obligatorio' => (bool) ($enviada['obligatorio'] ?? false),
            ];
            unset($columna['unidad']);
            if (filled($enviada['unidad'] ?? null)) {
                $columna['unidad'] = (string) $enviada['unidad'];
            }
            $centro[] = $columna;
        }

        // Una tabla que se evalúa lleva su columna de resultado al final.
        $tiposDeBloque = array_column($tipo->composicion ?? [], 'tipo');
        if ($final === [] && in_array('tabla', $tiposDeBloque, true) && in_array('resultado', $tiposDeBloque, true)) {
            $final[] = ['clave' => 'resultado', 'titulo' => 'Resultado', 'tipo' => 'resultado', 'obligatorio' => true];
        }

        $tipo->columnas = [...$inicio, ...$centro, ...$final];
    }

    /** @param  list<string>  $usadas */
    private function claveNueva(string $titulo, array $usadas): string
    {
        $base = Str::slug($titulo, '_') ?: 'columna';
        $clave = $base;
        $sufijo = 2;
        while (in_array($clave, $usadas, true)) {
            $clave = $base.'_'.$sufijo++;
        }

        return $clave;
    }

    /** @param array<string, mixed> $datos */
    private function relacionar(CertificateType $tipo, array $datos): void
    {
        foreach (['product_ids' => Product::class, 'tipo_de_servicio_ids' => TipoDeServicio::class] as $campo => $modelo) {
            if (! array_key_exists($campo, $datos)) {
                continue;
            }

            $ids = $datos[$campo];
            if ($modelo === Product::class && $ids !== [] && ! EmitirCertificadosDeProducto::seLlenaSolo($tipo)) {
                throw ValidationException::withMessages([$campo => 'Un certificado de producto sale solo con la venta: quita las pruebas y el resultado, o desvincula los productos.']);
            }
            $seleccionados = $modelo::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($seleccionados->contains(fn ($registro) => ! $registro->activo || ($registro->certificate_type_id !== null && (int) $registro->certificate_type_id !== $tipo->id))) {
                throw ValidationException::withMessages([$campo => 'Hay elementos inactivos o ligados a otro certificado. Desvincúlalos de su plantilla antes de elegirlos.']);
            }

            $modelo::query()->where('certificate_type_id', $tipo->id)->whereNotIn('id', $ids)->orderBy('id')->lockForUpdate()->get()
                ->each(fn ($registro) => $registro->update(['certificate_type_id' => null]));
            $seleccionados->each(fn ($registro) => $registro->update(['certificate_type_id' => $tipo->id]));
        }
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?CertificateType $certificado): array
    {
        $orientacion = $request->input('orientacion', $certificado->orientacion ?? 'vertical');
        $maximo = self::MAXIMO_COLUMNAS[$orientacion] ?? self::MAXIMO_COLUMNAS['vertical'];
        $composicionEnviada = $request->input('composicion', $certificado?->composicion);
        $sinTabla = is_array($composicionEnviada) && ! in_array('tabla', array_column($composicionEnviada, 'tipo'), true);

        $reglas = [
            'familia' => ['sometimes', Rule::in(['operatividad', 'diploma'])],
            'composicion' => ['sometimes', 'nullable', 'array', 'list', 'min:1', 'max:20'],
            'composicion.*' => ['required', 'array:tipo,titulo,texto'],
            'composicion.*.tipo' => ['required', Rule::in(self::BLOQUES)],
            'composicion.*.titulo' => ['sometimes', 'nullable', 'string', 'max:120'],
            'composicion.*.texto' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'product_ids' => ['sometimes', 'array', 'max:1000'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('activo', true)],
            'tipo_de_servicio_ids' => ['sometimes', 'array', 'max:1000'],
            'tipo_de_servicio_ids.*' => ['required', 'integer', 'distinct', Rule::exists('tipos_de_servicio', 'id')->where('activo', true)],
            // El marco (logo, QR, firmas, ASNEEX) es fijo: no se aceptan posiciones manuales.
            'diseno' => ['prohibited'],
            'nombre' => ['required', 'string', 'max:120', Rule::unique('certificate_types', 'nombre')->ignore($certificado)],
            'titulo' => ['required', 'string', 'max:120'],
            'subtitulo' => ['nullable', 'string', 'max:160'],
            'norma' => ['nullable', 'string', 'max:500'],
            'declaracion' => ['nullable', 'string', 'max:2000'],
            'responsabilidad' => ['nullable', 'string', 'max:2000'],
            'prefijo' => ['required', 'string', 'max:6', 'regex:/^[A-Z]+(?:-[A-Z]+)*$/', Rule::unique('certificate_types', 'prefijo')->ignore($certificado)],
            'vigencia_meses' => ['required', 'integer', 'min:0', 'max:120'],
            'activo' => ['sometimes', 'boolean'],
            'orientacion' => ['sometimes', Rule::in(['vertical', 'horizontal'])],
            'columnas' => ['sometimes', 'array', $sinTabla ? 'min:0' : 'min:1', "max:{$maximo}"],
            'columnas.*.clave' => ['nullable', 'string', 'max:60'],
            'columnas.*.titulo' => ['required', 'string', 'max:40'],
            'columnas.*.tipo' => ['required', Rule::in(['texto', 'numero'])],
            'columnas.*.unidad' => ['nullable', 'string', 'max:10'],
            'columnas.*.obligatorio' => ['sometimes', 'boolean'],
            'checklist' => ['sometimes', 'array', 'max:20'],
            'checklist.*.texto' => ['required', 'string', 'max:120'],
            'checklist.*.unidad' => ['nullable', 'string', 'max:10'],
            'checklist.*.minimo' => ['nullable', 'numeric'],
            'checklist.*.maximo' => ['nullable', 'numeric'],
            'firmantes' => ['sometimes', 'array', 'min:1', 'max:'.self::MAXIMO_FIRMANTES],
            'firmantes.*.id' => ['required', 'integer', Rule::exists('signers', 'id')->where('activo', true)],
            'firmantes.*.cargo' => ['nullable', 'string', 'max:120'],
        ];

        if ($certificado === null) {
            $reglas['base_id'] = ['nullable', 'integer', Rule::exists('certificate_types', 'id')->whereNotIn('codigo', EmitirCertificadoDeServicio::TIPOS_DE_EXTINTORES)];
            if (! $request->filled('base_id')) {
                // Desde cero no hay base de la que heredar bloques ni firmas.
                $reglas['composicion'] = ['required', ...array_diff($reglas['composicion'], ['sometimes', 'nullable'])];
                $reglas['firmantes'] = ['required', ...array_diff($reglas['firmantes'], ['sometimes'])];
            }
        }

        $datos = $request->validate($reglas, [
            'base_id.exists' => 'Ese certificado tiene reglas propias: elige un informe de servicio como base.',
            'prefijo.unique' => 'Otro certificado ya usa ese prefijo de numeración.',
            'prefijo.regex' => 'El prefijo lleva letras mayúsculas y guiones entre ellas, por ejemplo GB o BF-UME.',
            'nombre.unique' => 'Ya existe un certificado con ese nombre.',
            'diseno.prohibited' => 'El marco del certificado es fijo. Ordena los bloques del contenido.',
            'columnas.max' => "En hoja {$orientacion} entran hasta {$maximo} columnas. Quita alguna o usa la hoja horizontal.",
            'columnas.min' => 'La tabla necesita al menos una columna.',
            'columnas.*.titulo.required' => 'Ponle nombre a cada columna.',
            'checklist.*.texto.required' => 'Escribe cada prueba o quítala.',
            'firmantes.min' => 'El certificado necesita al menos una firma.',
            'firmantes.max' => 'Al pie entran hasta '.self::MAXIMO_FIRMANTES.' firmas.',
        ]);

        $composicion = array_key_exists('composicion', $datos) ? $datos['composicion'] : $certificado?->composicion;
        if ($composicion !== null) {
            if ($certificado && ! self::puedeSerBase($certificado)) {
                throw ValidationException::withMessages(['composicion' => 'Este formato conserva sus reglas técnicas. Crea una plantilla nueva para elegir sus bloques.']);
            }
            $tipos = array_column($composicion, 'tipo');
            $unicos = array_values(array_filter($tipos, fn (string $tipo) => $tipo !== 'texto'));
            if (count($unicos) !== count(array_unique($unicos))) {
                throw ValidationException::withMessages(['composicion' => 'Cada bloque se agrega una sola vez; puedes repetir los bloques de texto.']);
            }
            $familia = $datos['familia'] ?? $certificado->familia ?? 'operatividad';
            if (($familia === 'diploma') !== in_array('diploma', $tipos, true)) {
                throw ValidationException::withMessages(['composicion' => 'El formato diploma debe incluir su bloque de diploma; usa informe para los otros bloques.']);
            }
            if ($familia === 'diploma' && $orientacion !== 'horizontal') {
                throw ValidationException::withMessages(['orientacion' => 'El diploma usa la hoja horizontal.']);
            }
            $this->validarContenido($composicion, $datos, $certificado);
        }

        foreach ($datos['checklist'] ?? [] as $indice => $punto) {
            if (is_numeric($punto['minimo'] ?? null) && is_numeric($punto['maximo'] ?? null) && (float) $punto['maximo'] < (float) $punto['minimo']) {
                throw ValidationException::withMessages(["checklist.{$indice}.maximo" => 'El máximo no puede ser menor que el mínimo.']);
            }
        }

        return $datos;
    }

    /**
     * Cada bloque elegido necesita lo que dibuja: la tabla sus columnas, las
     * pruebas sus puntos, el texto su contenido y el resultado algo que evaluar.
     *
     * @param  list<array{tipo: string, titulo?: string|null, texto?: string|null}>  $composicion
     * @param  array<string, mixed>  $datos
     */
    private function validarContenido(array $composicion, array $datos, ?CertificateType $certificado): void
    {
        $tipos = array_column($composicion, 'tipo');
        $columnas = array_key_exists('columnas', $datos)
            ? $datos['columnas']
            : array_values(array_filter($certificado->columnas ?? [], fn (array $columna) => ! self::esColumnaFija($columna)));
        $checklist = $datos['checklist'] ?? $certificado->checklist ?? [];

        foreach ($composicion as $indice => $bloque) {
            if ($bloque['tipo'] === 'texto' && blank($bloque['texto'] ?? null)) {
                throw ValidationException::withMessages(["composicion.{$indice}.texto" => 'Escribe el texto del bloque o quítalo.']);
            }
        }
        if (in_array('tabla', $tipos, true) && $columnas === []) {
            throw ValidationException::withMessages(['columnas' => 'La tabla necesita al menos una columna.']);
        }
        if (in_array('pruebas', $tipos, true) && $checklist === []) {
            throw ValidationException::withMessages(['checklist' => 'Agrega al menos una prueba o quita el bloque de pruebas.']);
        }
        if (in_array('resultado', $tipos, true) && ! in_array('tabla', $tipos, true) && ! in_array('pruebas', $tipos, true)) {
            throw ValidationException::withMessages(['composicion' => 'El resultado evalúa la tabla o las pruebas: agrega uno de esos bloques.']);
        }
    }
}
