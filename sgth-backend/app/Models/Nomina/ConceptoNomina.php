<?php

namespace App\Models\Nomina;

use App\Enums\TipoConcepto;
use App\Observers\Nomina\ConceptoNominaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ConceptoNominaObserver::class)]
class ConceptoNomina extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'conceptos_nomina';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'formula',
        'porcentaje',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo'       => TipoConcepto::class,
            'porcentaje' => 'float',
            'activo'     => 'boolean',
        ];
    }
}
