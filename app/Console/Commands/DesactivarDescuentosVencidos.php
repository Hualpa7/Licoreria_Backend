<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Descuento;
use App\Models\Producto;
use Carbon\Carbon;
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
        // Uso carbon para poder comprar correctamente las fechas ya que si no usaria el accesor configurdo en el modelo  
        //el ucal no coincide en formato
        $hoy = Carbon::now()->format('Y-m-d H:i:s');

        DB::transaction(function () use ($hoy) {
            // Buscar descuentos vencidos usando comparación directa sin accessor
            $descuentosVencidos = Descuento::whereRaw('duracion < ?', [$hoy])->get();

            if ($descuentosVencidos->isEmpty()) {
                $this->info(' No hay descuentos vencidos.');
                return;
            }

            $ids = $descuentosVencidos->pluck('id_descuento');
            $cantidad = $ids->count();

            // desvinculo productos desde la tabla intermedia producto_descuento
            DB::table('producto_descuento')
                ->whereIn('id_descuento', $ids)
                ->delete();

            // eliminar descuentoo de su tambla
            Descuento::whereIn('id_descuento', $ids)->delete();

            $this->info(" {$cantidad} descuentos vencidos desactivados y desvinculados correctamente.");
        });
    }
}
