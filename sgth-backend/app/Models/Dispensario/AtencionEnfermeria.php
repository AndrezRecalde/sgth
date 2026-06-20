<?php

namespace App\Models\Dispensario;

use App\Models\User;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\CargaFamiliar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AtencionEnfermeria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'atenciones_enfermeria';

    protected $fillable = [
        'folio', 'enfermera_id', 'servidor_id',
        'carga_familiar_id', 'catalogo_servicio_id',
        'descripcion', 'atendido_en', 'created_by',
    ];

    protected function casts(): array
    {
        return ['atendido_en' => 'datetime'];
    }

    public function enfermera(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enfermera_id');
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function cargaFamiliar(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class);
    }

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(
            CatalogoServicioEnfermeria::class, 'catalogo_servicio_id'
        );
    }
}
