<?php

namespace App\Models\InventarioTi;

use App\Enums\CondicionFisicaBien;
use App\Enums\EstadoBien;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un equipo informático del inventario de TIC (docs/diseno-subsistema-tic.md,
 * 4.2).
 *
 * Lo identifica el código institucional, que asigna Activos Fijos (D2). El
 * código anterior y la serie son datos para buscarlo.
 *
 * Un componente con código propio (el monitor de un equipo «completo») es un
 * equipo más, enlazado al principal por `bien_padre_id`.
 */
class BienInformatico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bienes_informaticos';

    protected $fillable = [
        'codigo_qr', 'codigo_institucional', 'codigo_anterior',
        'tipo_bien_id', 'subtipo_bien_id', 'marca_id', 'origen_bien_id',
        'modelo', 'numero_serie', 'estado_operativo', 'condicion_fisica',
        'fecha_compra', 'fecha_fin_vida_util', 'caracteristicas_tecnicas',
        'ubicacion_id', 'unidad_administrativa_id',
        'nombre_red', 'usuario_dominio', 'direccion_ip',
        'bien_padre_id', 'contrato_vigencia_id', 'prestable', 'observaciones',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estado_operativo'         => EstadoBien::class,
            'condicion_fisica'         => CondicionFisicaBien::class,
            'fecha_compra'             => 'date',
            'fecha_fin_vida_util'      => 'date',
            'caracteristicas_tecnicas' => 'array',
            'prestable'                => 'boolean',
        ];
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoBien::class, 'tipo_bien_id');
    }

    public function subtipo(): BelongsTo
    {
        return $this->belongsTo(SubtipoBien::class, 'subtipo_bien_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(OrigenBien::class, 'origen_bien_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    /** Solo para el equipo sin custodio; el de quien lo tiene sale de su ficha. */
    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function contratoVigencia(): BelongsTo
    {
        return $this->belongsTo(ContratoVigencia::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'bien_padre_id');
    }

    public function componentes(): HasMany
    {
        return $this->hasMany(self::class, 'bien_padre_id')->orderBy('codigo_institucional');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionBien::class, 'bien_informatico_id');
    }

    /** La custodia vigente: como mucho una. */
    public function asignacionVigente(): HasOne
    {
        return $this->hasOne(AsignacionBien::class, 'bien_informatico_id')
            ->ofMany(['id' => 'max'], fn ($q) => $q->where('estado', 'activa'));
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(MantenimientoBien::class, 'bien_informatico_id');
    }

    public function bajas(): HasMany
    {
        return $this->hasMany(BajaBien::class, 'bien_informatico_id');
    }

    /** La baja propuesta o aprobada: hay como mucho una (índice parcial). */
    public function bajaVigente(): HasOne
    {
        return $this->hasOne(BajaBien::class, 'bien_informatico_id')
            ->ofMany(['id' => 'max'], fn ($q) => $q->whereIn('estado', ['propuesta', 'aprobada']));
    }

    /**
     * El estado que le toca por su custodia: en uso si alguien lo tiene,
     * disponible si no. Es a donde vuelve el equipo al salir de mantenimiento
     * o de garantía.
     */
    public function estadoSegunCustodia(): EstadoBien
    {
        return $this->asignacionVigente()->exists() ? EstadoBien::EN_USO : EstadoBien::DISPONIBLE;
    }
}
