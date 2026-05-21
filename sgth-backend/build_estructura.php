<?php
$base = 'C:/laragon/www/sgth/sgth-backend/';

function write($path, $content) {
    global $base;
    $dir = dirname($base . $path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($base . $path, trim($content) . "\n");
    echo "Created: $path\n";
}

write('app/Enums/NivelComplejidadPuesto.php', <<<'PHP'
<?php
namespace App\Enums;

enum NivelComplejidadPuesto: string
{
    case BAJO  = 'bajo';
    case MEDIO = 'medio';
    case ALTO  = 'alto';

    public function etiqueta(): string
    {
        return match($this) {
            self::BAJO  => 'Nivel Bajo',
            self::MEDIO => 'Nivel Medio',
            self::ALTO  => 'Nivel Alto',
        };
    }
}
PHP
);

write('app/Enums/RolPuesto.php', <<<'PHP'
<?php
namespace App\Enums;

enum RolPuesto: string
{
    case DIGNATARIO                    = 'dignatario';
    case EJECUCION_COORDINACION        = 'ejecucion_coordinacion';
    case EJECUCION_PROCESOS            = 'ejecucion_procesos';
    case EJECUCION_PROCESOS_APOYO      = 'ejecucion_procesos_apoyo';
    case ADMINISTRATIVO                = 'administrativo';
    case CODIGO_TRABAJO                = 'codigo_trabajo';

    public function etiqueta(): string
    {
        return match($this) {
            self::DIGNATARIO               => 'Dignatarios',
            self::EJECUCION_COORDINACION   => 'Ejecución y Coordinación de Procesos',
            self::EJECUCION_PROCESOS       => 'Ejecución de Procesos',
            self::EJECUCION_PROCESOS_APOYO => 'Ejecución de Procesos de Apoyo',
            self::ADMINISTRATIVO           => 'Administrativo',
            self::CODIGO_TRABAJO           => 'Código del Trabajo',
        };
    }
}
PHP
);

$ts1 = date('Y_m_d_His', time() + 1);
write("database/migrations/{$ts1}_crear_tabla_grupos_ocupacionales.php", <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_ocupacionales', function (Blueprint $table) {
            $table->id();
            $table->string('grado_codigo', 10)->unique();
            $table->unsignedTinyInteger('grado_numerico')->nullable();
            $table->string('grupo', 100);
            $table->string('denominacion_generica', 100)->nullable();
            $table->decimal('rmu', 10, 2);
            $table->enum('regimen', ['losep', 'codigo_trabajo'])->default('losep');
            $table->enum('nivel_complejidad', ['bajo', 'medio', 'alto'])->nullable();
            $table->enum('rol_puesto', [
                'dignatario',
                'ejecucion_coordinacion',
                'ejecucion_procesos',
                'ejecucion_procesos_apoyo',
                'administrativo',
                'codigo_trabajo',
            ])->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_ocupacionales');
    }
};
PHP
);

write('app/Models/Estructura/GrupoOcupacional.php', <<<'PHP'
<?php
namespace App\Models\Estructura;

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

    public function esLosep(): bool
    {
        return $this->regimen === 'losep';
    }

    public function esCodigoTrabajo(): bool
    {
        return $this->regimen === 'codigo_trabajo';
    }
}
PHP
);

