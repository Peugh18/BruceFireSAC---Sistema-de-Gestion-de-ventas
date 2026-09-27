<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de firmantes de certificados (con su firma escaneada y sello) y
     * qué firmantes lleva cada tipo de certificado, en orden.
     */
    public function up(): void
    {
        Schema::create('signers', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('cargo', 120);
            $table->string('cip', 20)->nullable();
            $table->string('especialidad', 120)->nullable();
            $table->string('firma_path')->nullable();
            $table->string('sello_path')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('certificate_type_signer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_type_id')->constrained('certificate_types')->cascadeOnDelete();
            $table->foreignId('signer_id')->constrained('signers')->cascadeOnDelete();
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();

            $table->unique(['certificate_type_id', 'signer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_type_signer');
        Schema::dropIfExists('signers');
    }
};
