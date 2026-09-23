#!/usr/bin/env python3
"""
Script de entrenamiento para el Modelo Predictivo de Recompra de Clientes (§39.1).
Bruce Fire S.A.C. - Sistema Web de Gestión

Entrena un modelo de clasificación binaria (Regresión Logística) sobre el historial de ventas
pre-corte para predecir si un cliente volverá a comprar en un horizonte de 6 meses.
Exporta pesos, estandarizadores y métricas en formato JSON para inferencia pura en PHP.
"""

import argparse
import datetime
import json
import os
import sys
import numpy as np
import pandas as pd
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    precision_score,
    recall_score,
    f1_score,
    roc_auc_score,
    confusion_matrix,
    classification_report
)
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler


FEATURE_COLUMNS = [
    'recencia_dias',
    'frecuencia_compras',
    'monto_total',
    'ticket_promedio',
    'antiguedad_dias',
    'diversidad_productos',
    'compro_recarga'
]

TARGET_COLUMN = 'target'


def parse_arguments():
    parser = argparse.ArgumentParser(description="Entrena el modelo de recompra de Bruce Fire SAC")
    parser.add_argument(
        "--dataset",
        type=str,
        default="storage/app/ml/retention_training_dataset.csv",
        help="Ruta al archivo CSV con el dataset preprocesado"
    )
    parser.add_argument(
        "--output",
        type=str,
        default="storage/app/ml/retention_model.json",
        help="Ruta donde se guardará el modelo exportado en JSON"
    )
    parser.add_argument(
        "--cutoff",
        type=str,
        default="2026-03-12",
        help="Fecha de corte temporal utilizada para armar las features"
    )
    parser.add_argument(
        "--random-state",
        type=int,
        default=42,
        help="Semilla para reproducibilidad estricta"
    )
    return parser.parse_args()


