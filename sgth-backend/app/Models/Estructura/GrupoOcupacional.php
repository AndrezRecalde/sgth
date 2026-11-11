<?php
namespace App\Models\Estructura;

use App\Enums\NivelCompetencia;
use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoOcupacional extends Model
{
    use HasFactory;

    protected $table = 'grupos_ocupacionales';

    protected $fillable = [
        'grado_codigo',
        'grado_numerico',
        'grupo',
        'denominacion_generica',
        'rmu',
        'regimen',
        'nivel_complejidad',
        'rol_puesto',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'rmu'              => 'decimal:2',
            'activo'           => 'boolean',
            'nivel_complejidad' => NivelComplejidadPuesto::class,
            'rol_puesto'       => RolPuesto::class,
        ];
    }

    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class);
    }

    /**
     * Los grupos NJS-1 a NJS-10 (diseño de Acciones de Personal, 8.4). No hay
     * columna: lo dice el código del grado, que es el de la escala del
     * Ministerio del Trabajo. Lo usan la comisión sin remuneración, que nunca
     * es para estos puestos (LOSEP 31), y más adelante la subrogación.
     */
    public function esNivelJerarquicoSuperior(): bool
    {
        return str_starts_with(strtoupper((string) $this->grado_codigo), 'NJS');
    }

    /**
     * El nivel en que se sugiere cada competencia de un puesto de este grado
     * (docs/diseno-manual-de-puestos.md, P2; TH, D5, 2026-11-10). Es el que más
     * usa el Manual de Puestos en 13 de sus 15 grados:
     *
     * - hasta el grado 7 (los de apoyo y SP1), bajo;
     * - del 8 al 13 (SP2 a SP7), medio;
     * - del 14 en adelante (SP8 y SP9), alto.
     *
     * El Nivel Jerárquico Superior numera sus grados aparte (NJS-5 es el 5), y
     * en el manual sus fichas van en alto. Fuera de la escala de la LOSEP —los
     * obreros— no hay grado del que sacarlo: `null`.
     */
    public function nivelDeCompetenciaSugerido(): ?NivelCompetencia
    {
        if ($this->esNivelJerarquicoSuperior()) {
            return NivelCompetencia::ALTO;
        }

        if (! $this->esLosep() || $this->grado_numerico === null) {
            return null;
        }

        return match (true) {
            $this->grado_numerico <= 7  => NivelCompetencia::BAJO,
            $this->grado_numerico <= 13 => NivelCompetencia::MEDIO,
            default                     => NivelCompetencia::ALTO,
        };
    }

    public function esLosep(): bool
    {
        return $this->regimen === 'losep';
    }

    public function esCodigoTrabajo(): bool
    {
        return $this->regimen === 'codigo_trabajo';
    }
}
