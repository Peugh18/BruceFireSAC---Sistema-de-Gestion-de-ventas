<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Las notas de crédito y débito ya no esperan la aprobación del Gerente: el
 * vendedor las emite directo. La tabla de solicitudes deja de usarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('note_requests');
    }

    public function down(): void
    {
        // Sin vuelta atrás: las solicitudes pendientes no se recrean.
    }
};
