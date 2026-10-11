<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['electronic_documents', 'note_requests'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->json('nota_datos')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['electronic_documents', 'note_requests'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('nota_datos'));
        }
    }
};
