<?php

namespace App\Models\Asistencia;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioPermiso extends Model
{
    use HasFactory;

    protected $table = 'folios_permiso';

    // Registro de control de trazabilidad inmutable: SIN SoftDeletes

    protected $fillable = [
        'permiso_id',
        'folio',
        'qr_ruta',
    ];

    // Relaciones
    public function permiso(): BelongsTo
    {
        return $this->belongsTo(PermisoServidor::class, 'permiso_id');
    }
}
