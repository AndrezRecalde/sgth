<?php

namespace App\Models\Expediente;

use App\Enums\TipoDeclaracion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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

    /**
     * La línea del archivo que se entrega a Contraloría.
     *
     * Hasta el 2026-10-03 tomaba el contrato VIGENTE HOY y el cargo de la
     * ficha para todas las declaraciones: una de fin de gestión de quien ya
     * salió iba sin nombramiento ni contrato, y una de inicio de 2020 con el
     * cargo actual. Y el cargo salía siempre vacío: leía `puesto->nombre`, una
     * columna que `puestos` no tiene.
     *
     * @param Collection<int, ContratoServidor>|null $contratos los del
     *        servidor con `puesto.cargo`, para no consultarlos una vez por
     *        línea al exportar varias.
     */
    public function toLineaContraloria(?Collection $contratos = null): string
    {
        $servidor = $this->servidor;
        $contratos ??= $servidor->contratos()->with('puesto.cargo')->get();
        $contrato = $this->contratoDeLaDeclaracion($contratos);

        $cedula          = $servidor->cedula ?? '';
        $apellidos       = mb_strtoupper(trim(($servidor->apellido ?? '') . ' ' . ($servidor->segundo_apellido ?? '')));
        $nombres         = mb_strtoupper(trim(($servidor->nombre ?? '') . ' ' . ($servidor->segundo_nombre ?? '')));
        $tipoNombramiento = '';
        $tipoContrato     = '';

        if ($contrato) {
            $tipo = $contrato->tipo_nombramiento->value ?? $contrato->tipo_nombramiento;
            // La elección popular es un nombramiento, no un contrato: caía en
            // el `default` de contratos e imprimía «ELECCION_POPULAR».
            $esNombramiento = in_array($tipo, [
                'nombramiento_permanente',
                'nombramiento_provisional',
                'libre_nombramiento_remocion',
                'eleccion_popular',
            ]);
            if ($esNombramiento) {
                $tipoNombramiento = match($tipo) {
                    'nombramiento_permanente'     => 'PERMANENTE',
                    'nombramiento_provisional'    => 'PROVISIONAL',
                    'libre_nombramiento_remocion' => 'LIBRE NOMBRAMIENTO Y REMOCION',
                    'eleccion_popular'            => 'ELECCION POPULAR',
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
        $cargo = mb_strtoupper($contrato?->puesto?->cargo?->nombre ?? '');
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

    /**
     * El vínculo al que corresponde la declaración: el vigente en su fecha.
     * Si no hay ninguno —la de fin de gestión se presenta después de salir;
     * la de inicio puede ser antes de entrar—, el último que empezó antes, y
     * si tampoco, el primero que empezó después. Los anulados (borrados) no
     * cuentan.
     *
     * @param Collection<int, ContratoServidor> $contratos
     */
    private function contratoDeLaDeclaracion(Collection $contratos): ?ContratoServidor
    {
        $fecha = $this->fecha_declaracion?->toDateString();
        if ($fecha === null) {
            return null;
        }

        $inicio = fn (ContratoServidor $c) => $c->fecha_inicio?->toDateString() ?? '';
        $fin    = fn (ContratoServidor $c) => $c->fecha_fin?->toDateString();
        $orden  = fn (ContratoServidor $c) => $inicio($c) . str_pad((string) $c->id, 10, '0', STR_PAD_LEFT);

        return $contratos
            ->filter(fn ($c) => $inicio($c) <= $fecha && ($fin($c) === null || $fin($c) >= $fecha))
            ->sortByDesc($orden)->first()
            ?? $contratos->filter(fn ($c) => $inicio($c) <= $fecha)->sortByDesc($orden)->first()
            ?? $contratos->filter(fn ($c) => $inicio($c) > $fecha)->sortBy($orden)->first();
    }
}