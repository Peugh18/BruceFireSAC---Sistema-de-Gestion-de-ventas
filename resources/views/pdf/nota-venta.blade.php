<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nota de Venta {{ $sale->numero_nota_venta }}</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #201F1D; font-size: 11px; line-height: 1.4; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 16px; border-bottom: 2px solid #C2410C; }
        .company { font-size: 16px; font-weight: 900; color: #C2410C; }
        .muted { font-size: 9.5px; color: #6B6965; }
        .box { border: 1px solid #E4E1DC; border-radius: 6px; padding: 8px 12px; text-align: center; }
        .box-title { font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .box-number { font-size: 14px; font-weight: 900; font-family: monospace; margin-top: 2px; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .info td { padding: 3px 6px; font-size: 10px; vertical-align: top; }
        .label { font-weight: 700; color: #4B5563; width: 22%; }
        .items { width: 100%; border-collapse: collapse; }
        .items th { background: #F5F4F2; text-align: left; padding: 6px; font-size: 10px; text-transform: uppercase; border-bottom: 1px solid #E4E1DC; }
        .items td { padding: 6px; border-bottom: 1px solid #EEECE9; }
        .right { text-align: right; }
        .totals { width: 40%; margin-left: 60%; margin-top: 12px; border-collapse: collapse; }
        .totals td { padding: 3px 6px; }
        .grand { font-weight: 900; font-size: 13px; border-top: 1px solid #201F1D; }
        .note { margin-top: 24px; font-size: 9.5px; color: #6B6965; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="company">{{ $company->razon_social }}</div>
                <div class="muted">
                    @if ($company->ruc) RUC {{ $company->ruc }} · @endif
                    {{ $company->direccion }}
                </div>
            </td>
            <td style="width: 32%">
                <div class="box">
                    <div class="box-title">Nota de Venta</div>
                    <div class="box-number">{{ $sale->numero_nota_venta }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="info">
        <tr><td class="label">Cliente</td><td>{{ $sale->client->razon_social }}</td><td class="label">Fecha</td><td>{{ $sale->fecha->format('d/m/Y') }}</td></tr>
        <tr><td class="label">Documento</td><td>{{ $sale->client->numero_documento }}</td><td class="label">Condición de pago</td><td>{{ $sale->condicion_pago === 'credito_30' ? 'Crédito 30 días' : 'Contado' }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>Descripción</th><th class="right">Cant.</th><th class="right">P. unit.</th><th class="right">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product?->nombre ?? $item->service?->nombre }} @if ($item->equipment?->numero_serie) · Serie {{ $item->equipment->numero_serie }} @endif</td>
                    <td class="right">{{ $item->cantidad }}</td>
                    <td class="right">{{ number_format((float) $item->precio_unitario, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ number_format((float) $sale->subtotal, 2) }}</td></tr>
        <tr><td>IGV</td><td class="right">{{ number_format((float) $sale->igv, 2) }}</td></tr>
        <tr class="grand"><td>Total S/</td><td class="right">{{ number_format((float) $sale->total, 2) }}</td></tr>
    </table>

    <div class="note">Documento interno de control. No es un comprobante de pago electrónico.</div>
</body>
</html>
