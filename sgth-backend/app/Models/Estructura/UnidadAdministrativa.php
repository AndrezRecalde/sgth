<?php

namespace App\Models\Estructura;

use App\Enums\EstadoSubrogacion;
use App\Models\Expediente\Subrogacion;
use App\Observers\UnidadAdministrativaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(UnidadAdministrativaObserver::class)]
class UnidadAdministrativa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'unidades_administrativas';

    protected $fillable = [
        'codigo',
        'nombre',
        'acronimo',
        'descripcion',
        'tipo_unidad_id',
        'unidad_padre_id',
        'nivel',
        'estado',
        // Anclajes de los firmantes de las Acciones de Personal: el jefe de
        // estas unidades es quien firma. A lo sumo una unidad con cada
        // bandera (índices únicos parciales).
        'es_unidad_talento_humano',
        'es_maxima_autoridad',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'nivel'  => 'integer',
            'tipo_unidad_id' => 'string',
            'es_unidad_talento_humano' => 'boolean',
            'es_maxima_autoridad'      => 'boolean',
        ];
    }

    /**
     * Unidad administrativa jerárquicamente superior (padre).
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_padre_id');
    }

    /**
     * Tipo de unidad administrativa.
     */
    public function tipoUnidad(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class, 'tipo_unidad_id');
    }

    /**
     * Unidades o subprocesos que dependen directamente de esta unidad (hijos).
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(UnidadAdministrativa::class, 'unidad_padre_id');
    }

    /**
     * Puestos orgánicos que pertenecen a esta unidad administrativa.
     */
    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class, 'unidad_administrativa_id');
    }

    /**
     * Extensiones telefónicas asociadas a esta unidad.
     */
    public function extensiones(): HasMany
    {
        return $this->hasMany(ExtensionTelefonica::class, 'unidad_administrativa_id');
    }

    /**
     * Extensiones telefónicas activas asociadas a esta unidad.
     */
    public function extensionesActivas(): HasMany
    {
        return $this->hasMany(ExtensionTelefonica::class, 'unidad_administrativa_id')->activas();
    }

    /**
     * Subrogaciones y encargos que están surtiendo efecto hoy en esta unidad.
     *
     * Acotada por fecha además de por estado: es la misma regla con la que se
     * resuelve quién firma, y las dos deben responder lo mismo — si el
     * organigrama dijera que alguien subroga y el firmante resolviera al
     * titular, una de las dos pantallas estaría mintiendo.
     */
    public function subrogacionesVigentes(): HasMany
    {
        return $this->hasMany(Subrogacion::class, 'unidad_administrativa_id')
            ->where('estado', EstadoSubrogacion::ACTIVA->value)
            ->whereDate('fecha_inicio', '<=', now())
            ->whereDate('fecha_fin', '>=', now());
    }
}
