<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend/app";

function ensureDir($dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

ensureDir("$baseDir/Http/Controllers/Dispensario");

// AgendaController
$agenda = <<<EOT
<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\AgendaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class AgendaController extends Controller
{
    public function __construct(
        private readonly AgendaServiceInterface \$agendaService
    ) {}

    public function index(Request \$request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de agenda');
    }

    public function store(Request \$request): JsonResponse
    {
        \$cita = \$this->agendaService->agendarCita(\$request->all());
        return ApiResponse::created(\$cita, 'Cita agendada');
    }

    public function show(int \$id): JsonResponse
    {
        return ApiResponse::ok(['id' => \$id], 'Detalle de cita');
    }

    public function update(Request \$request, int \$id): JsonResponse
    {
        return ApiResponse::ok([], 'Cita actualizada');
    }

    public function destroy(int \$id): JsonResponse
    {
        return ApiResponse::ok([], 'Cita cancelada');
    }
}
EOT;
file_put_contents("$baseDir/Http/Controllers/Dispensario/AgendaController.php", $agenda);

// HistoriaClinicaController
$historia = <<<EOT
<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class HistoriaClinicaController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaServiceInterface \$historiaService
    ) {}

    public function index(Request \$request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de historias');
    }

    public function store(Request \$request): JsonResponse
    {
        \$historia = \$this->historiaService->crearHistoria(\$request->all());
        return ApiResponse::created(\$historia, 'Historia clínica creada');
    }

    public function show(int \$id): JsonResponse
    {
        return ApiResponse::ok(['id' => \$id], 'Detalle de historia');
    }
}
EOT;
file_put_contents("$baseDir/Http/Controllers/Dispensario/HistoriaClinicaController.php", $historia);

// ConsultaMedicaController
$consulta = <<<EOT
<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class ConsultaMedicaController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaServiceInterface \$historiaService
    ) {}

    public function index(Request \$request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de consultas');
    }

    public function store(Request \$request): JsonResponse
    {
        \$consulta = \$this->historiaService->registrarConsulta(\$request->all());
        return ApiResponse::created(\$consulta, 'Consulta registrada');
    }

    public function show(int \$id): JsonResponse
    {
        return ApiResponse::ok(['id' => \$id], 'Detalle de consulta');
    }
}
EOT;
file_put_contents("$baseDir/Http/Controllers/Dispensario/ConsultaMedicaController.php", $consulta);

// RecetaController
$receta = <<<EOT
<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\RecetaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class RecetaController extends Controller
{
    public function __construct(
        private readonly RecetaServiceInterface \$recetaService
    ) {}

    public function store(Request \$request): JsonResponse
    {
        \$datosReceta = \$request->except('items');
        \$items = \$request->input('items', []);
        \$receta = \$this->recetaService->emitirReceta(\$datosReceta, \$items);
        return ApiResponse::created(\$receta, 'Receta emitida');
    }

    public function show(int \$id): JsonResponse
    {
        return ApiResponse::ok(['id' => \$id], 'Detalle de receta');
    }

    public function despachar(Request \$request, int \$id): JsonResponse
    {
        return ApiResponse::ok([], 'Receta despachada exitosamente');
    }
}
EOT;
file_put_contents("$baseDir/Http/Controllers/Dispensario/RecetaController.php", $receta);

// InventarioMedicinasController
$inventario = <<<EOT
<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\InventarioMedicinasServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class InventarioMedicinasController extends Controller
{
    public function __construct(
        private readonly InventarioMedicinasServiceInterface \$inventarioService
    ) {}

    public function index(Request \$request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de medicinas');
    }

    public function store(Request \$request): JsonResponse
    {
        \$medicina = \$this->inventarioService->ingresarMedicina(\$request->all());
        return ApiResponse::created(\$medicina, 'Medicina ingresada');
    }

    public function update(Request \$request, int \$id): JsonResponse
    {
        return ApiResponse::ok([], 'Medicina actualizada');
    }

    public function destroy(int \$id): JsonResponse
    {
        return ApiResponse::ok([], 'Medicina dada de baja');
    }

    public function kardex(int \$id): JsonResponse
    {
        return ApiResponse::ok([], 'Movimientos de kardex');
    }
}
EOT;
file_put_contents("$baseDir/Http/Controllers/Dispensario/InventarioMedicinasController.php", $inventario);

// FichaSaludOcupacionalController
$ficha = <<<EOT
<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class FichaSaludOcupacionalController extends Controller
{
    public function index(Request \$request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de fichas de salud');
    }

    public function store(Request \$request): JsonResponse
    {
        return ApiResponse::created([], 'Ficha registrada');
    }

    public function show(int \$id): JsonResponse
    {
        return ApiResponse::ok(['id' => \$id], 'Detalle de ficha');
    }
}
EOT;
file_put_contents("$baseDir/Http/Controllers/Dispensario/FichaSaludOcupacionalController.php", $ficha);

echo "Controladores creados exitosamente.";
?>
