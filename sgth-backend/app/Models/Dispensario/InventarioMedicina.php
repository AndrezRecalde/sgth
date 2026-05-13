<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventarioMedicina extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventario_medicinas';

    protected $fillable = [
        'codigo', 'nombre', 'principio_activo', 'presentacion',
        'concentracion', 'stock_actual', 'stock_minimo', 'fecha_caducidad',
        'lote', 'estado', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_caducidad' => 'date',
            'estado'          => 'boolean',
        ];
    }
}
