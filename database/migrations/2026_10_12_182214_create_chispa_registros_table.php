<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chispa_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rol', 60);
            $table->string('pagina', 200)->nullable();
            $table->text('pregunta');
            $table->text('respuesta');
            $table->string('fuente', 20);
            $table->boolean('util')->nullable();
            $table->timestamps();

            $table->index(['fuente', 'created_at']);
            $table->index(['util', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chispa_registros');
    }
};
