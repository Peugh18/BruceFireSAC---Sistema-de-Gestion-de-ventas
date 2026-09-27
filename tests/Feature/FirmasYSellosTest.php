<?php

use App\Models\CertificateType;
use App\Models\Signer;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentData;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
    Storage::fake('public');
});

function gerenteDeFirmas(): User
{
    $user = User::factory()->create();
    $user->assignRole('Gerente');

    return $user;
}

/**
 * Foto de una firma: hoja blanca (un poco gris, como con poca luz) con un
 * trazo azul en el centro.
 */
function fotoDeFirma(bool $conTrazo = true): UploadedFile
{
    $imagen = imagecreatetruecolor(800, 500);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 235, 235, 230));

    if ($conTrazo) {
        imagesetthickness($imagen, 6);
        imageline($imagen, 250, 300, 400, 200, imagecolorallocate($imagen, 20, 40, 140));
        imageline($imagen, 400, 200, 550, 280, imagecolorallocate($imagen, 20, 40, 140));
    }

    $ruta = tempnam(sys_get_temp_dir(), 'firma').'.jpg';
    imagejpeg($imagen, $ruta, 92);

    return new UploadedFile($ruta, 'firma.jpg', 'image/jpeg', null, true);
}

function rutaFirmas(User $user, ?Signer $signer = null): string
{
    return $signer
        ? route('gerente.configuracion.firmas.update', ['current_team' => $user->currentTeam, 'firmante' => $signer])
        : route('gerente.configuracion.firmas.index', ['current_team' => $user->currentTeam]);
}

test('el gerente ve los firmantes y los certificados que firman', function () {
    $user = gerenteDeFirmas();

    $this->actingAs($user)
        ->get(rutaFirmas($user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('gerente/configuracion/firmas')
            ->has('firmantes', Signer::count())
            ->has('tipos', CertificateType::count()));
});

test('la foto de la firma queda sin fondo, recortada y se comparte con el mismo nombre', function () {
    $user = gerenteDeFirmas();
    $administrador = Signer::where('nombre', 'Edgar Guevara Cabrera')->where('cargo', 'Administrador')->firstOrFail();
    $instructor = Signer::where('nombre', 'Edgar Guevara Cabrera')->where('cargo', 'Instructor')->firstOrFail();

    $this->actingAs($user)
        ->post(rutaFirmas($user, $administrador), [
            'nombre' => $administrador->nombre,
            'cargo' => $administrador->cargo,
            'firma' => fotoDeFirma(),
        ])
        ->assertSessionHasNoErrors();

    $ruta = $administrador->fresh()->firma_path;
    expect($ruta)->toEndWith('.png');

    $png = imagecreatefromstring(Storage::disk('public')->get($ruta));
    $alfaEsquina = (imagecolorat($png, 0, 0) >> 24) & 0x7F;

    expect($alfaEsquina)->toBe(127)
        ->and(imagesx($png))->toBeLessThan(400)
        ->and(imagesy($png))->toBeLessThan(200)
        ->and($instructor->fresh()->firma_path)->not->toBeNull();
});

test('una foto sin firma avisa en vez de guardar una imagen vacia', function () {
    $user = gerenteDeFirmas();
    $signer = Signer::firstOrFail();

    $this->actingAs($user)
        ->post(rutaFirmas($user, $signer), [
            'nombre' => $signer->nombre,
            'cargo' => $signer->cargo,
            'firma' => fotoDeFirma(conTrazo: false),
        ])
        ->assertSessionHasErrors('imagen');

    expect($signer->fresh()->firma_path)->toBeNull();
});

test('marcar y desmarcar un certificado cambia sus firmantes y sube su version', function () {
    $user = gerenteDeFirmas();
    $signer = Signer::factory()->create(['nombre' => 'Ing. Nuevo', 'cargo' => 'Ingeniero']);
    $luces = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
    $version = $luces->version;

    $this->actingAs($user)
        ->post(rutaFirmas($user, $signer), ['nombre' => 'Ing. Nuevo', 'cargo' => 'Ingeniero', 'tipos' => ['luces_emergencia']])
        ->assertSessionHasNoErrors();

    expect($luces->fresh()->signers->pluck('id'))->toContain($signer->id)
        ->and($luces->fresh()->version)->toBe($version + 1);

    $this->actingAs($user)
        ->post(rutaFirmas($user, $signer), ['nombre' => 'Ing. Nuevo', 'cargo' => 'Ingeniero', 'tipos' => []])
        ->assertSessionHasNoErrors();

    expect($luces->fresh()->signers->pluck('id'))->not->toContain($signer->id)
        ->and($luces->fresh()->version)->toBe($version + 2);
});

test('el sello propio se pega junto a la firma en el certificado', function () {
    $user = gerenteDeFirmas();
    $luces = CertificateType::where('codigo', 'luces_emergencia')->with('signers')->firstOrFail();
    $signer = $luces->signers->first();

    $this->actingAs($user)->post(rutaFirmas($user, $signer), ['nombre' => $signer->nombre, 'cargo' => $signer->cargo, 'firma' => fotoDeFirma()]);
    $soloFirma = app(CertificateDocumentData::class)->muestra($luces->fresh())['firmantes'][0]['firma'];

    $this->actingAs($user)->post(rutaFirmas($user, $signer), ['nombre' => $signer->nombre, 'cargo' => $signer->cargo, 'sello' => fotoDeFirma()]);
    $conSello = app(CertificateDocumentData::class)->muestra($luces->fresh())['firmantes'][0]['firma'];

    $ancho = fn (string $dataUri) => imagesx(imagecreatefromstring(base64_decode(explode(',', $dataUri)[1])));

    expect($soloFirma)->toStartWith('data:image/png')
        ->and($ancho($conSello))->toBeGreaterThan($ancho($soloFirma));

    $this->actingAs($user)
        ->get(route('gerente.configuracion.firmas.vista-previa', ['current_team' => $user->currentTeam, 'tipo' => 'luces_emergencia']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('el gerente agrega un firmante nuevo y otros roles no entran', function () {
    $user = gerenteDeFirmas();

    $this->actingAs($user)
        ->post(route('gerente.configuracion.firmas.store', ['current_team' => $user->currentTeam]), [
            'nombre' => 'Ing. Carla Ruiz',
            'cargo' => 'Ingeniera de seguridad',
            'cip' => '123456',
        ])
        ->assertSessionHasNoErrors();

    expect(Signer::where('nombre', 'Ing. Carla Ruiz')->where('cip', '123456')->exists())->toBeTrue();

    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');

    $this->actingAs($vendedor)
        ->get(route('gerente.configuracion.firmas.index', ['current_team' => $vendedor->currentTeam]))
        ->assertForbidden();
});
