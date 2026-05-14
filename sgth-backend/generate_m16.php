<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend";
$migPath = "$baseDir/database/migrations";

// 1. MIGRATIONS
file_put_contents("$migPath/2026_05_13_173001_crear_tabla_actividades_laborales.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('actividades_laborales', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            \$table->date('fecha');
            \$table->time('hora_inicio');
            \$table->time('hora_fin');
            \$table->text('descripcion');
            \$table->string('categoria', 50); // Enum
            \$table->string('producto_entregable')->nullable();
            \$table->string('estado', 50)->default('registrado'); // registrado, validado, observado, aprobado
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('actividades_laborales'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_173002_crear_tabla_informes_actividades.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('informes_actividades', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            \$table->integer('mes');
            \$table->integer('anio');
            \$table->string('url_pdf');
            \$table->string('estado', 50)->default('generado'); // generado, firmado, aprobado
            \$table->foreignId('aprobado_por')->nullable()->constrained('users');
            \$table->timestamps();
            \$table->softDeletes();
            
            \$table->unique(['servidor_id', 'mes', 'anio']);
        });
    }
    public function down(): void { Schema::dropIfExists('informes_actividades'); }
};
EOT);

// 2. ENUM & MODELS
$enumDir = "$baseDir/app/Enums/Actividades";
if (!is_dir($enumDir)) mkdir($enumDir, 0755, true);
file_put_contents("$enumDir/CategoriaActividadEnum.php", <<<EOT
<?php
namespace App\Enums\Actividades;
enum CategoriaActividadEnum: string {
    case REUNION = 'reunion';
    case VISITA_CAMPO = 'visita_campo';
    case ELABORACION_DOCUMENTOS = 'elaboracion_documentos';
    case COORDINACION = 'coordinacion';
    case CAPACITACION = 'capacitacion';
    case ATENCION_CIUDADANA = 'atencion_ciudadana';
    case OTRO = 'otro';
}
EOT);

$modDir = "$baseDir/app/Models/Actividades";
if (!is_dir($modDir)) mkdir($modDir, 0755, true);

file_put_contents("$modDir/ActividadLaboral.php", <<<EOT
<?php namespace App\Models\Actividades; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\Expediente\Servidor; use App\Enums\Actividades\CategoriaActividadEnum; class ActividadLaboral extends Model { use SoftDeletes; protected \$table = 'actividades_laborales'; protected \$fillable = ['servidor_id', 'fecha', 'hora_inicio', 'hora_fin', 'descripcion', 'categoria', 'producto_entregable', 'estado']; protected function casts(): array { return ['fecha' => 'date', 'categoria' => CategoriaActividadEnum::class]; } public function servidor() { return \$this->belongsTo(Servidor::class, 'servidor_id'); } }
EOT);

file_put_contents("$modDir/InformeActividad.php", <<<EOT
<?php namespace App\Models\Actividades; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\Expediente\Servidor; use App\Models\User; class InformeActividad extends Model { use SoftDeletes; protected \$table = 'informes_actividades'; protected \$fillable = ['servidor_id', 'mes', 'anio', 'url_pdf', 'estado', 'aprobado_por']; public function servidor() { return \$this->belongsTo(Servidor::class, 'servidor_id'); } public function aprobador() { return \$this->belongsTo(User::class, 'aprobado_por'); } }
EOT);

// 3. CONTRACT & SERVICES
$conDir = "$baseDir/app/Contracts/Actividades";
if (!is_dir($conDir)) mkdir($conDir, 0755, true);
$srvDir = "$baseDir/app/Services/Actividades";
if (!is_dir($srvDir)) mkdir($srvDir, 0755, true);

file_put_contents("$conDir/ActividadesServiceInterface.php", <<<EOT
<?php namespace App\Contracts\Actividades; use App\Models\Actividades\InformeActividad; interface ActividadesServiceInterface { public function registrarActividad(array \$datos); public function generarInformeMensual(int \$servidorId, int \$mes, int \$anio): InformeActividad; public function validarCruceBiometrico(int \$servidorId, string \$fecha): array; }
EOT);

