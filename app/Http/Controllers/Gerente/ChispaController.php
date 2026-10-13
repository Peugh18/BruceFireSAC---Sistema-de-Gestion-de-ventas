<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\ChispaRegistro;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class ChispaController extends Controller
{
    /**
     * Preguntas que Chispa no supo responder o que no le sirvieron al
     * usuario: de ahí salen las secciones que faltan en los manuales.
     */
    public function index(Team $current_team): Response
    {
        $pendientes = ChispaRegistro::query()
            ->where(fn ($q) => $q->where('util', false)->orWhere('fuente', 'sin_respuesta'))
            ->latest()
            ->paginate(20)
            ->through(fn (ChispaRegistro $r) => [
                'id' => $r->id,
                'fecha' => $r->created_at?->toDateTimeString(),
                'rol' => $r->rol,
                'pagina' => $r->pagina,
                'pregunta' => $r->pregunta,
                'respuesta' => $r->respuesta,
                'motivo' => $r->util === false ? 'No le sirvió' : 'Sin respuesta en el manual',
            ]);

        return Inertia::render('gerente/chispa/index', [
            'preguntas' => $pendientes,
            'resumen' => [
                'total' => ChispaRegistro::count(),
                'utiles' => ChispaRegistro::where('util', true)->count(),
                'no_utiles' => ChispaRegistro::where('util', false)->count(),
                'sin_respuesta' => ChispaRegistro::where('fuente', 'sin_respuesta')->count(),
            ],
        ]);
    }
}
