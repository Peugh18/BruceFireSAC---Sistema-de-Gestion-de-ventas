<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * X2: las firmas, los sellos y las fotos de capacitación pasan del disco
 * público (descargables sin sesión) al privado. La ruta relativa no cambia,
 * solo el disco donde vive el archivo, así que la base de datos no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->mover('public', 'local');
    }

    public function down(): void
    {
        $this->mover('local', 'public');
    }

    protected function mover(string $desde, string $hacia): void
    {
        $origen = Storage::disk($desde);
        $destino = Storage::disk($hacia);

        foreach ($this->rutas() as $ruta) {
            if (! $origen->exists($ruta) || $destino->exists($ruta)) {
                continue;
            }

            $destino->put($ruta, (string) $origen->get($ruta));
            $origen->delete($ruta);
        }
    }

    /**
     * @return list<string>
     */
    protected function rutas(): array
    {
        $firmas = DB::table('signers')->get(['firma_path', 'sello_path'])
            ->flatMap(fn (object $signer) => [$signer->firma_path, $signer->sello_path]);

        $fotos = DB::table('certificates')->whereNotNull('datos')->pluck('datos')
            ->flatMap(fn (?string $datos) => (array) (json_decode((string) $datos, true)['fotos'] ?? []));

        return array_values($firmas->merge($fotos)->filter(fn ($ruta) => is_string($ruta) && $ruta !== '')->unique()->all());
    }
};
