@php
    $nombreDocumento = match ($document->tipo) {
        'factura' => 'Factura electrónica',
        'boleta' => 'Boleta de venta electrónica',
        'nota_credito' => 'Nota de crédito electrónica',
        'nota_debito' => 'Nota de débito electrónica',
        'nota_venta' => 'Nota de venta',
        default => ucfirst(str_replace('_', ' ', $document->tipo)),
    };
    $esNotaVenta = $document->tipo === 'nota_venta';
    $numero = $esNotaVenta
        ? $sale->numero_nota_venta
        : $document->serie.'-'.str_pad((string) $document->correlativo, 8, '0', STR_PAD_LEFT);
    $fechaEmision = $document->fecha_emision ?? $sale->fecha;
    $esVenta = in_array($document->tipo, ['factura', 'boleta', 'nota_venta'], true);
    $vencimiento = $esVenta && $sale->esCredito() && $sale->installments->isNotEmpty() ? $sale->installments->last()->fecha_vencimiento : null;
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
            'SEDE', 'LOCAL' => 'Local / sede',
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
        @include('pdf.hoja._estilos')

        /* Comprobante a media hoja: cliente y operación en una franja */
        .franja { margin-top: 6px; border-bottom: 0.6px solid #d4d4d4; }
        .franja td { padding: 2px 0; }
        .k { color: #666; font-weight: bold; width: 70px; }
        .k2 { color: #666; font-weight: bold; width: 78px; }
        .fuerte { font-weight: bold; font-size: 9.8px; color: #111; }
        .nota { margin-top: 5px; background: {{ $marca['suave'] }}; border-left: 2.5px solid {{ $marca['color'] }}; padding: 3px 7px; font-size: 8.4px; }
        .nota b { text-transform: uppercase; font-size: 7.2px; letter-spacing: 0.5px; color: #555; margin-right: 5px; }
        .items { margin-top: 6px; }
        .items th { font-size: 7.2px; padding: 3px 5px; }
        .items td { padding: 4px 5px; font-size: 9px; }

        /* Cierre: QR · monto en letras y leyenda · totales */
        .cierre { margin-top: 6px; }
        .qr img { width: 72px; height: 72px; }
        .letras { padding: 3px 6px; font-size: 8.2px; }
        .legal { font-size: 7.2px; color: #666; margin-top: 3px; }
        .tot td { padding: 1.5px 6px; }
        .tot .e { color: #555; }
        .tot .g td { background: {{ $marca['color'] }}; color: {{ $marca['texto'] }}; font-weight: bold; font-size: 11.5px; padding: 4px 6px; }
        .compacta { margin-top: 5px; border: 0.5px solid #d0d0d0; }
        .compacta td, .compacta th { padding: 1.5px 5px; font-size: 7.6px; text-align: left; }
        .compacta th { color: #555; text-transform: uppercase; font-size: 6.6px; background: #f6f6f6; }
        .pie { margin-top: 6px; font-size: 7.4px; color: #666; text-align: center; }
        .pie i { color: #333; }

        .marca-anulado { top: 45mm; font-size: 80px; transform: rotate(-20deg); }
    </style>
</head>
<body>
    @include('pdf.hoja._corte')
    @if ($anulado ?? false)
        <div class="marca-anulado">ANULADO</div>
    @endif

    @include('pdf.hoja._cabecera', ['tipoDocumento' => $nombreDocumento, 'numeroDocumento' => $numero])

    <table class="franja">
        <tr>
            <td style="width: 58%; padding-right: 10px;">
                <table>
                    <tr><td class="k">Cliente</td><td class="fuerte">{{ $sale->client->razon_social }}</td></tr>
                    <tr><td class="k">{{ $etiquetaDocumento }}</td><td class="v">{{ $sale->client->numero_documento }}</td></tr>
                    <tr>
                        <td class="k">Dirección</td>
                        {{-- Siempre visible: si falta queda en blanco para completarla en la ficha del cliente. --}}
                        @if($direccionCliente)
                            <td class="v">{{ $direccionCliente }}</td>
                        @else
                            <td class="v vacio">&nbsp;</td>
                        @endif
                    </tr>
                    @if($destino)
                        <tr><td class="k">{{ $destino['titulo'] }}</td><td class="fuerte">{{ $destino['valor'] }}@if($destino['detalle'])<span class="v" style="font-weight: normal;"> · {{ $destino['detalle'] }}</span>@endif</td></tr>
                    @endif
                </table>
            </td>
            <td>
                <table>
                    <tr><td class="k2">Emisión</td><td class="v">{{ $fechaEmision->format('d/m/Y') }}@if($document->created_at) {{ $document->created_at->format('H:i') }}@endif</td></tr>
                    @if($vencimiento)<tr><td class="k2">Fecha de venc.</td><td class="v">{{ $vencimiento->format('d/m/Y') }}</td></tr>@endif
                    <tr><td class="k2">Moneda</td><td class="v">Soles (PEN)</td></tr>
                    @if($esVenta)<tr><td class="k2">Forma de pago</td><td class="v">{{ $condicionPago }}</td></tr>@endif
                    @if($sale->quote?->orden_compra)<tr><td class="k2">Orden de compra</td><td class="v">{{ $sale->quote->orden_compra }}</td></tr>@endif
                    @if($sale->vendedor)<tr><td class="k2">Vendedor</td><td class="v">{{ $sale->vendedor->name }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    @if($document->tipo === 'nota_credito' || $document->tipo === 'nota_debito')
        <div class="nota"><b>Modifica a</b>{{ ucfirst($document->cpeAfectado->tipo ?? '') }} {{ $document->cpeAfectado->serie ?? '' }}-{{ str_pad((string) $document->cpeAfectado?->correlativo, 8, '0', STR_PAD_LEFT) }}@if($document->motivo_catalogo) · Motivo {{ $document->motivo_catalogo }} {{ $motivoNota ?? '' }}@endif</div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;" class="num">Cant.</th>
                <th style="width: 32px;" class="center">Unid.</th>
                @if($mostrarCodigo)<th style="width: 54px;">Código</th>@endif
                <th>Descripción</th>
                <th style="width: 52px;" class="num">P. Unit.</th>
                @if($mostrarDescuento)<th style="width: 44px;" class="num">Dscto.</th>@endif
                <th style="width: 58px;" class="num">Importe</th>
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

    <table class="cierre">
        <tr>
            @if(! empty($qrBase64))
                <td class="qr" style="width: 70px;"><img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR"></td>
            @endif
            <td style="padding-right: 10px;">
                <div class="letras">{{ $montoEnLetras }}</div>
                @if($sale->observaciones)<div class="legal" style="color: #333;"><b>Observaciones:</b> {{ $sale->observaciones }}</div>@endif
                <div class="legal">
                    @if($esNotaVenta)
                        <b>Documento interno de control.</b>&nbsp;No es comprobante de pago; si lo necesita, solicite su boleta o factura.
                    @else
                        <b>Representación impresa de la {{ mb_strtolower($nombreDocumento) }}.</b>&nbsp;Consulte su validez en www.sunat.gob.pe con el RUC del emisor, tipo, serie, número, fecha e importe total.
                    @endif
                </div>
            </td>
            <td style="width: 175px;">
                <table class="tot">
                    @if($descuentoTotal > 0)<tr><td class="e">Descuentos</td><td class="num">S/ {{ number_format($descuentoTotal, 2) }}</td></tr>@endif
                    <tr><td class="e">Op. gravada</td><td class="num">S/ {{ number_format((float) ($montos['subtotal'] ?? $sale->subtotal), 2) }}</td></tr>
                    @if(($montos['exoneradas'] ?? 0) > 0)<tr><td class="e">Op. exonerada</td><td class="num">S/ {{ number_format($montos['exoneradas'], 2) }}</td></tr>@endif
                    @if(($montos['inafectas'] ?? 0) > 0)<tr><td class="e">Op. inafecta</td><td class="num">S/ {{ number_format($montos['inafectas'], 2) }}</td></tr>@endif
                    <tr><td class="e">I.G.V. 18%</td><td class="num">S/ {{ number_format((float) ($montos['igv'] ?? $sale->igv), 2) }}</td></tr>
                    <tr class="g"><td>TOTAL</td><td class="num">S/ {{ number_format((float) ($montos['total'] ?? $sale->total), 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($esVenta && $sale->esCredito() && $sale->installments->isNotEmpty())
        <table class="compacta">
            <tr><th colspan="4">Cuotas del crédito</th></tr>
            @if($detraccion['aplica'])
                <tr><td colspan="3">Monto neto pendiente de pago</td><td class="num">{{ number_format($netoPendiente, 2) }}</td></tr>
            @endif
            @foreach($sale->installments as $installment)
                <tr><td>Cuota {{ $installment->numero_cuota }}</td><td>{{ optional($installment->fecha_vencimiento)->format('d/m/Y') }}</td><td>Soles</td><td class="num">{{ number_format((float) $installment->monto, 2) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if($detraccion['aplica'])
        {{-- Leyenda y datos que SUNAT exige en la representación impresa. --}}
        <table class="compacta">
            <tr><th colspan="2">Información de la detracción</th></tr>
            <tr><td colspan="2">Operación sujeta al Sistema de Pago de Obligaciones Tributarias con el Gobierno Central</td></tr>
            <tr><td>{{ \App\Support\CatalogosSunat::detraccionConNombre($detraccion['codigo_bien']) }}</td><td>Medio de pago: {{ \App\Support\CatalogosSunat::medioDePagoConNombre((string) ($detraccion['medio_pago'] ?? config('billing.detraccion.cod_medio_pago'))) }}</td></tr>
            <tr><td>@if($company->cuenta_detraccion)Nro. Cta. Banco de la Nación: {{ $company->cuenta_detraccion }}@endif</td><td>Porcentaje: {{ rtrim(rtrim(number_format($detraccion['porcentaje'] ?? ((float) $montos['total'] > 0 ? (float) $detraccion['monto'] / (float) $montos['total'] * 100 : 0), 2), '0'), '.') }} % · Monto de la detracción: S/ {{ number_format($detraccion['monto'], 2) }}</td></tr>
        </table>
    @endif

    @if($bankAccounts->isNotEmpty())
        <table class="compacta">
            @foreach($bankAccounts as $account)
                <tr><td><b>{{ $account->banco }}</b> · {{ $account->titular }} · Cta. {{ $account->numero_cuenta }}@if($account->cci) · CCI {{ $account->cci }}@endif</td></tr>
            @endforeach
        </table>
    @endif

    <div class="pie">
        @if($company->condiciones_comprobante){{ \Illuminate\Support\Str::of($company->condiciones_comprobante)->squish() }} · @endif
        <i>{{ $company->mensaje_agradecimiento ?: 'Gracias por su preferencia.' }}</i>
        @if($company->leyenda_pie) · {{ $company->leyenda_pie }}@endif
    </div>
</body>
</html>
