<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Combo;
use Carbon\Carbon;

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
        $hoy = Carbon::now()->format('Y-m-d H:i:s');

        // busca combos vencidos sin depender del accessor
        $combosVencidos = Combo::whereRaw('duracion < ?', [$hoy])
            ->where('activo', true)
            ->get();

        if ($combosVencidos->isEmpty()) {
            $this->info(' No hay combos vencidos para desactivar.');
            return;
        }

        // desactivar combos vencidos
        Combo::whereRaw('duracion < ?', [$hoy])
            ->where('activo', true)
            ->update(['activo' => false]);

        $this->info('' . count($combosVencidos) . ' combos vencidos fueron desactivados correctamente.');
    }
}
