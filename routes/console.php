<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('descuentos:desactivar-vencidos')->daily(); //con esto le digo a laravel que ejecute el comando todos los dias


// Desactiva combos vencidos una vez por día
Schedule::command('combos:desactivar-vencidos')->daily();

//Verifica alertas de stock cada minuto
Schedule::command('stock:verificar-alertas')
    ->everyMinute()
    ->withoutOverlapping();