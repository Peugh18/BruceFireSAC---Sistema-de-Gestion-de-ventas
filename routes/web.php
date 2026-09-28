<?php

use App\Http\Controllers\ChispaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicCertificateVerificationController;
use App\Http\Controllers\PublicQuotePdfController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UbigeoController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Verificación pública por QR (inspectores ITSE). Con límite de consultas para
// que no se puedan probar tokens en masa.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/verificar-certificado/{token}', [PublicCertificateVerificationController::class, 'show'])->name('certificados.verificar');
    Route::get('/verificar-certificado/{token}/pdf', [PublicCertificateVerificationController::class, 'pdf'])->name('certificados.verificar.pdf');
    // Cotización que el cliente abre desde WhatsApp (enlace firmado que vence).
    Route::get('/cotizacion/{quote}', PublicQuotePdfController::class)->middleware('signed')->name('cotizaciones.publico');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        require __DIR__.'/vendedor.php';
        require __DIR__.'/gerente.php';
        require __DIR__.'/almacen.php';
        require __DIR__.'/tecnico-planta.php';
        require __DIR__.'/tecnico-campo.php';
    });

Route::middleware(['auth'])->group(function () {
    Route::get('ubigeos', UbigeoController::class)->name('ubigeos.buscar');
    Route::post('asistente', ChispaController::class)->middleware('throttle:20,1')->name('asistente');
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';

// Las URL que no existen pasan por el grupo web (sesión y usuario) antes del
// 404, así la página de error sabe el rol y ofrece volver a su panel.
Route::fallback(fn () => abort(404));
