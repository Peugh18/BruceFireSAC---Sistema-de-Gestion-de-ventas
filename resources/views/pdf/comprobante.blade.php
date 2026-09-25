<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 22px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5px; color: #1a1a1a; }
        table { border-collapse: collapse; width: 100%; }
        .header-table td { vertical-align: middle; padding: 0; }
        .logo-cell { width: 90px; }
        .logo-cell img { max-width: 80px; max-height: 70px; }
        .company-cell { text-align: center; padding: 0 8px; }
        .company-name { font-size: 13px; font-weight: bold; }
        .company-detail { font-size: 8.5px; color: #333; }
        .doc-box { width: 190px; border: 1px solid #000; }
        .doc-box td { border-bottom: 1px solid #000; padding: 4px 8px; text-align: center; }
        .doc-box tr:last-child td { border-bottom: none; }
        .doc-box .ruc { font-size: 9px; font-weight: bold; }
        .doc-box .tipo { font-size: 10.5px; font-weight: bold; text-transform: uppercase; }
        .doc-box .numero { font-size: 11px; font-weight: bold; }

        .info-table { margin-top: 10px; }
        .info-table td { vertical-align: top; padding: 1px 0; font-size: 9px; }
        .info-table .label { font-weight: bold; }
        .info-right { text-align: right; }

        .items-table { margin-top: 8px; border: 1px solid #333; }
        .items-table th { background: #ececec; border: 1px solid #333; padding: 3px 4px; font-size: 8.5px; text-align: left; }
        .items-table td { border: 1px solid #333; padding: 3px 4px; font-size: 8.5px; }
        .items-table .num { text-align: right; }

        .totals-wrap { margin-top: 0; }
        .totals-wrap td { vertical-align: top; padding-top: 4px; }
        .son { font-size: 8.5px; }
        .totals-box { width: 180px; }
        .totals-box td { padding: 1px 4px; font-size: 9px; }
        .totals-box .t-label { text-align: left; }
        .totals-box .t-value { text-align: right; font-weight: bold; }
        .totals-box .grand { font-weight: bold; border-top: 1px solid #333; }

        .cuotas-table { margin-top: 6px; border: 1px solid #333; width: 320px; }
        .cuotas-table th { background: #ececec; border: 1px solid #333; padding: 2px 5px; font-size: 8px; }
        .cuotas-table td { border: 1px solid #333; padding: 2px 5px; font-size: 8px; text-align: center; }

        .bank-box { border: 1px solid #333; padding: 5px 8px; margin-top: 8px; font-size: 8.5px; }
        .bank-box .title { font-weight: bold; }

        .ref-box { border: 1px solid #333; padding: 5px 8px; margin-top: 6px; font-size: 8.5px; }
        .ref-box .title { font-weight: bold; }

        .thanks { text-align: center; margin-top: 14px; font-size: 9px; font-style: italic; }

        .footer-table { margin-top: 10px; }
        .footer-table td { vertical-align: top; font-size: 7.5px; color: #444; }
        .footer-qr { width: 90px; text-align: right; }
        .footer-qr img { width: 78px; height: 78px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="logo">
                @endif
            </td>
            <td class="company-cell">
                <div class="company-name">{{ $company->razon_social }}</div>
                <div class="company-detail">
                    @if($company->direccion)
                        Dir: {{ $company->direccion }}
                        @if($company->distrito) {{ $company->distrito }} {{ $company->departamento }} @endif
                    @endif
                </div>
                @if($company->telefono || $company->email)
                    <div class="company-detail">
                        @if($company->telefono) Telf: {{ $company->telefono }} @endif
                        @if($company->email) &nbsp; Email: {{ $company->email }} @endif
                    </div>
                @endif
            </td>
            <td>
                <table class="doc-box">
                    <tr><td class="ruc">RUC {{ $company->ruc }}</td></tr>
                    <tr>
                        <td class="tipo">
                            @switch($document->tipo)
                                @case('factura') FACTURA ELECTRONICA @break
                                @case('boleta') BOLETA DE VENTA ELECTRONICA @break
                                @case('nota_credito') NOTA CREDITO ELECTRONICA @break
                                @case('nota_debito') NOTA DEBITO ELECTRONICA @break
                                @default {{ strtoupper($document->tipo) }}
                            @endswitch
                        </td>
                    </tr>
                    <tr><td class="numero">{{ $document->serie }}-{{ str_pad((string) $document->correlativo, 8, '0', STR_PAD_LEFT) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td style="width: 60%;">
                <span class="label">RUC/DNI:</span> {{ $sale->client->numero_documento }}<br>
                <span class="label">CLIENTE:</span> {{ $sale->client->razon_social }}<br>
                <span class="label">DIRECCIÓN:</span> {{ $sale->client->direccion_fiscal ?? '-' }}
            </td>
            <td class="info-right" style="width: 40%;">
                @if($document->tipo === 'factura' || $document->tipo === 'boleta')
                    <span class="label">CONDICIÓN DE PAGO:</span> {{ $sale->condicion_pago === 'credito_30' ? 'CREDITO 30 DIAS' : 'CONTADO' }}<br>
                @endif
                <span class="label">FECHA EMISIÓN:</span> {{ ($document->fecha_emision ?? $sale->fecha)->format('d/m/Y') }}
                @if($sale->condicion_pago === 'credito_30' && $sale->installments->isNotEmpty())
                    <br><span class="label">FECHA VENCIMIENTO:</span> {{ optional($sale->installments->last()->fecha_vencimiento)->format('d/m/Y') }}
                @endif
            </td>
        </tr>
    </table>

    @if($sale->destino === 'vehiculo' && $sale->vehicle)
        <div style="margin-top: 4px; font-size: 9px;"><span class="label">PLACA:</span> {{ $sale->vehicle->placa }}</div>
    @endif

    @if($document->tipo === 'nota_credito' || $document->tipo === 'nota_debito')
        <div class="ref-box">
            <span class="title">DOCUMENTO QUE MODIFICA</span><br>
            {{ strtoupper($document->cpeAfectado->tipo ?? '') }} {{ $document->cpeAfectado->serie ?? '' }}-{{ $document->cpeAfectado?->correlativo }}
            @if($document->motivo_catalogo) — {{ $document->motivo_catalogo }} @endif
        </div>
    @endif

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 22px;">Nro</th>
                <th style="width: 45px;">Código</th>
                <th>Descripción</th>
                <th style="width: 45px;">U.Medida</th>
                <th style="width: 32px;">Cant.</th>
                <th style="width: 42px;">P.Unit.</th>
                <th style="width: 40px;">Dscto.</th>
                <th style="width: 45px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->lineasComprobante() as $index => $item)
                @php $productOrService = $item->product ?? $item->service; @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $productOrService?->codigo }}</td>
                    <td>{{ $productOrService?->nombre }}</td>
                    <td>{{ $productOrService?->unidad_medida }}</td>
                    <td class="num">{{ number_format((float) $item->cantidad, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->precio_unitario, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->descuento, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td>
                <div class="son">{{ $montoEnLetras }}</div>
                @if($sale->observaciones)
                    <div class="son">Obs: {{ $sale->observaciones }}</div>
                @endif
            </td>
            <td style="width: 190px;">
                <table class="totals-box">
                    <tr><td class="t-label">GRAVADO S/</td><td class="t-value">{{ number_format((float) $sale->subtotal, 2) }}</td></tr>
                    <tr><td class="t-label">I.G.V (18%) S/</td><td class="t-value">{{ number_format((float) $sale->igv, 2) }}</td></tr>
                    <tr><td class="t-label grand">TOTAL S/</td><td class="t-value grand">{{ number_format((float) $sale->total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($sale->condicion_pago === 'credito_30' && $sale->installments->isNotEmpty())
        <table class="cuotas-table">
            <thead>
                <tr><th>Cuota</th><th>F.Vencimiento</th><th>Moneda</th><th>Importe</th></tr>
            </thead>
            <tbody>
                @foreach($sale->installments as $installment)
                    <tr>
                        <td>{{ $installment->numero_cuota }}</td>
                        <td>{{ optional($installment->fecha_vencimiento)->format('d/m/Y') }}</td>
                        <td>Soles</td>
                        <td>{{ number_format((float) $installment->monto, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($detraccion['aplica'])
        <div class="bank-box">
            <div class="title">Operación sujeta al Sistema de Pago de Obligaciones Tributarias (Detracción)</div>
            Código de bien/servicio: {{ $detraccion['codigo_bien'] }} (Catálogo 54) — Monto de la detracción: S/ {{ number_format($detraccion['monto'], 2) }}
            @if($company->cuenta_detraccion)
                <br>Depositar en cuenta del Banco de la Nación N.° {{ $company->cuenta_detraccion }}
            @endif
        </div>
    @endif

    @if($bankAccounts->isNotEmpty())
        <div class="bank-box">
            <div class="title">Cuentas Bancarias:</div>
            @foreach($bankAccounts as $account)
                {{ $account->banco }} — Titular: {{ $account->titular }} — N.° de Cuenta: {{ $account->numero_cuenta }}
                @if($account->cci) — CCI: {{ $account->cci }} @endif
                <br>
            @endforeach
        </div>
    @endif

    @if($company->leyenda_pie)
        <div class="thanks">{{ $company->leyenda_pie }}</div>
    @endif
    <div class="thanks">¡Muchas gracias por su preferencia!</div>

    <table class="footer-table">
        <tr>
            <td>
                REPRESENTACIÓN IMPRESA DE
                @switch($document->tipo)
                    @case('factura') FACTURA ELECTRÓNICA @break
                    @case('boleta') BOLETA DE VENTA ELECTRÓNICA @break
                    @case('nota_credito') NOTA DE CRÉDITO ELECTRÓNICA @break
                    @case('nota_debito') NOTA DE DÉBITO ELECTRÓNICA @break
                @endswitch.
                Este documento puede consultarse en el portal de SUNAT.
            </td>
            <td class="footer-qr">
                @if($qrBase64)
                    <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR">
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
