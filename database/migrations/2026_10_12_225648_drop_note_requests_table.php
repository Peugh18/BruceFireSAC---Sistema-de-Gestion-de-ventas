<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Conserva las solicitudes antiguas para consulta; no las emite automáticamente. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('note_requests')) {
            return;
        }
        if (Schema::hasTable('note_requests_archive')) {
            throw new RuntimeException('Existen solicitudes y un archivo anterior. Conserva ambas tablas y resuelve el archivo antes de migrar.');
        }

        Schema::rename('note_requests', 'note_requests_archive');
    }

    public function down(): void
    {
        if (Schema::hasTable('note_requests_archive') && ! Schema::hasTable('note_requests')) {
            Schema::rename('note_requests_archive', 'note_requests');
        }
    }
};
