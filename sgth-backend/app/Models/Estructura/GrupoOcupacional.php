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

    /**
     * Los grupos NJS-1 a NJS-10 (diseño de Acciones de Personal, 8.4). No hay
     * columna: lo dice el código del grado, que es el de la escala del
     * Ministerio del Trabajo. Lo usan la comisión sin remuneración, que nunca
     * es para estos puestos (LOSEP 31), y más adelante la subrogación.
     */
    public function esNivelJerarquicoSuperior(): bool
    {
        return str_starts_with(strtoupper((string) $this->grado_codigo), 'NJS');
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
