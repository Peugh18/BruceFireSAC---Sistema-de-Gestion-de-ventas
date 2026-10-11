<?php

use App\Actions\Equipment\RenewEquipmentAttentionDate;
use App\Http\Controllers\TecnicoCampo\DeliveryController;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('confirmacion relee responsable cambiado despues del middleware', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $sede = Sede::factory()->almacen()->create();
    $anterior = User::factory()->create(['sede_id' => $sede->id]);
    $anterior->assignRole('TecnicoCampo');
    $nuevo = User::factory()->create(['sede_id' => $sede->id]);
    $nuevo->assignRole('TecnicoCampo');
    $ordenCapturada = ServiceOrder::factory()->create([
        'sede_id' => $sede->id, 'estado' => 'listo_entrega', 'tecnico_id' => $anterior->id,
    ]);
    // Representa la orden resuelta por middleware antes del relevo concurrente.
    expect($ordenCapturada->atendiblePor($anterior))->toBeTrue();
    ServiceOrder::whereKey($ordenCapturada->id)->update(['tecnico_id' => $nuevo->id]);
    $request = Request::create('/', 'POST', ['receptor_nombre' => 'Ficticio', 'conformidad_aceptada' => true, 'cerrar_orden' => true]);
    $request->setUserResolver(fn () => $anterior);
    $this->actingAs($anterior);
    try {
        app(DeliveryController::class)->confirm($request, $anterior->currentTeam, $ordenCapturada, app(RenewEquipmentAttentionDate::class));
        test()->fail('El responsable anterior confirmó después del relevo.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }
    expect($ordenCapturada->fresh()->estado)->toBe('listo_entrega')
        ->and($ordenCapturada->events()->count())->toBe(0);
});
