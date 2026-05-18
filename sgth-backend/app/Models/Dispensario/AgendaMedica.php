<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;
use App\Models\Expediente\Servidor;

class AgendaMedica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'agendas_medicas';

    protected $fillable = [
        'medico_id', 'servidor_id', 'beneficiario_id', 'fecha', 'hora_inicio',
        'hora_fin', 'estado', 'motivo_solicitud', 'estado_registro',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha'           => 'date',
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

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    public function paciente()
    {
        return $this->servidor ?? $this->beneficiario;
    }

    public function triaje(): HasOne
    {
        return $this->hasOne(Triaje::class);
    }
}
