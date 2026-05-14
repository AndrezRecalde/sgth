<?php
namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Expediente\Servidor;

class AsignacionBien extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'asignaciones_bien';
    protected $fillable = [
        'bien_informatico_id', 'servidor_id', 'fecha_asignacion',
        'fecha_devolucion', 'observaciones', 'url_acta_pdf', 'estado',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'date',
            'fecha_devolucion' => 'date',
        ];
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
