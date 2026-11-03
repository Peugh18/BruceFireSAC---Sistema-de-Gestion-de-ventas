@php
    // Cotización con la hoja única: misma cabecera, datos, detalle, totales y
    // pie que el comprobante; no es un comprobante de pago (sin QR de SUNAT).
    $client = $quote->client;
    $etiquetaDocumento = match ($client->tipo_documento) {
        'ruc' => 'R.U.C.',
        'dni' => 'D.N.I.',
        default => 'Documento',
    };
    $direccionCliente = $quote->direccionComprobante();
    $mostrarDescuento = $quote->items->contains(fn ($item) => (float) $item->descuento > 0);
    $cantidad = fn ($valor) => fmod((float) $valor, 1.0) === 0.0 ? number_format((float) $valor, 0) : number_format((float) $valor, 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $quote->numero }}</title>
    <style>
        @include('pdf.hoja._estilos')
    </style>
</head>
<body>
    @include('pdf.hoja._corte')
    @include('pdf.hoja._cabecera', [
        'tipoDocumento' => 'Cotización',
        'numeroDocumento' => $quote->numero,
        'extraDocumento' => 'Válida hasta el '.$quote->vigencia_hasta->format('d/m/Y'),
    ])

    <table class="datos">
        <tr>
            <td style="width: 58%; padding-right: 14px;">
                <table>
                    <tr><td class="k">Señor(es)</td><td class="sep">:</td><td class="v-strong">{{ $client->razon_social }}</td></tr>
                    <tr><td class="k">{{ $etiquetaDocumento }}</td><td class="sep">:</td><td class="v">{{ $client->numero_documento }}</td></tr>
                    <tr>
                        <td class="k">Dirección</td><td class="sep">:</td>
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
                    <tr><td class="k2">Fecha</td><td class="sep">:</td><td class="v">{{ $quote->fecha->format('d/m/Y') }}</td></tr>
                    <tr><td class="k2">Válida hasta</td><td class="sep">:</td><td class="v"><b>{{ $quote->vigencia_hasta->format('d/m/Y') }}</b></td></tr>
                    <tr><td class="k2">Moneda</td><td class="sep">:</td><td class="v">Soles (PEN)</td></tr>
                    @if($quote->vendedor)
                        <tr><td class="k2">Atendido por</td><td class="sep">:</td><td class="v">{{ $quote->vendedor->name }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if($quote->referencia)
        <table class="destino">
            <tr><td><div class="titulo">Referencia</div><div class="valor-texto">{{ $quote->referencia }}</div></td></tr>
        </table>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th style="width: 34px;" class="num">Cant.</th>
                <th style="width: 38px;" class="center">Unid.</th>
                <th style="width: 62px;">Código</th>
                <th>Descripción</th>
                <th style="width: 56px;" class="num">P. Unit.</th>
                @if($mostrarDescuento)<th style="width: 48px;" class="num">Dscto.</th>@endif
                <th style="width: 62px;" class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
                @php($articulo = $item->product ?? $item->service)
                <tr class="{{ $loop->last ? 'last' : '' }}">
                    <td class="num">{{ $cantidad($item->cantidad) }}</td>
                    <td class="center">{{ $item->service ? 'SERV' : 'UND' }}</td>
                    <td class="code">{{ $articulo?->codigo }}</td>
                    <td>{{ $articulo?->nombre }}</td>
                    <td class="num">{{ number_format((float) $item->precio_unitario, 2) }}</td>
                    @if($mostrarDescuento)<td class="num">{{ (float) $item->descuento > 0 ? number_format((float) $item->descuento, 2) : '' }}</td>@endif
                    <td class="num">{{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td style="padding-right: 18px;">
                <div class="letras">
                    <b>Condiciones:</b> precios en soles, incluyen IGV. Cotización válida hasta el {{ $quote->vigencia_hasta->format('d/m/Y') }}.
                    Para aceptarla, responda este mensaje o envíenos su orden de compra.
                </div>
                @if($quote->observaciones)
                    <div class="obs"><b>Observaciones:</b> {{ $quote->observaciones }}</div>
                @endif
            </td>
            <td style="width: 215px;">
                <table class="totals">
                    <tr><td class="t-label">Op. gravada</td><td class="t-value">S/ {{ number_format((float) $quote->subtotal, 2) }}</td></tr>
                    <tr><td class="t-label">I.G.V. 18%</td><td class="t-value">S/ {{ number_format((float) $quote->igv, 2) }}</td></tr>
                    <tr class="grand"><td>TOTAL</td><td class="t-value">S/ {{ number_format((float) $quote->total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

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

    @include('pdf.hoja._pie', ['textoPie' => '<b>Cotización.</b> No es un comprobante de pago.'])
</body>
</html>
