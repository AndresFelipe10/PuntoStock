<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Resumen nocturno automatizado por correo a los administradores de cada negocio activo.
 * Se ejecuta diariamente a las 23:30 (11:30 PM) hora de Colombia (America/Bogota).
 */
Schedule::command('pos:send-daily-summary')
    ->dailyAt('23:30')
    ->timezone('America/Bogota')
    ->withoutOverlapping();

