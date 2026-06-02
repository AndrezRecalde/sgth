<?php

namespace App\Models\Asistencia;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Asistencia\PermisoServidorObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(PermisoServidorObserver::class)]
class PermisoServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'permisos_servidor';

    protected $fillable = [
        'servidor_id',
        'tipo',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'observacion',
        'estado',
        'folio',
        'confirmado_por',
        'confirmado_en',
        'validado_ts_por',
        'validado_ts_en',
        'anulado_por',
        'anulado_en',
        'vence_en',
        'qr_ruta',
        'unidad_administrativa_id',
        'jefe_id',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo'           => TipoPermiso::class,
            'estado'         => EstadoPermiso::class,
            'fecha'          => 'date',
            'hora_inicio'    => 'string',
            'hora_fin'       => 'string',
            'confirmado_en'  => 'datetime',
            'validado_ts_en' => 'datetime',
            'anulado_en'     => 'datetime',
            'vence_en'       => 'datetime',
        ];
    }

    // Relaciones
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function validadoTsPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_ts_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(
            UnidadAdministrativa::class,
            'unidad_administrativa_id'
        );
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'jefe_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'creado_por');
    }
}
