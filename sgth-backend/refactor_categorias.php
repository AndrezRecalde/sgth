<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend";
$migPath = "$baseDir/database/migrations";

file_put_contents("$migPath/2026_05_13_163000_crear_tabla_categorias_ticket.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('categorias_ticket', function (Blueprint \$table) {
            \$table->id();
            \$table->string('nombre', 100)->unique();
            \$table->boolean('es_hardware')->default(false);
            \$table->timestamps();
            \$table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('categorias_ticket'); }
};
EOT);

file_put_contents("$migPath/2026_05_13_163001_update_tickets_categoria.php", <<<EOT
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('tickets', function (Blueprint \$table) {
            \$table->dropColumn('categoria');
            \$table->foreignId('categoria_id')->nullable()->constrained('categorias_ticket')->restrictOnDelete();
        });
    }
    public function down(): void { 
        Schema::table('tickets', function (Blueprint \$table) {
            \$table->dropConstrainedForeignId('categoria_id');
            \$table->string('categoria', 50)->nullable();
        });
    }
};
EOT);

file_put_contents("$baseDir/app/Models/Helpdesk/CategoriaTicket.php", <<<EOT
<?php namespace App\Models\Helpdesk; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class CategoriaTicket extends Model { use SoftDeletes; protected \$table = 'categorias_ticket'; protected \$fillable = ['nombre', 'es_hardware']; protected function casts(): array { return ['es_hardware' => 'boolean']; } }
EOT);

$ticketModelPath = "$baseDir/app/Models/Helpdesk/Ticket.php";
$ticketContent = file_get_contents($ticketModelPath);
$ticketContent = str_replace("'categoria'", "'categoria_id'", $ticketContent);
$relacion = "public function categoria() { return \$this->belongsTo(CategoriaTicket::class); }\n    public function solicitante()";
$ticketContent = str_replace("public function solicitante()", $relacion, $ticketContent);
file_put_contents($ticketModelPath, $ticketContent);

$helpdeskServicePath = "$baseDir/app/Services/Helpdesk/HelpdeskService.php";
$svcContent = file_get_contents($helpdeskServicePath);
$useStmts = "use App\Models\Helpdesk\CategoriaTicket;\nuse App\Models\Helpdesk\Ticket;";
$svcContent = str_replace("use App\Models\Helpdesk\Ticket;", $useStmts, $svcContent);
$oldLogic = "if (!empty(\$datos['bien_informatico_id']) && \$datos['categoria'] === 'hardware')";
$newLogic = "\$categoria = CategoriaTicket::find(\$datos['categoria_id'] ?? null);\n            if (!empty(\$datos['bien_informatico_id']) && \$categoria && \$categoria->es_hardware)";
$svcContent = str_replace($oldLogic, $newLogic, $svcContent);
file_put_contents($helpdeskServicePath, $svcContent);

echo "Listo";
?>
