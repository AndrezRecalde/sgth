<?php

namespace App\Models\Asistencia;

use App\Enums\MotivoVacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Estructura\UnidadAdministrativa;
use App\Observers\Asistencia\VacacionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(VacacionObserver::class)]
class Vacacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vacaciones';

    protected $fillable = [
        'servidor_id',
        'fecha_inicio',
        'fecha_fin',
        'dias_solicitados',
        'tipo_dias',
        'estado',
        'aprobado_por',
        'folio',
        'codigo_qr',
        'jefe_id',
        'motivo',
        'fecha_retorno',
        'fecha_emision',
        'creado_por',
        'unidad_administrativa_id',
        'persona_reemplaza_id',
        'periodo_vacacion_id',
        'observacion',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio'     => 'date',
            'fecha_fin'        => 'date',
            'dias_solicitados' => 'float',
            'motivo'           => MotivoVacacion::class,
            'fecha_retorno'    => 'date',
            'fecha_emision'    => 'date',
            'anulado_en'       => 'datetime',
        ];
    }

    /**
     * A dónde lleva el QR impreso en la solicitud.
     *
     * Al listado de vacaciones del sistema, filtrado por este folio: lo escanea
     * Talento Humano con el papel firmado en la mano, igual que el QR del
     * permiso, y la pantalla va autenticada. Apuntaba a
     * `/api/v1/asistencia/vacaciones/verificar/{folio}`, una ruta que nunca
     * existió: todo QR impreso daba 404.
     *
     * Un solo sitio para la URL: el servicio la guarda en `codigo_qr` y el PDF
     * la dibuja, y no pueden discrepar.
     */
    public static function urlVerificacion(string $folio): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/sgth/asistencia/vacaciones?folio='.urlencode($folio);
    }

    // Relaciones
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'jefe_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(
            UnidadAdministrativa::class,
            'unidad_administrativa_id'
        );
    }

    public function personaReemplaza(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'persona_reemplaza_id');
    }

    public function periodoVacacion(): BelongsTo
    {
        return $this->belongsTo(PeriodoVacacion::class, 'periodo_vacacion_id');
    }

    /**
     * De qué períodos salieron sus días, y cuántos de cada uno.
     */
    public function descuentos(): HasMany
    {
        return $this->hasMany(VacacionDescuento::class);
    }
}
