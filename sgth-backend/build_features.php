<?php
$files = [
    'app/Enums/TipoEstudio.php' => <<<'EOT'
<?php

namespace App\Enums;

enum TipoEstudio: string
{
    case ESTUDIO      = 'estudio';
    case CAPACITACION = 'capacitacion';

    public function etiqueta(): string
    {
        return match($this) {
            self::ESTUDIO      => 'Estudio',
            self::CAPACITACION => 'Capacitación',
        };
    }
}
EOT,

    'app/Enums/NivelEstudio.php' => <<<'EOT'
<?php

namespace App\Enums;

enum NivelEstudio: string
{
    case PRIMARIA      = 'primaria';
    case SECUNDARIA    = 'secundaria';
    case TERCER_NIVEL  = 'tercer_nivel';
    case CUARTO_NIVEL  = 'cuarto_nivel';

    public function etiqueta(): string
    {
        return match($this) {
            self::PRIMARIA     => 'Primaria',
            self::SECUNDARIA   => 'Secundaria',
            self::TERCER_NIVEL => 'Tercer Nivel',
            self::CUARTO_NIVEL => 'Cuarto Nivel',
        };
    }
}
EOT,

    'app/Enums/NacionalidadEstudio.php' => <<<'EOT'
<?php

namespace App\Enums;

enum NacionalidadEstudio: string
{
    case NACIONAL       = 'nacional';
    case INTERNACIONAL  = 'internacional';

    public function etiqueta(): string
    {
        return match($this) {
            self::NACIONAL      => 'Nacional',
            self::INTERNACIONAL => 'Internacional',
        };
    }
}
EOT,

    'database/migrations/2026_05_21_143721_crear_tabla_historial_academico_servidor.php' => <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_academico_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->cascadeOnDelete();

            $table->enum('tipo_estudio', ['estudio', 'capacitacion']);
            $table->enum('nivel_estudio', [
                'primaria', 'secundaria', 'tercer_nivel', 'cuarto_nivel'
            ])->nullable();

            $table->enum('nacionalidad_estudio', ['nacional', 'internacional']);
            $table->string('institucion', 200);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('titulo_capacitacion', 300);
            $table->string('codigo_senescyt', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_academico_servidor');
    }
};
EOT,

    'app/Models/Expediente/HistorialAcademicoServidor.php' => <<<'EOT'
<?php

namespace App\Models\Expediente;

use App\Enums\NacionalidadEstudio;
use App\Enums\NivelEstudio;
use App\Enums\TipoEstudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistorialAcademicoServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historial_academico_servidor';

    protected $fillable = [
        'servidor_id',
        'tipo_estudio',
        'nivel_estudio',
        'nacionalidad_estudio',
        'institucion',
        'fecha_inicio',
        'fecha_fin',
        'titulo_capacitacion',
        'codigo_senescyt',
    ];

    protected function casts(): array
    {
        return [
            'tipo_estudio'        => TipoEstudio::class,
            'nivel_estudio'       => NivelEstudio::class,
            'nacionalidad_estudio' => NacionalidadEstudio::class,
            'fecha_inicio'        => 'date',
            'fecha_fin'           => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function estaEnCurso(): bool
    {
        return is_null($this->fecha_fin);
    }
}
EOT,

    'app/Http/Requests/Expediente/StoreHistorialAcademicoRequest.php' => <<<'EOT'
<?php

namespace App\Http\Requests\Expediente;

use App\Enums\NacionalidadEstudio;
use App\Enums\NivelEstudio;
use App\Enums\TipoEstudio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreHistorialAcademicoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tipo_estudio'         => ['required', new Enum(TipoEstudio::class)],
            'nivel_estudio'        => [
                'nullable',
                new Enum(NivelEstudio::class),
                'required_if:tipo_estudio,estudio',
            ],
            'nacionalidad_estudio' => ['required', new Enum(NacionalidadEstudio::class)],
            'institucion'          => ['required', 'string', 'max:200'],
            'fecha_inicio'         => ['required', 'date'],
            'fecha_fin'            => ['nullable', 'date', 'after:fecha_inicio'],
            'titulo_capacitacion'  => ['required', 'string', 'max:300'],
            'codigo_senescyt'      => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'nivel_estudio.required_if' =>
                'El nivel de estudio es obligatorio para estudios formales.',
            'fecha_fin.after' =>
                'La fecha de finalización debe ser posterior a la fecha de inicio.',
        ];
    }
}
EOT,

    'app/Http/Controllers/Expediente/HistorialAcademicoController.php' => <<<'EOT'
