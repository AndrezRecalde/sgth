<?php

namespace App\Models\Comunicacion;

use App\Enums\EstadoCredencial;
use App\Enums\MotivoRevocacionCredencial;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * La credencial de un servidor (docs/diseno-comunicacion-social.md, 6).
 *
 * Nace sola cuando el servidor tiene vínculo vigente y foto, y la mantiene al
 * día CredencialService: vence con la fecha de fin del contrato y se anula
 * cuando el vínculo termina. Comunicación Social la imprime, la revoca —y con
 * eso la repone con otro código— y recibe la tarjeta cuando el servidor sale.
 *
 * Lo que se enseña (nombre, cargo, unidad, contacto de emergencia) no se
 * guarda aquí: se lee del expediente, siempre actual. Lo que se imprimió queda
 * en cada impresión.
 */
class Credencial extends Model
{
    use LogsActivity;

    protected $table = 'credenciales';

    protected $fillable = [
        'servidor_id', 'codigo', 'estado', 'valida_hasta', 'reemplaza_a_id',
        'revocada_en', 'revocada_por', 'motivo_revocacion', 'detalle_revocacion',
        'anulada_en', 'devuelta_en', 'devolucion_registrada_por', 'devolucion_nota',
    ];

    protected function casts(): array
    {
        return [
            'estado'            => EstadoCredencial::class,
            'motivo_revocacion' => MotivoRevocacionCredencial::class,
            'valida_hasta'      => 'date',
            'revocada_en'       => 'datetime',
            'anulada_en'        => 'datetime',
            'devuelta_en'       => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class)->withTrashed();
    }

    public function impresiones(): HasMany
    {
        return $this->hasMany(ImpresionCredencial::class);
    }

    public function ultimaImpresion(): HasOne
    {
        return $this->hasOne(ImpresionCredencial::class)->ofMany(['created_at' => 'max', 'id' => 'max']);
    }

    public function reemplazaA(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplaza_a_id');
    }

    public function revocadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revocada_por');
    }

    public function devolucionRegistradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devolucion_registrada_por');
    }

    public function scopeVivas(Builder $query): Builder
    {
        return $query->whereIn('estado', EstadoCredencial::vivas());
    }

    /**
     * ¿Valida hoy? Además del estado mira la fecha: si la tarea de la noche no
     * corrió, una tarjeta cuyo contrato ya terminó no pasa por vigente.
     */
    public function estaVigente(): bool
    {
        return $this->estado === EstadoCredencial::VIGENTE
            && ($this->valida_hasta === null || $this->valida_hasta->gte(Carbon::today()));
    }

    public function urlVerificacion(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/verificar/credencial/'.$this->codigo;
    }

    /**
     * Cada cambio de estado, la revocación y la devolución, con quién. El
     * código no: es lo que valida la tarjeta y no tiene por qué repetirse en
     * el registro de actividad.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'servidor_id', 'estado', 'valida_hasta', 'reemplaza_a_id', 'motivo_revocacion',
                'detalle_revocacion', 'devuelta_en', 'devolucion_nota',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
