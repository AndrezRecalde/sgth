<?php

namespace App\Models\Comunicados;

use App\Enums\AudienciaComunicado;
use App\Enums\EstadoComunicado;
use App\Enums\TipoComunicado;
use App\Enums\VigenciaComunicado;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use App\Support\HtmlEnriquecido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Una noticia o un comunicado de Talento Humano
 * (docs/diseno-noticias-comunicados.md).
 *
 * Solo el borrador se edita. Lo publicado queda congelado salvo el fijado y la
 * fecha de fin; para corregirlo se retira y se duplica (D5). Las reglas viven
 * en ComunicadoService; aquí, las relaciones y la vigencia, que se deduce de
 * las fechas.
 */
class Comunicado extends Model
{
    use LogsActivity;

    /**
     * Cuántos la verían hoy. No es una columna: lo calcula el listado con
     * DestinatariosComunicadoService, porque depende de quién trabaja hoy.
     */
    public ?int $totalDestinatarios = null;

    protected $table = 'comunicados';

    protected $fillable = [
        'tipo', 'titulo', 'resumen', 'cuerpo', 'referencia',
        'estado', 'audiencia', 'regimenes', 'publicado_desde', 'publicado_hasta',
        'fijado', 'requiere_confirmacion', 'duplicado_de_id',
        'creado_por', 'publicado_por', 'publicado_at',
        'retirado_por', 'retirado_at', 'motivo_retiro',
    ];

    protected function casts(): array
    {
        return [
            'tipo'                  => TipoComunicado::class,
            'estado'                => EstadoComunicado::class,
            'audiencia'             => AudienciaComunicado::class,
            'regimenes'             => 'array',
            'publicado_desde'       => 'datetime',
            'publicado_hasta'       => 'datetime',
            'publicado_at'          => 'datetime',
            'retirado_at'           => 'datetime',
            'fijado'                => 'boolean',
            'requiere_confirmacion' => 'boolean',
        ];
    }

    /** Todas las unidades a las que va: las elegidas y, publicado, sus dependencias. */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(UnidadAdministrativa::class, 'comunicado_unidades')
            ->withPivot(['seleccionada', 'incluye_dependencias']);
    }

    /** Las que eligió Talento Humano, que es lo que se edita y se duplica. */
    public function unidadesSeleccionadas(): BelongsToMany
    {
        return $this->unidades()->wherePivot('seleccionada', true);
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(ComunicadoAdjunto::class)->orderBy('orden')->orderBy('id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(ComunicadoLectura::class);
    }

    public function duplicadoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicado_de_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function retiradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retirado_por');
    }

    /**
     * Lo que sale en la tarjeta del Portal y en la campana: el resumen si lo
     * escribieron; si no, el comienzo del texto. El resumen es opcional porque
     * casi siempre basta el texto, y existe porque un comunicado oficial suele
     * empezar con el saludo, que no dice nada de lo que se comunica.
     */
    public function textoDeTarjeta(): string
    {
        $resumen = trim((string) $this->resumen);

        return $resumen !== '' ? $resumen : HtmlEnriquecido::extracto($this->cuerpo);
    }

    public function esBorrador(): bool
    {
        return $this->estado === EstadoComunicado::BORRADOR;
    }

    /** Lo que se enseña: el estado guardado leído contra la fecha de hoy. */
    public function vigencia(?Carbon $ahora = null): VigenciaComunicado
    {
        $ahora ??= Carbon::now();

        return match ($this->estado) {
            EstadoComunicado::BORRADOR => VigenciaComunicado::BORRADOR,
            EstadoComunicado::RETIRADO => VigenciaComunicado::RETIRADO,
            EstadoComunicado::PUBLICADO => match (true) {
                $this->publicado_desde !== null && $this->publicado_desde->gt($ahora) => VigenciaComunicado::PROGRAMADO,
                $this->publicado_hasta !== null && $this->publicado_hasta->lt($ahora) => VigenciaComunicado::VENCIDO,
                default => VigenciaComunicado::PUBLICADO,
            },
        };
    }

    /** El mismo cálculo que vigencia(), en SQL, para filtrar el listado. */
    public function scopeEnVigencia(Builder $query, VigenciaComunicado $vigencia): Builder
    {
        $ahora = Carbon::now();
        $publicado = EstadoComunicado::PUBLICADO->value;

        return match ($vigencia) {
            VigenciaComunicado::BORRADOR => $query->where('estado', EstadoComunicado::BORRADOR->value),
            VigenciaComunicado::RETIRADO => $query->where('estado', EstadoComunicado::RETIRADO->value),
            VigenciaComunicado::PROGRAMADO => $query->where('estado', $publicado)
                ->where('publicado_desde', '>', $ahora),
            VigenciaComunicado::VENCIDO => $query->where('estado', $publicado)
                ->where('publicado_hasta', '<', $ahora),
            VigenciaComunicado::PUBLICADO => $query->where('estado', $publicado)
                ->where(fn ($q) => $q->whereNull('publicado_desde')->orWhere('publicado_desde', '<=', $ahora))
                ->where(fn ($q) => $q->whereNull('publicado_hasta')->orWhere('publicado_hasta', '>=', $ahora)),
        };
    }

    /**
     * Los fijados que cuentan para el tope de tres (3.5): publicados que no
     * vencieron. Uno programado también ocupa su lugar: saldrá fijado.
     */
    public function scopeFijadosQueCuentan(Builder $query): Builder
    {
        return $query->where('estado', EstadoComunicado::PUBLICADO->value)
            ->where('fijado', true)
            ->where(fn ($q) => $q->whereNull('publicado_hasta')->orWhere('publicado_hasta', '>=', Carbon::now()));
    }

    /**
     * Lo que cambia el estado o lo que ve el servidor. El cuerpo no: es largo,
     * y lo publicado ya no se edita.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tipo', 'titulo', 'estado', 'audiencia', 'regimenes', 'publicado_desde',
                'publicado_hasta', 'fijado', 'requiere_confirmacion', 'motivo_retiro',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
