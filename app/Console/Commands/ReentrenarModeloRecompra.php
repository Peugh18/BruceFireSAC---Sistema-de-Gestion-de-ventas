<?php

namespace App\Console\Commands;

use App\Models\MlEntrenamiento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('ml:reentrenar
    {--python= : Ejecutable de Python (por defecto ML_PYTHON o "python")}
    {--cortes= : Fechas de corte YYYY-MM-DD separadas por coma (por defecto se calculan con la última venta)}')]
#[Description('Reentrena el modelo de recompra con los datos al día: exporta el dataset, entrena en Python, guarda la precisión en el historial y recalcula a los clientes')]
class ReentrenarModeloRecompra extends Command
{
    public function handle(): int
    {
        $python = (string) ($this->option('python') ?: config('services.ml.python', 'python'));
        $modeloPath = storage_path('app/ml/retention_model.json');

        $this->info('1/4 Exportando el dataset (histórico + ventas del sistema)...');
        $opciones = $this->option('cortes') ? ['--cortes' => (string) $this->option('cortes')] : [];
        if ($this->call('ml:export-retention-dataset', $opciones) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('2/4 Entrenando en Python...');
        $entrenamiento = Process::path(base_path())->timeout(1800)->run([$python, 'scripts/ml/train_retention_model.py']);
        $this->line($entrenamiento->output());

        if ($entrenamiento->failed()) {
            $this->error('No se pudo entrenar: '.trim($entrenamiento->errorOutput() ?: 'revisa que Python, xgboost y shap estén instalados.'));
            $this->line('El modelo anterior se mantiene sin cambios.');

            return self::FAILURE;
        }

        $this->info('3/4 Guardando la precisión en el historial...');
        $modelo = json_decode((string) File::get($modeloPath), true);
        if (! is_array($modelo)) {
            $this->error('El modelo exportado no es un JSON válido.');

            return self::FAILURE;
        }
        $registro = MlEntrenamiento::registrar($modelo);
        $this->line(sprintf('Modelo %s · ROC-AUC %.3f · de los 100 con más probabilidad volvieron %s', $registro->modelo, (float) $registro->roc_auc, $registro->top_100_volvieron ?? '-'));

        $this->info('4/4 Recalculando la probabilidad de cada cliente...');

        return $this->call('ml:score-clients');
    }
}
