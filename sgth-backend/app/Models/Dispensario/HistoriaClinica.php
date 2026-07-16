<?php

namespace App\Models\Dispensario;

use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;

class HistoriaClinica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historias_clinicas';

    protected $fillable = [
        'numero_historia',
        'cedula_paciente',
        'tipo_paciente',
        'servidor_id',
        'carga_familiar_id',
        'medicacion_habitual',
        'grupo_sanguineo',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'medicacion_habitual' => 'encrypted',
            'estado' => 'boolean',
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

    public function alergias(): HasMany
    {
        return $this->hasMany(AlergiaPaciente::class);
    }

    public function antecedentes(): HasMany
    {
        return $this->hasMany(AntecedentePaciente::class);
    }

    public function propietario(): Servidor|CargaFamiliar|null
    {
        return $this->servidor ?? $this->cargaFamiliar;
    }

    public function getNombrePacienteAttribute(): string
    {
        if ($this->servidor) {
            return trim("{$this->servidor->nombre} {$this->servidor->apellido}");
        }
        if ($this->cargaFamiliar) {
            return trim("{$this->cargaFamiliar->nombres} {$this->cargaFamiliar->apellidos}");
        }

        return $this->cedula_paciente ?? '—';
    }

    public static function buscarOCrearPorCedula(
        string $cedula,
        string $tipoPaciente = 'servidor',
        ?int $servidorId = null,
        ?int $cargaId = null,
        ?int $userId = null
    ): self {
        $hce = self::where('cedula_paciente', $cedula)->first();

        if ($hce) {
            return $hce;
        }

        try {
            return self::create([
                'numero_historia' => $cedula,
                'cedula_paciente' => $cedula,
                'tipo_paciente' => $tipoPaciente,
                'servidor_id' => $servidorId,
                'carga_familiar_id' => $cargaId,
                'estado' => true,
                'created_by' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Petición concurrente ya creó la HCE para esta cédula
            // (ej. doble invocación de efectos en el cliente).
            return self::where('cedula_paciente', $cedula)->firstOrFail();
        }
    }
}
