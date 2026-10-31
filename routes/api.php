<?php

use App\Http\Controllers\Api\PrintAgentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Windows Print Agent (ESC/POS Centralized Queue)
|--------------------------------------------------------------------------
| Estas rutas son consumidas por el script de polling en el PC Windows del local
| protegido mediante el middleware X-Print-Token.
*/

Route::middleware('print.agent')->prefix('print')->group(function () {
    Route::post('/siguiente', [PrintAgentController::class, 'siguiente']);
    Route::post('/listo/{job?}', [PrintAgentController::class, 'listo']);
    Route::post('/error/{job?}', [PrintAgentController::class, 'error']);
});

