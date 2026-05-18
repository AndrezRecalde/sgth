<?php

namespace App\Models\Expediente;

use App\Enums\RegimenLaboral;
use App\Enums\TipoDiscapacidad;
use App\Enums\TipoNombramiento;
use App\Models\Dispensario\Beneficiario;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
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
        'user_id',
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
        'telefono_celular', 'telefono_convencional', 'correo_institucional', 'correo_personal',
        'direccion_domicilio',
        // Sección D
        'tiene_discapacidad',
        // Sección E
        'tiene_enfermedad_catastrofica',
        // Sección F
        'tipo_nombramiento', 'numero_contrato', 'fecha_ingreso_institucion', 'fecha_ingreso_sector_publico',
        'fecha_nombramiento', 'fecha_inicio_ultimo_contrato', 'fecha_fin_ultimo_contrato'
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
            'fecha_nacimiento'              => 'date',
            'pasaporte_vencimiento'         => 'date',
            'fecha_ingreso_institucion'     => 'date',
            'fecha_ingreso_sector_publico'  => 'date',
            'fecha_nombramiento'            => 'date',
            'fecha_inicio_ultimo_contrato'  => 'date',
            'fecha_fin_ultimo_contrato'     => 'date',
        ];
    }

    /**
     * Cuenta de usuario del servidor
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function beneficiarios(): HasMany
    {
        return $this->hasMany(Beneficiario::class);
    }

    public function beneficiariosActivos(): HasMany
    {
        return $this->hasMany(Beneficiario::class)->activos();
    }

    public function discapacidades(): HasMany
    {
        return $this->hasMany(DiscapacidadServidor::class);
    }

    public function enfermedadesCatastroficas(): HasMany
    {
        return $this->hasMany(EnfermedadCatastroficaServidor::class);
    }

    public function contratoVigente()
    {
        return $this->hasOne(ContratoServidor::class)->ofMany(
            ['fecha_inicio' => 'max', 'id' => 'max'],
            function ($query) {
                $query->where('estado', 'vigente')
                      ->where(function ($q) {
                          $q->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now()->toDateString());
                      });
            }
        );
    }

    public function codigoMarcacionVigente(): ?string
    {
        return $this->contratoVigente?->codigo_marcacion;
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

    public function scopePorCodigo(Builder $query, string $codigo): Builder
    {
        return $query->whereHas('contratos', function (Builder $q) use ($codigo) {
            $q->where('codigo_marcacion', $codigo)->where('estado', 'vigente');
        });
    }
}
