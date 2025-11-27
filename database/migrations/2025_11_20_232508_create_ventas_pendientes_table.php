<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ventas_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('external_reference')->unique();
            $table->longText('datos_venta'); // JSON con todos los datos de la venta
            $table->boolean('procesada')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Índice para búsquedas rápidas
            $table->index('external_reference');
            $table->index('procesada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas_pendientes');
    }
};