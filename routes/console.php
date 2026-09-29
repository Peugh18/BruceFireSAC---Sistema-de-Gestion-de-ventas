<?php

use App\Models\TeamInvitation;
use App\Services\SaludDelSistema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('quotes:expire')->daily()->withoutOverlapping();
Schedule::command('alerts:recompute')->daily();
Schedule::command('ml:score-clients')->daily();
Schedule::command('billing:enviar-programados')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ventas:descartar-borradores')->dailyAt('03:00');
Schedule::command('backup:bd')->dailyAt('02:00')->withoutOverlapping();

// Latido del programador: el panel del Gerente avisa si deja de correr
// (sin él no se envían comprobantes a SUNAT ni se hacen respaldos).
Schedule::call(fn () => Cache::forever(SaludDelSistema::CACHE_LATIDO, now()->toIso8601String()))
    ->everyMinute()
    ->description('Latido del programador de tareas');
Schedule::command('sistema:verificar')->dailyAt('02:30')->withoutOverlapping();
// Reentrena el modelo de recompra el día 1 de cada mes (necesita Python).
Schedule::command('ml:reentrenar')->monthlyOn(1, '04:00')->withoutOverlapping();
