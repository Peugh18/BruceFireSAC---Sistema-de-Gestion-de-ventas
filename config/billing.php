<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SUNAT / Greenter
    |--------------------------------------------------------------------------
    */
    'sunat' => [
        'beta' => env('SUNAT_BETA', true),
        'ruc' => env('SUNAT_RUC'),
        'usuario_sol' => env('SUNAT_USUARIO_SOL'),
        'clave_sol' => env('SUNAT_CLAVE_SOL'),
        'cert_path' => env('SUNAT_CERT_PATH', storage_path('app/certificates/certificate.pem')),
        'cert_password' => env('SUNAT_CERT_PASSWORD'),
        // Credenciales de la API REST de Guía de Remisión Electrónica (GRE) — distintas de la Clave SOL.
        'client_id' => env('SUNAT_GRE_CLIENT_ID'),
        'client_secret' => env('SUNAT_GRE_CLIENT_SECRET'),
    ],

    'company' => [
        'ruc' => env('BILLING_COMPANY_RUC'),
        'razon_social' => env('BILLING_COMPANY_RAZON_SOCIAL', 'BRUCE FIRE S.A.C.'),
        'nombre_comercial' => env('BILLING_COMPANY_NOMBRE_COMERCIAL', 'BRUCE FIRE'),
        'ubigeo' => env('BILLING_COMPANY_UBIGEO'),
        'departamento' => env('BILLING_COMPANY_DEPARTAMENTO'),
        'provincia' => env('BILLING_COMPANY_PROVINCIA'),
        'distrito' => env('BILLING_COMPANY_DISTRITO'),
        'direccion' => env('BILLING_COMPANY_DIRECCION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Detracción (SPOT)
    |--------------------------------------------------------------------------
    | Servicios de mantenimiento/recarga/reparación de extintores están sujetos
    | a detracción (12%) cuando el comprobante supera S/700 (R.S. 183-2004/SUNAT,
    | Catálogo 54). El código de bien/servicio exacto (020 vs 022 vs 037) está
    | PENDIENTE de confirmar con el contable de BRUCE FIRE antes de producción.
    | Se deja aquí como constante única para no dispersarlo por los builders.
    */
    'detraccion' => [
        'tasa' => 0.12,
        'monto_minimo' => 700.00,
        'codigo_bien' => env('BILLING_DETRACCION_CODIGO_BIEN', '020'),
        'cod_medio_pago' => '001', // Depósito en cuenta - Banco de la Nación (Catálogo 59)
    ],

    /*
    |--------------------------------------------------------------------------
    | Series por tipo de comprobante (semilla / valores por defecto)
    |--------------------------------------------------------------------------
    */
    'series' => [
        'factura' => 'F001',
        'boleta' => 'B001',
        'guia_remision' => 'T001',
    ],
];
