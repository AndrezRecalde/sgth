<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Expediente\Servidor;

class HistoriaClinica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historias_clinicas';

    protected $fillable = [
        'servidor_id', 'beneficiario_id',
        'medicacion_habitual', 'grupo_sanguineo', 'estado',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            // Cifrado estricto de campos de salud
            'medicacion_habitual'     => 'encrypted',
            'estado'                  => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function consultasMedicas(): HasMany
    {
        return $this->hasMany(ConsultaMedica::class);
    }

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    public function propietario()
    {
        return $this->servidor ?? $this->beneficiario;
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
