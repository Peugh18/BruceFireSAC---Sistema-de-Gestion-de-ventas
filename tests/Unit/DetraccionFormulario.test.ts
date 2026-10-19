import assert from 'node:assert/strict';
import test from 'node:test';
import { resumenDetraccion } from '../../resources/js/lib/detraccion.ts';

const reglas = {
    monto_minimo: 700,
    porcentaje: 12,
    cuenta_bn: '00-123-456789',
    servicios: [
        {
            id: 1,
            codigo: '022',
            nombre: '022 - Otros servicios empresariales',
            afectacion: '10',
        },
    ],
};

void test('el aviso se activa desde 700.01 y distingue el limite exacto', () => {
    const items = [{ service_id: 1 }];
    assert.equal(
        resumenDetraccion('factura', 700, items, reglas).estado,
        'bajo_minimo',
    );
    assert.equal(resumenDetraccion('factura', 699.99, items, reglas).monto, 0);
    assert.equal(resumenDetraccion('factura', 700.01, items, reglas).monto, 84);
});

void test('informa el reparto del pago sin reducir el total fiscal', () => {
    const resultado = resumenDetraccion(
        'factura',
        1180,
        [{ service_id: 1 }],
        reglas,
    );
    assert.equal(resultado.estado, 'aplica');
    assert.equal(resultado.monto, 141.6);
    assert.equal(resultado.neto, 1038.4);
    assert.equal(resultado.nombre, '022 - Otros servicios empresariales');
});

void test('no presume detraccion en boletas, bienes sueltos, exonerados o clasificacion pendiente', () => {
    assert.equal(
        resumenDetraccion('boleta', 1180, [{ service_id: 1 }], reglas).estado,
        'no_aplica',
    );
    assert.equal(
        resumenDetraccion('factura', 1180, [{ product_id: 2 }], reglas).estado,
        'no_aplica',
    );
    assert.equal(
        resumenDetraccion('factura', 1180, [{ service_id: 1 }], {
            ...reglas,
            servicios: [{ ...reglas.servicios[0], afectacion: '20' }],
        }).estado,
        'no_aplica',
    );
    assert.equal(
        resumenDetraccion('factura', 1180, [{ service_id: 1 }], {
            ...reglas,
            servicios: [{ ...reglas.servicios[0], codigo: null }],
        }).estado,
        'revisar',
    );
});

void test('advierte clasificaciones distintas y ausencia de cuenta bn', () => {
    assert.equal(
        resumenDetraccion('factura', 1180, [{ service_id: 1 }], {
            ...reglas,
            cuenta_bn: '',
        }).estado,
        'sin_cuenta',
    );
    assert.equal(
        resumenDetraccion(
            'factura',
            1180,
            [{ service_id: 1 }, { service_id: 2 }],
            {
                ...reglas,
                servicios: [
                    ...reglas.servicios,
                    { ...reglas.servicios[0], id: 2, codigo: '020' },
                ],
            },
        ).estado,
        'revisar',
    );
});

void test('usa el porcentaje del codigo y avisa si falta revisar la clasificacion', () => {
    const alquiler = {
        ...reglas,
        servicios: [
            {
                id: 2,
                codigo: '019',
                nombre: '019 - Arrendamiento',
                afectacion: '10',
                porcentaje: 10,
                estado: 'sujeto',
            },
            {
                id: 3,
                codigo: null,
                nombre: '',
                afectacion: '10',
                porcentaje: null,
                estado: 'pendiente',
            },
        ],
    };
    assert.equal(
        resumenDetraccion('factura', 1000, [{ service_id: 2 }], alquiler).monto,
        100,
    );
    assert.equal(
        resumenDetraccion('factura', 1000, [{ service_id: 3 }], alquiler)
            .estado,
        'revisar',
    );
});
