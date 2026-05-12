<?php

namespace App\Models\Sgd;

use App\Enums\EstadoTramite;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Sgd\TramiteObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(TramiteObserver::class)]
class Tramite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tramites';

    protected $fillable = [
        'expediente_id',
        'codigo',
        'asunto',
        'estado',
        'solicitante_id',
        'responsable_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoTramite::class,
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ExpedienteElectronico::class, 'expediente_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'solicitante_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoInstitucional::class, 'tramite_id');
    }
}