def main():
    args = parse_arguments()

    if not os.path.exists(args.dataset):
        print(f"[ERROR] Archivo de dataset no encontrado: {args.dataset}", file=sys.stderr)
        print("Ejecuta primero: php artisan ml:export-retention-dataset", file=sys.stderr)
        sys.exit(1)

    print(f"=== ENTRENAMIENTO MODELO DE RECOMPRA - BRUCE FIRE SAC ===")
    print(f"Dataset: {args.dataset}")
    print(f"Fecha de corte: {args.cutoff}")

    df = pd.read_csv(args.dataset)
    print(f"Total registros cargados: {len(df)}")

    # Validar columnas requeridas
    missing_cols = [c for c in FEATURE_COLUMNS + [TARGET_COLUMN] if c not in df.columns]
    if missing_cols:
        print(f"[ERROR] Faltan columnas en el dataset: {missing_cols}", file=sys.stderr)
        sys.exit(1)

    X = df[FEATURE_COLUMNS].copy()
    y = df[TARGET_COLUMN].copy()

    total_samples = len(y)
    positives = int(y.sum())
    negatives = total_samples - positives
    base_rate = positives / total_samples

    print(f"Distribución del target: {positives} positivos ({base_rate:.2%}), {negatives} negativos")

    # 1. Split Train / Test (80% / 20%) estratificado
    X_train, X_test, y_train, y_test = train_test_split(
        X,
        y,
        test_size=0.20,
        random_state=args.random_state,
        stratify=y
    )

    print(f"Muestras de entrenamiento: {len(X_train)} | Muestras de prueba: {len(X_test)}")

    # 2. Estandarización con StandardScaler
    # Guardamos media y desviación estándar de cada feature para reproducir en PHP puro
    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)

    # 3. Entrenamiento con Regresión Logística
    # Balanceamos pesos si es necesario, o usamos C=1.0 estándar
    model = LogisticRegression(
        max_iter=1000,
        random_state=args.random_state,
        solver="lbfgs"
    )
    model.fit(X_train_scaled, y_train)

    # 4. Evaluación en Train y Test
    y_train_pred = model.predict(X_train_scaled)
    y_train_prob = model.predict_proba(X_train_scaled)[:, 1]

    y_test_pred = model.predict(X_test_scaled)
    y_test_prob = model.predict_proba(X_test_scaled)[:, 1]

    train_acc = accuracy_score(y_train, y_train_pred)
    train_prec = precision_score(y_train, y_train_pred, zero_division=0)
    train_rec = recall_score(y_train, y_train_pred, zero_division=0)
    train_f1 = f1_score(y_train, y_train_pred, zero_division=0)
    train_auc = roc_auc_score(y_train, y_train_prob)

    test_acc = accuracy_score(y_test, y_test_pred)
    test_prec = precision_score(y_test, y_test_pred, zero_division=0)
    test_rec = recall_score(y_test, y_test_pred, zero_division=0)
    test_f1 = f1_score(y_test, y_test_pred, zero_division=0)
    test_auc = roc_auc_score(y_test, y_test_prob)

    print("\n--- MÉTRICAS EN CONJUNTO DE PRUEBA (TEST 20%) ---")
    print(f"Accuracy:  {test_acc:.4f}")
    print(f"Precision: {test_prec:.4f}")
    print(f"Recall:    {test_rec:.4f}")
    print(f"F1-Score:  {test_f1:.4f}")
    print(f"ROC-AUC:   {test_auc:.4f}")

    print("\nMatriz de confusión (Test):")
    tn, fp, fn, tp = confusion_matrix(y_test, y_test_pred).ravel()
    print(f"  Verdaderos Negativos (TN): {tn} | Falsos Positivos (FP): {fp}")
    print(f"  Falsos Negativos (FN): {fn}      | Verdaderos Positivos (TP): {tp}")

    # Chequeo estricto de fuga o colapso
    if test_auc > 0.98 or test_acc > 0.98:
        print("\n[ALERTA DE FUGA] Métricas sospechosamente cercanas al 100%. Revisa data leakage.", file=sys.stderr)
    elif test_auc < 0.52:
        print("\n[ALERTA DE DESEMPEÑO] ROC-AUC muy cercano a predicción aleatoria (0.50).", file=sys.stderr)
    else:
        print("\n[OK] Métricas dentro de rangos realistas y defendibles.")

    # 5. Coeficientes y pesos
    coefficients = dict(zip(FEATURE_COLUMNS, [float(c) for c in model.coef_[0]]))
    intercept = float(model.intercept_[0])

    print("\n--- COEFICIENTES DEL MODELO ---")
    for feat, coef in sorted(coefficients.items(), key=lambda x: abs(x[1]), reverse=True):
        print(f"  {feat:22s}: {coef:+.4f}")
    print(f"  Intercepto             : {intercept:+.4f}")

    # 6. Preparar estructura JSON
    model_payload = {
        "model_name": "brucefire_client_retention_logistic_regression",
        "version": "1.0.0",
        "description": "Modelo de regresion logistica para predecir recompra de clientes a 6 meses",
        "cutoff_date": args.cutoff,
        "trained_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
        "random_state": args.random_state,
        "features": FEATURE_COLUMNS,
        "scaler": {
            "mean": {feat: float(scaler.mean_[i]) for i, feat in enumerate(FEATURE_COLUMNS)},
            "std": {feat: float(scaler.scale_[i]) for i, feat in enumerate(FEATURE_COLUMNS)}
        },
        "coefficients": coefficients,
        "intercept": intercept,
        "metrics": {
            "train": {
                "accuracy": round(float(train_acc), 4),
                "precision": round(float(train_prec), 4),
                "recall": round(float(train_rec), 4),
                "f1": round(float(train_f1), 4),
                "roc_auc": round(float(train_auc), 4),
                "samples": len(y_train)
            },
            "test": {
                "accuracy": round(float(test_acc), 4),
                "precision": round(float(test_prec), 4),
                "recall": round(float(test_rec), 4),
                "f1": round(float(test_f1), 4),
                "roc_auc": round(float(test_auc), 4),
                "samples": len(y_test)
            }
        },
        "dataset_summary": {
            "total_samples": total_samples,
            "positives": positives,
            "negatives": negatives,
            "base_rate": round(float(base_rate), 4)
        }
    }

    out_dir = os.path.dirname(args.output)
    if out_dir and not os.path.exists(out_dir):
        os.makedirs(out_dir, exist_ok=True)

    with open(args.output, "w", encoding="utf-8") as f:
        json.dump(model_payload, f, indent=2, ensure_ascii=False)

    print(f"\n[ÉXITO] Modelo exportado a: {args.output}")


if __name__ == "__main__":
    main()
