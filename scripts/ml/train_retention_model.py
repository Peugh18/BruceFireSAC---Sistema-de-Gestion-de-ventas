#!/usr/bin/env python3
"""
Entrenamiento del modelo de recompra de clientes (§39.1) - Bruce Fire S.A.C.

Predice si un cliente vuelve a comprar en los próximos 6 meses. El dataset
(php artisan ml:export-retention-dataset) trae varias fechas de corte: se
entrena con los cortes anteriores y se prueba con el último, así la prueba
siempre es "el futuro" que el modelo no vio.

Compara la regresión logística con XGBoost y se queda con el que acierta más
(ROC-AUC en la prueba). SHAP explica qué variables pesan más. El modelo se
exporta a JSON para calcularlo en PHP puro, sin Python en el servidor: para
XGBoost se exportan los árboles con el valor esperado de cada nodo, con el que
PHP reparte la predicción entre las variables (aproximación de SHAP de Saabas,
la misma de xgboost pred_contribs approx_contribs=True).
"""

import argparse
import datetime
import json
import os
import sys

import numpy as np
import pandas as pd
import shap
import xgboost as xgb
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, f1_score, precision_score, recall_score, roc_auc_score
from sklearn.preprocessing import StandardScaler

FEATURE_COLUMNS = [
    'recencia_dias',
    'frecuencia_compras',
    'monto_total',
    'ticket_promedio',
    'antiguedad_dias',
    'diversidad_productos',
    'compro_recarga',
]
TARGET_COLUMN = 'target'
MEJORA_MINIMA_AUC = 0.01

XGB_PARAMS = {
    'n_estimators': 300,
    'max_depth': 3,
    'learning_rate': 0.05,
    'subsample': 0.9,
    'colsample_bytree': 0.9,
    'min_child_weight': 5,
    'reg_lambda': 1.0,
    'eval_metric': 'auc',
    'tree_method': 'exact',
}


def parse_arguments():
    parser = argparse.ArgumentParser(description='Entrena el modelo de recompra de Bruce Fire SAC')
    parser.add_argument('--dataset', default='storage/app/ml/retention_training_dataset.csv')
    parser.add_argument('--output', default='storage/app/ml/retention_model.json')
    parser.add_argument('--random-state', type=int, default=42)
    parser.add_argument('--modelo', choices=['auto', 'xgboost', 'logistica'], default='auto',
                        help='auto = elige por ROC-AUC en la prueba')
    return parser.parse_args()


def metricas(y_true, prob, umbral=0.5):
    pred = (prob >= umbral).astype(int)
    orden = np.argsort(-prob)
    top = min(100, len(orden))
    return {
        'accuracy': round(float(accuracy_score(y_true, pred)), 4),
        'precision': round(float(precision_score(y_true, pred, zero_division=0)), 4),
        'recall': round(float(recall_score(y_true, pred, zero_division=0)), 4),
        'f1': round(float(f1_score(y_true, pred, zero_division=0)), 4),
        'roc_auc': round(float(roc_auc_score(y_true, prob)), 4),
        'top_100_volvieron': int(np.asarray(y_true)[orden[:top]].sum()),
        'samples': int(len(y_true)),
    }


def entrenar_logistica(X_train, y_train, random_state):
    scaler = StandardScaler().fit(X_train)
    modelo = LogisticRegression(max_iter=1000, random_state=random_state, solver='lbfgs')
    modelo.fit(scaler.transform(X_train), y_train)
    return modelo, scaler


def entrenar_xgboost(X_train, y_train, random_state):
    modelo = xgb.XGBClassifier(**XGB_PARAMS, random_state=random_state)
    modelo.fit(X_train, y_train)
    return modelo


