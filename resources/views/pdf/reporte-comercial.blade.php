<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Comercial - BRUCE FIRE S.A.C.</title>
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
                    <div class="subtitle">Reporte Comercial y Desempeño de Ventas</div>
                </td>
                <td class="text-right">
                    <strong>Período:</strong> {{ $fechaDesde }} al {{ $fechaHasta }}<br>
                    <strong>Emisión:</strong> {{ now()->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>
    </div>

    <!-- KPIs Principales -->
    <table class="kpi-grid">
        <tr>
            <td class="kpi-cell" width="25%">
                <div class="kpi-title">Ventas Totales</div>
                <div class="kpi-value">S/ {{ number_format($totalVentas, 2) }}</div>
            </td>
            <td class="kpi-cell" width="25%">
                <div class="kpi-title">Cantidad de Ventas</div>
                <div class="kpi-value">{{ $cantidadVentas }}</div>
            </td>
            <td class="kpi-cell" width="25%">
                <div class="kpi-title">Ticket Promedio</div>
                <div class="kpi-value">S/ {{ number_format($ticketPromedio, 2) }}</div>
            </td>
            <td class="kpi-cell" width="25%">
                <div class="kpi-title">Tasa Conversión Cotizaciones</div>
                <div class="kpi-value">{{ $tasaConversion }}%</div>
            </td>
        </tr>
    </table>

    <!-- Ventas por Vendedor -->
    <h3>Ventas por Vendedor</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Vendedor</th>
                <th class="text-center">N° Operaciones</th>
                <th class="text-right">Total Facturado (S/)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porVendedor as $row)
            <tr>
                <td>{{ $row['nombre'] }}</td>
                <td class="text-center">{{ $row['cantidad'] }}</td>
                <td class="text-right">S/ {{ number_format($row['monto'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="text-center">Sin registros en el período seleccionado.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Top Clientes -->
    <h3>Top Clientes en el Período</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Cliente / Razón Social</th>
                <th class="text-center">N° Compras</th>
                <th class="text-right">Total Acumulado (S/)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topClientes as $c)
            <tr>
                <td>{{ $c['cliente'] }}</td>
                <td class="text-center">{{ $c['cantidad'] }}</td>
                <td class="text-right">S/ {{ number_format($c['total'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="text-center">Sin registros de clientes.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Productos y Servicios más Vendidos -->
    <h3>Top Ítems Vendidos (Productos & Servicios)</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Ítem / Catálogo</th>
                <th class="text-center">Cantidad</th>
                <th class="text-right">Total Ventas (S/)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topItems as $item)
            <tr>
                <td>{{ $item['nombre'] }}</td>
                <td class="text-center">{{ $item['cantidad'] }}</td>
                <td class="text-right">S/ {{ number_format($item['monto'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="text-center">Sin registros de ítems vendidos.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        BRUCE FIRE S.A.C. — Sistema Integrado de Gestión Empresarial — Documento generado confidencial para Gerencia General.
    </div>
</body>
</html>