write('database/seeders/GrupoOcupacionalSeeder.php', <<<'PHP'
<?php
namespace Database\Seeders;

use App\Models\Estructura\GrupoOcupacional;
use Illuminate\Database\Seeder;

class GrupoOcupacionalSeeder extends Seeder
{
    public function run(): void
    {
        $grupos = [
            // ── Dignatarios ──────────────────────────────
            [
                'grado_codigo'        => 'NJS-10',
                'grado_numerico'      => 10,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Prefecto/a',
                'rmu'                 => 5060.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'dignatario',
            ],
            [
                'grado_codigo'        => 'NJS-7',
                'grado_numerico'      => 7,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Viceprefecto/a',
                'rmu'                 => 4048.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'dignatario',
            ],
            // ── NJS Directivos ───────────────────────────
            [
                'grado_codigo'        => 'NJS-6',
                'grado_numerico'      => 6,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Coordinador/a, Procurador/a, Secretario/a General',
                'rmu'                 => 3848.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'NJS-5-ASESOR',
                'grado_numerico'      => 5,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Asesor/a 2',
                'rmu'                 => 3247.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'NJS-5',
                'grado_numerico'      => 5,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Director/a',
                'rmu'                 => 2967.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'NJS-1',
                'grado_numerico'      => 1,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Coordinador/a, Tesorero/a',
                'rmu'                 => 2034.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            // ── Servidores Públicos de Carrera ───────────
            [
                'grado_codigo'        => 'SP9',
                'grado_numerico'      => 15,
                'grupo'               => 'Servidor Público 9',
                'denominacion_generica' => 'Especialista',
                'rmu'                 => 2034.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'SP8',
                'grado_numerico'      => 14,
                'grupo'               => 'Servidor Público 8',
                'denominacion_generica' => 'Especialista',
                'rmu'                 => 1760.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'SP7',
                'grado_numerico'      => 13,
                'grupo'               => 'Servidor Público 7',
                'denominacion_generica' => 'Analista',
                'rmu'                 => 1676.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP6',
                'grado_numerico'      => 12,
                'grupo'               => 'Servidor Público 6',
                'denominacion_generica' => 'Analista',
                'rmu'                 => 1412.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP5',
                'grado_numerico'      => 11,
                'grupo'               => 'Servidor Público 5',
                'denominacion_generica' => 'Analista',
                'rmu'                 => 1212.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'medio',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP4',
                'grado_numerico'      => 10,
                'grupo'               => 'Servidor Público 4',
                'denominacion_generica' => 'Asistente',
                'rmu'                 => 1086.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'medio',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP3',
                'grado_numerico'      => 9,
                'grupo'               => 'Servidor Público 3',
                'denominacion_generica' => 'Asistente',
                'rmu'                 => 986.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'medio',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP2',
                'grado_numerico'      => 8,
                'grupo'               => 'Servidor Público 2',
                'denominacion_generica' => 'Asistente',
                'rmu'                 => 901.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'medio',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP1',
                'grado_numerico'      => 7,
                'grupo'               => 'Servidor Público 1',
                'denominacion_generica' => 'Asistente de Apoyo',
                'rmu'                 => 817.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'ejecucion_procesos_apoyo',
            ],
            // ── Servidor Público de Apoyo ────────────────
            [
                'grado_codigo'        => 'SPA4',
                'grado_numerico'      => 6,
                'grupo'               => 'Servidor Público de Apoyo 4',
                'denominacion_generica' => 'Asistente Administrativo de Apoyo',
                'rmu'                 => 733.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            [
                'grado_codigo'        => 'SPA3',
                'grado_numerico'      => 5,
                'grupo'               => 'Servidor Público de Apoyo 3',
                'denominacion_generica' => 'Asistente Administrativo de Apoyo',
                'rmu'                 => 675.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            [
                'grado_codigo'        => 'SPA2',
                'grado_numerico'      => 4,
                'grupo'               => 'Servidor Público de Apoyo 2',
                'denominacion_generica' => 'Asistente Administrativo de Apoyo',
                'rmu'                 => 622.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            [
                'grado_codigo'        => 'SPA1',
                'grado_numerico'      => 3,
                'grupo'               => 'Servidor Público de Apoyo 1',
                'denominacion_generica' => 'Asistente Administrativo de Apoyo',
                'rmu'                 => 585.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            // ── Servidor Público de Servicios ────────────
            [
                'grado_codigo'        => 'SPS2',
                'grado_numerico'      => 2,
                'grupo'               => 'Servidor Público de Servicios 2',
                'denominacion_generica' => 'Asistente Administrativo de Servicios',
                'rmu'                 => 553.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            [
                'grado_codigo'        => 'SPS1',
                'grado_numerico'      => 1,
                'grupo'               => 'Servidor Público de Servicios 1',
                'denominacion_generica' => 'Asistente Administrativo de Servicios',
                'rmu'                 => 527.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            // ── Código del Trabajo ───────────────────────
            // Sin escala fija. La RMU real va en contratos_servidor.
            // Este registro es solo referencial — el piso es el SBU.
            [
                'grado_codigo'        => 'CT',
                'grado_numerico'      => null,
                'grupo'               => 'Trabajador Código del Trabajo',
                'denominacion_generica' => 'Trabajador/a',
                'rmu'                 => 460.00,
                'regimen'             => 'codigo_trabajo',
                'nivel_complejidad'   => null,
                'rol_puesto'          => 'codigo_trabajo',
            ],
        ];

        foreach ($grupos as $grupo) {
            GrupoOcupacional::firstOrCreate(
                ['grado_codigo' => $grupo['grado_codigo']],
                $grupo
            );
        }
    }
}
PHP
);

$ts2 = date('Y_m_d_His', time() + 2);
write("database/migrations/{$ts2}_reestructurar_tabla_puestos.php", <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            // 1. Agregar FK a grupos_ocupacionales (nullable para CT)
            $table->foreignId('grupo_ocupacional_id')
                  ->nullable()
                  ->after('unidad_administrativa_id')
                  ->constrained('grupos_ocupacionales')
                  ->nullOnDelete();

            // 2. Agregar campo plazas (cuántos servidores puede tener)
            $table->unsignedSmallInteger('plazas')
                  ->default(1)
                  ->after('grupo_ocupacional_id');

            // 3. Agregar rol_puesto como enum
            $table->enum('rol_puesto', [
                'dignatario',
                'ejecucion_coordinacion',
                'ejecucion_procesos',
                'ejecucion_procesos_apoyo',
                'administrativo',
                'codigo_trabajo',
            ])->nullable()->after('plazas');

            // 4. Agregar nivel_complejidad
            $table->enum('nivel_complejidad', ['bajo', 'medio', 'alto'])
                  ->nullable()
                  ->after('rol_puesto');

            // 5. Agregar regimen_laboral
            $table->enum('regimen_laboral', ['losep', 'codigo_trabajo'])
                  ->default('losep')
                  ->after('nivel_complejidad');

            // 6. Eliminar campos que ya no aplican
            // rmu y grupo_ocupacional (string) se reemplazan por la FK
            $table->dropColumn(['grupo_ocupacional', 'grado_rmu', 'rmu']);

            // 7. Renombrar nivel → nivel_jerarquico para más claridad
            $table->renameColumn('nivel', 'nivel_jerarquico');

            // 8. Renombrar estado → activo para consistencia
            $table->renameColumn('estado', 'activo');

            // 9. Agregar misión del puesto (opcional, del manual)
            $table->text('mision')->nullable()->after('denominacion');
        });
    }

    public function down(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->dropForeign(['grupo_ocupacional_id']);
            $table->dropColumn([
                'grupo_ocupacional_id',
                'plazas',
                'rol_puesto',
                'nivel_complejidad',
                'regimen_laboral',
                'mision',
            ]);
            $table->string('grupo_ocupacional');
            $table->unsignedTinyInteger('grado_rmu');
            $table->decimal('rmu', 10, 2);
            $table->renameColumn('nivel_jerarquico', 'nivel');
            $table->renameColumn('activo', 'estado');
        });
    }
};
PHP
);

write('app/Models/Estructura/Puesto.php', <<<'PHP'
<?php
namespace App\Models\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use App\Observers\PuestoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(PuestoObserver::class)]
class Puesto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'puestos';

    protected $fillable = [
        'codigo',
        'denominacion',
        'mision',
        'unidad_administrativa_id',
        'grupo_ocupacional_id',
        'plazas',
        'rol_puesto',
        'nivel_complejidad',
        'nivel_jerarquico',
        'regimen_laboral',
        'es_jefe',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_jefe'           => 'boolean',
            'activo'            => 'boolean',
            'nivel_jerarquico'  => 'integer',
            'plazas'            => 'integer',
            'nivel_complejidad' => NivelComplejidadPuesto::class,
            'rol_puesto'        => RolPuesto::class,
        ];
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(
            UnidadAdministrativa::class,
            'unidad_administrativa_id'
        );
    }

    public function grupoOcupacional(): BelongsTo
    {
        return $this->belongsTo(GrupoOcupacional::class);
    }

    /**
     * Servidores que actualmente ocupan este puesto
     * a través de contratos vigentes.
     */
    public function contratosVigentes(): HasMany
    {
        return $this->hasMany(
            \App\Models\Expediente\ContratoServidor::class
        )->where('estado', 'vigente');
    }

    /**
     * Plazas ocupadas actualmente.
     */
    public function plazasOcupadas(): int
    {
        return $this->contratosVigentes()->count();
    }

    /**
     * Plazas disponibles (vacantes).
     */
    public function plazasDisponibles(): int
    {
        return max(0, $this->plazas - $this->plazasOcupadas());
    }

    /**
     * Indica si el puesto tiene vacantes disponibles.
     */
    public function tieneVacantes(): bool
    {
        return $this->plazasDisponibles() > 0;
    }

    /**
     * RMU del puesto — viene del grupo ocupacional para LOSEP.
     * Para CT devuelve el SBU referencial.
     */
    public function getRmuAttribute(): ?float
    {
        return $this->grupoOcupacional?->rmu;
    }

    /**
     * Indica si es régimen LOSEP.
     */
    public function esLosep(): bool
    {
        return $this->regimen_laboral === 'losep';
    }
}
PHP
);

