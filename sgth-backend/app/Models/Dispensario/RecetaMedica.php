<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class RecetaMedica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'recetas_medicas';

    protected $fillable = [
        'consulta_medica_id', 'fecha_emision', 'estado',
        'indicaciones_generales', 'despachado_por', 'despachado_en',
        'anulado_en', 'anulado_por', 'motivo_anulacion',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'despachado_en' => 'datetime',
            'anulado_en'    => 'datetime',
        ];
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemReceta::class);
    }

    public function despachador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'despachado_por');
    }
}
