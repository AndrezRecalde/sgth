<?php
namespace App\Models\Estructura;

use App\Enums\ClasificacionPersonal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cargo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cargos';

    protected $fillable = [
        'nombre',
        'denominacion_generica',
        'codigo_ciuo',
        'mision',
        'clasificacion_personal',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo'                 => 'boolean',
            'clasificacion_personal' => ClasificacionPersonal::class,
        ];
    }

    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class);
    }

    public function esObrero(): bool
    {
        return $this->clasificacion_personal === ClasificacionPersonal::OBRERO;
    }

    public function esContratado(): bool
    {
        return $this->clasificacion_personal === ClasificacionPersonal::CONTRATADO;
    }

    public function esEmpleado(): bool
    {
        return $this->clasificacion_personal === ClasificacionPersonal::EMPLEADO;
    }
}
