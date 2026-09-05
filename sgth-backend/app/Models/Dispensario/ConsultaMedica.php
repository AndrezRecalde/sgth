<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Dispensario\DiagnosticoCie10;
use App\Enums\EspecialidadAtencion;
use App\Support\HtmlClinico;

class ConsultaMedica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'consultas_medicas';

    protected $fillable = [
        'historia_clinica_id', 'agenda_medica_id', 'especialidad',
        'medico_id', 'fecha_consulta', 'hora_consulta',
        'motivo_consulta', 'enfermedad_actual',
        'examen_fisico', 'diagnostico_detallado',
        'diagnostico_cie10', 'diagnostico_cie10_id',
        'tipo_atencion', 'tipo_diagnostico',
        'plan_tratamiento', 'notas_medico', 'estado',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'motivo_consulta'       => 'encrypted',
            'enfermedad_actual'     => 'encrypted',
            'examen_fisico'         => 'encrypted',
            'diagnostico_detallado' => 'encrypted',
            'plan_tratamiento'      => 'encrypted',
            'notas_medico'          => 'encrypted',
            'fecha_consulta'        => 'date',
            'especialidad'          => EspecialidadAtencion::class,
            'estado'                => 'boolean',
        ];
    }

    /**
     * Los campos que el médico escribe con el editor enriquecido y que, por
     * tanto, llegan como HTML.
     *
     * @var list<string>
     */
    public const CAMPOS_HTML = [
        'enfermedad_actual', 'plan_tratamiento',
    ];

    /**
     * Los campos que componen la nota clínica: lo que se versiona al corregir.
     *
     * @var list<string>
     */
    public const CAMPOS_CLINICOS = [
        'tipo_atencion', 'tipo_diagnostico',
        'motivo_consulta', 'enfermedad_actual', 'examen_fisico',
        'diagnostico_detallado', 'plan_tratamiento', 'notas_medico',
        'diagnostico_cie10_id',
    ];

    /**
     * Pasa por el saneador los campos que vienen como HTML.
     *
     * Los demás se guardan tal cual: son texto plano y la pantalla los pinta
     * como texto, sin interpretarlos.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>  solo los campos HTML presentes, ya limpios
     */
    public static function limpiarCamposHtml(array $datos): array
    {
        $limpios = [];

        foreach (self::CAMPOS_HTML as $campo) {
            if (array_key_exists($campo, $datos) && is_string($datos[$campo])) {
                $limpios[$campo] = HtmlClinico::limpiar($datos[$campo]);
            }
        }

        return $limpios;
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    /**
     * Las versiones anteriores de la nota, de la más reciente a la más
     * antigua. Vacío mientras nadie la haya corregido.
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(VersionConsultaMedica::class)
            ->orderByDesc('id');
    }

    public function agendaMedica(): BelongsTo
    {
        return $this->belongsTo(AgendaMedica::class);
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    public function recetasMedicas(): HasMany
    {
        return $this->hasMany(RecetaMedica::class);
    }

    public function diagnosticosSecundarios(): HasMany
    {
        return $this->hasMany(
            DiagnosticoSecundarioConsulta::class
        );
    }

    public function diagnosticoCie10Principal(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosticoCie10::class, 'diagnostico_cie10_id'
        );
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoMedico::class);
    }
}