def exportar_arboles(booster):
    """Árboles planos con el valor esperado (ponderado por cobertura) de cada nodo."""
    arboles = []
    for texto in booster.get_dump(dump_format='json', with_stats=True):
        raiz = json.loads(texto)
        nodos = {}

        def visitar(nodo):
            nid = nodo['nodeid']
            if 'leaf' in nodo:
                nodos[nid] = {'leaf': float(nodo['leaf']), 'ev': float(nodo['leaf']), 'cover': float(nodo['cover'])}
                return float(nodo['leaf']), float(nodo['cover'])
            suma, cobertura = 0.0, 0.0
            for hijo in nodo['children']:
                valor, cob = visitar(hijo)
                suma += valor * cob
                cobertura += cob
            ev = suma / cobertura if cobertura > 0 else 0.0
            nodos[nid] = {
                'f': nodo['split'],
                't': float(nodo['split_condition']),
                'yes': nodo['yes'],
                'no': nodo['no'],
                'missing': nodo['missing'],
                'ev': ev,
                'cover': cobertura,
            }
            return ev, cobertura

        visitar(raiz)
        arboles.append({str(k): v for k, v in nodos.items()})
    return arboles


def contribuciones_saabas(arboles, base_margin, fila):
    """Referencia en Python del cálculo que hace RetentionModel.php."""
    contrib = {f: 0.0 for f in FEATURE_COLUMNS}
    for arbol in arboles:
        nodo = arbol['0']
        while 'leaf' not in nodo:
            valor = np.float32(fila[nodo['f']])
            siguiente = arbol[str(nodo['yes'] if valor < np.float32(nodo['t']) else nodo['no'])]
            contrib[nodo['f']] += siguiente['ev'] - nodo['ev']
            nodo = siguiente
    return sum(contrib.values()) + base_margin + sum(a['0']['ev'] for a in arboles), contrib


