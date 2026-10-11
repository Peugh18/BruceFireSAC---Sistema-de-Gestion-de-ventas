import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    pasosEntrega,
    pasosOrdenAtencion,
    pasosTaller,
} from '../../resources/js/components/progreso/orden-atencion.ts';
import { pasosCotizacion } from '../../resources/js/components/progreso/cotizacion.ts';

void test('una orden autorizada sin técnico no acredita asignación', () => {
    const pasos = pasosOrdenAtencion('autorizado', false);
    assert.equal(pasos[0].label, 'Por asignar');
    assert.equal(pasos[0].estado, 'pendiente');
    assert.equal(pasos[1].estado, 'actual');
});

void test('recepción con técnico conserva el trabajo pendiente', () => {
    const pasos = pasosOrdenAtencion('recibido_planta', true);
    assert.equal(pasos[0].label, 'Asignada');
    assert.equal(pasos[0].estado, 'actual');
    assert.equal(pasos[1].estado, 'pendiente');
});

void test('lista para entrega no acredita cierre ni trabajo pendiente', () => {
    const pasos = pasosOrdenAtencion('listo_entrega', true);
    assert.equal(pasos[1].estado, 'completado');
    assert.equal(pasos[2].estado, 'actual');
    assert.equal(pasos[3].estado, 'pendiente');
});

void test('cerrada acredita los hitos pero no inventa un técnico', () => {
    const pasos = pasosOrdenAtencion('cerrado', false);
    assert.equal(pasos[0].estado, 'pendiente');
    assert.ok(pasos.slice(1).every((paso) => paso.estado === 'completado'));
});

void test('anulada o desconocida no muestra una secuencia exitosa', () => {
    assert.deepEqual(pasosOrdenAtencion('anulada', true), []);
    assert.deepEqual(pasosOrdenAtencion('estado_desconocido', false), []);
});

void test('el taller solo acredita certificado cuando existe el documento', () => {
    assert.equal(pasosTaller('listo_entrega', false)[3].estado, 'pendiente');
    assert.equal(pasosTaller('listo_entrega', true)[3].estado, 'completado');
    assert.deepEqual(pasosTaller('anulada', true), []);
});

void test('una cotización enviada marca la etapa actual y deja pendiente lo demás', () => {
    const pasos = pasosCotizacion('enviada');
    assert.deepEqual(
        pasos.map((paso) => paso.estado),
        ['completado', 'actual', 'pendiente', 'pendiente'],
    );
});

void test('una cotización vendida completa todos los hitos', () => {
    assert.ok(
        pasosCotizacion('convertida').every(
            (paso) => paso.estado === 'completado',
        ),
    );
});

void test('rechazada, vencida o anulada no simulan una secuencia', () => {
    for (const estado of ['rechazada', 'vencida', 'anulada']) {
        assert.deepEqual(pasosCotizacion(estado), []);
    }
});

void test('la entrega marca por entregar, entregada y cerrada', () => {
    assert.deepEqual(
        pasosEntrega('entregado').map((p) => p.estado),
        ['completado', 'actual', 'pendiente'],
    );
    assert.deepEqual(
        pasosEntrega('cerrado').map((p) => p.estado),
        ['completado', 'completado', 'completado'],
    );
    assert.deepEqual(pasosEntrega('anulada'), []);
});
