<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte del mes - BRUCE FIRE S.A.C.</title>
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
    @php
        $formatear = function ($valor, string $formato): string {
            if ($valor === null) {
                return 'Sin datos';
            }

            return match ($formato) {
                'moneda' => 'S/ '.number_format((float) $valor, 2),
                'porcentaje' => $valor.' %',
                default => $valor.' días',
            };
        };
    @endphp

    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="title">BRUCE FIRE S.A.C.</div>
                    <div class="subtitle">Reporte del mes · {{ $periodo }} · {{ $sedeNombre }}</div>
                </td>
                <td class="text-right">
                    <strong>Período:</strong> {{ \Illuminate\Support\Carbon::parse($fechaDesde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($fechaHasta)->format('d/m/Y') }}<br>
                    <strong>Emisión:</strong> {{ now()->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>
    </div>

    <h3>Métricas del mes</h3>
    <table class="data-table">
        <tr>
            <th>Métrica</th>
            <th class="text-right">Este mes</th>
            <th class="text-right">Mes anterior</th>
            <th class="text-right">Variación</th>
        </tr>
        @foreach ($metricas as $metrica)
            <tr>
                <td>{{ $metrica['etiqueta'] }}</td>
                <td class="text-right">{{ $formatear($metrica['valor'], $metrica['formato']) }}</td>
                <td class="text-right">{{ $formatear($metrica['anterior'], $metrica['formato']) }}</td>
                <td class="text-right">{{ $metrica['variacion'] === null ? '—' : $metrica['variacion'].' %' }}</td>
            </tr>
        @endforeach
    </table>

    <h3>Ventas por vendedor</h3>
    <table class="data-table">
        <tr><th>Vendedor</th><th class="text-right">Monto</th></tr>
        @forelse ($ventasPorVendedor as $fila)
            <tr><td>{{ $fila['vendedor'] }}</td><td class="text-right">S/ {{ number_format($fila['monto'], 2) }}</td></tr>
        @empty
            <tr><td colspan="2">Sin ventas en el mes.</td></tr>
        @endforelse
    </table>

    <h3>Servicios más vendidos</h3>
    <table class="data-table">
        <tr><th>Servicio</th><th class="text-right">Cantidad</th><th class="text-right">Monto</th></tr>
        @forelse ($serviciosMasVendidos as $fila)
            <tr><td>{{ $fila['servicio'] }}</td><td class="text-right">{{ $fila['cantidad'] }}</td><td class="text-right">S/ {{ number_format($fila['monto'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3">Sin servicios vendidos en el mes.</td></tr>
        @endforelse
    </table>

    <h3>Cartera vencida (S/ {{ number_format($metrics['vencidoPorCobrar'], 2) }})</h3>
    <table class="data-table">
        <tr><th>Cliente</th><th>Debe desde</th><th class="text-right">Saldo</th></tr>
        @forelse ($clientesConDeuda as $fila)
            <tr><td>{{ $fila['cliente'] }}</td><td>{{ $fila['fecha_mas_antigua'] }}</td><td class="text-right">S/ {{ number_format($fila['saldo'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3">Nadie tiene deuda vencida.</td></tr>
        @endforelse
    </table>

    <h3>Recompra: extintores que vencen pronto</h3>
    <table class="data-table">
        <tr><th>Vencen en</th><th class="text-right">Extintores</th><th class="text-right">Clientes</th><th class="text-right">Monto estimado</th></tr>
        @foreach ($recompra as $tramo)
            <tr><td>{{ $tramo['dias'] }} días</td><td class="text-right">{{ $tramo['cantidad'] }}</td><td class="text-right">{{ $tramo['clientes'] }}</td><td class="text-right">S/ {{ number_format($tramo['monto_estimado'], 2) }}</td></tr>
        @endforeach
    </table>

    <h3>IA de recompra: clientes a llamar primero</h3>
    <p style="font-size: 9px; color: #6B6965; margin: 0 0 6px;">Situación actual de toda la empresa (no del período){{ $iaRecompra['calculado'] ? ', calculada el '.\Illuminate\Support\Carbon::parse($iaRecompra['calculado'])->format('d/m/Y') : '' }}.</p>
    <table class="data-table">
        <tr><th>Cliente</th><th>Por qué</th><th class="text-right">Probabilidad</th></tr>
        @forelse ($iaRecompra['clientes'] as $fila)
            <tr><td>{{ $fila['cliente'] }}</td><td>{{ $fila['resumen'] }}</td><td class="text-right">{{ $fila['porcentaje'] }} %</td></tr>
        @empty
            <tr><td colspan="3">La IA todavía no tiene clientes con probabilidad alta o media.</td></tr>
        @endforelse
    </table>

    <div style="page-break-inside: avoid;">
    <h3>Inventario valorizado</h3>
    <table class="data-table">
        <tr>
            <td>Valor del inventario al costo</td><td class="text-right">S/ {{ number_format($reporteInventario['valorizacionTotal'], 2) }}</td>
        </tr>
        <tr>
            <td>Productos / unidades</td><td class="text-right">{{ $reporteInventario['totalProductos'] }} / {{ $reporteInventario['totalUnidades'] }}</td>
        </tr>
        <tr>
            <td>Productos bajo el mínimo</td><td class="text-right">{{ $reporteInventario['totalBajoMinimo'] }}</td>
        </tr>
        <tr>
            <td>Productos sin costo registrado</td><td class="text-right">{{ $reporteInventario['productosSinCosto'] }}</td>
        </tr>
    </table>
    </div>

    <div class="footer">Generado por el sistema de BRUCE FIRE S.A.C. Las ventas netas descuentan notas de crédito y suman notas de débito aceptadas.</div>
</body>
</html>
