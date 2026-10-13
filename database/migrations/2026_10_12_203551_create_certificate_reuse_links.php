<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_sale', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['certificate_id', 'sale_id']);
        });

        Schema::create('certificate_service_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['certificate_id', 'service_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_service_order');
        Schema::dropIfExists('certificate_sale');
    }
};
