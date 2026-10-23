<?php

namespace App\Models\Dispensario;

use App\Enums\EspecialidadAtencion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un grupo de exámenes que se pide con un clic: «Control metabólico»,
 * «Prequirúrgico», «Ocupacional periódico, puesto administrativo».
 *
 * Es de una especialidad: el odontólogo arma y ve los suyos (D12).
 */
class PerfilExamen extends Model
{
    protected $table = 'perfiles_examen';

    protected $fillable = [
        'nombre', 'descripcion', 'especialidad',
        'ocupacional', 'activo', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'especialidad' => EspecialidadAtencion::class,
            'ocupacional'  => 'boolean',
            'activo'       => 'boolean',
        ];
    }

    public function examenes(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogoExamen::class,
            'perfil_examen_items',
            'perfil_examen_id',
            'catalogo_examen_id',
        );
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
