<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\CargaFamiliar;

class AgendaMedica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'agendas_medicas';

    protected $fillable = [
        'medico_id', 'servidor_id', 'carga_familiar_id',
        'folio', 'tipo_atencion', 'requiere_triaje',
        'fecha', 'hora_inicio', 'hora_fin', 'estado',
        'motivo_solicitud', 'registrado_en', 'estado_registro',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha'           => 'date',
            'registrado_en'   => 'datetime',
            'requiere_triaje' => 'boolean',
            'estado_registro' => 'boolean',
        ];
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function cargaFamiliar(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class);
    }

    public function paciente(): Servidor|CargaFamiliar|null
    {
        return $this->servidor ?? $this->cargaFamiliar;
    }

    public function triaje(): HasOne
    {
        return $this->hasOne(Triaje::class);
    }
}
