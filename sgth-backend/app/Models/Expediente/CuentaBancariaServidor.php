<?php

namespace App\Models\Expediente;

use App\Models\Catalogo\EntidadFinanciera;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CuentaBancariaServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cuentas_bancarias_servidor';

    protected $fillable = [
        'servidor_id',
        'entidad_financiera_id',
        'nombre_banco_otro',
        'tipo_cuenta',
        'numero_cuenta',
        'proposito',
        'es_principal_sueldo',
        'es_principal_viatico',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'es_principal_sueldo'  => 'boolean',
            'es_principal_viatico' => 'boolean',
            'estado'               => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function entidadFinanciera(): BelongsTo
    {
        return $this->belongsTo(EntidadFinanciera::class);
    }
}
