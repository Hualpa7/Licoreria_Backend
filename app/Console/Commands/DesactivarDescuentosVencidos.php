<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Descuento;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class DesactivarDescuentosVencidos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'descuentos:desactivar-vencidos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina descuentos y elimina a productos cuya fecha a expirado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hoy = now();

        DB::transaction(function () use ($hoy) {
            // Buscar los IDs de los descuentos vencidos
            $ids = Descuento::where('duracion', '<', $hoy)->pluck('id_descuento');

            if ($ids->isEmpty()) {
                $this->info('No hay descuentos vencidos.');
                return;
            }

            // Desvincular productos
            Producto::whereIn('id_descuento', $ids)->update(['id_descuento' => null]);

            // Eliminar descuentos
            Descuento::whereIn('id_descuento', $ids)->delete();

            $this->info('Descuentos vencidos desactivados y desvinculados correctamente.');
        });
    }
}
