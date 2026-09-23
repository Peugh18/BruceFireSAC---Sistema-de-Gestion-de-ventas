<?php

use App\Models\Client;
use App\Models\ClientRetentionScore;
use App\Models\Sale;
use App\Models\User;
use App\Services\Ml\RetentionModel;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('RetentionModel calcula la probabilidad sigmoide y estandarización correctamente con vector conocido', function () {
    // Creamos un modelo sintético temporal
    $tempDir = storage_path('framework/testing/ml');
    if (! File::isDirectory($tempDir)) {
        File::makeDirectory($tempDir, 0755, true);
    }

    $tempModelPath = $tempDir.'/test_retention_model.json';

    $syntheticModel = [
        'model_name' => 'test_model',
        'version' => '1.0.0',
        'features' => ['frecuencia_compras', 'recencia_dias'],
        'scaler' => [
            'mean' => [
                'frecuencia_compras' => 2.0,
                'recencia_dias' => 100.0,
            ],
            'std' => [
                'frecuencia_compras' => 1.0,
                'recencia_dias' => 50.0,
            ],
        ],
        'coefficients' => [
            'frecuencia_compras' => 1.0,
            'recencia_dias' => -1.0,
        ],
        'intercept' => 0.0,
    ];

    File::put($tempModelPath, json_encode($syntheticModel));

    $model = new RetentionModel($tempModelPath);

    // Caso 1: Valores exactamente en la media => z = 0, logit = 0 => prob = 0.5 (50%)
    $probCentro = $model->predictProbability([
        'frecuencia_compras' => 2.0,
        'recencia_dias' => 100.0,
    ]);

    expect($probCentro)->toEqualWithDelta(0.5, 0.001);

    // Caso 2: Frecuencia alta (3.0 => +1 std) y Recencia baja (50.0 => -1 std)
    // logit = 0 + (1.0 * 1.0) + (-1.0 * -1.0) = 2.0
    // prob = 1 / (1 + exp(-2)) ~= 0.8808
    $probFavorable = $model->predictProbability([
        'frecuencia_compras' => 3.0,
        'recencia_dias' => 50.0,
    ]);

    expect($probFavorable)->toEqualWithDelta(0.8808, 0.005);
    expect($model->categorizeProbability($probFavorable))->toBe('alta');

    // Caso 3: Frecuencia baja (1.0 => -1 std) y Recencia alta (150.0 => +1 std)
    // logit = 0 + (1.0 * -1.0) + (-1.0 * 1.0) = -2.0
    // prob = 1 / (1 + exp(2)) ~= 0.1192
    $probDesfavorable = $model->predictProbability([
        'frecuencia_compras' => 1.0,
        'recencia_dias' => 150.0,
    ]);

    expect($probDesfavorable)->toEqualWithDelta(0.1192, 0.005);
    expect($model->categorizeProbability($probDesfavorable))->toBe('baja');

    File::delete($tempModelPath);
});

test('RetentionModel carga el modelo exportado real y explica factores', function () {
    $model = new RetentionModel;

    expect($model->isAvailable())->toBeTrue();

    $meta = $model->getModelMetadata();
    expect($meta)->not->toBeNull();
    expect($meta['model_name'])->toBe('brucefire_client_retention_logistic_regression');
    expect($meta['metrics']['test'])->toHaveKeys(['accuracy', 'roc_auc', 'precision', 'recall']);
    expect($meta['metrics']['test']['roc_auc'])->toBeGreaterThan(0.60);

    // Evaluación de explicabilidad de factores
    $factores = $model->explainFactors([
        'recencia_dias' => 10,
        'frecuencia_compras' => 15,
        'monto_total' => 5000.0,
        'ticket_promedio' => 333.33,
        'antiguedad_dias' => 300,
        'diversidad_productos' => 6,
        'compro_recarga' => 1,
    ]);

    expect($factores)->toHaveKeys(['positivos', 'negativos']);
    expect($factores['positivos'])->toBeArray();
});

test('comando ml:score-clients evalúa y guarda scores de retención en la base de datos', function () {
    $client = Client::factory()->create([
        'razon_social' => 'EMPRESA INDUSTRIAL TEST SAC',
        'numero_documento' => '20123456789',
        'activo' => true,
    ]);

    Sale::factory()->create([
        'client_id' => $client->id,
        'fecha' => '2026-02-15',
        'total' => 850.00,
        'estado' => 'confirmada',
    ]);

    $this->artisan('ml:score-clients', ['--limit' => 10])
        ->assertSuccessful();

    $score = ClientRetentionScore::where('client_id', $client->id)->first();
    expect($score)->not->toBeNull();
    expect($score->probabilidad)->toBeGreaterThanOrEqual(0.0);
    expect($score->probabilidad)->toBeLessThanOrEqual(1.0);
    expect(['alta', 'media', 'baja'])->toContain($score->categoria);
    expect($score->client_id)->toBe($client->id);
    expect($score->frecuencia_compras)->toBe(1);
    expect((float) $score->monto_total)->toEqualWithDelta(850.00, 0.01);
});

test('Dashboard del Gerente maneja estado vacío si no hay scores de retención', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    // Sin registros en client_retention_scores
    ClientRetentionScore::query()->delete();

    $response = $this->actingAs($gerente)
        ->get(route('gerente.dashboard', ['current_team' => $gerente->currentTeam]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('gerente/dashboard')
        ->where('aiRetention', null)
    );
});

test('Dashboard del Gerente renderiza la sección de IA con scores activos', function () {
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    $client = Client::factory()->create([
        'razon_social' => 'GRUPO GLORIA S.A.',
        'numero_documento' => '20100190797',
    ]);

    ClientRetentionScore::create([
        'client_id' => $client->id,
        'probabilidad' => 0.8520,
        'categoria' => 'alta',
        'recencia_dias' => 15,
        'frecuencia_compras' => 8,
        'monto_total' => 12500.00,
        'ticket_promedio' => 1562.50,
        'antiguedad_dias' => 240,
        'diversidad_productos' => 4,
        'compro_recarga' => true,
        'factores_json' => [
            'positivos' => [
                ['factor' => 'Compra reciente', 'impacto' => 'positivo', 'detalle' => 'Última compra hace 15 días'],
            ],
            'negativos' => [],
        ],
        'scored_at' => now(),
    ]);

    $response = $this->actingAs($gerente)
        ->get(route('gerente.dashboard', ['current_team' => $gerente->currentTeam]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('gerente/dashboard')
        ->has('aiRetention', fn (Assert $ai) => $ai
            ->where('totalEvaluados', 1)
            ->where('distribucion.alta.cantidad', 1)
            ->where('modelo.disponible', true)
            ->has('topClientes', 1)
            ->where('topClientes.0.cliente', 'GRUPO GLORIA S.A.')
            ->etc()
        )
    );
});
