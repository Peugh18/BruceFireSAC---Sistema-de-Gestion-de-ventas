<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Cabeceras de seguridad de todas las páginas (X5): HSTS solo por https y
     * una CSP que deja el servidor de Vite en desarrollo. La CSP va en modo
     * "solo reporte" mientras `seguridad.csp_solo_reporte` sea true.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // El nonce se crea antes de dibujar la página: @vite y el script del
        // tema claro/oscuro lo usan.
        $nonce = Vite::useCspNonce();

        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $cabecera = config('seguridad.csp_solo_reporte') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        $response->headers->set($cabecera, $this->politica($nonce));

        return $response;
    }

    protected function politica(string $nonce): string
    {
        $vite = $this->servidorDeVite();
        $viteWs = $vite ? preg_replace('/^http/', 'ws', $vite) : '';
        $fuentes = 'https://fonts.bunny.net';

        return collect([
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' {$vite}",
            // React y Radix ponen estilos en línea (style="...").
            "style-src 'self' 'unsafe-inline' {$fuentes} {$vite}",
            "font-src 'self' data: {$fuentes} {$vite}",
            "img-src 'self' data: blob: {$vite}",
            "connect-src 'self' {$vite} {$viteWs}",
            "frame-src 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ])->map(fn (string $directiva) => trim((string) preg_replace('/\s+/', ' ', $directiva)))->implode('; ');
    }

    /**
     * Origen del servidor de Vite (`npm run dev`), si está corriendo.
     */
    protected function servidorDeVite(): string
    {
        $hot = Vite::hotFile();

        if (! File::exists($hot)) {
            return '';
        }

        $url = parse_url(trim(File::get($hot)));

        return isset($url['scheme'], $url['host'])
            ? $url['scheme'].'://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '')
            : '';
    }
}
