<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cargo depende del certificado (Edgar firma como administrador en un
     * informe y como instructor en la capacitación), no de la persona. Pasa
     * al pivote y las fichas repetidas de una misma persona se unen en una.
     */
    public function up(): void
    {
        Schema::table('certificate_type_signer', function (Blueprint $table) {
            $table->string('cargo', 120)->nullable()->after('orden');
        });

        $cargos = DB::table('signers')->pluck('cargo', 'id');
        foreach (DB::table('certificate_type_signer')->get() as $fila) {
            DB::table('certificate_type_signer')
                ->where('certificate_type_id', $fila->certificate_type_id)
                ->where('signer_id', $fila->signer_id)
                ->update(['cargo' => $cargos[$fila->signer_id] ?? null]);
        }

        $personas = DB::table('signers')->orderBy('id')->get()
            ->groupBy(fn (stdClass $signer) => mb_strtolower(trim((string) $signer->nombre)));

        foreach ($personas as $fichas) {
            $queda = $fichas->shift();
            if (! $queda instanceof stdClass) {
                continue;
            }
            foreach ($fichas as $repetida) {
                $this->unir($queda, $repetida);
                $queda = DB::table('signers')->where('id', $queda->id)->first() ?? $queda;
            }
        }
    }

    public function down(): void
    {
        Schema::table('certificate_type_signer', function (Blueprint $table) {
            $table->dropColumn('cargo');
        });
    }

    /** Pasa los certificados, las fotos que falten y las versiones emitidas de una ficha a la otra. */
    private function unir(stdClass $queda, stdClass $repetida): void
    {
        foreach (DB::table('certificate_type_signer')->where('signer_id', $repetida->id)->get() as $fila) {
            $yaFirma = DB::table('certificate_type_signer')
                ->where('certificate_type_id', $fila->certificate_type_id)
                ->where('signer_id', $queda->id)
                ->exists();

            $consulta = DB::table('certificate_type_signer')
                ->where('certificate_type_id', $fila->certificate_type_id)
                ->where('signer_id', $repetida->id);

            $yaFirma ? $consulta->delete() : $consulta->update(['signer_id' => $queda->id]);
        }

        DB::table('signers')->where('id', $queda->id)->update([
            'firma_path' => $queda->firma_path ?? $repetida->firma_path,
            'sello_path' => $queda->sello_path ?? $repetida->sello_path,
            'cip' => $queda->cip ?? $repetida->cip,
            'activo' => $queda->activo || $repetida->activo,
        ]);

        // Lo ya emitido apunta a la ficha por su id: se cambia por la que queda.
        foreach (DB::table('certificate_type_versions')->get(['id', 'configuracion']) as $version) {
            $configuracion = json_decode((string) $version->configuracion, true);
            $firmantes = $configuracion['firmantes'] ?? [];
            $cambio = false;

            foreach ($firmantes as $i => $firmante) {
                if ((int) ($firmante['id'] ?? 0) === (int) $repetida->id) {
                    $firmantes[$i]['id'] = $queda->id;
                    $cambio = true;
                }
            }

            if ($cambio) {
                $configuracion['firmantes'] = $firmantes;
                DB::table('certificate_type_versions')->where('id', $version->id)->update(['configuracion' => json_encode($configuracion)]);
            }
        }

        DB::table('signers')->where('id', $repetida->id)->delete();
    }
};
