<?php
namespace App\Models\Viatico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaFactura extends Model
{
    protected $table = 'categorias_factura';
    protected $fillable = [
        'nombre', 'codigo', 'descripcion', 'activo', 'orden',
    ];
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
    public function facturas(): HasMany
    {
        return $this->hasMany(FacturaViatico::class);
    }
}
