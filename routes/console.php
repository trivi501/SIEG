<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Corrida de saldos del presupuesto: el día 1 de cada mes, lo disponible del mes anterior pasa al mes.
// Requiere el cron del scheduler en el servidor (ver docs/operacion-y-despliegue.md).
Schedule::command('presupuesto:correr-saldos')
    ->monthlyOn(1, '00:05')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->onOneServer();
