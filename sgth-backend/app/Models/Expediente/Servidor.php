<?php

namespace App\Models\Expediente;

use App\Enums\RegimenLaboral;
use App\Enums\TipoDiscapacidad;
use App\Enums\TipoNombramiento;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Geografia\Canton;
use App\Models\Geografia\Provincia;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(\App\Observers\ServidorObserver::class)]
class Servidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'servidores';

    protected $fillable = [
        'cedula',
        'nombre',
        'segundo_nombre',
        'apellido',
        'segundo_apellido',
        'regimen_laboral',
        'unidad_administrativa_id',
        'puesto_id',
        'estado',
        // Sección A
        'fecha_nacimiento', 'genero', 'estado_civil', 'tipo_sangre', 'es_extranjero', 
        'nacionalidad', 'pais_origen', 'provincia_nacimiento_id', 'canton_nacimiento_id',
        // Sección B
        'numero_papeleta_votacion', 'pasaporte_numero', 'pasaporte_vencimiento',
        // Sección C
        'telefono_celular', 'telefono_convencional', 'correo_personal', 'codigo_medico',
        'direccion_domicilio',
        'contacto_emergencia_nombre', 'contacto_emergencia_parentesco', 'contacto_emergencia_telefono',
        // Sección D
        'tiene_discapacidad',
        // Sección E
        'tiene_enfermedad_catastrofica',
        // Sección F
        'tipo_nombramiento', 'fecha_ingreso_institucion', 'fecha_ingreso_sector_publico',
        'fecha_nombramiento', 'puede_marcar'
    ];

    protected function casts(): array
    {
        return [
            'regimen_laboral'               => RegimenLaboral::class,
            'tipo_nombramiento'             => TipoNombramiento::class,
            'estado'                        => 'boolean',
            'es_extranjero'                 => 'boolean',
            'tiene_discapacidad'            => 'boolean',
            'tiene_enfermedad_catastrofica' => 'boolean',
            'puede_marcar'                  => 'boolean',
            'fecha_nacimiento'              => 'date',
            'pasaporte_vencimiento'         => 'date',
            'fecha_ingreso_institucion'     => 'date',
            'fecha_ingreso_sector_publico'  => 'date',
            'fecha_nombramiento'            => 'date',
        ];
    }

    /**
     * Cuenta de usuario del servidor
     */
    public function usuario(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'servidor_id');
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    public function provinciaNacimiento(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_nacimiento_id');
    }

    public function cantonNacimiento(): BelongsTo
    {
        return $this->belongsTo(Canton::class, 'canton_nacimiento_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoServidor::class);
    }

    public function expedientesDisciplinarios(): HasMany
    {
        return $this->hasMany(ExpedienteDisciplinario::class);
    }

    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(CuentaBancariaServidor::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoPersonal::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(ContratoServidor::class);
    }

    public function historiaClinica(): HasOne
    {
        return $this->hasOne(HistoriaClinica::class, 'servidor_id');
    }



    public function discapacidades(): HasMany
    {
        return $this->hasMany(DiscapacidadServidor::class);
    }

    public function enfermedadesCatastroficas(): HasMany
    {
        return $this->hasMany(EnfermedadCatastroficaServidor::class);
    }

    public function historialAcademico(): HasMany
    {
        return $this->hasMany(HistorialAcademicoServidor::class);
    }

    public function cargasFamiliares(): HasMany
    {
        return $this->hasMany(CargaFamiliar::class);
    }

    public function declaracionesJuramentadas(): HasMany
    {
        return $this->hasMany(DeclaracionJuramentada::class);
    }

    public function contratoVigente(): HasOne
    {
        return $this->hasOne(ContratoServidor::class)
            ->where('estado', 'vigente')
            ->orderByDesc('fecha_inicio')
            ->with(['puesto.cargo', 'unidadAdministrativa'])
            ->limit(1);
    }

    public function codigoMarcacionVigente(): ?string
    {
        return $this->cedula;
    }

    /**
     * Tiempo de servicio EN LA INSTITUCIÓN, en años cumplidos.
     *
     * Son dos conceptos distintos y los dos son legítimos, así que conviene no
     * volver a mezclarlos:
     *
     * - **Antigüedad en el sector público.** De ella cuelgan derechos: en
     *   régimen LOSEP los días de vacaciones se calculan sobre el tiempo
     *   acumulado en TODO el sector público, no solo aquí. Eso vive donde
     *   corresponde, en `PeriodoVacacionService::calcularAntiguedad`, y no se
     *   toca.
     * - **Tiempo de servicio en la institución.** Es lo que el GAD certifica:
     *   cuánto lleva esta persona trabajando AQUÍ.
     *
     * Este accesor es el segundo. Contaba desde el sector público en régimen
     * LOSEP, y como solo se muestra en la ficha del expediente, un servidor
     * LOSEP veía en pantalla una cifra y otra distinta en su certificado
     * laboral — que sí cuenta desde el ingreso a la institución, porque así lo
     * pidió la UATH. La incoherencia la confirmaron ellos el 2026-09-26.
     *
     * Años cumplidos, enteros: `diffInYears()` devuelve un float desde Carbon 3
     * y el expediente llegó a mostrar «9.034447289411942 años».
     */
    protected function aniosServicio(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->fecha_ingreso_institucion) {
                    return null;
                }

                return (int) floor(
                    \Carbon\Carbon::parse($this->fecha_ingreso_institucion)->diffInYears(now())
                );
            }
        );
    }

    /**
     * Accessor para el nombre completo.
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // Combina los nombres limpiando espacios extra si los campos nulos están vacíos
                $partes = [
                    $attributes['nombre'] ?? '',
                    $attributes['segundo_nombre'] ?? '',
                    $attributes['apellido'] ?? '',
                    $attributes['segundo_apellido'] ?? ''
                ];
                
                // Filtrar elementos vacíos y unir con un espacio
                return implode(' ', array_filter($partes));
            }
        );
    }

    /**
     * Scopes locales
     */
    public function scopeConDiscapacidad(Builder $query): Builder
    {
        return $query->where('tiene_discapacidad', true);
    }

    public function scopeConEnfermedadCatastrofica(Builder $query): Builder
    {
        return $query->where('tiene_enfermedad_catastrofica', true);
    }

    public function scopePorCedula(Builder $query, string $cedula): Builder
    {
        return $query->where('cedula', $cedula);
    }
}