write('database/seeders/UnidadAdministrativaSeeder.php', <<<'PHP'
<?php
namespace Database\Seeders;

use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnidadAdministrativaSeeder extends Seeder
{
    public function run(): void
    {
        // ── IDs de tipos de unidad (del TipoUnidadSeeder) ──
        $GOBERNANTE  = '11111111-1111-1111-1111-111111111111';
        $APOYO       = '22222222-2222-2222-2222-222222222222';
        $ASESOR      = '33333333-3333-3333-3333-333333333333';
        $AGREGADOR   = '44444444-4444-4444-4444-444444444444';

        // ── RAÍZ ─────────────────────────────────────────
        $gadpe = $this->crear([
            'nombre'          => 'GADPE',
            'descripcion'     => 'Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas',
            'tipo_unidad_id'  => $GOBERNANTE,
            'unidad_padre_id' => null,
        ]);

        // ══════════════════════════════════════════════════
        // PROCESOS GOBERNANTES
        // ══════════════════════════════════════════════════
        $prefectura = $this->crear([
            'nombre'          => 'Prefectura Provincial',
            'tipo_unidad_id'  => $GOBERNANTE,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $viceprefectura = $this->crear([
            'nombre'          => 'Viceprefectura Provincial',
            'tipo_unidad_id'  => $GOBERNANTE,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // ══════════════════════════════════════════════════
        // PROCESOS HABILITANTES DE ASESORÍA
        // ══════════════════════════════════════════════════
        $coordinacion = $this->crear([
            'nombre'          => 'Gestión de Coordinación Institucional',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $auditoria = $this->crear([
            'nombre'          => 'Unidad de Gestión de Auditoría Interna',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $procuraduria = $this->crear([
            'nombre'          => 'Gestión de Procuraduría Síndica',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Procuraduría
        $this->crear(['nombre' => 'Patrocinio Jurídico',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $procuraduria->id]);
        $this->crear(['nombre' => 'Asesoría Legal',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $procuraduria->id]);
        $this->crear(['nombre' => 'Coactiva',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $procuraduria->id]);
        $this->crear(['nombre' => 'Archivo, Manejo y Control Documental Jurídico',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $procuraduria->id]);

        $comunicacion = $this->crear([
            'nombre'          => 'Gestión de Comunicación Social',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Comunicación
        $this->crear(['nombre' => 'Comunicación Organizacional',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);
        $this->crear(['nombre' => 'Logística Comunicacional',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);
        $this->crear(['nombre' => 'Comunicación Estratégica',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);
        $this->crear(['nombre' => 'Relaciones Públicas y Eventos',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);
        $this->crear(['nombre' => 'Producción Audiovisual y Diseño Gráfico',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);

        $planificacion = $this->crear([
            'nombre'          => 'Gestión de Planificación',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Planificación
        $this->crear(['nombre' => 'Planificación Territorial',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $planificacion->id]);
        $this->crear(['nombre' => 'Planificación Institucional',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $planificacion->id]);
        $this->crear(['nombre' => 'Proyectos Estratégicos y Operativos',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $planificacion->id]);

        $accionSocial = $this->crear([
            'nombre'          => 'Gestión de Acción Social, Inclusión y Participación',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Acción Social
        $this->crear(['nombre' => 'Participación Ciudadana',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $accionSocial->id]);
        $this->crear(['nombre' => 'Igualdades y Derechos',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $accionSocial->id]);

        $calidad = $this->crear([
            'nombre'          => 'Unidad de Gestión de Calidad',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $riesgos = $this->crear([
            'nombre'          => 'Unidad de Gestión de Riesgos de Desastres',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // ══════════════════════════════════════════════════
        // PROCESOS HABILITANTES DE APOYO
        // ══════════════════════════════════════════════════
        $talentoHumano = $this->crear([
            'nombre'          => 'Gestión de Talento Humano y Riesgos Laborales',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Talento Humano
        $this->crear(['nombre' => 'Administración del Talento Humano',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);
        $this->crear(['nombre' => 'Nómina y Remuneraciones',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);
        $this->crear(['nombre' => 'Seguridad y Salud Ocupacional',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);
        $this->crear(['nombre' => 'Bienestar Social',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);
        $this->crear(['nombre' => 'Dispensario Médico',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);

        $financiera = $this->crear([
            'nombre'          => 'Gestión Financiera',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Financiera
        $this->crear(['nombre' => 'Presupuesto',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $financiera->id]);
        $this->crear(['nombre' => 'Contabilidad',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $financiera->id]);
        $this->crear(['nombre' => 'Tesorería',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $financiera->id]);
        $this->crear(['nombre' => 'Rentas',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $financiera->id]);

        $administrativa = $this->crear([
            'nombre'          => 'Gestión Administrativa',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Administrativa
        $this->crear(['nombre' => 'Servicios Generales y Transporte',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $administrativa->id]);
        $this->crear(['nombre' => 'Activos Fijos y Bodega',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $administrativa->id]);
        $this->crear(['nombre' => 'Guardalmacén',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $administrativa->id]);

        $fiscalizacion = $this->crear([
            'nombre'          => 'Gestión de Fiscalización',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $secretaria = $this->crear([
            'nombre'          => 'Gestión de Secretaría General',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Secretaría
        $this->crear(['nombre' => 'Archivo Central y Gestión Documental',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $secretaria->id]);

        $tic = $this->crear([
            'nombre'          => 'Gestión de Tecnologías de la Información y Comunicación',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de TIC
        $this->crear(['nombre' => 'Infraestructura y Redes',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $tic->id]);
        $this->crear(['nombre' => 'Desarrollo de Software',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $tic->id]);
        $this->crear(['nombre' => 'Soporte Técnico',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $tic->id]);
        $this->crear(['nombre' => 'Seguridad de la Información',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $tic->id]);

        $contratacion = $this->crear([
            'nombre'          => 'Unidad de Gestión de Contratación Pública',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // ══════════════════════════════════════════════════
        // PROCESOS AGREGADORES DE VALOR
        // ══════════════════════════════════════════════════
        $vial = $this->crear([
            'nombre'          => 'Gestión de Infraestructura Vial',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Infraestructura Vial
        $this->crear(['nombre' => 'Planificación Vial',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $vial->id]);
        $this->crear(['nombre' => 'Construcción y Mantenimiento Vial',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $vial->id]);
        $this->crear(['nombre' => 'Maquinaria y Equipo',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $vial->id]);

        $fomento = $this->crear([
            'nombre'          => 'Gestión de Fomento y Desarrollo Productivo',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $cuencas = $this->crear([
            'nombre'          => 'Gestión de Cuencas, Riego y Drenaje',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $ambiental = $this->crear([
            'nombre'          => 'Gestión Ambiental',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Gestión Ambiental
        $this->crear(['nombre' => 'Calidad Ambiental',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $ambiental->id]);
        $this->crear(['nombre' => 'Áreas Protegidas y Biodiversidad',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $ambiental->id]);

        $cooperacion = $this->crear([
            'nombre'          => 'Gestión de Relaciones Internacionales y Cooperación',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);
    }

    private function crear(array $datos): UnidadAdministrativa
    {
        return UnidadAdministrativa::firstOrCreate(
            ['nombre' => $datos['nombre']],
            array_merge($datos, [
                'descripcion'     => $datos['descripcion'] ?? null,
                'unidad_padre_id' => $datos['unidad_padre_id'] ?? null,
            ])
        );
    }
}
PHP
);

$ts3 = date('Y_m_d_His', time() + 3);
write("database/migrations/{$ts3}_crear_tabla_partidas_presupuestarias.php", <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidas_presupuestarias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descripcion', 200);
            $table->string('grupo_gasto', 100)->default('Gastos en Personal');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('unidad_partida_presupuestaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_administrativa_id')
                  ->constrained('unidades_administrativas')
                  ->cascadeOnDelete();
            $table->foreignId('partida_presupuestaria_id')
                  ->constrained('partidas_presupuestarias')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('anio_fiscal')
                  ->default(date('Y'));
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique([
                'unidad_administrativa_id',
                'partida_presupuestaria_id',
                'anio_fiscal',
            ], 'unidad_partida_anio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_partida_presupuestaria');
        Schema::dropIfExists('partidas_presupuestarias');
    }
};
PHP
);

$ts4 = date('Y_m_d_His', time() + 4);
write("database/migrations/{$ts4}_agregar_partida_presupuestaria_a_puestos.php", <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->foreignId('partida_presupuestaria_id')
                  ->nullable()
                  ->after('grupo_ocupacional_id')
                  ->constrained('partidas_presupuestarias')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->dropForeign(['partida_presupuestaria_id']);
            $table->dropColumn('partida_presupuestaria_id');
        });
    }
};
PHP
);

write('app/Models/Estructura/PartidaPresupuestaria.php', <<<'PHP'
<?php
namespace App\Models\Estructura;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartidaPresupuestaria extends Model
{
    use HasFactory;

    protected $table = 'partidas_presupuestarias';

    protected $fillable = [
        'codigo',
        'descripcion',
        'grupo_gasto',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(
            UnidadAdministrativa::class,
            'unidad_partida_presupuestaria',
            'partida_presupuestaria_id',
            'unidad_administrativa_id'
        )->withPivot('anio_fiscal', 'observacion')
         ->withTimestamps();
    }

    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class);
    }
}
PHP
);

write('database/seeders/PartidaPresupuestariaSeeder.php', <<<'PHP'
<?php
namespace Database\Seeders;

use App\Models\Estructura\PartidaPresupuestaria;
use Illuminate\Database\Seeder;

class PartidaPresupuestariaSeeder extends Seeder
{
    public function run(): void
    {
        $partidas = [
            // ── Grupo 51: Gastos en Personal ─────────────
            ['codigo' => '510101', 'descripcion' => 'Remuneraciones Básicas - Sueldos'],
            ['codigo' => '510102', 'descripcion' => 'Remuneraciones Básicas - Salarios'],
            ['codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas'],
            ['codigo' => '510106', 'descripcion' => 'Salarios Unificados'],
            ['codigo' => '510203', 'descripcion' => 'Decimotercer Sueldo'],
            ['codigo' => '510204', 'descripcion' => 'Decimocuarto Sueldo'],
            ['codigo' => '510510', 'descripcion' => 'Servicios Personales por Contrato'],
            ['codigo' => '510601', 'descripcion' => 'Aporte Patronal'],
            ['codigo' => '510602', 'descripcion' => 'Fondo de Reserva'],
            ['codigo' => '510706', 'descripcion' => 'Beneficios Sociales - Desahucio'],
            ['codigo' => '510707', 'descripcion' => 'Beneficios Sociales - Indemnización'],
            ['codigo' => '510803', 'descripcion' => 'Horas Extraordinarias y Suplementarias'],
            ['codigo' => '510901', 'descripcion' => 'Subrogaciones'],
            ['codigo' => '510902', 'descripcion' => 'Encargos'],
            // ── Grupo 53: Bienes y Servicios (viáticos) ──
            ['codigo' => '530303', 'descripcion' => 'Viáticos y Subsistencias en el Interior',
             'grupo_gasto' => 'Bienes y Servicios'],
            ['codigo' => '530304', 'descripcion' => 'Viáticos y Subsistencias en el Exterior',
             'grupo_gasto' => 'Bienes y Servicios'],
        ];

        foreach ($partidas as $partida) {
            PartidaPresupuestaria::firstOrCreate(
                ['codigo' => $partida['codigo']],
                array_merge($partida, [
                    'grupo_gasto' => $partida['grupo_gasto'] ?? 'Gastos en Personal',
                    'activo'      => true,
                ])
            );
        }
    }
}
PHP
);
