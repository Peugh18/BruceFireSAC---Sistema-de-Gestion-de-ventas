#!/usr/bin/env python3
"""
ETL del histórico de ventas 2025-2026 para la predicción de recompra.

Lee los Excel mensuales exportados del sistema anterior y deja un CSV limpio
con SOLO facturas y boletas (compras reales), una fila por línea vendida:
sin cotizaciones (PR), sin notas de crédito (NC) y sin clientes que no se
pueden identificar (CLIENTE VARIOS, códigos de colegio, documentos
incompletos). El CSV se carga con `php artisan ml:importar-historico`.

Uso:
    python scripts/ml/etl_historico.py "D:/.../Datos historicos" salida.csv
"""

import argparse
import glob
import os
import re
import sys

import pandas as pd

# Comprobantes que el negocio confirmó como errores de registro: no se usan.
COMPROBANTES_CON_ERROR = {
    'F001-10323': '851 extintores a INVERSIONES FREKA S.A.C. (S/ 72,335), confirmado como error el 28/09/2026',
}

COLUMNAS = ['fecha', 'tipo_doc', 'comprobante', 'documento_cliente', 'nombre_cliente',
            'categoria', 'producto_original', 'cantidad', 'total', 'archivo_origen']


def categoria(producto: str) -> str:
    """Categoría limpia del producto; el nombre original no es confiable."""
    nombre = producto.upper()
    if 'HIDROST' in nombre:
        return 'prueba_hidrostatica'
    if 'RECARGA' in nombre:
        return 'recarga_mantenimiento'
    if 'MANTENIMIENTO' in nombre:
        return 'mantenimiento'
    if 'EXTINTOR' in nombre:
        return 'extintor'
    if any(p in nombre for p in ('INSTALACION', 'INSTALACIÓN', 'CAPACITACION', 'CAPACITACIÓN', 'SERVICIO', 'SEÑALIZACION', 'SEÑALIZACIÓN')):
        return 'otros_servicios'
    return 'seguridad'


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('carpeta')
    parser.add_argument('salida')
    args = parser.parse_args()

    archivos = sorted(glob.glob(os.path.join(args.carpeta, '*.xlsx'))) + sorted(glob.glob(os.path.join(args.carpeta, '*', '*.xlsx')))
    if not archivos:
        print('No se encontraron Excel en', args.carpeta)
        return 1

    partes = []
    for archivo in archivos:
        hoja = pd.read_excel(archivo, sheet_name='data', dtype={'Serie': str, 'NroDocumento': str, 'Ruc_cliente': str})
        hoja['archivo_origen'] = os.path.relpath(archivo, args.carpeta).replace('\\', '/')
        partes.append(hoja)
    datos = pd.concat(partes, ignore_index=True)
    leidas = len(datos)

    ventas = datos[datos['Doc'].isin(['F', 'B'])].copy()
    ventas['documento_cliente'] = ventas['Ruc_cliente'].fillna('').str.strip()
    identificado = ventas['documento_cliente'].str.fullmatch(r'\d{11}|\d{8}')
    sin_cliente = int((~identificado).sum())
    ventas = ventas[identificado]

    salida = pd.DataFrame({
        'fecha': pd.to_datetime(ventas['fecha_emision'], format='%d/%m/%Y').dt.strftime('%Y-%m-%d'),
        'tipo_doc': ventas['Doc'],
        'comprobante': ventas['Serie'].str.strip() + '-' + ventas['NroDocumento'].str.strip(),
        'documento_cliente': ventas['documento_cliente'],
        'nombre_cliente': ventas['cliente'].fillna('').astype(str).str.strip(),
        'categoria': ventas['Producto'].fillna('').astype(str).map(categoria),
        'producto_original': ventas['Producto'].fillna('').astype(str).str.strip(),
        'cantidad': ventas['Cantidad'],
        'total': ventas['Total'].round(2),
        'archivo_origen': ventas['archivo_origen'],
    })[COLUMNAS]

    con_error = salida['comprobante'].isin(COMPROBANTES_CON_ERROR.keys())
    salida = salida[~con_error]

    salida.to_csv(args.salida, index=False, encoding='utf-8')

    print(f'Líneas leídas: {leidas}')
    print(f'Descartadas: {int((~datos["Doc"].isin(["F", "B"])).sum())} de cotizaciones y notas de crédito, {sin_cliente} sin cliente identificable, {int(con_error.sum())} de comprobantes con error')
    print(f'Líneas de facturas y boletas: {len(salida)} | comprobantes: {salida["comprobante"].nunique()} | clientes: {salida["documento_cliente"].nunique()}')
    print(f'Periodo: {salida["fecha"].min()} a {salida["fecha"].max()} | total vendido: S/ {salida["total"].sum():,.2f}')
    print(salida['categoria'].value_counts().to_string())
    return 0


if __name__ == '__main__':
    sys.exit(main())
