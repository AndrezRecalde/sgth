<?php

namespace App\Models\Asistencia;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Aquí colgaba un `#[ObservedBy(PermisoServidorObserver::class)]` cuyo observer
// estaba vacío desde que se creó: solo tenía un `//`. Un observer registrado y
// sin nada dentro no es un punto de extensión, es una pista falsa — quien
// busque dónde se engancha el ciclo de vida de un permiso lo encuentra y no
// encuentra nada. Las reglas del permiso viven en `PermisoService`.
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
        'rechazado_por',
        'rechazado_en',
        'motivo_rechazo',
        'anulado_por',
        'anulado_en',
        'vence_en',
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
            'rechazado_en'   => 'datetime',
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

    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por');
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
