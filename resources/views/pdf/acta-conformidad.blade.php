<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Conformidad - {{ $order->codigo }}</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        body {
            color: #201F1D;
            font-size: 11px;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            border-bottom: 2px solid #0284C7;
            padding-bottom: 12px;
        }
        .company-title {
            font-size: 16px;
            font-weight: 900;
            color: #0284C7;
            letter-spacing: -0.5px;
        }
        .company-info {
            font-size: 9.5px;
            color: #6B6965;
        }
        .doc-title-box {
            background-color: #F0F9FF;
            border: 1px solid #BAE6FD;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
        }
        .doc-title {
            font-size: 12px;
            font-weight: 900;
            color: #0369A1;
            text-transform: uppercase;
        }
        .doc-number {
            font-size: 14px;
            font-weight: 900;
            color: #201F1D;
            margin-top: 2px;
            font-family: monospace;
        }
        .section-title {
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            color: #0284C7;
            margin-top: 14px;
            margin-bottom: 6px;
            border-bottom: 1px solid #E4E1DC;
            padding-bottom: 2px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .info-grid td {
            padding: 3px 6px;
            vertical-align: top;
            font-size: 10px;
        }
        .info-label {
            font-weight: 700;
            color: #4B5563;
            width: 25%;
        }
        .info-val {
            color: #111827;
        }
        .equipment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 14px;
        }
        .equipment-table th {
            background-color: #F8FAFC;
            border: 1px solid #CBD5E1;
            padding: 6px 4px;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            color: #334155;
            text-align: center;
        }
        .equipment-table td {
            border: 1px solid #E2E8F0;
            padding: 5px 4px;
            font-size: 9px;
            text-align: center;
        }
        .equipment-table tr:nth-child(even) {
            background-color: #F8FAFC;
        }
        .statement-box {
            background-color: #FAF9F7;
            border: 1px solid #E4E1DC;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 9.5px;
            color: #374151;
            margin-top: 15px;
            text-align: justify;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 45px;
        }
        .signature-cell {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 20px;
        }
        .signature-line {
            border-top: 1px solid #201F1D;
            width: 80%;
            margin: 0 auto 6px auto;
        }
        .signature-name {
            font-size: 10px;
            font-weight: 800;
            color: #111827;
        }
        .signature-sub {
            font-size: 9px;
            color: #6B6965;
        }
    </style>
</head>
<body>

    <!-- Header Table -->
    <table class="header-table">
        <tr>
            <td style="width: 58%;">
                <div class="company-title">{{ $company->razon_social ?: 'BRUCE FIRE S.A.C.' }}</div>
                <div class="company-info">
                    RUC: {{ $company->ruc ?: '20600000000' }}<br>
                    {{ $company->direccion ?: 'Av. Industrial 456, Trujillo - La Libertad' }}<br>
                    Teléfono: {{ $company->telefono ?: '(044) 283921 / 944 123 456' }} | Email: operaciones@brucefire.pe
                </div>
            </td>
            <td style="width: 42%;">
                <div class="doc-title-box">
                    <div class="doc-title">ACTA DE CONFORMIDAD DE SERVICIO</div>
                    <div class="doc-number">OS: {{ $order->codigo }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Datos del Cliente y Servicio -->
    <div class="section-title">1. Información del Cliente y Orden de Servicio</div>
    <table class="info-grid">
        <tr>
            <td class="info-label">Razón Social:</td>
            <td class="info-val" colspan="3"><strong>{{ $client->razon_social }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">RUC / Documento:</td>
            <td class="info-val">{{ $client->numero_documento ?: 'S/D' }}</td>
            <td class="info-label">Teléfono:</td>
            <td class="info-val">{{ $client->telefono ?: 'S/D' }}</td>
        </tr>
        <tr>
            <td class="info-label">Dirección / Sede:</td>
            <td class="info-val" colspan="3">{{ $sede->direccion ?? $client->direccion_fiscal ?? 'Sede cliente' }}</td>
        </tr>
        <tr>
            <td class="info-label">Fecha de Recepción:</td>
            <td class="info-val">{{ $order->fecha ? $order->fecha->format('d/m/Y') : now()->format('d/m/Y') }}</td>
            <td class="info-label">Fecha de Entrega:</td>
            <td class="info-val">{{ $fechaEntrega }}</td>
        </tr>
        <tr>
            <td class="info-label">Objeto del Servicio:</td>
            <td class="info-val" colspan="3">
                {{ strtoupper(str_replace('_', ' ', $order->tipo_servicio ?: 'Recarga y Mantenimiento de Extintores Contra Incendios')) }}
            </td>
        </tr>
    </table>

    <!-- Tabla Dinámica de Equipos (§23) -->
    <div class="section-title">2. Relación de Equipos / Extintores Atendidos (Total: {{ $equipments->count() }})</div>
    <table class="equipment-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 18%;">Cód. Interno</th>
                <th style="width: 15%;">Serie Fab.</th>
                <th style="width: 14%;">Agente</th>
                <th style="width: 12%;">Capacidad</th>
                <th style="width: 14%;">Marca</th>
                <th style="width: 22%;">Ubicación / Notas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($equipments as $index => $eq)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $eq->numero_serie }}</td>
                    <td>{{ $eq->serie_fabricante ?: 'N/E' }}</td>
                    <td><strong>{{ $eq->tipo_agente ?: 'PQS' }}</strong></td>
                    <td>{{ $eq->capacidad ?: '6 kg' }}</td>
                    <td>{{ $eq->marca ?: 'Bruce Fire' }}</td>
                    <td style="text-align: left;">{{ $eq->ubicacion_actual ?: 'Sede cliente' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding: 10px; color: #6B6965;">No se registraron equipos en el servicio.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Observaciones Técnicas -->
    @if($order->observaciones || !empty($custody['observaciones_entrega']))
        <div class="section-title">3. Observaciones del Servicio</div>
        <div style="font-size: 9.5px; padding: 4px 6px; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 4px;">
            {{ $custody['observaciones_entrega'] ?? $order->observaciones }}
        </div>
    @endif

    <!-- Declaración de Conformidad (§23) -->
    <div class="statement-box">
        <strong>DECLARACIÓN DE CONFORMIDAD:</strong> Por medio del presente documento, el CLIENTE declara haber recibido
        a entera satisfacción los equipos contra incendios arriba detallados, verificando su estado físico, operatividad,
        precintos de seguridad, tarjetas de inspección y sellos correspondientes de conformidad con la Norma Técnica Peruana
        <strong>NTP 350.043 / NFPA 10</strong>.
    </div>

    <!-- Bloque de Firmas -->
    <table class="signatures-table">
        <tr>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-name">
                    {{ $custody['responsable_nombre'] ?? 'TÉCNICO DE CAMPO' }}
                </div>
                <div class="signature-sub">Por: BRUCE FIRE S.A.C.</div>
                <div class="signature-sub">Dpto. de Operaciones y Mantenimiento</div>
            </td>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-name">
                    {{ $custody['receptor_nombre'] ?? $custody['conformidad_nombre'] ?? 'RECEPTOR DEL CLIENTE' }}
                </div>
                <div class="signature-sub">Por: {{ $client->razon_social }}</div>
                <div class="signature-sub">Conformidad en Sede / DNI: {{ $custody['receptor_dni'] ?? 'Verificado' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
