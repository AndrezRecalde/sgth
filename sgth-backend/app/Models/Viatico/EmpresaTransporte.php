<?php
namespace App\Models\Viatico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaTransporte extends Model
{
    protected $table = 'empresas_transporte';
    protected $fillable = [
        'catalogo_transporte_id', 'nombre', 'codigo',
        'activo', 'orden',
    ];
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(CatalogoTransporte::class,
            'catalogo_transporte_id');
    }
}
