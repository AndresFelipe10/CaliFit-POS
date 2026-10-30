<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_IMPRIMIENDO = 'imprimiendo';
    public const ESTADO_IMPRESO = 'impreso';
    public const ESTADO_ERROR = 'error';

    public const TIPO_COMANDA = 'comanda';
    public const TIPO_FACTURA = 'factura';
    public const TIPO_PRECUENTA = 'precuenta';
    public const TIPO_PRECUENTA_PARCIAL = 'precuenta_parcial';
    public const TIPO_DESPACHO = 'despacho';
    public const TIPO_CUADRE = 'cuadre';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'print_jobs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tipo',
        'referencia_id',
        'contenido',
        'estado',
        'intentos',
        'tomado_at',
        'impreso_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intentos' => 'integer',
            'tomado_at' => 'datetime',
            'impreso_at' => 'datetime',
        ];
    }
}

