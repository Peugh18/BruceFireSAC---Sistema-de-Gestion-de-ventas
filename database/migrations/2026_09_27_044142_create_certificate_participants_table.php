<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('orden');
            $table->string('nombres');
            $table->string('dni', 20)->nullable();
            $table->string('cargo')->nullable();
            $table->unsignedInteger('sufijo');
            $table->uuid('qr_token')->unique();
            $table->timestamp('anulado_at')->nullable();
            $table->timestamps();

            $table->unique(['certificate_id', 'sufijo']);
        });
    }
};
