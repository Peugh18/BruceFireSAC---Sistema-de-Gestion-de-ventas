<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario - BRUCE FIRE S.A.C.</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #201F1D;
            line-height: 1.3;
            margin: 20px;
        }
        .header {
            border-bottom: 2px solid #E31E24;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header table {
            width: 100%;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #201F1D;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 10px;
            color: #6B6965;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kpi-grid {
            width: 100%;
            margin-bottom: 20px;
        }
        .kpi-cell {
            background-color: #F8F9FA;
            border: 1px solid #E7E4DE;
            padding: 10px;
            border-radius: 4px;
        }
        .kpi-title {
            font-size: 9px;
            color: #6B6965;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 14px;
            font-weight: bold;
            color: #201F1D;
        }
        h3 {
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 1px solid #E7E4DE;
            padding-bottom: 4px;
            margin-top: 15px;
            margin-bottom: 8px;
            color: #201F1D;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.data-table th {
            background-color: #F0EEEA;
            border: 1px solid #E7E4DE;
            padding: 6px 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            color: #4A4742;
        }
        table.data-table td {
            border: 1px solid #E7E4DE;
            padding: 5px 8px;
            font-size: 10px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-danger {
            background-color: #FEE2E2;
            color: #991B1B;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 8px;
        }
        .badge-ok {
            background-color: #D1FAE5;
            color: #065F46;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 8px;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #E7E4DE;
            padding-top: 8px;
            font-size: 8px;
            color: #8A8680;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="title">BRUCE FIRE S.A.C.</div>
                    <div class="subtitle">Reporte Consolidado de Inventario & Valorización</div>
                </td>
                <td class="text-right">
                    <strong>Sede:</strong> {{ $sedeNombre }}<br>
                    <strong>Fecha Emisión:</strong> {{ now()->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>
    </div>

    <!-- KPIs de Inventario -->
    <table class="kpi-grid">
        <tr>
            <td class="kpi-cell" width="25%">
                <div class="kpi-title">Valorización Estimada</div>
                <div class="kpi-value">S/ {{ number_format($valorizacionTotal, 2) }}</div>
            </td>
            <td class="kpi-cell" width="25%">
                <div class="kpi-title">Total Productos Activos</div>
                <div class="kpi-value">{{ $totalProductos }}</div>
            </td>
            <td class="kpi-cell" width="25%">
                <div class="kpi-title">Unidades Físicas Disponibles</div>
                <div class="kpi-value">{{ $totalUnidades }}</div>
            </td>
            <td class="kpi-cell" width="25%">
                <div class="kpi-title">Ítems Bajo Mínimo</div>
                <div class="kpi-value" style="color: #E31E24;">{{ $totalBajoMinimo }}</div>
            </td>
        </tr>
    </table>

    <!-- Tabla de Existencias -->
    <h3>Estado de Existencias por Producto</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th class="text-center">Tipo</th>
                <th class="text-center">U.M.</th>
                <th class="text-right">Precio Venta</th>
                <th class="text-center">Stock Mínimo</th>
                <th class="text-center">Stock Actual</th>
                <th class="text-right">Subtotal Val. (S/)</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($productos as $p)
            <tr>
                <td><strong>{{ $p['codigo'] }}</strong></td>
                <td>{{ $p['nombre'] }}</td>
                <td class="text-center">{{ $p['serializado'] ? 'Serializado' : 'A Granel' }}</td>
                <td class="text-center">{{ $p['unidad_medida'] }}</td>
                <td class="text-right">S/ {{ number_format($p['precio_venta'], 2) }}</td>
                <td class="text-center">{{ $p['stock_minimo'] ?? '—' }}</td>
                <td class="text-center"><strong>{{ $p['stock_disponible'] }}</strong></td>
                <td class="text-right">S/ {{ number_format($p['valorizacion'], 2) }}</td>
                <td class="text-center">
                    @if($p['bajo_minimo'])
                        <span class="badge-danger">BAJO MÍNIMO</span>
                    @else
                        <span class="badge-ok">NORMAL</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No hay productos que coincidan con los filtros.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        BRUCE FIRE S.A.C. — Sistema Integrado de Gestión Empresarial — Documento de control de existencias para Gerencia y Almacén.
    </div>
</body>
</html>