<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreHistorialAcademicoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;

class HistorialAcademicoController extends Controller
{
    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $historial = $servidor->historialAcademico()
            ->orderByDesc('fecha_inicio')
            ->get();
        return ApiResponse::ok($historial, 'Historial académico del servidor.');
    }

    public function store(
        StoreHistorialAcademicoRequest $request,
        int $servidorId
    ): JsonResponse {
        Servidor::findOrFail($servidorId);
        $registro = HistorialAcademicoServidor::create(
            array_merge($request->validated(), ['servidor_id' => $servidorId])
        );
        return ApiResponse::created($registro, 'Registro académico agregado.');
    }

    public function update(
        StoreHistorialAcademicoRequest $request,
        int $servidorId,
        int $id
    ): JsonResponse {
        $registro = HistorialAcademicoServidor::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $registro->update($request->validated());
        return ApiResponse::ok($registro, 'Registro académico actualizado.');
    }

    public function destroy(int $servidorId, int $id): JsonResponse
    {
        $registro = HistorialAcademicoServidor::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $registro->delete();
        return ApiResponse::ok(null, 'Registro académico eliminado.');
    }
}
EOT,

    'app/Enums/TipoParentesco.php' => <<<'EOT'
<?php

namespace App\Enums;

enum TipoParentesco: string
{
    case CONYUGUE = 'conyugue';
    case HIJO     = 'hijo';

    public function etiqueta(): string
    {
        return match($this) {
            self::CONYUGUE => 'Cónyuge',
            self::HIJO     => 'Hijo/a',
        };
    }
}
EOT,

    'database/migrations/2026_05_21_143722_crear_tabla_cargas_familiares.php' => <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargas_familiares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->cascadeOnDelete();

            $table->string('apellidos', 100);
            $table->string('nombres', 100);
            $table->enum('parentesco', ['conyugue', 'hijo']);
            $table->date('fecha_nacimiento');
            $table->boolean('persona_con_discapacidad')->default(false);
            $table->boolean('posee_enfermedad_catastrofica')->default(false);
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_familiares');
    }
};
EOT,

    'database/migrations/2026_05_21_143723_crear_tabla_discapacidades_carga_familiar.php' => <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discapacidades_carga_familiar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_familiar_id')
                  ->constrained('cargas_familiares')
                  ->cascadeOnDelete();

            $table->enum('tipo_discapacidad', [
                'fisica', 'sensorial', 'intelectual',
                'psicosocial', 'visceral', 'multiple'
            ]);
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->string('numero_carnet_conadis')->nullable();
            $table->string('carnet_ruta')->nullable();
            $table->string('carnet_nombre_archivo')->nullable();
            $table->date('carnet_vencimiento')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discapacidades_carga_familiar');
    }
};
EOT,

    'database/migrations/2026_05_21_143724_crear_tabla_enfermedades_catastroficas_carga_familiar.php' => <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enfermedades_catastroficas_carga_familiar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_familiar_id')
                  ->constrained('cargas_familiares')
                  ->cascadeOnDelete();

            $table->string('tipo_enfermedad', 150);
            $table->string('codigo_cie10', 10)->nullable();
            $table->string('certificado_ruta')->nullable();
            $table->string('certificado_nombre_archivo')->nullable();
            $table->date('fecha_diagnostico')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enfermedades_catastroficas_carga_familiar');
    }
};
EOT,

    'app/Models/Expediente/CargaFamiliar.php' => <<<'EOT'
<?php

namespace App\Models\Expediente;

use App\Enums\TipoParentesco;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CargaFamiliar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cargas_familiares';

    protected $fillable = [
        'servidor_id',
        'apellidos',
        'nombres',
        'parentesco',
        'fecha_nacimiento',
        'persona_con_discapacidad',
        'posee_enfermedad_catastrofica',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'parentesco'                   => TipoParentesco::class,
            'fecha_nacimiento'             => 'date',
            'persona_con_discapacidad'     => 'boolean',
            'posee_enfermedad_catastrofica' => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function discapacidades(): HasMany
    {
        return $this->hasMany(DiscapacidadCargaFamiliar::class);
    }

    public function enfermedadesCatastroficas(): HasMany
    {
        return $this->hasMany(EnfermedadCatastroficaCargaFamiliar::class);
    }
}
EOT,

    'app/Models/Expediente/DiscapacidadCargaFamiliar.php' => <<<'EOT'
