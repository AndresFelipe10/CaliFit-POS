<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrintAgentController extends Controller
{
    /**
     * Obtiene el siguiente trabajo de impresión en cola (orden FIFO bajo lockForUpdate).
     * Reintenta o marca error en trabajos colgados hace más de 60 segundos.
     */
    public function siguiente(Request $request): JsonResponse
    {
        // 1. Recuperar trabajos colgados (> 60s en imprimiendo)
        $hungJobs = PrintJob::where('estado', 'imprimiendo')
            ->where('tomado_at', '<', now()->subSeconds(60))
            ->get();

        foreach ($hungJobs as $hung) {
            if ($hung->intentos >= 5) {
                $hung->update(['estado' => 'error']);
            } else {
                $hung->update(['estado' => 'pendiente']);
            }
        }

        // 2. Tomar el siguiente trabajo pendiente de forma atómica
        $job = DB::transaction(function () {
            $pending = PrintJob::where('estado', 'pendiente')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($pending) {
                $pending->update([
                    'estado'    => 'imprimiendo',
                    'tomado_at' => now(),
                    'intentos'  => $pending->intentos + 1,
                ]);
            }

            return $pending;
        });

        if (! $job) {
            return response()->json(null, 200);
        }

        return response()->json([
            'id'        => $job->id,
            'tipo'      => $job->tipo,
            'contenido' => $job->contenido,
        ], 200);
    }

    /**
     * Marca un trabajo como exitosamente impreso.
     */
    public function listo($job = null): JsonResponse
    {
        $id = $job instanceof PrintJob ? $job->id : $job;

        if ($id) {
            PrintJob::where('id', $id)->update([
                'estado'     => 'impreso',
                'impreso_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Registra un fallo en la impresión del trabajo, reintentando hasta 5 veces.
     */
    public function error($job = null): JsonResponse
    {
        $id = $job instanceof PrintJob ? $job->id : $job;

        if ($id) {
            $record = PrintJob::find($id);
            if ($record) {
                $record->update([
                    'estado' => $record->intentos >= 5 ? 'error' : 'pendiente',
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}