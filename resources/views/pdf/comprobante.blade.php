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
    $esVenta = $document->tipo === 'factura' || $document->tipo === 'boleta';
    $vencimiento = $sale->esCredito() && $sale->installments->isNotEmpty() ? $sale->installments->last()->fecha_vencimiento : null;
    $ubicacion = collect([$company->distrito, $company->provincia, $company->departamento])->filter()->unique()->implode(' - ');
    $direccionCliente = $sale->client->direccionImprimible();
    $etiquetaDocumento = match ($sale->client->tipo_documento) {
        'ruc' => 'R.U.C.',
        'dni' => 'D.N.I.',
        default => 'Documento',
    };

    // Destino que escribió el vendedor ("PLACA: AVR-833", "SEDE: Planta
    // Chimbote" u otro texto): solo aparece si existe, y destacado.
    $destino = null;
    if ($sale->referencia) {
        $tieneEtiqueta = str_contains($sale->referencia, ':');
        $etiqueta = $tieneEtiqueta ? trim(mb_strtoupper(strstr($sale->referencia, ':', true))) : ($sale->destino === 'vehiculo' ? 'PLACA' : 'REFERENCIA');
        $valor = $tieneEtiqueta ? trim(substr(strstr($sale->referencia, ':'), 1)) : trim($sale->referencia);
        if ($valor !== '') {
            $destino = ['etiqueta' => $etiqueta, 'valor' => $valor, 'detalle' => null];
        }
    } elseif ($sale->destino === 'vehiculo' && $sale->vehicle) {
        $destino = ['etiqueta' => 'PLACA', 'valor' => $sale->vehicle->placa, 'detalle' => $sale->vehicle->descripcion];
    }
    if ($destino) {
        $destino['titulo'] = match ($destino['etiqueta']) {
            'PLACA' => 'Vehículo · placa',
            'SEDE', 'LOCAL' => 'Local / sede del cliente',
            default => mb_convert_case(mb_strtolower($destino['etiqueta']), MB_CASE_TITLE),
        };
        if ($destino['etiqueta'] === 'PLACA' && ! $destino['detalle'] && $sale->client->relationLoaded('vehicles')) {
            $destino['detalle'] = $sale->client->vehicles->firstWhere('placa', $destino['valor'])?->descripcion;
        }
        if (in_array($destino['etiqueta'], ['SEDE', 'LOCAL'], true) && $sale->client->relationLoaded('sites')) {
            $destino['detalle'] = $sale->client->sites->first(fn ($sitio) => mb_strtolower(trim($sitio->nombre)) === mb_strtolower($destino['valor']))?->direccion;
        }
    }

    $descuentoTotal = $lineas->sum(fn ($item) => (float) $item->descuento);
    $cantidad = fn ($valor) => fmod((float) $valor, 1.0) === 0.0 ? number_format((float) $valor, 0) : number_format((float) $valor, 2);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px 26px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.8px; color: #222; line-height: 1.4; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        /* Cabecera */
        .header td { vertical-align: middle; }
        .company-name { font-size: 13px; font-weight: bold; color: #111; letter-spacing: 0.2px; }
        .company-line { font-size: 8.2px; color: #555; margin-top: 2px; }
        .doc-box { width: 200px; border: 1.4px solid {{ $marca['color'] }}; }
        .doc-box td { text-align: center; }
        .doc-box .ruc { font-size: 11px; font-weight: bold; color: #111; padding: 8px 6px 5px; letter-spacing: 0.6px; }
        .doc-box .tipo { font-size: 9.6px; font-weight: bold; color: #111; text-transform: uppercase; letter-spacing: 0.4px; padding: 6px; background: #f2f2f2; border-top: 0.6px solid #cfcfcf; border-bottom: 0.6px solid #cfcfcf; }
        .doc-box .numero { font-size: 13.5px; font-weight: bold; color: #111; padding: 6px 6px 8px; letter-spacing: 0.8px; }
        .rule { border-top: 2px solid {{ $marca['color'] }}; margin: 12px 0 0; }

        /* Datos del cliente y de la operación: "Etiqueta : valor" */
        .datos { margin-top: 12px; }
        .datos td { padding: 2px 0; font-size: 8.8px; }
        .k { width: 74px; color: #555; font-weight: bold; }
        .k2 { width: 92px; color: #555; font-weight: bold; }
        .sep { width: 10px; color: #555; }
        .v { color: #111; }
        .v-strong { color: #111; font-weight: bold; font-size: 9.6px; }
        .vacio { border-bottom: 0.6px dotted #b5b5b5; }

        /* Destino destacado: placa o local */
        .destino { margin-top: 10px; border: 0.8px solid #d7d7d7; border-left: 3px solid {{ $marca['color'] }}; background: {{ $marca['suave'] }}; }
        .destino td { padding: 7px 10px; vertical-align: middle; }
        .destino .titulo { font-size: 7.4px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; color: #555; }
        .destino .valor { font-size: 13px; font-weight: bold; color: #111; letter-spacing: 0.6px; }
        .destino .detalle { font-size: 8.4px; color: #444; }

        /* Detalle */
        .items { margin-top: 14px; }
        .items th { font-size: 7.4px; font-weight: bold; color: #333; text-transform: uppercase; letter-spacing: 0.4px; padding: 6px 6px; text-align: left; background: #f2f2f2; border-top: 1px solid #333; border-bottom: 1px solid #333; }
        .items td { padding: 6px 6px; font-size: 8.8px; border-bottom: 0.6px solid #e1e1e1; }
        .items tr.last td { border-bottom: 1px solid #333; }
        .num, .items th.num { text-align: right; white-space: nowrap; }
        .center, .items th.center { text-align: center; }
        .code { font-size: 8px; color: #666; }

        /* Totales */
        .summary { margin-top: 10px; }
        .letras { font-size: 8.4px; border: 0.6px solid #cfcfcf; padding: 6px 8px; }
        .letras b { color: #111; }
        .obs { margin-top: 6px; font-size: 8.4px; color: #333; }
        .totals td { padding: 3px 8px; font-size: 8.8px; }
        .totals .t-label { color: #444; }
        .totals .t-value { text-align: right; white-space: nowrap; color: #111; }
        .totals .grand td { border-top: 1.2px solid #111; border-bottom: 1.2px solid #111; padding: 6px 8px; font-size: 11px; font-weight: bold; color: #111; background: #f2f2f2; }

        /* Bloques opcionales */
        .box { margin-top: 10px; border: 0.6px solid #cfcfcf; padding: 6px 9px; font-size: 8.2px; color: #333; }
        .box-title { font-size: 7.3px; font-weight: bold; color: #111; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .mini th { font-size: 7.2px; text-transform: uppercase; color: #555; text-align: left; padding: 2px 4px; border-bottom: 0.6px solid #cfcfcf; }
        .mini td { font-size: 8.2px; padding: 2px 4px; }

        /* Pie */
        .footer { margin-top: 16px; border-top: 0.6px solid #cfcfcf; padding-top: 8px; }
        .footer td { vertical-align: middle; font-size: 7.6px; color: #555; }
        .footer-qr { width: 84px; }
        .footer-qr img { width: 76px; height: 76px; }
        .thanks { font-size: 9px; font-style: italic; color: #333; margin-top: 4px; }
        .leyenda { font-size: 7.8px; font-style: italic; color: #777; }
    </style>
</head>
<body>
    {{-- Cabecera: logo y empresa | recuadro tributario --}}
    <table class="header">
        <tr>
            @if($logo)
                <td style="width: {{ $logo['ancho'] + 16 }}px; padding-right: 16px;">
                    <img src="{{ $logo['src'] }}" alt="logo" style="width: {{ $logo['ancho'] }}px; height: {{ $logo['alto'] }}px;">
                </td>
            @endif
            <td>
                <div class="company-name">{{ $company->razon_social }}</div>
                @if(! $logo && $company->nombre_comercial && $company->nombre_comercial !== $company->razon_social)
                    <div class="company-line">{{ $company->nombre_comercial }}</div>
                @endif
                @if($company->direccion)
                    <div class="company-line">{{ $company->direccion }}@if($ubicacion), {{ $ubicacion }}@endif</div>
                @endif
                @if($company->telefono || $company->email)
                    <div class="company-line">{{ collect([$company->telefono ? 'Telf. '.$company->telefono : null, $company->email])->filter()->implode('  ·  ') }}</div>
                @endif
                @if($company->sitio_web)
                    <div class="company-line">{{ $company->sitio_web }}</div>
                @endif
            </td>
            <td style="width: 200px;">
                <table class="doc-box">
                    <tr><td class="ruc">R.U.C. N.° {{ $company->ruc }}</td></tr>
                    <tr><td class="tipo">{{ $nombreDocumento }}</td></tr>
                    <tr><td class="numero">{{ $numero }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- Cliente | datos de la operación --}}
    <table class="datos">
        <tr>
            <td style="width: 58%; padding-right: 14px;">
                <table>
                    <tr><td class="k">Señor(es)</td><td class="sep">:</td><td class="v-strong">{{ $sale->client->razon_social }}</td></tr>
                    <tr><td class="k">{{ $etiquetaDocumento }}</td><td class="sep">:</td><td class="v">{{ $sale->client->numero_documento }}</td></tr>
                    <tr>
                        <td class="k">Dirección</td><td class="sep">:</td>
                        {{-- Siempre visible: si falta queda en blanco para completarla en la ficha del cliente. --}}
                        @if($direccionCliente)
                            <td class="v">{{ $direccionCliente }}</td>
                        @else
                            <td class="v vacio">&nbsp;</td>
                        @endif
                    </tr>
                </table>
            </td>
            <td style="width: 42%;">
                <table>
                    <tr><td class="k2">Fecha de emisión</td><td class="sep">:</td><td class="v">{{ $fechaEmision->format('d/m/Y') }}@if($document->created_at) {{ $document->created_at->format('H:i') }}@endif</td></tr>
                    @if($vencimiento)
                        <tr><td class="k2">Fecha de venc.</td><td class="sep">:</td><td class="v">{{ $vencimiento->format('d/m/Y') }}</td></tr>
                    @endif
                    <tr><td class="k2">Moneda</td><td class="sep">:</td><td class="v">Soles (PEN)</td></tr>
                    @if($esVenta)
                        <tr><td class="k2">Forma de pago</td><td class="sep">:</td><td class="v">{{ $condicionPago }}</td></tr>
                    @endif
                    @if($sale->vendedor)
                        <tr><td class="k2">Vendedor</td><td class="sep">:</td><td class="v">{{ $sale->vendedor->name }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if($destino)
        <table class="destino">
            <tr>
                <td style="width: 150px;">
                    <div class="titulo">{{ $destino['titulo'] }}</div>
                    <div class="valor">{{ $destino['valor'] }}</div>
                </td>
                @if($destino['detalle'])
                    <td class="detalle">{{ $destino['detalle'] }}</td>
                @endif
            </tr>
        </table>
    @endif

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
                <th style="width: 34px;" class="num">Cant.</th>
                <th style="width: 38px;" class="center">Unid.</th>
                @if($mostrarCodigo)<th style="width: 62px;">Código</th>@endif
                <th>Descripción</th>
                <th style="width: 56px;" class="num">P. Unit.</th>
                @if($mostrarDescuento)<th style="width: 48px;" class="num">Dscto.</th>@endif
                <th style="width: 62px;" class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineas as $index => $item)
                @php $catalogo = $item->product ?? $item->service; @endphp
                <tr class="{{ $loop->last ? 'last' : '' }}">
                    <td class="num">{{ $cantidad($item->cantidad) }}</td>
                    <td class="center">{{ $unidades[$index] }}</td>
                    @if($mostrarCodigo)<td class="code">{{ $catalogo?->codigo }}</td>@endif
                    <td>{{ $catalogo?->nombre }}</td>
                    <td class="num">{{ number_format((float) $item->precio_unitario, 2) }}</td>
                    @if($mostrarDescuento)<td class="num">{{ (float) $item->descuento > 0 ? number_format((float) $item->descuento, 2) : '' }}</td>@endif
                    <td class="num">{{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Monto en letras y observaciones | totales --}}
    <table class="summary">
        <tr>
            <td style="padding-right: 18px;">
                <div class="letras"><b>SON:</b> {{ $montoEnLetras }}</div>
                @if($sale->observaciones)
                    <div class="obs"><b>Observaciones:</b> {{ $sale->observaciones }}</div>
                @endif
            </td>
            <td style="width: 215px;">
                <table class="totals">
                    @if($descuentoTotal > 0)
                        <tr><td class="t-label">Descuentos</td><td class="t-value">S/ {{ number_format($descuentoTotal, 2) }}</td></tr>
                    @endif
                    <tr><td class="t-label">Op. gravada</td><td class="t-value">S/ {{ number_format((float) $sale->subtotal, 2) }}</td></tr>
                    <tr><td class="t-label">I.G.V. 18%</td><td class="t-value">S/ {{ number_format((float) $sale->igv, 2) }}</td></tr>
                    <tr class="grand"><td>IMPORTE TOTAL</td><td class="t-value">S/ {{ number_format((float) $sale->total, 2) }}</td></tr>
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
                        <td><b>{{ $account->banco }}</b></td>
                        <td>{{ $account->titular }}</td>
                        <td>{{ $account->numero_cuenta }}</td>
                        <td>{{ $account->cci }}</td>
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

    <table class="footer">
        <tr>
            <td class="footer-qr">
                @if($qrBase64)
                    <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR">
                @endif
            </td>
            <td>
                <b>Representación impresa de la {{ mb_strtolower($nombreDocumento) }}.</b><br>
                Consulte su validez en www.sunat.gob.pe con el RUC del emisor, el tipo, la serie, el número, la fecha de emisión y el importe total.
                <div class="thanks">{{ $company->mensaje_agradecimiento ?: 'Gracias por su preferencia.' }}</div>
                @if($company->leyenda_pie)
                    <div class="leyenda">{{ $company->leyenda_pie }}</div>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
