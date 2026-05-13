<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend";
// MIGRATIONS
$migPath = "$baseDir/database/migrations";
file_put_contents("$migPath/2026_05_13_160917_crear_tabla_areas_dtic.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('areas_dtic', function (Blueprint \$table) {
            \$table->id();
            \$table->string('nombre', 100);
            \$table->text('descripcion')->nullable();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('areas_dtic'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160918_crear_tabla_tecnicos_dtic.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tecnicos_dtic', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            \$table->foreignId('area_dtic_id')->constrained('areas_dtic')->restrictOnDelete();
            \$table->integer('nivel')->default(1); // 1: Mesa, 2: Especialista, 3: Proveedor
            \$table->boolean('estado')->default(true);
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('tecnicos_dtic'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160918_crear_tabla_slas.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('slas', function (Blueprint \$table) {
            \$table->id();
            \$table->string('prioridad', 50)->unique(); // critica, alta, media, baja
            \$table->integer('tiempo_resolucion_horas');
            \$table->integer('tiempo_respuesta_horas')->nullable();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('slas'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160919_crear_tabla_tickets.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tickets', function (Blueprint \$table) {
            \$table->id();
            \$table->string('codigo_ticket', 50)->unique();
            \$table->foreignId('solicitante_id')->constrained('servidores')->restrictOnDelete();
            \$table->string('tipo_ticket', 50); // incidente, solicitud_servicio, cambio, problema
            \$table->string('categoria', 50);
            \$table->foreignId('sla_id')->constrained('slas')->restrictOnDelete();
            \$table->string('estado', 50)->default('nuevo'); // nuevo, asignado, en_progreso, escalado, resuelto, cerrado
            \$table->string('asunto');
            \$table->text('descripcion');
            \$table->foreignId('bien_informatico_id')->nullable()->constrained('bienes_informaticos');
            \$table->foreignId('tecnico_id')->nullable()->constrained('users');
            \$table->datetime('fecha_vencimiento_sla')->nullable();
            \$table->datetime('fecha_cierre')->nullable();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('tickets'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160919_crear_tabla_comentarios_ticket.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('comentarios_ticket', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            \$table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            \$table->text('comentario');
            \$table->boolean('es_interno')->default(false);
            \$table->string('evidencia_url')->nullable();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('comentarios_ticket'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160919_crear_tabla_encuestas_satisfaccion.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('encuestas_satisfaccion', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            \$table->integer('calificacion'); // 1 a 5
            \$table->text('comentarios')->nullable();
            \$table->datetime('fecha_respuesta');
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('encuestas_satisfaccion'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160920_crear_tabla_base_conocimiento.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('base_conocimiento', function (Blueprint \$table) {
            \$table->id();
            \$table->string('titulo');
            \$table->text('contenido');
            \$table->string('categoria', 50);
            \$table->string('etiquetas')->nullable();
            \$table->foreignId('autor_id')->constrained('users');
            \$table->integer('vistas')->default(0);
            \$table->boolean('es_publico')->default(true);
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('base_conocimiento'); }
};
EOT);

// MODELS
mkdir("$baseDir/app/Models/Helpdesk", 0755, true);
file_put_contents("$baseDir/app/Models/Helpdesk/AreaDtic.php", <<<EOT
<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class AreaDtic extends Model { use SoftDeletes; protected \$table = 'areas_dtic'; protected \$fillable = ['nombre', 'descripcion']; }
EOT);

file_put_contents("$baseDir/app/Models/Helpdesk/TecnicoDtic.php", <<<EOT
<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\User; class TecnicoDtic extends Model { use SoftDeletes; protected \$table = 'tecnicos_dtic'; protected \$fillable = ['user_id', 'area_dtic_id', 'nivel', 'estado']; public function user() { return \$this->belongsTo(User::class); } public function area() { return \$this->belongsTo(AreaDtic::class, 'area_dtic_id'); } }
EOT);

file_put_contents("$baseDir/app/Models/Helpdesk/Sla.php", <<<EOT
<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class Sla extends Model { use SoftDeletes; protected \$table = 'slas'; protected \$fillable = ['prioridad', 'tiempo_resolucion_horas', 'tiempo_respuesta_horas']; }
EOT);

file_put_contents("$baseDir/app/Models/Helpdesk/Ticket.php", <<<EOT
<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\Expediente\Servidor; use App\Models\User; use App\Models\InventarioTi\BienInformatico; class Ticket extends Model { use SoftDeletes; protected \$table = 'tickets'; protected \$fillable = ['codigo_ticket', 'solicitante_id', 'tipo_ticket', 'categoria', 'sla_id', 'estado', 'asunto', 'descripcion', 'bien_informatico_id', 'tecnico_id', 'fecha_vencimiento_sla', 'fecha_cierre']; protected function casts(): array { return ['fecha_vencimiento_sla' => 'datetime', 'fecha_cierre' => 'datetime']; } public function solicitante() { return \$this->belongsTo(Servidor::class); } public function sla() { return \$this->belongsTo(Sla::class); } public function bien() { return \$this->belongsTo(BienInformatico::class, 'bien_informatico_id'); } public function tecnico() { return \$this->belongsTo(User::class, 'tecnico_id'); } }
EOT);

file_put_contents("$baseDir/app/Models/Helpdesk/ComentarioTicket.php", <<<EOT
<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\User; class ComentarioTicket extends Model { use SoftDeletes; protected \$table = 'comentarios_ticket'; protected \$fillable = ['ticket_id', 'user_id', 'comentario', 'es_interno', 'evidencia_url']; protected function casts(): array { return ['es_interno' => 'boolean']; } public function ticket() { return \$this->belongsTo(Ticket::class); } public function user() { return \$this->belongsTo(User::class); } }
EOT);

file_put_contents("$baseDir/app/Models/Helpdesk/EncuestaSatisfaccion.php", <<<EOT
<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class EncuestaSatisfaccion extends Model { use SoftDeletes; protected \$table = 'encuestas_satisfaccion'; protected \$fillable = ['ticket_id', 'calificacion', 'comentarios', 'fecha_respuesta']; protected function casts(): array { return ['fecha_respuesta' => 'datetime']; } public function ticket() { return \$this->belongsTo(Ticket::class); } }
EOT);

file_put_contents("$baseDir/app/Models/Helpdesk/BaseConocimiento.php", <<<EOT
<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use App\Models\User; class BaseConocimiento extends Model { use SoftDeletes; protected \$table = 'base_conocimiento'; protected \$fillable = ['titulo', 'contenido', 'categoria', 'etiquetas', 'autor_id', 'vistas', 'es_publico']; protected function casts(): array { return ['es_publico' => 'boolean']; } public function autor() { return \$this->belongsTo(User::class, 'autor_id'); } }
EOT);

// CONTRACT & SERVICE
mkdir("$baseDir/app/Contracts/Helpdesk", 0755, true);
mkdir("$baseDir/app/Services/Helpdesk", 0755, true);

file_put_contents("$baseDir/app/Contracts/Helpdesk/HelpdeskServiceInterface.php", <<<EOT
<?php namespace App\Contracts\Helpdesk; use App\Models\Helpdesk\Ticket; interface HelpdeskServiceInterface { public function crearTicket(array \$datos): Ticket; public function cerrarTicket(int \$id, array \$datos): Ticket; }
EOT);

file_put_contents("$baseDir/app/Services/Helpdesk/HelpdeskService.php", <<<EOT
<?php namespace App\Services\Helpdesk; use App\Contracts\Helpdesk\HelpdeskServiceInterface; use App\Models\Helpdesk\Ticket; use App\Models\Helpdesk\Sla; use App\Models\InventarioTi\BienInformatico; use App\Models\InventarioTi\MantenimientoBien; use Illuminate\Support\Facades\DB; class HelpdeskService implements HelpdeskServiceInterface { public function crearTicket(array \$datos): Ticket { return DB::transaction(function () use (\$datos) { \$sla = Sla::findOrFail(\$datos['sla_id']); \$datos['fecha_vencimiento_sla'] = now()->addHours(\$sla->tiempo_resolucion_horas); \$datos['codigo_ticket'] = 'TIC-' . strtoupper(uniqid()); \$ticket = Ticket::create(\$datos); if (!empty(\$datos['bien_informatico_id']) && \$datos['categoria'] === 'hardware') { MantenimientoBien::create(['bien_informatico_id' => \$datos['bien_informatico_id'], 'ticket_id' => \$ticket->id, 'tipo_mantenimiento' => 'correctivo', 'fecha_mantenimiento' => now()->toDateString(), 'descripcion' => \$ticket->asunto, 'costo' => 0]); \$bien = BienInformatico::find(\$datos['bien_informatico_id']); if(\$bien) \$bien->update(['estado' => 'en_mantenimiento']); } return \$ticket; }); } public function cerrarTicket(int \$id, array \$datos): Ticket { return DB::transaction(function () use (\$id, \$datos) { \$ticket = Ticket::findOrFail(\$id); \$ticket->update(['estado' => 'cerrado', 'fecha_cierre' => now()]); if (\$ticket->bien_informatico_id) { \$bien = BienInformatico::find(\$ticket->bien_informatico_id); if (\$bien) \$bien->update(['estado' => 'activo']); } // Disparar encuesta de satisfaccion (Mock/Simulado) return \$ticket; }); } }
EOT);

// CONTROLLERS
mkdir("$baseDir/app/Http/Controllers/Helpdesk", 0755, true);

file_put_contents("$baseDir/app/Http/Controllers/Helpdesk/TicketController.php", <<<EOT
<?php namespace App\Http\Controllers\Helpdesk; use App\Http\Controllers\Controller; use App\Http\Responses\ApiResponse; use App\Contracts\Helpdesk\HelpdeskServiceInterface; use Illuminate\Http\Request; class TicketController extends Controller { public function __construct(private readonly HelpdeskServiceInterface \$service) {} public function index() { return ApiResponse::ok([], 'Tickets listados'); } public function store(Request \$request) { return ApiResponse::created(\$this->service->crearTicket(\$request->all()), 'Ticket creado'); } public function update(Request \$request, int \$id) { return ApiResponse::ok([], 'Ticket actualizado'); } public function cerrar(Request \$request, int \$id) { return ApiResponse::ok(\$this->service->cerrarTicket(\$id, \$request->all()), 'Ticket cerrado'); } }
EOT);

file_put_contents("$baseDir/app/Http/Controllers/Helpdesk/ComentarioTicketController.php", <<<EOT
<?php namespace App\Http\Controllers\Helpdesk; use App\Http\Controllers\Controller; use App\Http\Responses\ApiResponse; use Illuminate\Http\Request; class ComentarioTicketController extends Controller { public function store(Request \$request) { return ApiResponse::created([], 'Comentario agregado'); } }
EOT);

file_put_contents("$baseDir/app/Http/Controllers/Helpdesk/BaseConocimientoController.php", <<<EOT
<?php namespace App\Http\Controllers\Helpdesk; use App\Http\Controllers\Controller; use App\Http\Responses\ApiResponse; use Illuminate\Http\Request; class BaseConocimientoController extends Controller { public function index() { return ApiResponse::ok([], 'Articulos listados'); } public function show(int \$id) { return ApiResponse::ok(['id'=>\$id], 'Detalle de articulo'); } }
EOT);

echo "M13 Listo";
?>
