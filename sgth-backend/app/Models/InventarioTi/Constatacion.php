<?php

namespace App\Models\InventarioTi;

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una constatación física (fase 5.2): el recorrido de TIC por una ubicación
 * escaneando las etiquetas. Mientras está en curso se escanea; al cerrarla,
 * lo esperado que no apareció queda como no encontrado. Si participa Activos
 * Fijos y Bodega (5.2b), su responsable firma el acta y queda sellado al cerrar.
 *
 * @property array{nombre: string|null, cargo: string|null}|null $firmante_activos_fijos
 */
class Constatacion extends Model
{
    public const EN_CURSO = 'en_curso';

    public const CERRADA = 'cerrada';

    protected $table = 'constataciones';

    protected $fillable = [
        'nombre', 'ubicacion_id', 'unidad_administrativa_id', 'estado', 'observaciones',
        'participa_activos_fijos', 'firmante_activos_fijos',
        'iniciada_por', 'cerrada_por', 'cerrada_en',
    ];

    protected function casts(): array
    {
        return [
            'cerrada_en'              => 'datetime',
            'participa_activos_fijos' => 'boolean',
            'firmante_activos_fijos'  => 'array',
        ];
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class)->withTrashed();
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConstatacionItem::class);
    }

    public function iniciadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciada_por');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function enCurso(): bool
    {
        return $this->estado === self::EN_CURSO;
    }
}
