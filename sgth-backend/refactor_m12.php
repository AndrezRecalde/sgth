<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend";
$migPath = "$baseDir/database/migrations";

// 1. Delete old M12 migrations
$files = glob("$migPath/*");
foreach($files as $file) {
    if (strpos($file, 'bienes_informaticos') !== false || 
        strpos($file, 'asignaciones_bien') !== false || 
        strpos($file, 'mantenimientos_bien') !== false) {
        unlink($file);
    }
}

// 2. Create New Migrations (Timestamps must be BEFORE M13 tickets which is 160919)
file_put_contents("$migPath/2026_05_13_160210_crear_tabla_tipos_bien.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tipos_bien', function (Blueprint \$table) {
            \$table->id();
            \$table->string('nombre', 100)->unique();
            \$table->integer('anios_vida_util')->default(0);
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('tipos_bien'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160211_crear_tabla_marcas.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('marcas', function (Blueprint \$table) {
            \$table->id();
            \$table->string('nombre', 100)->unique();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('marcas'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160212_crear_tabla_origenes_bien.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('origenes_bien', function (Blueprint \$table) {
            \$table->id();
            \$table->string('tipo_origen', 50); // compra_publica, donacion_nacional, donacion_internacional, comodato
            \$table->string('identificador_documento', 100)->nullable(); // Opcional para donaciones
            \$table->string('entidad_origen', 150);
            \$table->date('fecha_adquisicion');
            \$table->date('garantia_hasta')->nullable();
            \$table->string('url_documento_pdf')->nullable();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('origenes_bien'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160213_crear_tabla_bienes_informaticos.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('bienes_informaticos', function (Blueprint \$table) {
            \$table->id();
            \$table->string('codigo_qr', 100)->unique();
            \$table->string('codigo_institucional', 100)->unique();
            \$table->foreignId('tipo_bien_id')->constrained('tipos_bien')->restrictOnDelete();
            \$table->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();
            \$table->foreignId('origen_bien_id')->constrained('origenes_bien')->restrictOnDelete();
            \$table->string('modelo', 100)->nullable();
            \$table->string('numero_serie', 100)->unique();
            \$table->string('estado_operativo', 50)->default('activo'); // activo, en_mantenimiento, dado_de_baja, robado, perdido
            \$table->string('condicion_fisica', 50)->default('bueno'); // bueno, regular, malo
            \$table->date('fecha_fin_vida_util')->nullable(); // Calculado auto
            \$table->json('caracteristicas_tecnicas')->nullable();
            \$table->foreignId('created_by')->nullable()->constrained('users');
            \$table->foreignId('updated_by')->nullable()->constrained('users');
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('bienes_informaticos'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160214_crear_tabla_asignaciones_bien.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('asignaciones_bien', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('bien_informatico_id')->constrained('bienes_informaticos')->restrictOnDelete();
            \$table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            \$table->date('fecha_asignacion');
            \$table->date('fecha_devolucion')->nullable();
            \$table->text('observaciones')->nullable();
            \$table->string('url_acta_pdf')->nullable();
            \$table->string('estado', 50)->default('activa');
            \$table->foreignId('created_by')->nullable()->constrained('users');
            \$table->foreignId('updated_by')->nullable()->constrained('users');
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('asignaciones_bien'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_160215_crear_tabla_mantenimientos_bien.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('mantenimientos_bien', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('bien_informatico_id')->constrained('bienes_informaticos')->restrictOnDelete();
            \$table->unsignedBigInteger('ticket_id')->nullable(); // nullable fk to tickets
            \$table->string('tipo_mantenimiento', 50);
            \$table->date('fecha_mantenimiento');
            \$table->foreignId('tecnico_id')->nullable()->constrained('users');
            \$table->text('descripcion');
            \$table->decimal('costo', 10, 2)->default(0);
            \$table->foreignId('created_by')->nullable()->constrained('users');
            \$table->foreignId('updated_by')->nullable()->constrained('users');
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('mantenimientos_bien'); }
};
EOT);

// 3. Update Models
$modelsPath = "$baseDir/app/Models/InventarioTi";
file_put_contents("$modelsPath/TipoBien.php", <<<EOT
<?php namespace App\Models\InventarioTi; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class TipoBien extends Model { use SoftDeletes; protected \$table = 'tipos_bien'; protected \$fillable = ['nombre', 'anios_vida_util']; }
EOT);

file_put_contents("$modelsPath/Marca.php", <<<EOT
<?php namespace App\Models\InventarioTi; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class Marca extends Model { use SoftDeletes; protected \$table = 'marcas'; protected \$fillable = ['nombre']; }
EOT);

file_put_contents("$modelsPath/OrigenBien.php", <<<EOT
<?php namespace App\Models\InventarioTi; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class OrigenBien extends Model { use SoftDeletes; protected \$table = 'origenes_bien'; protected \$fillable = ['tipo_origen', 'identificador_documento', 'entidad_origen', 'fecha_adquisicion', 'garantia_hasta', 'url_documento_pdf']; protected function casts(): array { return ['fecha_adquisicion' => 'date', 'garantia_hasta' => 'date']; } }
EOT);

file_put_contents("$modelsPath/BienInformatico.php", <<<EOT
<?php
namespace App\Models\InventarioTi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BienInformatico extends Model {
    use HasFactory, SoftDeletes;
    protected \$table = 'bienes_informaticos';
    protected \$fillable = ['codigo_qr', 'codigo_institucional', 'tipo_bien_id', 'marca_id', 'origen_bien_id', 'modelo', 'numero_serie', 'estado_operativo', 'condicion_fisica', 'fecha_fin_vida_util', 'caracteristicas_tecnicas', 'created_by', 'updated_by'];
    protected function casts(): array { return ['fecha_fin_vida_util' => 'date', 'caracteristicas_tecnicas' => 'array']; }
    public function tipo() { return \$this->belongsTo(TipoBien::class, 'tipo_bien_id'); }
    public function marca() { return \$this->belongsTo(Marca::class, 'marca_id'); }
    public function origen() { return \$this->belongsTo(OrigenBien::class, 'origen_bien_id'); }
}
EOT);

// 4. Update Service
file_put_contents("$baseDir/app/Services/InventarioTi/InventarioTiService.php", <<<EOT
<?php
namespace App\Services\InventarioTi;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\OrigenBien;
use Illuminate\Support\Facades\DB;
final class InventarioTiService implements InventarioTiServiceInterface {
    public function registrarBien(array \$datos): BienInformatico {
        return DB::transaction(function () use (\$datos) {
            // Calcular vida útil si tenemos el origen y el tipo
            if (!empty(\$datos['origen_bien_id']) && !empty(\$datos['tipo_bien_id'])) {
                \$origen = OrigenBien::find(\$datos['origen_bien_id']);
                \$tipo = TipoBien::find(\$datos['tipo_bien_id']);
                if (\$origen && \$tipo && \$tipo->anios_vida_util > 0) {
                    \$datos['fecha_fin_vida_util'] = \$origen->fecha_adquisicion->copy()->addYears(\$tipo->anios_vida_util);
                }
            }
            // Generación de QR
            \$datos['codigo_qr'] = \$datos['codigo_institucional'] . '-QR';
            return BienInformatico::create(\$datos);
        });
    }
    public function asignarBien(array \$datos): AsignacionBien {
        return DB::transaction(function () use (\$datos) {
            \$asignacion = AsignacionBien::create(\$datos);
            \$asignacion->update(['url_acta_pdf' => '/storage/actas/acta_entrega_' . \$asignacion->id . '.pdf']);
            return \$asignacion;
        });
    }
}
EOT);

// 5. Update HelpdeskService since BienInformatico estado is now estado_operativo
$helpdeskSvc = "$baseDir/app/Services/Helpdesk/HelpdeskService.php";
$content = file_get_contents($helpdeskSvc);
$content = str_replace("'estado' => 'en_mantenimiento'", "'estado_operativo' => 'en_mantenimiento'", $content);
$content = str_replace("'estado' => 'activo'", "'estado_operativo' => 'activo'", $content);
file_put_contents($helpdeskSvc, $content);

echo "Refactor M12 Completo";
?>
