<?php

return [

    /*
    | El Gerente no entra al sistema sin la verificación en dos pasos
    | confirmada (X3). Por defecto solo se exige en producción (APP_ENV=production);
    | en local y en las pruebas queda apagado salvo que se active a mano.
    */
    'exigir_2fa_gerente' => (bool) env('SEGURIDAD_EXIGIR_2FA_GERENTE', env('APP_ENV') === 'production'),

    /*
    | Proxies de confianza (X4): IP o rangos separados por comas, o "*" detrás
    | del balanceador del hosting. Sin esto, todos los usuarios parecen venir
    | de la misma IP y el límite de intentos de login bloquea a todos.
    */
    'proxies_confiables' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),

    /*
    | Content-Security-Policy (X5). Mientras sea true se envía como
    | Content-Security-Policy-Report-Only: el navegador avisa en la consola
    | lo que bloquearía, sin bloquearlo. Pasar a false después de revisar la
    | consola en producción sin avisos.
    */
    'csp_solo_reporte' => (bool) env('SEGURIDAD_CSP_SOLO_REPORTE', true),

];
