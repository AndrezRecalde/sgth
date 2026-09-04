<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'marcado_no_presentado_en', 'marcado_no_presentado_por',
        'reactivado_en', 'reactivado_por',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha'                      => 'date',
            'registrado_en'              => 'datetime',
            'marcado_no_presentado_en'   => 'datetime',
            'reactivado_en'              => 'datetime',
            'requiere_triaje'            => 'boolean',
            'estado_registro'            => 'boolean',
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

    /**
     * Todas las tomas de signos vitales del turno, de la más antigua a la más
     * reciente. Un turno puede tener varias: una corrección de digitación, o
     * una segunda medición porque el paciente llevaba rato esperando.
     */
    public function triajes(): HasMany
    {
        return $this->hasMany(Triaje::class)->orderBy('id');
    }

    /**
     * La toma vigente, que es la última. Antes esto era un `hasOne` a secas y
     * rehacer el triaje sobrescribía la fila anterior: la lectura previa
     * desaparecía sin dejar constancia de que existió.
     */
    public function triaje(): HasOne
    {
        return $this->hasOne(Triaje::class)->latestOfMany();
    }

    public function consultaMedica(): HasOne
    {
        return $this->hasOne(ConsultaMedica::class);
    }

    public function marcadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class, 'marcado_no_presentado_por'
        );
    }

    public function reactivadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivado_por');
    }
}
