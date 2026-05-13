<?php
namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class MantenimientoBien extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mantenimientos_bien';
    protected $fillable = [
        'bien_informatico_id', 'ticket_id', 'tipo_mantenimiento',
        'fecha_mantenimiento', 'tecnico_id', 'descripcion', 'costo',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_mantenimiento' => 'date',
            'costo' => 'decimal:2',
        ];
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }
}
