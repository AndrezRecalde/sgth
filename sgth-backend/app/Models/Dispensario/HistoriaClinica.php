<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\CargaFamiliar;

class HistoriaClinica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historias_clinicas';

    protected $fillable = [
        'servidor_id', 'carga_familiar_id',
        'medicacion_habitual', 'grupo_sanguineo', 'estado',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'medicacion_habitual' => 'encrypted',
            'estado'              => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function cargaFamiliar(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class);
    }

    public function consultasMedicas(): HasMany
    {
        return $this->hasMany(ConsultaMedica::class);
    }

    public function propietario(): Servidor|CargaFamiliar|null
    {
        return $this->servidor ?? $this->cargaFamiliar;
    }

    public function alergias(): HasMany
    {
        return $this->hasMany(AlergiaPaciente::class);
    }

    public function antecedentes(): HasMany
    {
        return $this->hasMany(AntecedentePaciente::class);
    }
}
