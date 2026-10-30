<?php

use App\Models\PrintJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Tarea programada diaria: Limpieza de trabajos de impresión impresos con más de 3 días de antigüedad.
 */
Schedule::call(function () {
    PrintJob::where('estado', PrintJob::ESTADO_IMPRESO)
        ->where('impreso_at', '<', now()->subDays(3))
        ->delete();
})->daily()->name('print-jobs-cleanup')->withoutOverlapping();
