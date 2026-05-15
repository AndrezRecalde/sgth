<?php

namespace App\Models\Catalogo;

use App\Models\Expediente\CuentaBancariaServidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntidadFinanciera extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'entidades_financieras';

    protected $fillable = [
        'nombre',
        'tipo',
        'codigo_bce',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(CuentaBancariaServidor::class);
    }
}
