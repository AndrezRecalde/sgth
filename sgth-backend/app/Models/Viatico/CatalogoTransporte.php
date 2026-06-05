<?php
namespace App\Models\Viatico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTransporte extends Model
{
    protected $table = 'catalogo_transportes';
    protected $fillable = [
        'nombre', 'codigo', 'tipo_vehiculo',
        'requiere_autorizacion', 'activo', 'orden',
    ];
    protected function casts(): array
    {
        return [
            'requiere_autorizacion' => 'boolean',
            'activo'                => 'boolean',
        ];
    }
    public function empresas(): HasMany
    {
        return $this->hasMany(EmpresaTransporte::class);
    }
}
