<?php
namespace App\Models\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoOcupacional extends Model
{
    use HasFactory;

    protected $table = 'grupos_ocupacionales';

    protected $fillable = [
        'grado_codigo',
        'grado_numerico',
        'grupo',
        'denominacion_generica',
        'rmu',
        'regimen',
        'nivel_complejidad',
        'rol_puesto',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'rmu'              => 'decimal:2',
            'activo'           => 'boolean',
            'nivel_complejidad' => NivelComplejidadPuesto::class,
            'rol_puesto'       => RolPuesto::class,
        ];
    }

    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class);
    }

    public function esLosep(): bool
    {
        return $this->regimen === 'losep';
    }

    public function esCodigoTrabajo(): bool
    {
        return $this->regimen === 'codigo_trabajo';
    }
}
