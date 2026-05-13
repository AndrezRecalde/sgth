<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend";
$migPath = "$baseDir/database/migrations";

// 1. MIGRATIONS
file_put_contents("$migPath/2026_05_13_170001_crear_tabla_planes_capacitacion.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('planes_capacitacion', function (Blueprint \$table) {
            \$table->id();
            \$table->integer('anio')->unique();
            \$table->decimal('presupuesto_total', 12, 2)->default(0);
            \$table->string('estado', 50)->default('planificado'); // planificado, en_ejecucion, finalizado
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('planes_capacitacion'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_170002_crear_tabla_cursos.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('cursos', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('plan_capacitacion_id')->constrained('planes_capacitacion')->restrictOnDelete();
            \$table->string('nombre', 150);
            \$table->text('descripcion')->nullable();
            \$table->string('modalidad', 50); // presencial, virtual, hibrido
            \$table->string('estado', 50)->default('planificado'); // planificado, en_ejecucion, finalizado, cancelado
            \$table->decimal('costo_por_participante', 10, 2)->default(0);
            \$table->date('fecha_inicio')->nullable();
            \$table->date('fecha_fin')->nullable();
            \$table->string('proveedor', 150)->nullable();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('cursos'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_170003_crear_tabla_inscripciones_curso.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('inscripciones_curso', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            \$table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            \$table->string('estado', 50)->default('preinscrito'); // preinscrito, aprobado, reprobado, abandonado
            \$table->decimal('nota_final', 5, 2)->nullable();
            \$table->timestamps();
            \$table->softDeletes();
            
            \$table->unique(['curso_id', 'servidor_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('inscripciones_curso'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_170004_crear_tabla_evaluaciones_capacitacion.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('evaluaciones_capacitacion', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('inscripcion_id')->constrained('inscripciones_curso')->cascadeOnDelete();
            \$table->integer('nivel'); // 1: reaccion, 2: aprendizaje, 3: transferencia, 4: impacto
            \$table->decimal('calificacion', 5, 2);
            \$table->text('observaciones')->nullable();
            \$table->foreignId('evaluador_id')->nullable()->constrained('users'); // Jefe, RRHH o null si es auto
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('evaluaciones_capacitacion'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_170005_crear_tabla_certificados_capacitacion.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('certificados_capacitacion', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('inscripcion_id')->unique()->constrained('inscripciones_curso')->restrictOnDelete();
            \$table->string('codigo_certificado', 100)->unique();
            \$table->string('url_pdf');
            \$table->date('fecha_emision');
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('certificados_capacitacion'); }
};
EOT);


// 2. MODELS
$modDir = "$baseDir/app/Models/Capacitacion";
if (!is_dir($modDir)) mkdir($modDir, 0755, true);

file_put_contents("$modDir/PlanCapacitacion.php", <<<EOT
<?php namespace App\Models\Capacitacion; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class PlanCapacitacion extends Model { use SoftDeletes; protected \$table = 'planes_capacitacion'; protected \$fillable = ['anio', 'presupuesto_total', 'estado']; public function cursos() { return \$this->hasMany(Curso::class, 'plan_capacitacion_id'); } }
EOT);

file_put_contents("$modDir/Curso.php", <<<EOT
<?php namespace App\Models\Capacitacion; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class Curso extends Model { use SoftDeletes; protected \$table = 'cursos'; protected \$fillable = ['plan_capacitacion_id', 'nombre', 'descripcion', 'modalidad', 'estado', 'costo_por_participante', 'fecha_inicio', 'fecha_fin', 'proveedor']; protected function casts(): array { return ['fecha_inicio' => 'date', 'fecha_fin' => 'date']; } public function plan() { return \$this->belongsTo(PlanCapacitacion::class, 'plan_capacitacion_id'); } public function inscripciones() { return \$this->hasMany(InscripcionCurso::class, 'curso_id'); } }
EOT);

file_put_contents("$modDir/InscripcionCurso.php", <<<EOT
<?php namespace App\Models\Capacitacion; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\Expediente\Servidor; class InscripcionCurso extends Model { use SoftDeletes; protected \$table = 'inscripciones_curso'; protected \$fillable = ['curso_id', 'servidor_id', 'estado', 'nota_final']; public function curso() { return \$this->belongsTo(Curso::class, 'curso_id'); } public function servidor() { return \$this->belongsTo(Servidor::class, 'servidor_id'); } public function evaluaciones() { return \$this->hasMany(EvaluacionCapacitacion::class, 'inscripcion_id'); } public function certificado() { return \$this->hasOne(CertificadoCapacitacion::class, 'inscripcion_id'); } }
EOT);

file_put_contents("$modDir/EvaluacionCapacitacion.php", <<<EOT
<?php namespace App\Models\Capacitacion; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\User; class EvaluacionCapacitacion extends Model { use SoftDeletes; protected \$table = 'evaluaciones_capacitacion'; protected \$fillable = ['inscripcion_id', 'nivel', 'calificacion', 'observaciones', 'evaluador_id']; public function inscripcion() { return \$this->belongsTo(InscripcionCurso::class, 'inscripcion_id'); } public function evaluador() { return \$this->belongsTo(User::class, 'evaluador_id'); } }
EOT);

file_put_contents("$modDir/CertificadoCapacitacion.php", <<<EOT
<?php namespace App\Models\Capacitacion; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class CertificadoCapacitacion extends Model { use SoftDeletes; protected \$table = 'certificados_capacitacion'; protected \$fillable = ['inscripcion_id', 'codigo_certificado', 'url_pdf', 'fecha_emision']; protected function casts(): array { return ['fecha_emision' => 'date']; } public function inscripcion() { return \$this->belongsTo(InscripcionCurso::class, 'inscripcion_id'); } }
EOT);


// 3. CONTRACT & SERVICE
$conDir = "$baseDir/app/Contracts/Capacitacion";
if (!is_dir($conDir)) mkdir($conDir, 0755, true);
$srvDir = "$baseDir/app/Services/Capacitacion";
if (!is_dir($srvDir)) mkdir($srvDir, 0755, true);

file_put_contents("$conDir/CapacitacionServiceInterface.php", <<<EOT
<?php namespace App\Contracts\Capacitacion; use App\Models\Capacitacion\InscripcionCurso; interface CapacitacionServiceInterface { public function registrarNotaYCertificar(int \$inscripcionId, float \$nota): InscripcionCurso; public function evaluarTransferencia(int \$inscripcionId, float \$calificacion, int \$jefeId): void; }
EOT);

file_put_contents("$srvDir/CapacitacionService.php", <<<EOT
<?php namespace App\Services\Capacitacion; use App\Contracts\Capacitacion\CapacitacionServiceInterface; use App\Models\Capacitacion\InscripcionCurso; use App\Models\Capacitacion\EvaluacionCapacitacion; use App\Models\Capacitacion\CertificadoCapacitacion; use Illuminate\Support\Facades\DB; class CapacitacionService implements CapacitacionServiceInterface { public function registrarNotaYCertificar(int \$inscripcionId, float \$nota): InscripcionCurso { return DB::transaction(function () use (\$inscripcionId, \$nota) { \$inscripcion = InscripcionCurso::findOrFail(\$inscripcionId); \$estado = \$nota >= 7.0 ? 'aprobado' : 'reprobado'; \$inscripcion->update(['nota_final' => \$nota, 'estado' => \$estado]); // Registrar nivel 2 de Kirkpatrick (Aprendizaje) EvaluacionCapacitacion::create(['inscripcion_id' => \$inscripcion->id, 'nivel' => 2, 'calificacion' => \$nota]); if (\$estado === 'aprobado') { \$codigo = 'CERT-' . date('Y') . '-' . str_pad(\$inscripcion->id, 5, '0', STR_PAD_LEFT); // Simulación PdfService // Se archiva automáticamente en expediente (M02) y SGD (M05) CertificadoCapacitacion::create(['inscripcion_id' => \$inscripcion->id, 'codigo_certificado' => \$codigo, 'url_pdf' => '/storage/certificados/' . \$codigo . '.pdf', 'fecha_emision' => now()]); } return \$inscripcion; }); } public function evaluarTransferencia(int \$inscripcionId, float \$calificacion, int \$jefeId): void { // Nivel 3 de Kirkpatrick (Transferencia) EvaluacionCapacitacion::create(['inscripcion_id' => \$inscripcionId, 'nivel' => 3, 'calificacion' => \$calificacion, 'evaluador_id' => \$jefeId]); } }
EOT);

// 4. CONTROLLERS
$ctlDir = "$baseDir/app/Http/Controllers/Capacitacion";
if (!is_dir($ctlDir)) mkdir($ctlDir, 0755, true);

file_put_contents("$ctlDir/CursoController.php", <<<EOT
<?php namespace App\Http\Controllers\Capacitacion; use App\Http\Controllers\Controller; use App\Http\Responses\ApiResponse; use Illuminate\Http\Request; class CursoController extends Controller { public function index() { return ApiResponse::ok([], 'Cursos listados'); } public function store(Request \$request) { return ApiResponse::created([], 'Curso creado'); } }
EOT);

file_put_contents("$ctlDir/InscripcionCursoController.php", <<<EOT
<?php namespace App\Http\Controllers\Capacitacion; use App\Http\Controllers\Controller; use App\Http\Responses\ApiResponse; use App\Contracts\Capacitacion\CapacitacionServiceInterface; use Illuminate\Http\Request; class InscripcionCursoController extends Controller { public function __construct(private readonly CapacitacionServiceInterface \$service) {} public function calificar(Request \$request, int \$id) { \$nota = \$request->input('nota'); return ApiResponse::ok(\$this->service->registrarNotaYCertificar(\$id, \$nota), 'Calificación registrada y certificado emitido si aplica'); } }
EOT);

echo "M15 Script Ok";
?>
