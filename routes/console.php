<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// La corrida de saldos del presupuesto es manual (botón "Correr saldos" o presupuesto:correr-saldos):
// no se programa.
