<?php
namespace App\Models\Estructura;

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
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class);
    }
}
