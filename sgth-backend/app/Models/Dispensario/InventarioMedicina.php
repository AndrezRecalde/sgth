<?php
namespace App\Models\Dispensario;
use App\Enums\PresentacionMedicamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\Dispensario\InventarioMedicinaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(InventarioMedicinaObserver::class)]
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
            'presentacion'    => PresentacionMedicamento::class,
            'fecha_caducidad' => 'date',
            'estado'          => 'boolean',
        ];
    }
}
