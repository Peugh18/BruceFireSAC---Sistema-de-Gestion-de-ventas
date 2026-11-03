@php
    // Acta de conformidad con la hoja única. Muestra solo lo registrado: un
    // dato que falta queda en «—», nunca se inventa (antes ponía «6 kg» o «PQS»).
    $etiquetaDocumento = match ($client->tipo_documento) {
        'ruc' => 'R.U.C.',
        'dni' => 'D.N.I.',
        default => 'Documento',
    };
    $observaciones = $custody['observaciones_entrega'] ?? $order->observaciones;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acta de conformidad {{ $order->codigo }}</title>
    <style>
        @include('pdf.hoja._estilos')
        .fotos td { width: 33%; padding: 3px; text-align: center; font-size: 7.6px; color: #555; }
        .fotos img { width: 100%; max-height: 120px; }
    </style>
</head>
<body>
    @include('pdf.hoja._cabecera', [
        'tipoDocumento' => 'Acta de conformidad',
        'numeroDocumento' => $order->codigo,
    ])

    <table class="datos">
        <tr>
            <td style="width: 58%; padding-right: 14px;">
                <table>
                    <tr><td class="k">Cliente</td><td class="sep">:</td><td class="v-strong">{{ $client->razon_social }}</td></tr>
                    <tr><td class="k">{{ $etiquetaDocumento }}</td><td class="sep">:</td><td class="v">{{ $client->numero_documento ?: '—' }}</td></tr>
                    <tr><td class="k">Dirección</td><td class="sep">:</td><td class="v">{{ $sede->direccion ?? $client->direccionImprimible() ?? '—' }}</td></tr>
                    <tr><td class="k">Servicio</td><td class="sep">:</td><td class="v">{{ $order->resumenServicios() }}</td></tr>
                </table>
            </td>
            <td style="width: 42%;">
                <table>
                    <tr><td class="k2">Recepción</td><td class="sep">:</td><td class="v">{{ $order->fecha?->format('d/m/Y') ?? '—' }}</td></tr>
                    <tr><td class="k2">Entrega</td><td class="sep">:</td><td class="v">{{ $fechaEntrega }}</td></tr>
                    @if($client->telefono)
                        <tr><td class="k2">Teléfono</td><td class="sep">:</td><td class="v">{{ $client->telefono }}</td></tr>
                    @endif
                    @if($order->sale)
                        @php($comprobante = $order->sale->electronicDocuments->whereIn('tipo', ['factura', 'boleta'])->last())
                        <tr><td class="k2">Comprobante</td><td class="sep">:</td><td class="v">{{ $comprobante ? $comprobante->serie.'-'.str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT) : ($order->sale->numero_nota_venta ?? $order->sale->numero_interno) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 22px;">#</th>
                <th>Código BF-EQ</th>
                <th>Serie de fábrica</th>
                <th>Agente</th>
                <th>Capacidad</th>
                <th>Marca</th>
                <th>Ubicación</th>
            </tr>
        </thead>
        <tbody>
            @forelse($equipments as $equipo)
                <tr class="{{ $loop->last ? 'last' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td class="code">{{ $equipo->numero_serie }}</td>
                    <td>{{ $equipo->serie_fabricante ?: '—' }}</td>
                    <td>{{ $equipo->tipo_agente ?: '—' }}</td>
                    <td>{{ $equipo->capacidad ?: '—' }}</td>
                    <td>{{ $equipo->marca ?: '—' }}</td>
                    <td>{{ $equipo->ubicacion_actual ?: '—' }}</td>
                </tr>
            @empty
                <tr class="last"><td colspan="7">No se registraron equipos en el servicio.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($observaciones)
        <table class="destino">
            <tr><td><div class="titulo">Observaciones del servicio</div><div class="valor-texto">{{ $observaciones }}</div></td></tr>
        </table>
    @endif

    @if(! empty($fotos))
        <div class="box">
            <div class="box-title">Evidencia fotográfica (antes y después)</div>
            <table class="fotos">
                @foreach(array_chunk($fotos, 3) as $fila)
                    <tr>
                        @foreach($fila as $foto)
                            <td>
                                <img src="{{ $foto['src'] }}" alt="Foto">
                                {{ ucfirst($foto['etapa']) }}{{ $foto['equipo'] ? ' · '.$foto['equipo'] : '' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="box">
        <div class="box-title">Declaración de conformidad</div>
        El cliente declara haber recibido a satisfacción los equipos detallados, verificando su estado físico,
        operatividad, precintos de seguridad, tarjetas de inspección y sellos, conforme a la NTP 350.043 y la NFPA 10.
    </div>

    @include('pdf.hoja._firmas', ['firmas' => [
        ['nombre' => $custody['responsable_nombre'] ?? 'Entregó', 'detalle' => 'Por '.$company->razon_social],
        [
            'nombre' => $custody['receptor_nombre'] ?? $custody['conformidad_nombre'] ?? 'Recibí conforme',
            'detalle' => 'Por '.$client->razon_social.(! empty($custody['receptor_dni']) ? ' · DNI '.$custody['receptor_dni'] : ''),
            'imagen' => $firmaCliente ?? null,
        ],
    ]])

    @include('pdf.hoja._pie', ['textoPie' => '<b>Acta de conformidad de servicio.</b>'])
</body>
</html>
