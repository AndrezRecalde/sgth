<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend";
$migPath = "$baseDir/database/migrations";

// 1. MIGRATIONS
file_put_contents("$migPath/2026_05_13_180001_crear_tabla_planes_bienestar.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('planes_bienestar', function (Blueprint \$table) {
            \$table->id();
            \$table->integer('anio')->unique();
            \$table->decimal('presupuesto', 12, 2)->default(0);
            \$table->string('estado', 50)->default('planificado'); // planificado, en_ejecucion, finalizado
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('planes_bienestar'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_180002_crear_tabla_actividades_bienestar.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('actividades_bienestar', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('plan_bienestar_id')->constrained('planes_bienestar')->cascadeOnDelete();
            \$table->string('nombre', 150);
            \$table->text('descripcion')->nullable();
            \$table->date('fecha_inicio')->nullable();
            \$table->date('fecha_fin')->nullable();
            \$table->string('estado', 50)->default('planificada'); // planificada, ejecutada, cancelada
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('actividades_bienestar'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_180003_crear_tabla_encuestas_clima.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('encuestas_clima', function (Blueprint \$table) {
            \$table->id();
            \$table->integer('anio');
            \$table->string('titulo', 150);
            \$table->date('fecha_inicio');
            \$table->date('fecha_fin');
            \$table->string('estado', 50)->default('activa'); // activa, cerrada
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('encuestas_clima'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_180004_crear_tabla_resultados_clima.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('resultados_clima', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('encuesta_id')->constrained('encuestas_clima')->cascadeOnDelete();
            
            // Usamos un unsignedBigInteger sin foreign() estricto en caso de que 
            // la tabla de unidades administrativas se llame distinto, o lo 
            // vinculamos a departaments si existe. Para este módulo, asume la existencia
            // del id de la unidad para tabular resultados agregados.
            \$table->unsignedBigInteger('unidad_administrativa_id'); 
            
            // OJO: NUNCA SE INCLUYE servidor_id PARA GARANTIZAR ANONIMATO
            
            // Dimensiones calificadas (del 1 al 5)
            \$table->decimal('liderazgo', 5, 2);
            \$table->decimal('comunicacion', 5, 2);
            \$table->decimal('trabajo_en_equipo', 5, 2);
            \$table->decimal('condiciones_trabajo', 5, 2);
            \$table->decimal('desarrollo_profesional', 5, 2);
            \$table->decimal('reconocimiento', 5, 2);
            \$table->decimal('satisfaccion_general', 5, 2);
            
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('resultados_clima'); }
};
EOT);

// 2. MODELS
$modDir = "$baseDir/app/Models/Bienestar";
if (!is_dir($modDir)) mkdir($modDir, 0755, true);

file_put_contents("$modDir/PlanBienestar.php", <<<EOT
<?php namespace App\Models\Bienestar; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class PlanBienestar extends Model { use SoftDeletes; protected \$table = 'planes_bienestar'; protected \$fillable = ['anio', 'presupuesto', 'estado']; public function actividades() { return \$this->hasMany(ActividadBienestar::class, 'plan_bienestar_id'); } }
EOT);

file_put_contents("$modDir/ActividadBienestar.php", <<<EOT
<?php namespace App\Models\Bienestar; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class ActividadBienestar extends Model { use SoftDeletes; protected \$table = 'actividades_bienestar'; protected \$fillable = ['plan_bienestar_id', 'nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'estado']; protected function casts(): array { return ['fecha_inicio' => 'date', 'fecha_fin' => 'date']; } public function plan() { return \$this->belongsTo(PlanBienestar::class, 'plan_bienestar_id'); } }
EOT);

file_put_contents("$modDir/EncuestaClima.php", <<<EOT
<?php namespace App\Models\Bienestar; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class EncuestaClima extends Model { use SoftDeletes; protected \$table = 'encuestas_clima'; protected \$fillable = ['anio', 'titulo', 'fecha_inicio', 'fecha_fin', 'estado']; protected function casts(): array { return ['fecha_inicio' => 'date', 'fecha_fin' => 'date']; } public function resultados() { return \$this->hasMany(ResultadoClima::class, 'encuesta_id'); } }
EOT);

file_put_contents("$modDir/ResultadoClima.php", <<<EOT
<?php namespace App\Models\Bienestar; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class ResultadoClima extends Model { use SoftDeletes; protected \$table = 'resultados_clima'; protected \$fillable = ['encuesta_id', 'unidad_administrativa_id', 'liderazgo', 'comunicacion', 'trabajo_en_equipo', 'condiciones_trabajo', 'desarrollo_profesional', 'reconocimiento', 'satisfaccion_general']; public function encuesta() { return \$this->belongsTo(EncuestaClima::class, 'encuesta_id'); } }
EOT);

// 3. CONTRACT & SERVICE
$conDir = "$baseDir/app/Contracts/Bienestar";
if (!is_dir($conDir)) mkdir($conDir, 0755, true);
$srvDir = "$baseDir/app/Services/Bienestar";
if (!is_dir($srvDir)) mkdir($srvDir, 0755, true);

file_put_contents("$conDir/BienestarServiceInterface.php", <<<EOT
<?php namespace App\Contracts\Bienestar; use App\Models\Bienestar\ResultadoClima; interface BienestarServiceInterface { public function registrarRespuestaAnonima(array \$datos): void; public function obtenerResultadosAgregadosPorUnidad(int \$encuestaId, int \$unidadId): array; }
EOT);

file_put_contents("$srvDir/BienestarService.php", <<<EOT
<?php namespace App\Services\Bienestar; use App\Contracts\Bienestar\BienestarServiceInterface; use App\Models\Bienestar\ResultadoClima; use Illuminate\Support\Facades\DB; class BienestarService implements BienestarServiceInterface { public function registrarRespuestaAnonima(array \$datos): void { // Garantizamos que aunque el request traiga servidor_id, NUNCA se guarde unset(\$datos['servidor_id']); unset(\$datos['user_id']); ResultadoClima::create(\$datos); } public function obtenerResultadosAgregadosPorUnidad(int \$encuestaId, int \$unidadId): array { return ResultadoClima::where('encuesta_id', \$encuestaId)->where('unidad_administrativa_id', \$unidadId)->select( DB::raw('AVG(liderazgo) as prom_liderazgo'), DB::raw('AVG(comunicacion) as prom_comunicacion'), DB::raw('AVG(trabajo_en_equipo) as prom_equipo'), DB::raw('AVG(condiciones_trabajo) as prom_condiciones'), DB::raw('AVG(desarrollo_profesional) as prom_desarrollo'), DB::raw('AVG(reconocimiento) as prom_reconocimiento'), DB::raw('AVG(satisfaccion_general) as prom_satisfaccion'), DB::raw('COUNT(id) as total_respuestas') )->first()->toArray(); } }
EOT);

// 4. CONTROLLERS
$ctlDir = "$baseDir/app/Http/Controllers/Bienestar";
if (!is_dir($ctlDir)) mkdir($ctlDir, 0755, true);

file_put_contents("$ctlDir/ResultadoClimaController.php", <<<EOT
<?php namespace App\Http\Controllers\Bienestar; use App\Http\Controllers\Controller; use App\Http\Responses\ApiResponse; use App\Contracts\Bienestar\BienestarServiceInterface; use Illuminate\Http\Request; class ResultadoClimaController extends Controller { public function __construct(private readonly BienestarServiceInterface \$service) {} public function storeRespuestaAnonima(Request \$request) { \$this->service->registrarRespuestaAnonima(\$request->all()); return ApiResponse::created([], 'Gracias por su participación. Su respuesta anónima ha sido registrada.'); } public function reporteUnidad(int \$encuestaId, int \$unidadId) { \$datos = \$this->service->obtenerResultadosAgregadosPorUnidad(\$encuestaId, \$unidadId); return ApiResponse::ok(\$datos, 'Reporte agregado por unidad.'); } }
EOT);

echo "M17 Script Ok";
?>