<?php

namespace App\Models\Expediente;

use App\Enums\TipoDiscapacidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscapacidadCargaFamiliar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'discapacidades_carga_familiar';

    protected $fillable = [
        'carga_familiar_id',
        'tipo_discapacidad',
        'porcentaje',
        'numero_carnet_conadis',
        'carnet_ruta',
        'carnet_nombre_archivo',
        'carnet_vencimiento',
    ];

    protected function casts(): array
    {
        return [
            'tipo_discapacidad' => TipoDiscapacidad::class,
            'porcentaje'        => 'decimal:2',
            'carnet_vencimiento' => 'date',
        ];
    }

    public function cargaFamiliar(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class);
    }
}
EOT,

    'app/Models/Expediente/EnfermedadCatastroficaCargaFamiliar.php' => <<<'EOT'
<?php

namespace App\Models\Expediente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnfermedadCatastroficaCargaFamiliar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'enfermedades_catastroficas_carga_familiar';

    protected $fillable = [
        'carga_familiar_id',
        'tipo_enfermedad',
        'codigo_cie10',
        'certificado_ruta',
        'certificado_nombre_archivo',
        'fecha_diagnostico',
    ];

    protected function casts(): array
    {
        return [
            'fecha_diagnostico' => 'date',
        ];
    }

    public function cargaFamiliar(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class);
    }
}
EOT,

    'app/Http/Requests/Expediente/StoreCargaFamiliarRequest.php' => <<<'EOT'
<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoParentesco;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCargaFamiliarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'apellidos'                    => ['required', 'string', 'max:100'],
            'nombres'                      => ['required', 'string', 'max:100'],
            'parentesco'                   => ['required', new Enum(TipoParentesco::class)],
            'fecha_nacimiento'             => ['required', 'date', 'before:today'],
            'persona_con_discapacidad'     => ['required', 'boolean'],
            'posee_enfermedad_catastrofica' => ['required', 'boolean'],
            'observaciones'                => ['nullable', 'string'],
        ];
    }
}
EOT,

    'app/Http/Controllers/Expediente/CargaFamiliarController.php' => <<<'EOT'
<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreCargaFamiliarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;

class CargaFamiliarController extends Controller
{
    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $cargas = $servidor->cargasFamiliares()
            ->with(['discapacidades', 'enfermedadesCatastroficas'])
            ->orderBy('apellidos')
            ->get();
        return ApiResponse::ok($cargas, 'Cargas familiares del servidor.');
    }

    public function store(
        StoreCargaFamiliarRequest $request,
        int $servidorId
    ): JsonResponse {
        Servidor::findOrFail($servidorId);
        $carga = CargaFamiliar::create(
            array_merge($request->validated(), ['servidor_id' => $servidorId])
        );
        return ApiResponse::created($carga, 'Carga familiar registrada.');
    }

    public function update(
        StoreCargaFamiliarRequest $request,
        int $servidorId,
        int $id
    ): JsonResponse {
        $carga = CargaFamiliar::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $carga->update($request->validated());
        return ApiResponse::ok($carga, 'Carga familiar actualizada.');
    }

    public function destroy(int $servidorId, int $id): JsonResponse
    {
        $carga = CargaFamiliar::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $carga->delete();
        return ApiResponse::ok(null, 'Carga familiar eliminada.');
    }
}
EOT,

    'app/Enums/TipoDeclaracion.php' => <<<'EOT'
<?php

namespace App\Enums;

enum TipoDeclaracion: string
{
    case INICIO_GESTION = 'inicio_gestion';
    case PERIODICA      = 'periodica';
    case FIN_GESTION    = 'fin_gestion';

    public function etiqueta(): string
    {
        return match($this) {
            self::INICIO_GESTION => 'Inicio de Gestión',
            self::PERIODICA      => 'Periódica',
            self::FIN_GESTION    => 'Fin de Gestión',
        };
    }

