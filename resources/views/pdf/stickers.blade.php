<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Stickers de código de barras</title>
    <style>
        /* Hoja A4 con 20 stickers de 5 x 5 cm (4 columnas x 5 filas). */
        @page {
            margin: 2cm 0.5cm 0 0.5cm;
            size: A4 portrait;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #111827;
        }

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        .grid {
            width: 20cm;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .grid td {
            width: 5cm;
            height: 5cm;
            padding: 0;
            text-align: center;
            vertical-align: middle;
        }

        .logo {
            max-height: 1.1cm;
            max-width: 3.6cm;
        }

        .brand {
            font-size: 11px;
            font-weight: bold;
            color: #b91c1c;
        }

        .barcode {
            width: 4.2cm;
            height: 2cm;
            margin-top: 0.25cm;
        }

        .serie {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 0.15cm;
        }
    </style>
</head>
<body>
    @php
        // Los huecos de las posiciones ya usadas van vacíos; luego los stickers.
        $celdas = array_merge(array_fill(0, max(0, $inicio - 1), null), $stickers->all());
        $paginas = array_chunk($celdas, 20);
    @endphp

    @forelse ($paginas as $pagina)
        <div class="page">
            <table class="grid">
                @foreach (array_chunk(array_pad($pagina, 20, null), 4) as $fila)
                    <tr>
                        @foreach ($fila as $sticker)
                            <td>
                                @if ($sticker)
                                    @if ($logoBase64)
                                        <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
                                    @else
                                        <div class="brand">BRUCE FIRE S.A.C.</div>
                                    @endif
                                    <img src="data:image/png;base64,{{ $sticker['barcode_base64'] }}" class="barcode" alt="Código de barras">
                                    <div class="serie">{{ $sticker['numero_serie'] }}</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @empty
        <div style="padding: 2cm; text-align: center; color: #6b7280;">
            <h3>No hay unidades con serie para generar stickers.</h3>
        </div>
    @endforelse
</body>
</html>
