<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('quotes:expire')->daily();
Schedule::command('alerts:recompute')->daily();
Schedule::command('ml:score-clients')->daily();
Schedule::command('billing:enviar-programados')->everyFiveMinutes()->withoutOverlapping();
