<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Este dashboard genérico del starter es un placeholder vacío. Los roles
     * de negocio tienen el suyo propio — se redirige ahí, salvo que el
     * usuario tenga una invitación de equipo pendiente por aceptar (eso sí
     * se muestra aquí, no tiene página propia todavía).
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        if ($pendingInvitations->isEmpty()) {
            if ($request->user()->hasRole('Vendedor')) {
                return redirect()->route('vendedor.dashboard', [
                    'current_team' => $request->route('current_team'),
                ]);
            }

            if ($request->user()->hasRole('Almacen')) {
                return redirect()->route('almacen.dashboard', [
                    'current_team' => $request->route('current_team'),
                ]);
            }
        }

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
        ]);
    }
}
