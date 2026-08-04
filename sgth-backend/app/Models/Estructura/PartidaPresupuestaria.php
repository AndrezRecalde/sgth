<?php
namespace App\Models\Estructura;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartidaPresupuestaria extends Model
{
    use HasFactory;

    protected $table = 'partidas_presupuestarias';

    protected $fillable = [
        'codigo',
        'descripcion',
        'grupo_gasto',
        'activo',
        'disponible',
    ];

    protected function casts(): array
    {
        return [
            'activo'     => 'boolean',
            'disponible' => 'boolean',
        ];
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(
            UnidadAdministrativa::class,
            'unidad_partida_presupuestaria',
            'partida_presupuestaria_id',
            'unidad_administrativa_id'
        )->withPivot('anio_fiscal', 'observacion')
         ->withTimestamps();
    }

    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class);
    }
}
