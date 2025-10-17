<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Combo;

class DesactivarCombosVencidos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'combos:desactivar-vencidos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Desactiva los combos cuya fecha de duración haya expirad';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hoy = now();

        // Buscar combos vencidos (fecha pasada y aún activos)
        $combosVencidos = Combo::where('duracion', '<', $hoy)
            ->where('activo', true)
            ->get();

        if ($combosVencidos->isEmpty()) {
            $this->info('No hay combos vencidos para desactivar.');
            return;
        }

        // Desactivar combos vencidos
        Combo::where('duracion', '<', $hoy)
            ->where('activo', true)
            ->update(['activo' => false]);

        $this->info(count($combosVencidos) . ' combos vencidos fueron desactivados correctamente.');
    }
}
