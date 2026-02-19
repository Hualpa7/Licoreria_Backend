<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\AlertaStock;
use App\Models\Producto;
use App\Models\Sucursal;

class VerificarAlertasStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:verificar-alertas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica productos con stock bajo y crea/elimina alertas automáticamente';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando verificación de alertas de stock...');

        try {
            // Obtener todos los productos activos
            $productos = Producto::where('activo', true)->get();

            if ($productos->isEmpty()) {
                $this->warn('No hay productos activos para verificar.');
                return;
            }

            $alertasCreadas = 0;
            $alertasEliminadas = 0;

            foreach ($productos as $producto) {
                // Por cada producto, verificar en TODAS las sucursales
                $sucursales = Sucursal::all();

                foreach ($sucursales as $sucursal) {
                    try {
                        // Calcular el stock actual del producto en esta sucursal
                        $stockActual = DB::table('stock')
                            ->where('id_producto', $producto->id_producto)
                            ->where('id_sucursal', $sucursal->id_sucursal)
                            ->sum('cantidad');

                        // Buscar si ya existe alerta para este producto-sucursal
                        $alertaExistente = AlertaStock::where('id_producto', $producto->id_producto)
                            ->where('id_sucursal', $sucursal->id_sucursal)
                            ->first();

                        // Verificar si el stock está por debajo de la alerta mínima
                        if ($stockActual < $producto->alerta_minima) {
                            // Si NO existe alerta, crearla
                            if (!$alertaExistente) {
                                AlertaStock::create([
                                    'id_producto' => $producto->id_producto,
                                    'producto' => $producto->producto,
                                    'id_sucursal' => $sucursal->id_sucursal,
                                    'nombre' => $sucursal->nombre,
                                    'stock_actual' => $stockActual,
                                    'alerta_minima' => $producto->alerta_minima,
                                ]);

                                $this->info("✓ Alerta creada: {$producto->producto} en {$sucursal->nombre} (Stock: {$stockActual})");
                                $alertasCreadas++;
                            } else {
                                // Si ya existe la alerta, actualizar su stock_actual (y alerta_minima si cambió)
                                $alertaExistente->stock_actual = $stockActual;
                                $alertaExistente->alerta_minima = $producto->alerta_minima;
                                // opcional: actualizar nombre/producto por si cambiaron
                                $alertaExistente->producto = $producto->producto;
                                $alertaExistente->nombre = $sucursal->nombre;
                                $alertaExistente->save();

                                $this->info("↻ Alerta actualizada: {$producto->producto} en {$sucursal->nombre} (Stock: {$stockActual})");
                            }
                        } else {
                            // El stock está OK (igual o mayor a la alerta mínima)
                            // Si existe alerta, ELIMINARLA
                            if ($alertaExistente) {
                                $alertaExistente->delete();
                                $this->info("✗ Alerta eliminada: {$producto->producto} en {$sucursal->nombre} (Stock: {$stockActual})");
                                $alertasEliminadas++;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->error("Error procesando {$producto->producto} en {$sucursal->nombre}: " . $e->getMessage());
                    }
                }
            }

            $this->info("✓ Verificación completada | Creadas: {$alertasCreadas} | Eliminadas: {$alertasEliminadas}");

        } catch (\Exception $e) {
            $this->error('Error al verificar alertas: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}