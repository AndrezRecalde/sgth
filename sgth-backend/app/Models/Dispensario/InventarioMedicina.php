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

    /**
     * Una medicina sin fecha registrada no se considera caducada: no hay dato
     * que lo afirme, y bloquear por omisión dejaría la farmacia parada.
     *
     * El día de la caducidad todavía es válido, que es como se lee la fecha
     * impresa en el envase.
     */
    public function estaCaducado(): bool
    {
        return $this->fecha_caducidad !== null
            && $this->fecha_caducidad->startOfDay()->isBefore(now()->startOfDay());
    }
}