def main():
    args = parse_arguments()

    if not os.path.exists(args.dataset):
        print(f'[ERROR] No existe el dataset: {args.dataset}. Ejecuta: php artisan ml:export-retention-dataset', file=sys.stderr)
        sys.exit(1)

    df = pd.read_csv(args.dataset)
    faltan = [c for c in FEATURE_COLUMNS + [TARGET_COLUMN, 'corte'] if c not in df.columns]
    if faltan:
        print(f'[ERROR] Faltan columnas en el dataset: {faltan}', file=sys.stderr)
        sys.exit(1)

    cortes = sorted(df['corte'].unique())
    if len(cortes) < 2:
        print('[ERROR] Se necesitan al menos dos fechas de corte (entrenar con las anteriores, probar con la última).', file=sys.stderr)
        sys.exit(1)

    train = df[df['corte'] != cortes[-1]]
    test = df[df['corte'] == cortes[-1]]
    X_train, y_train = train[FEATURE_COLUMNS], train[TARGET_COLUMN]
    X_test, y_test = test[FEATURE_COLUMNS], test[TARGET_COLUMN]

    print('=== ENTRENAMIENTO MODELO DE RECOMPRA - BRUCE FIRE SAC ===')
    print(f'Entrenamiento: cortes {cortes[:-1]} ({len(train)} filas) | Prueba: corte {cortes[-1]} ({len(test)} filas)')
    print(f'Tasa de recompra: entrenamiento {y_train.mean():.1%} | prueba {y_test.mean():.1%}')

    logistica, scaler = entrenar_logistica(X_train, y_train, args.random_state)
    prob_log = logistica.predict_proba(scaler.transform(X_test))[:, 1]
    m_log = metricas(y_test, prob_log)

    xgbm = entrenar_xgboost(X_train, y_train, args.random_state)
    prob_xgb = xgbm.predict_proba(X_test)[:, 1]
    m_xgb = metricas(y_test, prob_xgb)

    print('\n--- PRUEBA (último corte, datos que el modelo no vio) ---')
    print(f"{'':22s}{'Regresión logística':>22s}{'XGBoost':>12s}")
    for clave in ['roc_auc', 'accuracy', 'precision', 'recall', 'f1', 'top_100_volvieron']:
        print(f'{clave:22s}{m_log[clave]:>22}{m_xgb[clave]:>12}')

    # XGBoost es más complejo: solo reemplaza a la logística si acierta claramente más.
    usar_xgb = m_xgb['roc_auc'] >= m_log['roc_auc'] + MEJORA_MINIMA_AUC
    if args.modelo != 'auto':
        usar_xgb = args.modelo == 'xgboost'
    print(f"\nModelo elegido: {'XGBoost' if usar_xgb else 'Regresión logística'} (XGBoost debe superar a la logística por {MEJORA_MINIMA_AUC} de ROC-AUC)")

    # Importancia global con SHAP del modelo elegido, sobre la prueba.
    if usar_xgb:
        shap_valores = shap.TreeExplainer(xgbm).shap_values(X_test)
    else:
        shap_valores = shap.LinearExplainer(logistica, scaler.transform(X_train)).shap_values(scaler.transform(X_test))
    importancia = {f: round(float(v), 4) for f, v in zip(FEATURE_COLUMNS, np.abs(shap_valores).mean(axis=0))}
    print('\n--- IMPORTANCIA SHAP (promedio |SHAP| en la prueba) ---')
    for f, v in sorted(importancia.items(), key=lambda x: -x[1]):
        print(f'  {f:22s}{v:.4f}')

    # Modelo final: se reentrena con todos los cortes (más datos), mismos parámetros.
    X_all, y_all = df[FEATURE_COLUMNS], df[TARGET_COLUMN]
    base = {
        'version': '2.0.0',
        'horizonte_meses': 6,
        'cortes': [str(c) for c in cortes],
        'trained_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
        'random_state': args.random_state,
        'features': FEATURE_COLUMNS,
        'metrics': {
            'test': m_xgb if usar_xgb else m_log,
            'comparacion': {'regresion_logistica': m_log, 'xgboost': m_xgb},
            'importancia_shap': importancia,
        },
        'dataset_summary': {
            'total_samples': int(len(df)),
            'positives': int(y_all.sum()),
            'negatives': int(len(df) - y_all.sum()),
            'base_rate': round(float(y_all.mean()), 4),
            'clientes': int(df['documento'].nunique()),
        },
    }

    if usar_xgb:
        final = entrenar_xgboost(X_all, y_all, args.random_state)
        booster = final.get_booster()
        arboles = exportar_arboles(booster)

        # Margen base: el término de sesgo de xgboost menos el valor esperado de las raíces.
        dmat = xgb.DMatrix(X_all.head(1))
        sesgo = float(booster.predict(dmat, pred_contribs=True, approx_contribs=True)[0][-1])
        base_margin = sesgo - sum(a['0']['ev'] for a in arboles)

        # Verificación: el cálculo que hará PHP debe dar lo mismo que xgboost.
        margenes = booster.predict(xgb.DMatrix(X_all), output_margin=True)
        muestra = X_all.sample(min(300, len(X_all)), random_state=args.random_state)
        diferencia = max(abs(contribuciones_saabas(arboles, base_margin, fila)[0] - margenes[i]) for i, fila in muestra.iterrows())
        print(f'\nVerificación del cálculo para PHP: diferencia máxima {diferencia:.2e}')
        if diferencia > 1e-3:
            print('[ERROR] La exportación de los árboles no reproduce a xgboost.', file=sys.stderr)
            sys.exit(1)

        payload = {
            **base,
            'type': 'xgboost',
            'model_name': 'brucefire_client_retention_xgboost',
            'description': 'XGBoost para predecir recompra de clientes a 6 meses (histórico 2025-2026 + ventas del sistema)',
            'base_margin': base_margin,
            'trees': arboles,
        }
    else:
        final, scaler = entrenar_logistica(X_all, y_all, args.random_state)
        payload = {
            **base,
            'type': 'logistic',
            'model_name': 'brucefire_client_retention_logistic_regression',
            'description': 'Regresión logística para predecir recompra de clientes a 6 meses',
            'scaler': {
                'mean': {f: float(scaler.mean_[i]) for i, f in enumerate(FEATURE_COLUMNS)},
                'std': {f: float(scaler.scale_[i]) for i, f in enumerate(FEATURE_COLUMNS)},
            },
            'coefficients': dict(zip(FEATURE_COLUMNS, [float(c) for c in final.coef_[0]])),
            'intercept': float(final.intercept_[0]),
        }

    os.makedirs(os.path.dirname(args.output) or '.', exist_ok=True)
    with open(args.output, 'w', encoding='utf-8') as f:
        json.dump(payload, f, ensure_ascii=False)

    print(f'\n[ÉXITO] Modelo exportado a: {args.output}')


if __name__ == '__main__':
    main()
