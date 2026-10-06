<?php

/*
| Consulta de marcaciones al biométrico (Sirha7) por cédula.
|
| El biométrico es un SQL Server externo que no existe en las pruebas, así que
| el servicio se sustituye por un doble; lo que se fija aquí es lo que hace el
| SGTH alrededor: qué cédula y qué fechas pide, qué responde cuando el
| procedimiento rechaza la consulta y qué código HTTP sale en cada error. Antes
| todos salían con 422 porque el código iba en el argumento `errores`.
|
| La traducción del RAISERROR se prueba aparte con una conexión simulada.
*/

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\MarcacionBiometricaService;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['nombre' => 'Dirección TIC']);

    $this->marca = Servidor::create([
        'cedula' => '0802704171', 'nombre' => 'Ana', 'apellido' => 'Marca',
        'puesto_id' => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
        'puede_marcar' => true,
    ]);

    $this->noMarca = Servidor::create([
        'cedula' => '0802704172', 'nombre' => 'Beto', 'apellido' => 'Libre',
        'puesto_id' => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
        'puede_marcar' => false,
    ]);

    $this->usuario = User::create([
        'email' => 'ana.marca@example.com', 'usuario_ti' => 'ana_marca',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $this->marca->id,
    ]);
    $this->usuario->assignRole('admin-uath');
});

function filaBiometrico(string $fecha, ?string $entrada = '08:00:12'): object
{
    return (object) [
        'Fecha' => $fecha, 'Cedula' => '0802704171', 'BADGENUMBER' => '802704171',
        'Entrada' => $entrada, 'AlmuerzoSalida' => '12:35:00', 'AlmuerzoRetorno' => '13:28:00',
        'Salida' => '17:02:00', 'MarcasPorRevisar' => null,
    ];
}

/** Excepción como la que deja el driver de SQL Server ante un RAISERROR o una caída. */
function errorSqlServer(int $numero, string $mensaje): QueryException
{
    $pdo = new PDOException($mensaje);
    $pdo->errorInfo = ['42000', $numero, "[Microsoft][ODBC Driver 18 for SQL Server][SQL Server]{$mensaje}"];

    return new QueryException('sqlsrv', 'EXEC dbo.sp_SGTH_MarcacionesPorCedula ?, ?, ?', [], $pdo);
}

it('consulta el biométrico con la cédula de 10 dígitos y el rango pedido', function () {
    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('porCedula')->once()
        ->withArgs(fn ($cedula, $desde, $hasta) => $cedula === '0802704171'
            && $desde->format('Y-m-d') === '2026-09-01'
            && $hasta->format('Y-m-d') === '2026-09-30')
        ->andReturn([filaBiometrico('2026-09-01'), filaBiometrico('2026-09-02')]);

    $this->actingAs($this->usuario)
        ->getJson('/api/v1/asistencia/marcaciones?cedula=0802704171&fecha_inicio=2026-09-01&fecha_fin=2026-09-30')
        ->assertOk()
        ->assertJsonCount(2, 'datos')
        ->assertJsonPath('datos.0.Fecha', '2026-09-01')
        ->assertJsonPath('datos.0.Entrada', '08:00:12');
});

it('responde 404 si el servidor no tiene la marcación habilitada, sin consultar el biométrico', function () {
    $this->mock(MarcacionBiometricaService::class)->shouldNotReceive('porCedula');

    $this->actingAs($this->usuario)
        ->getJson('/api/v1/asistencia/marcaciones?cedula=0802704172&fecha_inicio=2026-09-01&fecha_fin=2026-09-30')
        ->assertNotFound();
});

it('devuelve 422 con el mensaje del procedimiento cuando rechaza la cédula', function () {
    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('porCedula')
        ->andThrow(new ReglaNegocioException('La cédula 0802704171 está registrada en varios usuarios del biométrico.'));

    $this->actingAs($this->usuario)
        ->getJson('/api/v1/asistencia/marcaciones?cedula=0802704171&fecha_inicio=2026-09-01&fecha_fin=2026-09-30')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La cédula 0802704171 está registrada en varios usuarios del biométrico.');
});

it('devuelve 503 cuando no se puede hablar con el biométrico', function () {
    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('porCedula')
        ->andThrow(errorSqlServer(-1, 'Login timeout expired'));

    $this->actingAs($this->usuario)
        ->getJson('/api/v1/asistencia/marcaciones?cedula=0802704171&fecha_inicio=2026-09-01&fecha_fin=2026-09-30')
        ->assertStatus(503);
});

it('el estado de hoy es la fila de hoy de quien consulta', function () {
    Carbon::setTestNow('2026-10-05 10:00:00');

    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('porCedula')->once()
        ->withArgs(fn ($cedula, $desde, $hasta) => $cedula === '0802704171'
            && $desde->format('Y-m-d') === '2026-10-05'
            && $hasta->format('Y-m-d') === '2026-10-05')
        ->andReturn([filaBiometrico('2026-10-05', '07:58:00')]);

    $this->actingAs($this->usuario)
        ->getJson('/api/v1/asistencia/marcaciones/estado-hoy')
        ->assertOk()
        ->assertJsonPath('datos.Entrada', '07:58:00');
});

it('el estado de hoy es null si el biométrico no devuelve fila para hoy', function () {
    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('porCedula')->andReturn([]);

    $this->actingAs($this->usuario)
        ->getJson('/api/v1/asistencia/marcaciones/estado-hoy')
        ->assertOk()
        ->assertJsonPath('datos', null);
});

it('el estado de hoy responde 403 si quien consulta no tiene la marcación habilitada', function () {
    $this->usuario->update(['servidor_id' => $this->noMarca->id]);
    $this->mock(MarcacionBiometricaService::class)->shouldNotReceive('porCedula');

    $this->actingAs($this->usuario->fresh())
        ->getJson('/api/v1/asistencia/marcaciones/estado-hoy')
        ->assertForbidden();
});

/**
 * El servicio con una conexión simulada en lugar de la de SQL Server. No se
 * sustituye la fachada DB: eso dejaría a RefreshDatabase sin su conexión.
 */
function biometricoSobre(ConnectionInterface $conexion): MarcacionBiometricaService
{
    return new class ($conexion) extends MarcacionBiometricaService {
        public function __construct(private ConnectionInterface $simulada) {}

        protected function conexion(): ConnectionInterface
        {
            return $this->simulada;
        }
    };
}

describe('servicio', function () {
    it('llama al procedimiento nuevo con las fechas en ISO básico', function () {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->once()
            ->with('EXEC dbo.sp_SGTH_MarcacionesPorCedula ?, ?, ?', ['0802704171', '20260901', '20260930'])
            ->andReturn([]);

        expect(biometricoSobre($conexion)
            ->porCedula('0802704171', Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')))
            ->toBe([]);
    });

    it('convierte el RAISERROR del procedimiento en una regla de negocio sin los prefijos del driver', function () {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')
            ->andThrow(errorSqlServer(50000, '1111111111 es la cédula de relleno del biométrico, no identifica a nadie.'));

        biometricoSobre($conexion)->porCedula('1111111111', Carbon::today(), Carbon::today());
    })->throws(ReglaNegocioException::class, '1111111111 es la cédula de relleno del biométrico, no identifica a nadie.');

    it('deja pasar los demás errores de SQL Server', function () {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->andThrow(errorSqlServer(2812, 'No se encontró el procedimiento almacenado'));

        biometricoSobre($conexion)->porCedula('0802704171', Carbon::today(), Carbon::today());
    })->throws(QueryException::class);
});
