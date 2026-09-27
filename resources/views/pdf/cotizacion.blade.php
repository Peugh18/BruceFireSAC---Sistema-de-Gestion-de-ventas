<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización {{ $quote->numero }}</title>
    {{--
        Cotización para el cliente con la marca Bruce Fire: logo, número y
        vigencia, cliente, detalle con precios que incluyen IGV, totales,
        condiciones y cuentas para pagar. No es un comprobante de pago.
    --}}
    <style>
        @page { margin: 16mm 15mm 18mm; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1b1918; font-size: 10.5px; line-height: 1.4; }
        table { border-collapse: collapse; width: 100%; }
        .rojo { color: #d20404; }
        .gris { color: #6b6965; }

        .cabecera td { vertical-align: middle; }
        .cabecera .logo img { width: 62mm; }
        .caja { border: 1.4px solid #d20404; border-radius: 6px; text-align: center; padding: 7px 10px; }
        .caja .titulo { font-size: 15px; font-weight: bold; letter-spacing: 3px; color: #d20404; }
        .caja .numero { font-size: 14px; font-weight: bold; font-family: 'DejaVu Sans Mono', monospace; margin-top: 2px; }
        .caja .fechas { font-size: 9px; color: #3f3d3b; margin-top: 3px; }
        .empresa { margin-top: 6px; font-size: 9px; color: #3f3d3b; border-bottom: 2px solid #d20404; padding-bottom: 7px; }
        .empresa b { color: #1b1918; }

        .seccion { margin-top: 12px; font-size: 9px; font-weight: bold; letter-spacing: 1.5px; color: #d20404; text-transform: uppercase; }
        .datos { margin-top: 4px; background: #f6f5f3; border-radius: 5px; }
        .datos td { padding: 4px 9px; font-size: 10px; vertical-align: top; }
        .datos .et { width: 19%; color: #6b6965; font-weight: bold; }

        .items { margin-top: 5px; }
        .items th { background: #2e2d2c; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; padding: 6px 7px; text-align: left; }
        .items td { padding: 6px 7px; border-bottom: 1px solid #e7e5e2; vertical-align: top; }
        .items tr:nth-child(even) td { background: #fbfaf9; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .cod { font-size: 8.5px; color: #6b6965; }

        .pie td { vertical-align: top; }
        .totales { margin-top: 8px; }
        .totales td { padding: 3px 8px; }
        .totales .monto { text-align: right; font-weight: bold; }
        .totales .total td { background: #d20404; color: #fff; font-size: 13px; font-weight: bold; padding: 6px 8px; }

        .condiciones { margin-top: 8px; font-size: 9.5px; }
        .condiciones li { margin-bottom: 2px; }
        .bancos { margin-top: 10px; border: 1px solid #e4e1dc; border-radius: 5px; padding: 6px 9px; font-size: 9.5px; }
        .final { margin-top: 18px; text-align: center; font-size: 9.5px; color: #6b6965; }
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td class="logo">
                @if ($logo)
                    <img src="{{ $logo }}" alt="Extintores Bruce Fire">
                @else
                    <b style="font-size: 18px;">{{ $company->razon_social }}</b>
                @endif
            </td>
            <td style="width: 38%;">
                <div class="caja">
                    <div class="titulo">COTIZACIÓN</div>
                    <div class="numero">{{ $quote->numero }}</div>
                    <div class="fechas">
                        Fecha: {{ $quote->fecha->format('d/m/Y') }} ·
                        <b>Válida hasta: {{ $quote->vigencia_hasta->format('d/m/Y') }}</b>
                    </div>
                </div>
            </td>
        </tr>
    </table>
    <div class="empresa">
        <b>{{ $company->razon_social }}</b>
        @if ($company->ruc) · RUC {{ $company->ruc }} @endif
        @if ($company->direccion) · {{ $company->direccion }} @endif
        @if ($company->telefono) · Tel. {{ $company->telefono }} @endif
        @if ($company->email) · {{ $company->email }} @endif
    </div>

    <div class="seccion">Cliente</div>
    <table class="datos">
        <tr>
            <td class="et">Razón social</td>
            <td><b>{{ $quote->client->razon_social }}</b></td>
            <td class="et">{{ strtoupper((string) $quote->client->tipo_documento) === 'RUC' ? 'RUC' : 'Documento' }}</td>
            <td>{{ $quote->client->numero_documento }}</td>
        </tr>
        @if ($quote->client->direccion_fiscal || $quote->referencia)
            <tr>
                <td class="et">Dirección</td>
                <td>{{ $quote->client->direccion_fiscal ?: '—' }}</td>
                <td class="et">Referencia</td>
                <td>{{ $quote->referencia ?: '—' }}</td>
            </tr>
        @endif
        <tr>
            <td class="et">Atendido por</td>
            <td colspan="3">{{ $quote->vendedor?->name }}@if ($quote->vendedor?->email) · {{ $quote->vendedor->email }}@endif</td>
        </tr>
    </table>

    <div class="seccion">Detalle</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th>Descripción</th>
                <th class="num" style="width: 9%;">Cant.</th>
                <th class="num" style="width: 15%;">P. unit.</th>
                <th class="num" style="width: 12%;">Desc.</th>
                <th class="num" style="width: 15%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $indice => $item)
                @php($articulo = $item->product ?? $item->service)
                <tr>
                    <td>{{ str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) }}</td>
                    <td>
                        {{ $articulo?->nombre }}
                        @if ($articulo?->codigo)
                            <div class="cod">Código {{ $articulo->codigo }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $item->cantidad }}</td>
                    <td class="num">S/ {{ number_format((float) $item->precio_unitario, 2) }}</td>
                    <td class="num">{{ (float) $item->descuento > 0 ? 'S/ '.number_format((float) $item->descuento, 2) : '—' }}</td>
                    <td class="num"><b>S/ {{ number_format((float) $item->subtotal, 2) }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="pie">
        <tr>
            <td style="width: 56%; padding-right: 14px;">
                <div class="seccion">Condiciones</div>
                <ul class="condiciones">
                    <li>Forma de pago: <b>{{ $quote->condicion_pago_propuesta ?: 'Contado' }}</b>.</li>
                    <li>Precios en soles, <b>incluyen IGV</b>.</li>
                    <li>Cotización válida hasta el <b>{{ $quote->vigencia_hasta->format('d/m/Y') }}</b>.</li>
                    @if ($quote->observaciones)
                        <li>{{ $quote->observaciones }}</li>
                    @endif
                </ul>
            </td>
            <td>
                <table class="totales">
                    <tr><td>Op. gravada</td><td class="monto">S/ {{ number_format((float) $quote->subtotal, 2) }}</td></tr>
                    <tr><td>IGV (18 %)</td><td class="monto">S/ {{ number_format((float) $quote->igv, 2) }}</td></tr>
                    <tr class="total"><td>TOTAL</td><td style="text-align: right;">S/ {{ number_format((float) $quote->total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($bankAccounts->isNotEmpty())
        <div class="bancos">
            <b class="rojo">Cuentas para el pago</b> (a nombre de {{ $company->razon_social }})
            @foreach ($bankAccounts as $cuenta)
                <div>{{ $cuenta->banco }} ({{ $cuenta->moneda }}): {{ $cuenta->numero_cuenta }}@if ($cuenta->cci) · CCI {{ $cuenta->cci }}@endif</div>
            @endforeach
        </div>
    @endif

    <div class="final">
        Gracias por su preferencia. Para aceptar esta cotización responda a este mensaje o llámenos.
        @if ($company->leyenda_pie)
            <br><i>{{ $company->leyenda_pie }}</i>
        @endif
    </div>
</body>
</html>
