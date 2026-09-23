<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado - {{ $certificate->numero }}</title>
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
            border-bottom: 2px solid #E31E24;
            padding-bottom: 12px;
        }
        .company-title {
            font-size: 16px;
            font-weight: 900;
            color: #E31E24;
            letter-spacing: -0.5px;
        }
        .company-info {
            font-size: 9.5px;
            color: #6B6965;
        }
        .doc-title-box {
            background-color: #FBE7E7;
            border: 1px solid #F3B9BA;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
        }
        .doc-title {
            font-size: 12px;
            font-weight: 900;
            color: #B91C1C;
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
            color: #E31E24;
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
        .units-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 14px;
        }
        .units-table th {
            background-color: #F8FAFC;
            border: 1px solid #CBD5E1;
            padding: 6px 4px;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            color: #334155;
            text-align: center;
        }
        .units-table td {
            border: 1px solid #E2E8F0;
            padding: 5px 4px;
            font-size: 9px;
            text-align: center;
        }
        .units-table tr:nth-child(even) {
            background-color: #F8FAFC;
        }
        .footer-table {
            width: 100%;
            margin-top: 30px;
        }
        .qr-cell {
            width: 25%;
            text-align: center;
            vertical-align: top;
        }
        .qr-cell img {
            width: 80px;
            height: 80px;
        }
        .qr-label {
            font-size: 8px;
            color: #6B6965;
            margin-top: 4px;
        }
        .signature-cell {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
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
        .vigencia-box {
            background-color: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-top: 15px;
            font-size: 10px;
            text-align: center;
            color: #166534;
        }
        .vigencia-box strong {
            font-size: 12px;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 58%;">
                <div class="company-title">{{ $company->razon_social ?: 'BRUCE FIRE S.A.C.' }}</div>
                <div class="company-info">
                    RUC: {{ $company->ruc ?: '20600000000' }}<br>
                    {{ $company->direccion ?: 'Av. Industrial 456, Trujillo - La Libertad' }}<br>
                    Teléfono: {{ $company->telefono ?: '(044) 283921' }}
                </div>
            </td>
            <td style="width: 42%;">
                <div class="doc-title-box">
                    <div class="doc-title">{{ $certificateType->nombre ?? 'Certificado' }}</div>
                    <div class="doc-number">{{ $certificate->numero }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">1. Datos del Cliente</div>
    <table class="info-grid">
        <tr>
            <td class="info-label">Razón Social:</td>
            <td class="info-val" colspan="3"><strong>{{ $client->razon_social }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">RUC / Documento:</td>
            <td class="info-val">{{ $client->numero_documento ?: 'S/D' }}</td>
            <td class="info-label">Dirección:</td>
            <td class="info-val">{{ $client->direccion_fiscal ?: 'S/D' }}</td>
        </tr>
        <tr>
            <td class="info-label">Fecha de Emisión:</td>
            <td class="info-val">{{ $certificate->fecha_emision?->format('d/m/Y') }}</td>
            <td class="info-label">Vigente Hasta:</td>
            <td class="info-val"><strong>{{ $certificate->fecha_vigencia_hasta?->format('d/m/Y') }}</strong></td>
        </tr>
    </table>

    <div class="section-title">2. Equipos Certificados (Total: {{ $units->count() }})</div>
    <table class="units-table">
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 40%;">Código Interno</th>
                <th style="width: 26%;">Últ. Recarga</th>
                <th style="width: 26%;">Últ. Prueba Hidrostática</th>
            </tr>
        </thead>
        <tbody>
            @forelse($units as $index => $unit)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $unit->numero_serie_snapshot }}</td>
                    <td>{{ $unit->fecha_ultima_recarga?->format('d/m/Y') ?: 'N/E' }}</td>
                    <td>{{ $unit->fecha_ultima_ph?->format('d/m/Y') ?: 'N/E' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="padding: 10px; color: #6B6965;">Sin unidades registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="vigencia-box">
        Este certificado avala la operatividad de los equipos listados hasta el
        <strong>{{ $certificate->fecha_vigencia_hasta?->format('d/m/Y') }}</strong>,
        conforme a la Norma Técnica Peruana NTP 350.043 / NFPA 10.
    </div>

    <table class="footer-table">
        <tr>
            <td class="qr-cell">
                @if($qrBase64)
                    <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR de verificación">
                @endif
                <div class="qr-label">Escanea para verificar</div>
            </td>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-name">BRUCE FIRE S.A.C.</div>
                <div class="signature-sub">Dpto. Técnico</div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>

</body>
</html>
