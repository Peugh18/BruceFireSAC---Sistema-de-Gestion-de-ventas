<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Stickers - Recepción {{ $reception->id }}</title>
    <style>
        @page {
            margin: 1.2cm 1.0cm;
            size: A4 portrait;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #1a1a1a;
            font-size: 11px;
            line-height: 1.2;
        }

        .page {
            page-break-after: always;
            height: 100%;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        .grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0.8cm 1.0cm;
        }

        .sticker-cell {
            width: 50%;
            vertical-align: top;
        }

        .sticker-box {
            border: 1px dashed #9ca3af;
            border-radius: 6px;
            padding: 8px 10px;
            height: 5.6cm;
            box-sizing: border-box;
            background-color: #ffffff;
            position: relative;
        }

        .sticker-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-bottom: 6px;
            display: table;
            width: 100%;
        }

        .header-logo {
            display: table-cell;
            vertical-align: middle;
            text-align: left;
        }

        .header-brand {
            font-size: 10px;
            font-weight: bold;
            color: #b91c1c;
            letter-spacing: 0.5px;
        }

        .header-sede {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 8.5px;
            color: #6b7280;
        }

        .barcode-section {
            text-align: center;
            margin: 6px 0 4px 0;
        }

        .barcode-img {
            max-width: 85%;
            height: 38px;
            display: block;
            margin: 0 auto;
        }

        .serial-text {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1.5px;
            color: #111827;
            margin-top: 3px;
        }

        .product-info {
            margin-top: 6px;
            text-align: center;
        }

        .product-name {
            font-size: 10.5px;
            font-weight: 600;
            color: #1f2937;
            line-height: 1.25;
            max-height: 2.5em;
            overflow: hidden;
        }

        .product-details {
            margin-top: 5px;
            font-size: 8.5px;
            color: #4b5563;
            border-top: 1px dotted #d1d5db;
            padding-top: 3px;
        }

        .detail-badge {
            display: inline-block;
            margin: 0 4px;
        }

        .detail-label {
            color: #9ca3af;
            text-transform: uppercase;
        }

        .detail-value {
            font-weight: 600;
            color: #374151;
        }

        .empty-cell {
            visibility: hidden;
        }
    </style>
</head>
<body>
    @php
        // Agrupar los stickers en páginas de 4 (grilla 2x2)
        $stickerChunks = $stickers->chunk(4);
    @endphp

    @forelse ($stickerChunks as $chunkIndex => $pageStickers)
        <div class="page">
            <table class="grid">
                @php
                    $rows = $pageStickers->chunk(2);
                @endphp
                @foreach ($rows as $rowIndex => $rowStickers)
                    <tr>
                        @foreach ($rowStickers as $sticker)
                            <td class="sticker-cell">
                                <div class="sticker-box">
                                    <div class="sticker-header">
                                        <div class="header-logo">
                                            @if ($logoBase64)
                                                <img src="{{ $logoBase64 }}" style="height: 16px; vertical-align: middle;">
                                            @else
                                                <span class="header-brand">BRUCE FIRE S.A.C.</span>
                                            @endif
                                        </div>
                                        <div class="header-sede">
                                            {{ $reception->sedeAlmacen->nombre ?? 'ALMACÉN' }}
                                        </div>
                                    </div>

                                    <div class="barcode-section">
                                        <img src="data:image/png;base64,{{ $sticker['barcode_base64'] }}" class="barcode-img" alt="Barcode">
                                        <div class="serial-text">{{ $sticker['numero_serie'] }}</div>
                                    </div>

                                    <div class="product-info">
                                        <div class="product-name">
                                            {{ Str::limit($sticker['producto'], 50) }}
                                        </div>
                                        <div class="product-details">
                                            @if ($sticker['marca'])
                                                <span class="detail-badge">
                                                    <span class="detail-label">Marca:</span>
                                                    <span class="detail-value">{{ $sticker['marca'] }}</span>
                                                </span>
                                            @endif
                                            @if ($sticker['anio_fabricacion'])
                                                <span class="detail-badge">
                                                    <span class="detail-label">Año:</span>
                                                    <span class="detail-value">{{ $sticker['anio_fabricacion'] }}</span>
                                                </span>
                                            @endif
                                            <span class="detail-badge">
                                                <span class="detail-label">Recep:</span>
                                                <span class="detail-value">#{{ $reception->id }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        @endforeach

                        @if ($rowStickers->count() < 2)
                            <td class="sticker-cell empty-cell">
                                <div class="sticker-box"></div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @empty
        <div style="padding: 2cm; text-align: center; color: #6b7280;">
            <h3>Esta recepción no contiene unidades serializadas para generar stickers.</h3>
        </div>
    @endforelse
</body>
</html>
