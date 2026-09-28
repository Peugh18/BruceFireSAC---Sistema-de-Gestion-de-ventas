<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tercera forma normal de las direcciones: departamento, provincia y distrito
 * dependen del código de ubigeo, no del cliente, la empresa o la sede. Se
 * crea el catálogo oficial del INEI (el que usa SUNAT) y cada tabla guarda
 * solo el código, con llave foránea al catálogo.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $tablasConUbigeo = ['clients', 'company_settings', 'sedes', 'client_sites'];

    public function up(): void
    {
        Schema::create('ubigeos', function (Blueprint $table): void {
            $table->char('codigo', 6)->primary();
            $table->string('departamento', 60);
            $table->string('provincia', 60);
            $table->string('distrito', 80);
            $table->index(['departamento', 'provincia']);
        });

        $lineas = file(database_path('data/ubigeos_inei.csv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $filas = [];
        foreach (array_slice($lineas, 1) as $linea) {
            [$codigo, $departamento, $provincia, $distrito] = str_getcsv(trim($linea), escape: '');
            $filas[] = ['codigo' => $codigo, 'departamento' => $departamento, 'provincia' => $provincia, 'distrito' => $distrito];
        }

        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('ubigeos')->insert($lote);
        }

        // Datos existentes: se completa el código a partir del texto cuando
        // coincide exacto, y los códigos que no existen quedan vacíos.
        foreach (['clients', 'company_settings'] as $tabla) {
            DB::table($tabla)->whereNull('ubigeo')->whereNotNull('distrito')->orderBy('id')->each(function (object $fila) use ($tabla): void {
                $codigo = DB::table('ubigeos')
                    ->whereRaw('UPPER(distrito) = UPPER(?)', [trim((string) $fila->distrito)])
                    ->when($fila->provincia, fn ($query) => $query->whereRaw('UPPER(provincia) = UPPER(?)', [trim((string) $fila->provincia)]))
                    ->when($fila->departamento, fn ($query) => $query->whereRaw('UPPER(departamento) = UPPER(?)', [trim((string) $fila->departamento)]))
                    ->pluck('codigo');

                if ($codigo->count() === 1) {
                    DB::table($tabla)->where('id', $fila->id)->update(['ubigeo' => $codigo->first()]);
                }
            });
        }

        // La ciudad de una sede es la capital de su provincia (Trujillo → 130101).
        DB::table('sedes')->whereNull('ubigeo')->whereNotNull('ciudad')->orderBy('id')->each(function (object $sede): void {
            $codigo = DB::table('ubigeos')
                ->whereRaw('UPPER(distrito) = UPPER(?)', [trim((string) $sede->ciudad)])
                ->whereColumn('distrito', 'provincia')
                ->pluck('codigo');

            if ($codigo->count() === 1) {
                DB::table('sedes')->where('id', $sede->id)->update(['ubigeo' => $codigo->first()]);
            }
        });

        foreach ($this->tablasConUbigeo as $tabla) {
            DB::table($tabla)->whereNotNull('ubigeo')->whereNotIn('ubigeo', DB::table('ubigeos')->select('codigo'))->update(['ubigeo' => null]);

            Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
                $table->char('ubigeo', 6)->nullable()->change();
                $table->foreign('ubigeo', "{$tabla}_ubigeo_foreign")->references('codigo')->on('ubigeos')->restrictOnDelete();
            });
        }

        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['departamento', 'provincia', 'distrito']));
        Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn(['departamento', 'provincia', 'distrito']));
        Schema::table('sedes', fn (Blueprint $table) => $table->dropColumn('ciudad'));
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('departamento')->nullable()->after('direccion_fiscal');
            $table->string('provincia')->nullable()->after('departamento');
            $table->string('distrito')->nullable()->after('provincia');
        });
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->string('departamento')->nullable()->after('ubigeo');
            $table->string('provincia')->nullable()->after('departamento');
            $table->string('distrito')->nullable()->after('provincia');
        });
        Schema::table('sedes', fn (Blueprint $table) => $table->string('ciudad')->nullable()->after('ubigeo'));

        foreach (['clients', 'company_settings'] as $tabla) {
            DB::table($tabla)->join('ubigeos', 'ubigeos.codigo', '=', "{$tabla}.ubigeo")->update([
                "{$tabla}.departamento" => DB::raw('ubigeos.departamento'),
                "{$tabla}.provincia" => DB::raw('ubigeos.provincia'),
                "{$tabla}.distrito" => DB::raw('ubigeos.distrito'),
            ]);
        }
        DB::table('sedes')->join('ubigeos', 'ubigeos.codigo', '=', 'sedes.ubigeo')->update(['sedes.ciudad' => DB::raw('ubigeos.provincia')]);

        foreach ($this->tablasConUbigeo as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropForeign("{$tabla}_ubigeo_foreign"));
        }

        Schema::dropIfExists('ubigeos');
    }
};
