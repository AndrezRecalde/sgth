<?php

namespace App\Models\Asistencia;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Aquí colgaba un `#[ObservedBy(PermisoServidorObserver::class)]` cuyo observer
// estaba vacío desde que se creó: solo tenía un `//`. Un observer registrado y
// sin nada dentro no es un punto de extensión, es una pista falsa — quien
// busque dónde se engancha el ciclo de vida de un permiso lo encuentra y no
// encuentra nada. Las reglas del permiso viven en `PermisoService`.
class PermisoServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'permisos_servidor';

    protected $fillable = [
        'servidor_id',
        'tipo',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'observacion',
        'estado',
        'folio',
        'confirmado_por',
        'confirmado_en',
        'validado_ts_por',
        'validado_ts_en',
        'rechazado_por',
        'rechazado_en',
        'motivo_rechazo',
        'anulado_por',
        'anulado_en',
        'vence_en',
        'unidad_administrativa_id',
        'jefe_id',
        'dirigido_a_talento_humano',
        'creado_por',
        'sirha7_leave_id',
        'sirha7_leave_nombre',
        'sirha7_userid',
        'sirha7_aprobado_por',
        'sirha7_aprobado_en',
        'sirha7_dias_omitidos',
    ];

    /** Lo lee el frontend para ofrecer «Aprobar en Sirha7» solo donde cabe. */
    protected $appends = ['pendiente_sirha7'];

    protected function casts(): array
    {
        return [
            'tipo'           => TipoPermiso::class,
            'estado'         => EstadoPermiso::class,
            'fecha'          => 'date',
            'hora_inicio'    => 'string',
            'hora_fin'       => 'string',
            'confirmado_en'  => 'datetime',
            'validado_ts_en' => 'datetime',
            'rechazado_en'   => 'datetime',
            'dirigido_a_talento_humano' => 'boolean',
            'anulado_en'     => 'datetime',
            'revertido_en'   => 'datetime',
            'vence_en'       => 'datetime',
            'sirha7_aprobado_en'   => 'datetime',
            'sirha7_dias_omitidos' => 'array',
        ];
    }

    // Relaciones
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function validadoTsPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_ts_por');
    }

    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function revertidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertido_por');
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(
            UnidadAdministrativa::class,
            'unidad_administrativa_id'
        );
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'jefe_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'creado_por');
    }

    /** Quien lo aprobó en Sirha7. */
    public function aprobadoSirha7Por(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sirha7_aprobado_por');
    }

    /** Las filas que se escribieron en dbo.USER_SPEDAY al aprobarlo, una por día. */
    public function filasSirha7(): HasMany
    {
        return $this->hasMany(PermisoSirha7Fila::class, 'permiso_servidor_id');
    }

    /**
     * El certificado médico que lo originó, si vino del dispensario. Ahí está
     * el rango de días de reposo: el permiso guarda solo el primero.
     */
    public function certificadoMedico(): HasOne
    {
        return $this->hasOne(CertificadoMedico::class, 'permiso_servidor_id');
    }

    /**
     * ¿Se le puede aprobar en Sirha7 ahora?
     *
     * Confirmado por Recepción (activo) y todavía sin aprobar, y confirmado
     * desde la fecha de corte (`services.biometrico.aprobacion_permisos_desde`):
     * lo anterior ya lo cargó TH a mano (decisión del 2026-10-07). Sin fecha de
     * corte la función está apagada.
     *
     * Es la condición de estado; quién puede aprobar lo dice la policy.
     */
    public function estaPendienteDeSirha7(): bool
    {
        $corte = config('services.biometrico.aprobacion_permisos_desde');

        if (! $corte || $this->sirha7_aprobado_en !== null || $this->confirmado_en === null) {
            return false;
        }

        $estado = $this->estado instanceof EstadoPermiso
            ? $this->estado
            : EstadoPermiso::tryFrom((string) $this->estado);

        return $estado === EstadoPermiso::ACTIVO
            && $this->confirmado_en->greaterThanOrEqualTo(Carbon::parse($corte)->startOfDay());
    }

    protected function pendienteSirha7(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estaPendienteDeSirha7());
    }

    /**
     * De qué períodos de vacaciones salieron sus horas, y cuántas de cada uno.
     *
     * Solo los permisos personales de un servidor LOSEP descuentan, y solo al
     * confirmarse: un permiso rechazado o vencido no tiene ninguna fila aquí.
     * Es por eso la única fuente fiable de qué consumió un permiso — la fecha
     * no lo dice, porque el descuento se reparte del período más antiguo al
     * más nuevo.
     */
    public function descuentos(): HasMany
    {
        return $this->hasMany(PermisoDescuento::class, 'permiso_servidor_id');
    }
}