    public function etiquetaContraloria(): string
    {
        return match($this) {
            self::INICIO_GESTION => 'INICIO DE GESTION',
            self::PERIODICA      => 'PERIODICA',
            self::FIN_GESTION    => 'FIN DE GESTION',
        };
    }
}
EOT,

    'database/migrations/2026_05_21_143725_crear_tabla_declaraciones_juramentadas.php' => <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declaraciones_juramentadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->cascadeOnDelete();

            $table->date('fecha_declaracion');
            $table->string('codigo_barras', 100);
            $table->enum('tipo_declaracion', [
                'inicio_gestion', 'periodica', 'fin_gestion'
            ]);
            $table->string('documento_ruta')->nullable();
            $table->string('documento_nombre_archivo')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaraciones_juramentadas');
    }
};
EOT,

    'app/Models/Expediente/DeclaracionJuramentada.php' => <<<'EOT'
<?php

namespace App\Models\Expediente;

use App\Enums\TipoDeclaracion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeclaracionJuramentada extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'declaraciones_juramentadas';

    protected $fillable = [
        'servidor_id',
        'fecha_declaracion',
        'codigo_barras',
        'tipo_declaracion',
        'documento_ruta',
        'documento_nombre_archivo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_declaracion'  => TipoDeclaracion::class,
            'fecha_declaracion' => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function toLineaContraloria(): string
    {
        $servidor = $this->servidor;
        $contrato = $servidor->contratos()
            ->where('estado', 'vigente')
            ->latest()
            ->first();

        $cedula          = $servidor->cedula ?? '';
        $apellidos       = strtoupper(trim(($servidor->apellido ?? '') . ' ' . ($servidor->segundo_apellido ?? '')));
        $nombres         = strtoupper(trim(($servidor->nombre ?? '') . ' ' . ($servidor->segundo_nombre ?? '')));
        $tipoNombramiento = '';
        $tipoContrato     = '';

        if ($contrato) {
            $tipo = $contrato->tipo_nombramiento->value ?? $contrato->tipo_nombramiento;
            $esNombramiento = in_array($tipo, [
                'nombramiento_permanente',
                'nombramiento_provisional',
                'libre_nombramiento_remocion',
            ]);
            if ($esNombramiento) {
                $tipoNombramiento = match($tipo) {
                    'nombramiento_permanente'     => 'PERMANENTE',
                    'nombramiento_provisional'    => 'PROVISIONAL',
                    'libre_nombramiento_remocion' => 'LIBRE NOMBRAMIENTO Y REMOCION',
                    default                       => strtoupper($tipo),
                };
            } else {
                $tipoContrato = match($tipo) {
                    'servicios_ocasionales'   => 'SERVICIOS OCASIONALES',
                    'codigo_trabajo'          => 'CONTRATO INDIVIDUAL DE TRABAJO A TIEMPO INDEFINIDO',
                    'servicios_profesionales' => 'SERVICIOS PROFESIONALES',
                    default                   => strtoupper($tipo),
                };
            }
        }

        $tipoDeclaracion = $this->tipo_declaracion->etiquetaContraloria();
        $cargo = strtoupper($servidor->puesto?->nombre ?? '');
        $codigoBarras = $this->codigo_barras ?? '';

        return implode('|', [
            $cedula,
            $apellidos,
            $nombres,
            $tipoNombramiento,
            $tipoContrato,
            $tipoDeclaracion,
            $cargo,
            $codigoBarras,
        ]);
    }
}
EOT,

    'app/Http/Requests/Expediente/StoreDeclaracionJuramentadaRequest.php' => <<<'EOT'
<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoDeclaracion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDeclaracionJuramentadaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'fecha_declaracion' => ['required', 'date'],
            'codigo_barras'     => ['required', 'string', 'max:100'],
            'tipo_declaracion'  => ['required', new Enum(TipoDeclaracion::class)],
            'documento'         => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'documento.mimes' => 'El documento debe ser un archivo PDF.',
            'documento.max'   => 'El documento no debe superar los 10 MB.',
        ];
    }
}
EOT,

    'app/Http/Controllers/Expediente/DeclaracionJuramentadaController.php' => <<<'EOT'
