<?php
namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class BienInformatico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bienes_informaticos';
    protected $fillable = [
        'codigo_qr', 'codigo_institucional', 'tipo_bien', 'marca', 'modelo',
        'numero_serie', 'estado', 'fecha_compra', 'garantia_hasta',
        'proveedor', 'caracteristicas_tecnicas', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'garantia_hasta' => 'date',
            'caracteristicas_tecnicas' => 'array',
        ];
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionBien::class);
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(MantenimientoBien::class);
    }
}
