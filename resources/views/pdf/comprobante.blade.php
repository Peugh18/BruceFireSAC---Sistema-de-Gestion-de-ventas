@php
    $nombreDocumento = match ($document->tipo) {
        'factura' => 'Factura electrónica',
        'boleta' => 'Boleta de venta electrónica',
        'nota_credito' => 'Nota de crédito electrónica',
        'nota_debito' => 'Nota de débito electrónica',
        default => ucfirst(str_replace('_', ' ', $document->tipo)),
    };
    $numero = $document->serie.'-'.str_pad((string) $document->correlativo, 8, '0', STR_PAD_LEFT);
    $fechaEmision = $document->fecha_emision ?? $sale->fecha;
    $vencimiento = $sale->esCredito() && $sale->installments->isNotEmpty() ? $sale->installments->last()->fecha_vencimiento : null;
    $ubicacion = collect([$company->distrito, $company->provincia, $company->departamento])->filter()->unique()->implode(' - ');
    $etiquetaReferencia = null;
    $valorReferencia = null;
    $direccionCliente = $sale->client->direccionImprimible();
    if ($sale->referencia) {
        $etiquetaReferencia = str_contains($sale->referencia, ':')
            ? trim(mb_strtoupper(strstr($sale->referencia, ':', true)))
            : ($sale->destino === 'vehiculo' ? 'PLACA' : 'REFERENCIA');
        $valorReferencia = str_contains($sale->referencia, ':') ? trim(substr(strstr($sale->referencia, ':'), 1)) : $sale->referencia;
    } elseif ($sale->destino === 'vehiculo' && $sale->vehicle) {
        [$etiquetaReferencia, $valorReferencia] = ['PLACA', $sale->vehicle->placa];
    }
    $descuentoTotal = $lineas->sum(fn ($item) => (float) $item->descuento);
    $cantidad = fn ($valor) => fmod((float) $valor, 1.0) === 0.0 ? number_format((float) $valor, 0) : number_format((float) $valor, 2);
    $columnas = 6 + ($mostrarCodigo ? 1 : 0) + ($mostrarDescuento ? 1 : 0);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 26px 30px 24px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #222; line-height: 1.35; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .muted { color: #6b6b6b; }
        .upper { text-transform: uppercase; }

        /* Cabecera */
        .header td { vertical-align: middle; }
        .company-name { font-size: 15px; font-weight: bold; color: {{ $marca['color'] }}; letter-spacing: 0.3px; }
        .company-legal { font-size: 9px; font-weight: bold; color: #333; margin-top: 1px; }
        .company-line { font-size: 8.3px; color: #555; margin-top: 2px; }
        .doc-box { width: 205px; border: 1.5px solid {{ $marca['color'] }}; border-radius: 6px; }
        .doc-box .ruc { background: {{ $marca['color'] }}; color: {{ $marca['texto'] }}; font-weight: bold; font-size: 10.5px; padding: 6px 8px; text-align: center; letter-spacing: 0.5px; }
        .doc-box .tipo { font-size: 10.5px; font-weight: bold; text-transform: uppercase; padding: 7px 8px 2px; text-align: center; }
        .doc-box .numero { font-size: 13px; font-weight: bold; padding: 2px 8px 8px; text-align: center; color: {{ $marca['color'] }}; letter-spacing: 0.5px; }
        .rule { height: 3px; background: {{ $marca['color'] }}; margin: 12px 0 10px; }

        /* Cliente y datos de la operación */
        .panel { background: {{ $marca['suave'] }}; border-radius: 6px; }
        .panel td { padding: 8px 10px; }
        .field { margin-bottom: 3px; }
        .label { font-size: 7.3px; font-weight: bold; color: #6b6b6b; text-transform: uppercase; letter-spacing: 0.4px; }
        .value { font-size: 9.3px; color: #1a1a1a; }
        .value-strong { font-size: 10px; font-weight: bold; color: #1a1a1a; }

        /* Ítems */
        .items { margin-top: 12px; }
        .items th { background: {{ $marca['color'] }}; color: {{ $marca['texto'] }}; font-size: 7.8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; padding: 6px 6px; text-align: left; }
        .items td { padding: 6px 6px; font-size: 8.8px; border-bottom: 0.6px solid {{ $marca['linea'] }}; }
        .items tr.par td { background: #fafafa; }
        .num, .items th.num { text-align: right; white-space: nowrap; }
        .center, .items th.center { text-align: center; }
        .code { font-family: Courier, monospace; font-size: 8.2px; color: #555; }

        /* Totales */
        .summary { margin-top: 10px; }
        .letras { font-size: 8.6px; font-weight: bold; border-left: 3px solid {{ $marca['color'] }}; padding: 5px 8px; background: #f7f7f7; }
        .obs { margin-top: 6px; font-size: 8.4px; }
        .totals td { padding: 3px 8px; font-size: 9px; }
        .totals .t-value { text-align: right; font-weight: bold; white-space: nowrap; }
        .totals .grand td { background: {{ $marca['color'] }}; color: {{ $marca['texto'] }}; font-size: 11.5px; font-weight: bold; padding: 7px 8px; }

        /* Bloques de información */
        .box { margin-top: 10px; border: 0.8px solid #dcdcdc; border-radius: 5px; padding: 7px 9px; font-size: 8.3px; }
        .box-title { font-size: 7.5px; font-weight: bold; color: {{ $marca['color'] }}; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 3px; }
        .mini th { font-size: 7.4px; text-transform: uppercase; color: #6b6b6b; text-align: left; padding: 2px 4px; border-bottom: 0.6px solid #dcdcdc; }
        .mini td { font-size: 8.3px; padding: 2px 4px; }

        /* Pie */
        .thanks { margin-top: 14px; text-align: center; font-size: 10px; font-weight: bold; color: {{ $marca['color'] }}; }
        .leyenda { text-align: center; font-size: 8.3px; font-style: italic; color: #555; margin-top: 2px; }
        .footer { margin-top: 12px; border-top: 0.8px solid #dcdcdc; padding-top: 8px; }
        .footer td { vertical-align: middle; font-size: 7.6px; color: #555; }
        .footer-qr { width: 86px; }
        .footer-qr img { width: 78px; height: 78px; }
    </style>
</head>
<body>
    {{-- Cabecera: logo + empresa | recuadro tributario --}}
    <table class="header">
        <tr>
            @if($logo)
                <td style="width: {{ $logo['ancho'] + 14 }}px; padding-right: 14px;">
                    <img src="{{ $logo['src'] }}" alt="logo" style="width: {{ $logo['ancho'] }}px; height: {{ $logo['alto'] }}px;">
                </td>
            @endif
            <td>
                <div class="company-name">{{ $company->nombre_comercial ?: $company->razon_social }}</div>
                @if($company->nombre_comercial && $company->nombre_comercial !== $company->razon_social)
                    <div class="company-legal">{{ $company->razon_social }}</div>
                @endif
                @if($company->direccion)
                    <div class="company-line">{{ $company->direccion }}@if($ubicacion) · {{ $ubicacion }}@endif</div>
                @endif
                @if($company->telefono || $company->email || $company->sitio_web)
                    <div class="company-line">
                        {{ collect([$company->telefono ? 'Telf. '.$company->telefono : null, $company->email, $company->sitio_web])->filter()->implode('  ·  ') }}
                    </div>
                @endif
            </td>
            <td style="width: 205px;">
                <table class="doc-box">
                    <tr><td class="ruc">R.U.C. {{ $company->ruc }}</td></tr>
                    <tr><td class="tipo">{{ $nombreDocumento }}</td></tr>
                    <tr><td class="numero">{{ $numero }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- Cliente | datos de la operación --}}
    <table class="panel">
        <tr>
            <td style="width: 58%;">
                <div class="field">
                    <div class="label">Cliente</div>
                    <div class="value-strong">{{ $sale->client->razon_social }}</div>
                </div>
                <div class="field">
                    <div class="label">{{ $sale->client->tipo_documento === 'ruc' ? 'RUC' : ($sale->client->tipo_documento === 'dni' ? 'DNI' : 'Documento') }}</div>
                    <div class="value">{{ $sale->client->numero_documento }}</div>
                </div>
                {{-- Siempre visible: si el cliente no tiene dirección queda en blanco
                     para que se note y se complete en su ficha. --}}
                <div class="field">
                    <div class="label">{{ $sale->client->tieneRuc() ? 'Dirección fiscal' : 'Dirección' }}</div>
                    <div class="value">{!! $direccionCliente !== null ? e($direccionCliente) : '&nbsp;' !!}</div>
                </div>
                @if($etiquetaReferencia)
                    <div class="field">
                        <div class="label">{{ $etiquetaReferencia }}</div>
                        <div class="value">{{ $valorReferencia }}</div>
                    </div>
                @endif
            </td>
            <td style="width: 42%;">
                <table>
                    <tr>
                        <td style="padding: 0 6px 4px 0;">
                            <div class="label">Fecha de emisión</div>
                            <div class="value">{{ $fechaEmision->format('d/m/Y') }} {{ $document->created_at?->format('H:i') }}</div>
                        </td>
                        <td style="padding: 0 0 4px;">
                            <div class="label">Moneda</div>
                            <div class="value">Soles (PEN)</div>
                        </td>
                    </tr>
                    @if($document->tipo === 'factura' || $document->tipo === 'boleta')
                        <tr>
                            <td style="padding: 0 6px 4px 0;">
                                <div class="label">Condición de pago</div>
                                <div class="value">{{ $condicionPago }}</div>
                            </td>
                            <td style="padding: 0 0 4px;">
                                @if($vencimiento)
                                    <div class="label">Vencimiento</div>
                                    <div class="value">{{ $vencimiento->format('d/m/Y') }}</div>
                                @endif
                            </td>
                        </tr>
                    @endif
                    @if($sale->vendedor)
                        <tr>
                            <td colspan="2" style="padding: 0;">
                                <div class="label">Atendido por</div>
                                <div class="value">{{ $sale->vendedor->name }}</div>
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if($document->tipo === 'nota_credito' || $document->tipo === 'nota_debito')
        <div class="box">
            <div class="box-title">Documento que modifica</div>
            {{ strtoupper($document->cpeAfectado->tipo ?? '') }} {{ $document->cpeAfectado->serie ?? '' }}-{{ $document->cpeAfectado?->correlativo }}
            @if($document->motivo_catalogo) — {{ $document->motivo_catalogo }} @endif
        </div>
    @endif

    {{-- Detalle --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 18px;" class="center">#</th>
                @if($mostrarCodigo)<th style="width: 62px;">Código</th>@endif
                <th>Descripción</th>
                <th style="width: 38px;" class="center">Unid.</th>
                <th style="width: 36px;" class="num">Cant.</th>
                <th style="width: 54px;" class="num">P. Unit.</th>
                @if($mostrarDescuento)<th style="width: 46px;" class="num">Dscto.</th>@endif
                <th style="width: 60px;" class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineas as $index => $item)
                @php $catalogo = $item->product ?? $item->service; @endphp
                <tr class="{{ $index % 2 === 1 ? 'par' : '' }}">
                    <td class="center muted">{{ $index + 1 }}</td>
                    @if($mostrarCodigo)<td class="code">{{ $catalogo?->codigo }}</td>@endif
                    <td>{{ $catalogo?->nombre }}</td>
                    <td class="center">{{ $unidades[$index] }}</td>
                    <td class="num">{{ $cantidad($item->cantidad) }}</td>
                    <td class="num">{{ number_format((float) $item->precio_unitario, 2) }}</td>
                    @if($mostrarDescuento)<td class="num">{{ (float) $item->descuento > 0 ? number_format((float) $item->descuento, 2) : '—' }}</td>@endif
                    <td class="num"><strong>{{ number_format((float) $item->subtotal, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Monto en letras y observaciones | totales --}}
    <table class="summary">
        <tr>
            <td style="padding-right: 16px;">
                <div class="letras">SON: {{ $montoEnLetras }}</div>
                @if($sale->observaciones)
                    <div class="obs"><span class="label">Observaciones</span><br>{{ $sale->observaciones }}</div>
                @endif
            </td>
            <td style="width: 210px;">
                <table class="totals">
                    @if($descuentoTotal > 0)
                        <tr><td>Descuentos</td><td class="t-value">S/ {{ number_format($descuentoTotal, 2) }}</td></tr>
                    @endif
                    <tr><td>Op. gravada</td><td class="t-value">S/ {{ number_format((float) $sale->subtotal, 2) }}</td></tr>
                    <tr><td>I.G.V. (18%)</td><td class="t-value">S/ {{ number_format((float) $sale->igv, 2) }}</td></tr>
                    <tr class="grand"><td>TOTAL</td><td class="t-value">S/ {{ number_format((float) $sale->total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($sale->esCredito() && $sale->installments->isNotEmpty())
        <div class="box">
            <div class="box-title">Cuotas del crédito</div>
            <table class="mini">
                <tr><th>Cuota</th><th>Vencimiento</th><th>Moneda</th><th style="text-align: right;">Importe</th></tr>
                @foreach($sale->installments as $installment)
                    <tr>
                        <td>{{ $installment->numero_cuota }}</td>
                        <td>{{ optional($installment->fecha_vencimiento)->format('d/m/Y') }}</td>
                        <td>Soles</td>
                        <td class="num">{{ number_format((float) $installment->monto, 2) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if($detraccion['aplica'])
        <div class="box">
            <div class="box-title">Operación sujeta al Sistema de Pago de Obligaciones Tributarias (Detracción)</div>
            Código de bien/servicio: {{ $detraccion['codigo_bien'] }} (Catálogo 54) — Monto de la detracción: S/ {{ number_format($detraccion['monto'], 2) }}
            @if($company->cuenta_detraccion)
                <br>Depositar en la cuenta del Banco de la Nación N.° {{ $company->cuenta_detraccion }}
            @endif
        </div>
    @endif

    @if($bankAccounts->isNotEmpty())
        <div class="box">
            <div class="box-title">Cuentas bancarias</div>
            <table class="mini">
                <tr><th>Banco</th><th>Titular</th><th>N.° de cuenta</th><th>CCI</th></tr>
                @foreach($bankAccounts as $account)
                    <tr>
                        <td><strong>{{ $account->banco }}</strong></td>
                        <td>{{ $account->titular }}</td>
                        <td>{{ $account->numero_cuenta }}</td>
                        <td>{{ $account->cci ?: '—' }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if($company->condiciones_comprobante)
        <div class="box">
            <div class="box-title">Condiciones</div>
            {!! nl2br(e($company->condiciones_comprobante)) !!}
        </div>
    @endif

    <div class="thanks">{{ $company->mensaje_agradecimiento ?: '¡Gracias por su preferencia!' }}</div>
    @if($company->leyenda_pie)
        <div class="leyenda">{{ $company->leyenda_pie }}</div>
    @endif

    <table class="footer">
        <tr>
            <td class="footer-qr">
                @if($qrBase64)
                    <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR">
                @endif
            </td>
            <td>
                <strong>Representación impresa de la {{ mb_strtolower($nombreDocumento) }}.</strong><br>
                Puede consultar su validez en el portal de SUNAT (www.sunat.gob.pe) con el RUC del emisor, el tipo, la serie, el número, la fecha y el importe total.
            </td>
        </tr>
    </table>
</body>
</html>
