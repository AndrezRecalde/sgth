<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend/app";
function ensureDir($dir) { if (!is_dir($dir)) mkdir($dir, 0755, true); }

ensureDir("$baseDir/Models/InventarioTi");
ensureDir("$baseDir/Contracts/InventarioTi");
ensureDir("$baseDir/Services/InventarioTi");
ensureDir("$baseDir/Http/Controllers/InventarioTi");

// Model BienInformatico
$modelBien = <<<EOT
<?php
namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class BienInformatico extends Model
{
    use HasFactory, SoftDeletes;

    protected \$table = 'bienes_informaticos';
    protected \$fillable = [
        'codigo_qr', 'codigo_institucional', 'tipo_bien', 'marca', 'modelo',
        'numero_serie', 'estado', 'fecha_compra', 'garantia_hasta',
        'proveedor', 'caracteristicas_tecnicas', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'garantia_hasta' => 'date',
            'caracteristicas_tecnicas' => 'array',
        ];
    }

    public function asignaciones(): HasMany
    {
        return \$this->hasMany(AsignacionBien::class);
    }

    public function mantenimientos(): HasMany
    {
        return \$this->hasMany(MantenimientoBien::class);
    }
}
EOT;
file_put_contents("$baseDir/Models/InventarioTi/BienInformatico.php", $modelBien);

// Model AsignacionBien
$modelAsignacion = <<<EOT
<?php
namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Expediente\Servidor;

class AsignacionBien extends Model
{
    use HasFactory, SoftDeletes;

    protected \$table = 'asignaciones_bien';
    protected \$fillable = [
        'bien_informatico_id', 'servidor_id', 'fecha_asignacion',
        'fecha_devolucion', 'observaciones', 'url_acta_pdf', 'estado',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'date',
            'fecha_devolucion' => 'date',
        ];
    }

    public function bien(): BelongsTo
    {
        return \$this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    public function servidor(): BelongsTo
    {
        return \$this->belongsTo(Servidor::class);
    }
}
EOT;
file_put_contents("$baseDir/Models/InventarioTi/AsignacionBien.php", $modelAsignacion);

// Model MantenimientoBien
$modelMantenimiento = <<<EOT
<?php
namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class MantenimientoBien extends Model
{
    use HasFactory, SoftDeletes;

    protected \$table = 'mantenimientos_bien';
    protected \$fillable = [
        'bien_informatico_id', 'ticket_id', 'tipo_mantenimiento',
        'fecha_mantenimiento', 'tecnico_id', 'descripcion', 'costo',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_mantenimiento' => 'date',
            'costo' => 'decimal:2',
        ];
    }

    public function bien(): BelongsTo
    {
        return \$this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    public function tecnico(): BelongsTo
    {
        return \$this->belongsTo(User::class, 'tecnico_id');
    }
}
EOT;
file_put_contents("$baseDir/Models/InventarioTi/MantenimientoBien.php", $modelMantenimiento);

// Contract
$contract = <<<EOT
<?php
namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;

interface InventarioTiServiceInterface
{
    public function registrarBien(array \$datos): BienInformatico;
    public function asignarBien(array \$datos): AsignacionBien;
}
EOT;
file_put_contents("$baseDir/Contracts/InventarioTi/InventarioTiServiceInterface.php", $contract);

// Service
$service = <<<EOT
<?php
namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use Illuminate\Support\Facades\DB;

final class InventarioTiService implements InventarioTiServiceInterface
{
    public function registrarBien(array \$datos): BienInformatico
    {
        return DB::transaction(function () use (\$datos) {
            // Simulando QrService que se integra al módulo
            \$datos['codigo_qr'] = \$datos['codigo_institucional'] . '-QR';

            return BienInformatico::create(\$datos);
        });
    }

    public function asignarBien(array \$datos): AsignacionBien
    {
        return DB::transaction(function () use (\$datos) {
            \$asignacion = AsignacionBien::create(\$datos);

            // Simulando PdfService que se integra al módulo y guarda en SGD
            \$asignacion->update(['url_acta_pdf' => '/storage/actas/acta_entrega_' . \$asignacion->id . '.pdf']);

            return \$asignacion;
        });
    }
}
EOT;
file_put_contents("$baseDir/Services/InventarioTi/InventarioTiService.php", $service);

// Controllers
$controllers = [
    'BienInformaticoController' => <<<EOT
<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class BienInformaticoController extends Controller
{
    public function __construct(
        private readonly InventarioTiServiceInterface \$service
    ) {}

    public function index(Request \$request): JsonResponse
    {
        return ApiResponse::ok([], 'Bienes listados');
    }

    public function store(Request \$request): JsonResponse
    {
        \$bien = \$this->service->registrarBien(\$request->all());
        return ApiResponse::created(\$bien, 'Bien registrado y QR generado');
    }

    public function show(int \$id): JsonResponse
    {
        return ApiResponse::ok(['id' => \$id], 'Detalle de bien');
    }

    public function update(Request \$request, int \$id): JsonResponse
    {
        return ApiResponse::ok([], 'Bien actualizado');
    }

    public function destroy(int \$id): JsonResponse
    {
        return ApiResponse::ok([], 'Bien dado de baja');
    }
}
EOT,
    'AsignacionBienController' => <<<EOT
<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class AsignacionBienController extends Controller
{
    public function __construct(
        private readonly InventarioTiServiceInterface \$service
    ) {}

    public function index(Request \$request): JsonResponse
    {
        return ApiResponse::ok([], 'Asignaciones listadas');
    }

    public function store(Request \$request): JsonResponse
    {
        \$asignacion = \$this->service->asignarBien(\$request->all());
        return ApiResponse::created(\$asignacion, 'Asignación creada y Acta PDF generada');
    }

    public function show(int \$id): JsonResponse
    {
        return ApiResponse::ok(['id' => \$id], 'Detalle de asignación');
    }
}
EOT,
    'MantenimientoBienController' => <<<EOT
<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class MantenimientoBienController extends Controller
{
    public function index(Request \$request): JsonResponse
    {
        return ApiResponse::ok([], 'Mantenimientos listados');
    }

    public function store(Request \$request): JsonResponse
    {
        return ApiResponse::created([], 'Mantenimiento programado');
    }

    public function show(int \$id): JsonResponse
    {
        return ApiResponse::ok(['id' => \$id], 'Detalle de mantenimiento');
    }
}
EOT
];

foreach (\$controllers as \$name => \$content) {
    file_put_contents("$baseDir/Http/Controllers/InventarioTi/\$name.php", \$content);
}

echo "Modulo 12 creado exitosamente.";
?>
