<!DOCTYPE html>
@php($esA4 = ($formato ?? 'rollo') === 'a4')
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Stickers de código de barras</title>
    <style>
        @page {
            @if ($esA4)
                size: A4 portrait;
                margin: 15mm 20mm;
            @else
                size: 80mm 50mm;
                margin: 0;
            @endif
        }

        html,
        body {
            width: {{ $esA4 ? '170mm' : '80mm' }};
            margin: 0;
            padding: 0;
        }

        body {
            color: #111827;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        .hoja {
            position: relative;
            width: {{ $esA4 ? '170mm' : '80mm' }};
            height: {{ $esA4 ? '266mm' : '49mm' }};
            page-break-after: always;
        }

        .hoja:last-child {
            page-break-after: avoid;
        }

        .sticker {
            position: absolute;
            width: 72mm;
            height: 45mm;
            margin: 0;
            padding: 2.5mm 4mm 2mm;
            overflow: hidden;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .details td {
            padding: 0.4mm 0;
            vertical-align: top;
            font-size: 8.5px;
            line-height: 1.1;
        }

        .details .label {
            width: 17mm;
            color: #4b5563;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.4px;
        }

        .details .value {
            color: #111827;
            font-weight: bold;
        }

        .serie {
            margin-top: 1mm;
            overflow: hidden;
            font-family: 'Courier New', Courier, monospace;
            font-size: 15px;
            font-weight: bold;
            line-height: 1;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .barcode {
            display: block;
            width: 72mm;
            height: 13mm;
            margin: 1.5mm auto 0;
        }

        .empty {
            box-sizing: border-box;
            width: 80mm;
            height: 50mm;
            padding: 8mm 5mm;
            color: #6b7280;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    @forelse ($stickers->chunk($esA4 ? 10 : 1) as $hoja)
        <div class="hoja">
        @foreach ($hoja->values() as $indice => $sticker)
        <section class="sticker" style="left: {{ $esA4 ? ($indice % 2) * 90 : 0 }}mm; top: {{ $esA4 ? intdiv($indice, 2) * 54 : 0 }}mm;">
            <table class="details" aria-label="Datos del extintor">
                @if (isset($sticker['producto']))
                <tr><td class="label">PRODUCTO</td><td class="value">{{ $sticker['producto'] }}</td></tr>
                @else
                <tr>
                    <td class="label">MARCA</td>
                    <td class="value">{{ $sticker['marca'] }}</td>
                </tr>
                <tr>
                    <td class="label">KILAJE</td>
                    <td class="value">{{ $sticker['kilaje'] }}</td>
                </tr>
                @if ($sticker['agente'] !== '')
                    <tr>
                        <td class="label">AGENTE</td>
                        <td class="value">{{ $sticker['agente'] }}</td>
                    </tr>
                @endif
                @endif
            </table>

            <div class="serie">{{ $sticker['numero_serie'] }}</div>
            <img src="data:image/png;base64,{{ $sticker['barcode_base64'] }}" class="barcode" alt="Código de barras de {{ $sticker['numero_serie'] }}">
        </section>
        @endforeach
        </div>
    @empty
        <div class="empty">
            No hay productos recibidos para generar stickers.
        </div>
    @endforelse
</body>
</html>
