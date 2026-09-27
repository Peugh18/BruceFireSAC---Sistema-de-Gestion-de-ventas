<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;

test('a 403 from role middleware renders the branded error page, not the generic laravel page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $response = $this->actingAs($user)->get(route('almacen.dashboard', [
        'current_team' => $user->currentTeam,
    ]));

    $response->assertForbidden();
    $response->assertInertia(fn ($page) => $page
        ->component('errors/error')
        ->where('status', 403)
    );
});

test('a 404 for a missing route renders the branded error page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/'.$user->currentTeam->slug.'/ruta-que-no-existe');

    $response->assertNotFound();
    $response->assertInertia(fn ($page) => $page
        ->component('errors/error')
        ->where('status', 404)
    );
});

test('an expired session (419) goes back to the page with a visible toast', function () {
    Route::post('/_prueba-sesion-vencida', fn () => abort(419))->middleware('web');

    $response = $this->from('/pagina-anterior')->post('/_prueba-sesion-vencida');

    $response->assertRedirect('/pagina-anterior');
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => 'Tu sesión se venció. Vuelve a intentarlo.',
    ]);
});

test('a 500 outside local renders the branded error page without the exception details', function () {
    $this->app['env'] = 'production';
    config(['app.debug' => false]);
    Route::get('/_prueba-error-interno', fn () => throw new RuntimeException('SQLSTATE detalle interno'))->middleware('web');

    $response = $this->get('/_prueba-error-interno');

    $response->assertInternalServerError();
    $response->assertDontSee('SQLSTATE detalle interno');
    $response->assertInertia(fn ($page) => $page
        ->component('errors/error')
        ->where('status', 500)
    );
});

test('a 404 for a missing url keeps the session so the page can send the user back to their panel', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    $response = $this->actingAs($user)->get('/esta-url-no-existe');

    $response->assertNotFound();
    $response->assertInertia(fn ($page) => $page
        ->component('errors/error')
        ->where('status', 404)
        ->where('auth.user.id', $user->id)
        ->where('auth.roles', ['Vendedor'])
    );
});
