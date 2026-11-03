@php
    // Guía de remisión remitente con la hoja única. Los servicios no viajan:
    // solo los bienes de la guía. El QR es el enlace de consulta de SUNAT.
    $motivo = \App\Models\DispatchGuide::MOTIVOS[$guia->motivo] ?? $guia->motivo_descripcion ?? $guia->motivo;
    $modalidad = \App\Models\DispatchGuide::MODALIDADES[$guia->modalidad] ?? $guia->modalidad;
    $etiquetaDocumento = match ($guia->destinatario_tipo_doc) {
        '6' => 'R.U.C.',
        '1' => 'D.N.I.',
        default => 'Documento',
    };
    $cantidad = fn ($valor) => fmod((float) $valor, 1.0) === 0.0 ? number_format((float) $valor, 0) : number_format((float) $valor, 2);
    $borrador = ! in_array($guia->estado_sunat, [\App\Models\DispatchGuide::ACEPTADA], true);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Guía de remisión {{ $guia->numero() }}</title>
    <style>
        @include('pdf.hoja._estilos')
    </style>
</head>
<body>
    @include('pdf.hoja._corte')
    @include('pdf.hoja._cabecera', [
        'tipoDocumento' => 'Guía de remisión remitente',
        'numeroDocumento' => $guia->serie.'-'.str_pad((string) $guia->correlativo, 8, '0', STR_PAD_LEFT),
        'extraDocumento' => $borrador ? 'Aún sin aceptación de SUNAT' : null,
    ])

    <table class="datos">
        <tr>
            <td style="width: 58%; padding-right: 14px;">
                <table>
                    <tr><td class="k">Destinatario</td><td class="sep">:</td><td class="v-strong">{{ $guia->destinatario_nombre }}</td></tr>
                    <tr><td class="k">{{ $etiquetaDocumento }}</td><td class="sep">:</td><td class="v">{{ $guia->destinatario_num_doc }}</td></tr>
                    <tr><td class="k">Motivo</td><td class="sep">:</td><td class="v">{{ $guia->motivo }} {{ $motivo }}</td></tr>
                </table>
            </td>
            <td style="width: 42%;">
                <table>
                    <tr><td class="k2">Emisión</td><td class="sep">:</td><td class="v">{{ $guia->fecha_emision->format('d/m/Y') }}</td></tr>
                    <tr><td class="k2">Inicio de traslado</td><td class="sep">:</td><td class="v">{{ $guia->fecha_traslado->format('d/m/Y') }}</td></tr>
                    <tr><td class="k2">Peso bruto</td><td class="sep">:</td><td class="v">{{ number_format((float) $guia->peso_bruto, 2) }} kg</td></tr>
                    @if($guia->doc_relacionado_numero)
                        <tr><td class="k2">Doc. relacionado</td><td class="sep">:</td><td class="v">{{ $guia->doc_relacionado_numero }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="destino">
        <tr>
            <td style="width: 50%;">
                <div class="titulo">Punto de partida</div>
                <div class="valor-texto">{{ $guia->partida_direccion }}</div>
                <div class="detalle">{{ $partida }}</div>
            </td>
            <td>
                <div class="titulo">Punto de llegada</div>
                <div class="valor-texto">{{ $guia->llegada_direccion }}</div>
                <div class="detalle">{{ $llegada }}</div>
            </td>
        </tr>
    </table>

    <table class="destino">
        <tr>
            <td>
                <div class="titulo">{{ $modalidad }}</div>
                @if($guia->modalidad === '02')
                    <div class="valor-texto">
                        Placa {{ $guia->vehiculo?->placa ?? '—' }}
                        @if($guia->conductor) · {{ trim($guia->conductor->nombres.' '.$guia->conductor->apellidos) }}, DNI {{ $guia->conductor->dni }} · Lic. {{ $guia->conductor->licencia }} @endif
                    </div>
                @else
                    <div class="valor-texto">{{ $guia->transportista_razon }} · RUC {{ $guia->transportista_ruc }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 34px;" class="num">Cant.</th>
                <th style="width: 38px;" class="center">Unid.</th>
                <th style="width: 70px;">Código</th>
                <th>Descripción del bien</th>
                <th style="width: 62px;" class="num">Peso</th>
            </tr>
        </thead>
        <tbody>
            @foreach($guia->items as $item)
                <tr class="{{ $loop->last ? 'last' : '' }}">
                    <td class="num">{{ $cantidad($item->cantidad) }}</td>
                    <td class="center">{{ $item->unidad }}</td>
                    <td class="code">{{ $item->codigo }}</td>
                    <td>{{ $item->descripcion }}</td>
                    <td class="num">{{ $item->peso_kg !== null ? number_format((float) $item->peso_kg, 2).' kg' : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('pdf.hoja._firmas', ['firmas' => [
        ['nombre' => 'Transportista / conductor', 'detalle' => 'Nombre y DNI'],
        ['nombre' => 'Recibí conforme', 'detalle' => 'Nombre, DNI y fecha'],
    ]])

    @include('pdf.hoja._pie', [
        'textoPie' => $enlaceConsulta
            ? '<b>Representación impresa de la guía de remisión remitente electrónica.</b><br>Escanee el código QR para consultarla en SUNAT.'
            : '<b>Guía de remisión remitente.</b> El código QR de consulta aparece cuando SUNAT acepta la guía.',
    ])
</body>
</html>
