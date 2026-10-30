<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;

class RequeuePrintJobCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'print:requeue {id : ID del trabajo de impresión en print_jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reencola un trabajo de impresión (estado pendiente, intentos 0) para soporte o reimpresión forzada';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');
        $job = PrintJob::find($id);

        if (! $job) {
            $this->error("El trabajo de impresión con ID #{$id} no existe.");
            return self::FAILURE;
        }

        $job->update([
            'estado'     => PrintJob::ESTADO_PENDIENTE,
            'intentos'   => 0,
            'tomado_at'  => null,
            'impreso_at' => null,
        ]);

        $this->info("Trabajo #{$job->id} (tipo: {$job->tipo}) reencolado exitosamente como 'pendiente'.");

        return self::SUCCESS;
    }
}

