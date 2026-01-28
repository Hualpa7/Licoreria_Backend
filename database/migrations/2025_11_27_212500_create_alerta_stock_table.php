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
        Schema::create('alerta_stock', function (Blueprint $table) {
            $table->id('id_alerta');
            $table->unsignedInteger('id_producto');
            $table->string('producto'); // Nombre del producto
            $table->unsignedInteger('id_sucursal');
            $table->string('nombre'); // Nombre de la sucursal
            $table->integer('stock_actual');
            $table->integer('alerta_minima');
            $table->timestamp('fecha_alerta')->useCurrent();

            // Foreign keys
            $table->foreign('id_producto')
                ->references('id_producto')
                ->on('producto')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('id_sucursal')
                ->references('id_sucursal')
                ->on('sucursal')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Índices para mejor rendimiento
            $table->index(['id_sucursal']);
            $table->index('fecha_alerta');
            // Índice único para evitar alertas duplicadas del mismo producto-sucursal
            $table->unique(['id_producto', 'id_sucursal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerta_stock');
    }
};
