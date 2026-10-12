<?php

namespace App\Models\Asistencia;

use App\Enums\CausaLicencia;
use App\Models\Expediente\ClausulaDeLicencia;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Una licencia con remuneración (LOSEP, Art. 27; Código del Trabajo, Art. 152;
 * TH N12; fase 5.1): con su causa, su tope y su respaldo, sin acción de
 * personal y sin descontar vacaciones.
 *
 * La registra Talento Humano y queda pendiente; al aprobarla se escribe en
 * Sirha7, como los certificados médicos. Ocupa todos sus días desde que se
 * registra: sobre ella no caben vacaciones, permisos, reposos ni otra licencia.
 *
 * A un obrero, el contrato colectivo puede darle más días o una causa que la
 * ley no tiene (fase 5.3): entonces la licencia guarda la cláusula que la
 * respalda, y su nombre, su base y su tope salen de ella.
 */
class Licencia extends Model
{
    use LogsActivity;

    public const REGISTRO_SGTH   = 'sgth';
    public const REGISTRO_MANUAL = 'manual';

    protected $table = 'licencias';

    protected $fillable = [
        'folio',
        'servidor_id',
        'causa',
        'alcance',
        'clausula_de_licencia_id',
        'fecha_inicio',
        'fecha_fin',
        'dias',
        'observacion',
        'documento_ruta',
        'documento_nombre',
        'created_by',
        'updated_by',
    ];

    /** La ruta del archivo no sale del API: se descarga por su endpoint. */
    protected $hidden = ['documento_ruta'];

    protected function casts(): array
    {
        return [
            'causa'                => CausaLicencia::class,
            'fecha_inicio'         => 'date',
            'fecha_fin'            => 'date',
            'dias'                 => 'integer',
            'aprobado_en'          => 'datetime',
            'anulado_en'           => 'datetime',
            'sirha7_leave_id'      => 'integer',
            'sirha7_userid'        => 'integer',
            'sirha7_dias_omitidos' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['causa', 'alcance', 'clausula_de_licencia_id', 'fecha_inicio', 'fecha_fin', 'aprobado_en', 'registro_sirha7', 'anulado_en', 'motivo_anulacion'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Las que no están anuladas y tocan alguno de esos días. */
    public function scopeVigentesDe(Builder $query, int $servidorId, string $desde, string $hasta): Builder
    {
        return $query->where('servidor_id', $servidorId)
            ->whereNull('anulado_en')
            ->whereDate('fecha_inicio', '<=', $hasta)
            ->whereDate('fecha_fin', '>=', $desde)
            ->orderBy('fecha_inicio')
            ->orderBy('id');
    }

    /** Su causa en palabras: la de la ley, o la que el contrato colectivo nombra. */
    public function etiquetaDeLaCausa(): string
    {
        return $this->causa === CausaLicencia::PACTADA
            ? ($this->clausulaDeLicencia?->nombre ?? $this->causa->etiqueta())
            : $this->causa->etiqueta();
    }

    /** La ley, la cláusula del contrato colectivo, o las dos. */
    public function baseLegal(): string
    {
        $clausula = $this->clausulaDeLicencia;

        return match (true) {
            $clausula === null                     => $this->causa->baseLegal(),
            $this->causa === CausaLicencia::PACTADA => $clausula->baseLegal(),
            default                                => $this->causa->baseLegal().'; '.$clausula->baseLegal(),
        };
    }

    /**
     * Los días que admitía al registrarse: los de la ley, o los de la cláusula
     * que la respalda —que solo se guarda cuando da más—. Null si no tiene tope.
     */
    public function tope(): ?int
    {
        return $this->clausulaDeLicencia?->dias ?? $this->causa->tope($this->alcance);
    }

    /** @return 'pendiente'|'aprobada'|'anulada' */
    public function estado(): string
    {
        return match (true) {
            $this->anulado_en !== null  => 'anulada',
            $this->aprobado_en !== null => 'aprobada',
            default                     => 'pendiente',
        };
    }

    public function estaPendienteDeAprobacion(): bool
    {
        return $this->estado() === 'pendiente';
    }

    /**
     * ¿La puede escribir el SGTH en Sirha7, o solo cabe aprobarla a mano?
     * Como los permisos y los certificados: solo lo registrado desde la fecha
     * de corte; sin ella, la escritura está apagada.
     */
    public function seRegistraDesdeElSgth(): bool
    {
        $corte = config('services.biometrico.aprobacion_permisos_desde');

        return $corte
            && $this->created_at !== null
            && $this->created_at->gte(Carbon::parse($corte)->startOfDay());
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function clausulaDeLicencia(): BelongsTo
    {
        return $this->belongsTo(ClausulaDeLicencia::class);
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Lo escrito en dbo.USER_SPEDAY al aprobarla: una fila por día, con la referencia de su tramo. */
    public function filasSirha7(): HasMany
    {
        return $this->hasMany(LicenciaSirha7Fila::class);
    }
}