file_put_contents("$srvDir/ActividadesService.php", <<<EOT
<?php namespace App\Services\Actividades; use App\Contracts\Actividades\ActividadesServiceInterface; use App\Models\Actividades\ActividadLaboral; use App\Models\Actividades\InformeActividad; use App\Services\Actividades\GenerarInformeActividadesService; use Illuminate\Support\Facades\DB; class ActividadesService implements ActividadesServiceInterface { public function __construct(private readonly GenerarInformeActividadesService \$informeService) {} public function registrarActividad(array \$datos) { return ActividadLaboral::create(\$datos); } public function validarCruceBiometrico(int \$servidorId, string \$fecha): array { \$marcaciones = DB::table('marcaciones')->where('servidor_id', \$servidorId)->whereDate('fecha_hora', \$fecha)->count(); \$actividades = ActividadLaboral::where('servidor_id', \$servidorId)->where('fecha', \$fecha)->count(); \$alerta = false; if (\$marcaciones > 0 && \$actividades === 0) { \$alerta = true; } return ['marcaciones' => \$marcaciones, 'actividades' => \$actividades, 'alerta_sin_documentar' => \$alerta]; } public function generarInformeMensual(int \$servidorId, int \$mes, int \$anio): InformeActividad { return DB::transaction(function () use (\$servidorId, \$mes, \$anio) { \$pdfUrl = \$this->informeService->generarPdf(\$servidorId, \$mes, \$anio); return InformeActividad::updateOrCreate( ['servidor_id' => \$servidorId, 'mes' => \$mes, 'anio' => \$anio], ['url_pdf' => \$pdfUrl, 'estado' => 'generado'] ); }); } }
EOT);

file_put_contents("$srvDir/GenerarInformeActividadesService.php", <<<EOT
<?php namespace App\Services\Actividades; use App\Models\Expediente\Servidor; use App\Models\Actividades\ActividadLaboral; class GenerarInformeActividadesService { public function generarPdf(int \$servidorId, int \$mes, int \$anio): string { \$servidor = Servidor::findOrFail(\$servidorId); \$actividades = ActividadLaboral::where('servidor_id', \$servidorId)->whereMonth('fecha', \$mes)->whereYear('fecha', \$anio)->get(); \$url = '/storage/informes/actividades_' . \$servidorId . '_' . \$anio . '_' . \$mes . '.pdf'; // Simulacion de PdfService con membrete oficial del GAD Provincial de Esmeraldas // Incluye: // 1. Escudo y logo // 2. Datos del servidor, cargo y unidad // 3. Detalle iterativo de las actividades // 4. Bloque de firma digital return \$url; } }
EOT);

// 4. CONTROLLERS
$ctlDir = "$baseDir/app/Http/Controllers/Actividades";
if (!is_dir($ctlDir)) mkdir($ctlDir, 0755, true);

file_put_contents("$ctlDir/ActividadLaboralController.php", <<<EOT
<?php namespace App\Http\Controllers\Actividades; use App\Http\Controllers\Controller; use App\Http\Responses\ApiResponse; use App\Contracts\Actividades\ActividadesServiceInterface; use Illuminate\Http\Request; class ActividadLaboralController extends Controller { public function __construct(private readonly ActividadesServiceInterface \$service) {} public function store(Request \$request) { return ApiResponse::created(\$this->service->registrarActividad(\$request->all()), 'Actividad registrada'); } public function verificarBiometrico(int \$servidorId, string \$fecha) { return ApiResponse::ok(\$this->service->validarCruceBiometrico(\$servidorId, \$fecha), 'Verificación de cruce completada'); } }
EOT);

file_put_contents("$ctlDir/InformeActividadController.php", <<<EOT
<?php namespace App\Http\Controllers\Actividades; use App\Http\Controllers\Controller; use App\Http\Responses\ApiResponse; use App\Contracts\Actividades\ActividadesServiceInterface; use Illuminate\Http\Request; class InformeActividadController extends Controller { public function __construct(private readonly ActividadesServiceInterface \$service) {} public function generar(Request \$request) { return ApiResponse::ok(\$this->service->generarInformeMensual(\$request->servidor_id, \$request->mes, \$request->anio), 'Informe generado correctamente'); } }
EOT);

echo "M16 Script Ok";
?>
