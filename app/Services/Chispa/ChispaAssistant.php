<?php

namespace App\Services\Chispa;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ChispaAssistant
{
    /**
     * Responde como "Chispa" usando solo el manual de ayuda del rol del
     * usuario. El proveedor (gemini o groq) se elige en services.chispa; si
     * no hay key o el proveedor falla, responde con la sección más parecida
     * del manual para que la ayuda nunca quede muda.
     *
     * @param  list<array{role: string, content: string}>  $mensajes
     * @return array{respuesta: string, fuente: string}
     */
    public function responder(User $user, array $mensajes, ?string $pagina = null): array
    {
        $manual = $this->manual($user);
        $pregunta = (string) (collect($mensajes)->where('role', 'user')->last()['content'] ?? '');
        $proveedor = (string) config('services.chispa.provider', 'gemini');

        try {
            $respuesta = match ($proveedor) {
                'groq' => $this->groq($this->sistema($user, $manual, $pagina), $mensajes),
                default => $this->gemini($this->sistema($user, $manual, $pagina), $mensajes),
            };

            return ['respuesta' => trim($respuesta), 'fuente' => $proveedor];
        } catch (Throwable $exception) {
            Log::warning('Chispa no pudo usar el proveedor de IA; responde con el manual.', [
                'proveedor' => $proveedor,
                'error' => $exception->getMessage(),
            ]);

            return ['respuesta' => $this->buscarEnManual($manual, $pregunta), 'fuente' => 'manual'];
        }
    }

    public function manual(User $user): string
    {
        $archivo = match (true) {
            $user->hasRole('Gerente') => 'gerente',
            $user->hasRole('Almacen') => 'almacen',
            $user->hasRole(['TecnicoPlanta', 'TecnicoCampo']) => 'tecnico',
            default => 'vendedor',
        };

        return (string) file_get_contents(resource_path("ayuda/{$archivo}.md"));
    }

    protected function sistema(User $user, string $manual, ?string $pagina): string
    {
        $rol = $user->getRoleNames()->first() ?? 'Vendedor';

        return <<<PROMPT
        Eres Chispa, el asistente de ayuda del sistema de gestión de Bruce Fire S.A.C. (venta, recarga y mantenimiento de extintores en Trujillo, Perú).
        Hablas en español sencillo y cercano, como un compañero de trabajo que sabe usar el sistema.

        Reglas:
        - Responde SOLO con lo que dice el MANUAL de abajo. Si algo no está en el manual, dilo con honestidad y sugiere preguntar al Gerente.
        - Explica paso a paso con una lista numerada corta cuando sea un procedimiento. Usa los nombres exactos de botones y menús en **negrita**.
        - Sé breve: máximo unas 8 líneas.
        - No inventes datos de clientes, ventas ni precios; no tienes acceso a la base de datos.
        - El usuario es {$user->name}, con rol {$rol}. Está en la pantalla: {$pagina}.

        MANUAL:
        {$manual}
        PROMPT;
    }

    /**
     * @param  list<array{role: string, content: string}>  $mensajes
     */
    protected function gemini(string $sistema, array $mensajes): string
    {
        $key = config('services.chispa.gemini.key');

        if (! $key) {
            throw new RuntimeException('Falta GEMINI_API_KEY.');
        }

        $modelo = config('services.chispa.gemini.model');
        $respuesta = Http::withHeaders(['x-goog-api-key' => $key])
            ->connectTimeout(5)
            ->timeout(25)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $sistema]]],
                'contents' => array_map(fn (array $m) => [
                    'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $m['content']]],
                ], $mensajes),
                'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 2048],
            ])
            ->throw()
            ->json();

        $texto = collect($respuesta['candidates'][0]['content']['parts'] ?? [])->pluck('text')->implode('');

        if ($texto === '') {
            throw new RuntimeException('Gemini no devolvió texto.');
        }

        return $texto;
    }

    /**
     * @param  list<array{role: string, content: string}>  $mensajes
     */
    protected function groq(string $sistema, array $mensajes): string
    {
        $key = config('services.chispa.groq.key');

        if (! $key) {
            throw new RuntimeException('Falta GROQ_API_KEY.');
        }

        $respuesta = Http::withToken($key)
            ->connectTimeout(5)
            ->timeout(25)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.chispa.groq.model'),
                'temperature' => 0.3,
                'max_tokens' => 700,
                'messages' => [['role' => 'system', 'content' => $sistema], ...$mensajes],
            ])
            ->throw()
            ->json();

        $texto = (string) ($respuesta['choices'][0]['message']['content'] ?? '');

        if ($texto === '') {
            throw new RuntimeException('Groq no devolvió texto.');
        }

        return $texto;
    }

    /**
     * Sin IA disponible: devuelve la sección del manual con más palabras en
     * común con la pregunta.
     */
    public function buscarEnManual(string $manual, string $pregunta): string
    {
        $palabras = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($pregunta)))
            ->filter(fn ($p) => mb_strlen($p) > 3)
            ->unique();

        $mejor = collect(preg_split('/^## /m', $manual))
            ->skip(1)
            ->map(fn (string $seccion) => [
                'texto' => '## '.trim($seccion),
                'puntaje' => $palabras->filter(fn ($p) => str_contains(mb_strtolower($seccion), $p))->count(),
            ])
            ->sortByDesc('puntaje')
            ->first();

        if (! $mejor || $mejor['puntaje'] === 0) {
            return 'Ahora no puedo conectarme al asistente y no encontré eso en el manual. Prueba con otras palabras o consulta al Gerente.';
        }

        return "Ahora no puedo conectarme al asistente, pero esto dice el manual:\n\n".$mejor['texto'];
    }
}
