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
            'fusionada_en' => 'datetime',
        ];
    }

    /** La historia en la que se fusionó esta, si es una retirada por `sgth:historias:fusionar`. */
    public function fusionadaCon(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fusionada_con_id');
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

    /**
     * La historia de un servidor o de un familiar, buscada por su dueño.
     *
     * Si quedó más de una —lo que corrige `sgth:historias:fusionar`—, la que
     * tiene cédula y, entre ellas, la más antigua: así todas las pantallas
     * eligen la misma. Antes cada una tomaba la que le devolvía la base, y una
     * misma atención llegó a quedar con el triaje en una y la consulta en otra.
     */
    public static function delDueno(?int $servidorId, ?int $cargaId): ?self
    {
        if ($servidorId === null && $cargaId === null) {
            return null;
        }

        return self::query()
            ->where($servidorId !== null ? 'servidor_id' : 'carga_familiar_id', $servidorId ?? $cargaId)
            ->orderByRaw('cedula_paciente IS NULL')
            ->orderBy('id')
            ->first();
    }

    /**
     * La historia del paciente, o una nueva si no tiene.
     *
     * Primero por su dueño y después por la cédula. Solo por la cédula, a un
     * servidor con una historia sin cédula —las que abría la recepción antes
     * del 2026-09-05— o con la cédula corregida en el expediente se le abría
     * una segunda.
     */
    public static function buscarOCrearPorCedula(
        string $cedula,
        string $tipoPaciente = 'servidor',
        ?int $servidorId = null,
        ?int $cargaId = null,
        ?int $userId = null
    ): self {
        $delDueno = self::delDueno($servidorId, $cargaId);

        if ($delDueno) {
            // La que no tenía cédula la estrena, si nadie más la tiene.
            if ($delDueno->cedula_paciente === null && ! self::withTrashed()->where('cedula_paciente', $cedula)->exists()) {
                $delDueno->update([
                    'cedula_paciente' => $cedula,
                    'numero_historia' => $delDueno->numero_historia ?? $cedula,
                ]);
            }

            return $delDueno;
        }

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

    /**
     * Cuando Talento Humano corrige la cédula de un servidor o de un familiar,
     * su historia la sigue: se busca por ella en la recepción y en la ficha
     * ocupacional, y con la vieja ya no se encontraba y se abría otra.
     *
     * Si la nueva ya la tiene otra historia —la del preocupacional de esa
     * persona, por ejemplo— no se toca: son dos historias de la misma persona
     * y las junta `sgth:historias:fusionar`.
     */
    public static function seguirCedula(?int $servidorId, ?int $cargaId, ?string $anterior, ?string $nueva): void
    {
        $historia = self::delDueno($servidorId, $cargaId);

        if (! $historia || ! $nueva || $historia->cedula_paciente === $nueva
            || self::withTrashed()->where('cedula_paciente', $nueva)->exists()) {
            return;
        }

        $historia->update([
            'cedula_paciente' => $nueva,
            // El número es la cédula; uno distinto (el de una siembra) se respeta.
            'numero_historia' => in_array($historia->numero_historia, [null, $anterior], true)
                ? $nueva
                : $historia->numero_historia,
        ]);
    }
}
