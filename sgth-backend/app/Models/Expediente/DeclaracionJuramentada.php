<?php

namespace App\Models\Expediente;

use App\Enums\TipoDeclaracion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeclaracionJuramentada extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'declaraciones_juramentadas';

    protected $fillable = [
        'servidor_id',
        'fecha_declaracion',
        'codigo_barras',
        'tipo_declaracion',
        'documento_ruta',
        'documento_nombre_archivo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_declaracion'  => TipoDeclaracion::class,
            'fecha_declaracion' => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function toLineaContraloria(): string
    {
        $servidor = $this->servidor;
        $contrato = $servidor->contratos()
            ->where('estado', 'vigente')
            ->latest()
            ->first();

        $cedula          = $servidor->cedula ?? '';
        $apellidos       = strtoupper(trim(($servidor->apellido ?? '') . ' ' . ($servidor->segundo_apellido ?? '')));
        $nombres         = strtoupper(trim(($servidor->nombre ?? '') . ' ' . ($servidor->segundo_nombre ?? '')));
        $tipoNombramiento = '';
        $tipoContrato     = '';

        if ($contrato) {
            $tipo = $contrato->tipo_nombramiento->value ?? $contrato->tipo_nombramiento;
            $esNombramiento = in_array($tipo, [
                'nombramiento_permanente',
                'nombramiento_provisional',
                'libre_nombramiento_remocion',
            ]);
            if ($esNombramiento) {
                $tipoNombramiento = match($tipo) {
                    'nombramiento_permanente'     => 'PERMANENTE',
                    'nombramiento_provisional'    => 'PROVISIONAL',
                    'libre_nombramiento_remocion' => 'LIBRE NOMBRAMIENTO Y REMOCION',
                    default                       => strtoupper($tipo),
                };
            } else {
                $tipoContrato = match($tipo) {
                    'servicios_ocasionales'   => 'SERVICIOS OCASIONALES',
                    'codigo_trabajo'          => 'CONTRATO INDIVIDUAL DE TRABAJO A TIEMPO INDEFINIDO',
                    'servicios_profesionales' => 'SERVICIOS PROFESIONALES',
                    default                   => strtoupper($tipo),
                };
            }
        }

        $tipoDeclaracion = $this->tipo_declaracion->etiquetaContraloria();
        $cargo = strtoupper($servidor->puesto?->nombre ?? '');
        $codigoBarras = $this->codigo_barras ?? '';

        return implode('|', [
            $cedula,
            $apellidos,
            $nombres,
            $tipoNombramiento,
            $tipoContrato,
            $tipoDeclaracion,
            $cargo,
            $codigoBarras,
        ]);
    }
}