@php
    // Constancia de recepción con la hoja única: el cliente deja sus extintores
    // y con ella los recoge. Misma cabecera, datos, tabla, firmas y pie.
    $client = $order->client;
    $etiquetaDocumento = match ($client->tipo_documento) {
        'ruc' => 'R.U.C.',
        'dni' => 'D.N.I.',
        default => 'Documento',
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia de recepción {{ $order->codigo }}</title>
    <style>
        @include('pdf.hoja._estilos')
    </style>
</head>
<body>
    @include('pdf.hoja._cabecera', [
        'tipoDocumento' => 'Constancia de recepción',
        'numeroDocumento' => $order->codigo,
    ])

    <table class="datos">
        <tr>
            <td style="width: 58%; padding-right: 14px;">
                <table>
                    <tr><td class="k">Cliente</td><td class="sep">:</td><td class="v-strong">{{ $client->razon_social }}</td></tr>
                    <tr><td class="k">{{ $etiquetaDocumento }}</td><td class="sep">:</td><td class="v">{{ $client->numero_documento }}</td></tr>
                    <tr><td class="k">Dirección</td><td class="sep">:</td><td class="v">{{ $client->direccionImprimible() ?: '—' }}</td></tr>
                </table>
            </td>
            <td style="width: 42%;">
                <table>
                    <tr><td class="k2">Recepción</td><td class="sep">:</td><td class="v">{{ $order->created_at?->format('d/m/Y H:i') }}</td></tr>
                    <tr><td class="k2">Entrega estimada</td><td class="sep">:</td><td class="v">{{ $order->fecha->format('d/m/Y') }}</td></tr>
                    @if($order->sede)
                        <tr><td class="k2">Recibido en</td><td class="sep">:</td><td class="v">{{ $order->sede->nombre }}</td></tr>
                    @endif
                    @if($order->quote)
                        <tr><td class="k2">Cotización</td><td class="sep">:</td><td class="v">{{ $order->quote->numero }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if($order->observaciones)
        <table class="destino">
            <tr><td><div class="titulo">Observaciones</div><div class="valor-texto">{{ $order->observaciones }}</div></td></tr>
        </table>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th style="width: 22px;">#</th>
                <th>Código BF-EQ</th>
                <th>Agente</th>
                <th>Capacidad</th>
                <th>Marca</th>
                <th>Serie de fábrica</th>
                <th>Estado de llegada</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->equipments as $equipment)
                <tr class="{{ $loop->last ? 'last' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td class="code">{{ $equipment->numero_serie }}</td>
                    <td>{{ $equipment->tipo_agente }}</td>
                    <td>{{ $equipment->capacidad }}</td>
                    <td>{{ $equipment->marca }}</td>
                    <td>{{ $equipment->serie_fabricante }}</td>
                    <td>{{ $equipment->pivot->observaciones ?: 'Sin observaciones' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('pdf.hoja._firmas', ['firmas' => [
        ['nombre' => 'Entregó', 'detalle' => 'Nombre y DNI de quien deja los equipos', 'imagen' => $firmaRecojo ?? null],
        ['nombre' => 'Recibió', 'detalle' => $company->razon_social],
    ]])

    @include('pdf.hoja._pie', ['textoPie' => '<b>Constancia de recepción.</b> Consérvela: con ella recoge sus equipos. Consulte el avance con el número de orden.'])
</body>
</html>
