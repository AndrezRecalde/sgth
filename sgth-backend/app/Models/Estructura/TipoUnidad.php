<?php

namespace App\Models\Estructura;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoUnidad extends Model
{
    use HasFactory;

    protected $table = 'tipos_unidad';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'acronimo',
        'descripcion',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    public function unidadesAdministrativas(): HasMany
    {
        return $this->hasMany(UnidadAdministrativa::class, 'tipo_unidad_id');
    }
}