<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreDeclaracionJuramentadaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeclaracionJuramentadaController extends Controller
{
    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $declaraciones = $servidor->declaracionesJuramentadas()
            ->orderByDesc('fecha_declaracion')
            ->get();
        return ApiResponse::ok($declaraciones, 'Declaraciones juramentadas.');
    }

    public function store(
        StoreDeclaracionJuramentadaRequest $request,
        int $servidorId
    ): JsonResponse {
        $servidor = Servidor::findOrFail($servidorId);
        $datos = $request->validated();

        if ($request->hasFile('documento')) {
            $archivo  = $request->file('documento');
            $ruta = $archivo->storeAs(
                "expedientes/{$servidor->cedula}/declaraciones",
                time() . '_' . $archivo->getClientOriginalName(),
                'local'
            );
            $datos['documento_ruta']           = $ruta;
            $datos['documento_nombre_archivo'] = $archivo->getClientOriginalName();
        }

        unset($datos['documento']);
        $declaracion = DeclaracionJuramentada::create(
            array_merge($datos, ['servidor_id' => $servidorId])
        );

        return ApiResponse::created($declaracion, 'Declaración juramentada registrada.');
    }

    public function destroy(int $servidorId, int $id): JsonResponse
    {
        $declaracion = DeclaracionJuramentada::where('servidor_id', $servidorId)
            ->findOrFail($id);

        if ($declaracion->documento_ruta) {
            Storage::disk('local')->delete($declaracion->documento_ruta);
        }

        $declaracion->delete();
        return ApiResponse::ok(null, 'Declaración eliminada.');
    }

    public function exportar(Request $request, int $servidorId): mixed
    {
        $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'formato'      => ['required', 'in:txt,pdf'],
        ]);

        $servidor = Servidor::findOrFail($servidorId);
        $declaraciones = $servidor->declaracionesJuramentadas()
            ->whereBetween('fecha_declaracion', [
                $request->fecha_inicio,
                $request->fecha_fin,
            ])
            ->orderBy('fecha_declaracion')
            ->get();

        if ($declaraciones->isEmpty()) {
            return ApiResponse::ok([], 'No hay declaraciones en el rango indicado.');
        }

        $lineas = $declaraciones->map(fn($d) => $d->toLineaContraloria());
        $contenido = $lineas->implode("\n");
        $nombreArchivo = "declaraciones_{$servidor->cedula}_{$request->fecha_inicio}_{$request->fecha_fin}";

        if ($request->formato === 'txt') {
            return response($contenido, 200, [
                'Content-Type'        => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}.txt\"",
            ]);
        }

        $html = view('exports.declaraciones-contraloria', [
            'servidor'     => $servidor,
            'declaraciones' => $declaraciones,
            'lineas'        => $lineas,
            'fechaInicio'   => $request->fecha_inicio,
            'fechaFin'      => $request->fecha_fin,
        ])->render();

        $pdf = app('dompdf.wrapper')->loadHTML($html);

        return $pdf->download("{$nombreArchivo}.pdf");
    }

    public function verDocumento(int $servidorId, int $id): mixed
    {
        $declaracion = DeclaracionJuramentada::where('servidor_id', $servidorId)
            ->findOrFail($id);

        if (!$declaracion->documento_ruta ||
            !Storage::disk('local')->exists($declaracion->documento_ruta)) {
            return ApiResponse::error('Documento no encontrado.', 404);
        }

        return Storage::disk('local')->response(
            $declaracion->documento_ruta,
            $declaracion->documento_nombre_archivo
        );
    }
}
EOT,

    'resources/views/exports/declaraciones-contraloria.blade.php' => <<<'EOT'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Declaraciones Juramentadas - Contraloría</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 10px; margin: 20px; }
        h2 { font-size: 12px; text-align: center; margin-bottom: 5px; }
        p  { font-size: 9px; text-align: center; margin: 2px 0; }
        pre { font-size: 9px; margin-top: 15px; line-height: 1.8; }
        .footer { margin-top: 20px; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <h2>DECLARACIONES JURAMENTADAS DE BIENES</h2>
    <p>Contraloría General del Estado - Ecuador</p>
    <p>Formato: Acuerdo 005-CG-2019 Art.7</p>
    <p>Servidor: {{ strtoupper(($servidor->apellido ?? '').' '.($servidor->nombre ?? '')) }}</p>
    <p>Cédula: {{ $servidor->cedula }}</p>
    <p>Período: {{ $fechaInicio }} al {{ $fechaFin }}</p>
    <hr>
    <pre>{{ $lineas->implode("\n") }}</pre>
    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} desde SGTH - GAD Provincial de Esmeraldas
    </div>
</body>
</html>
EOT,
];

foreach ($files as $path => $content) {
    $dir = dirname(__DIR__ . '/' . $path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(__DIR__ . '/' . $path, $content);
}
echo "Archivos creados.";
