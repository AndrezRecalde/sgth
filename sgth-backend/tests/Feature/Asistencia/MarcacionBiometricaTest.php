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

/** Usuario con un único rol y, si se indica, vinculado a un servidor. */
function usuarioMarcaciones(string $rol, ?Servidor $servidor = null): User
{
    $usuario = User::create([
        'email' => "{$rol}.marcaciones@example.com", 'usuario_ti' => "{$rol}_marcaciones",
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $servidor?->id,
    ]);
    $usuario->assignRole($rol);

    return $usuario;
}

describe('quién consulta', function () {
    beforeEach(function () {
        $this->otro = Servidor::create([
            'cedula' => '0802704173', 'nombre' => 'Carla', 'apellido' => 'Otra',
            'puesto_id' => $this->marca->puesto_id,
            'unidad_administrativa_id' => $this->marca->unidad_administrativa_id,
            'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
            'puede_marcar' => true,
        ]);
        $this->consulta = fn (string $cedula) =>
            "/api/v1/asistencia/marcaciones?cedula={$cedula}&fecha_inicio=2026-09-01&fecha_fin=2026-09-30";
    });

    it('un servidor consulta sus propias marcaciones', function () {
        $this->mock(MarcacionBiometricaService::class)
            ->shouldReceive('porCedula')->once()->andReturn([filaBiometrico('2026-09-01')]);

        $this->actingAs(usuarioMarcaciones('servidor', $this->otro))
            ->getJson(($this->consulta)('0802704173'))
            ->assertOk();
    });

    it('un servidor no consulta las de otro', function () {
        $this->mock(MarcacionBiometricaService::class)->shouldNotReceive('porCedula');

        $this->actingAs(usuarioMarcaciones('servidor', $this->otro))
            ->getJson(($this->consulta)('0802704171'))
            ->assertForbidden();
    });

    it('a quien no puede consultar, una cédula inexistente le da 403 y no 404', function () {
        $this->mock(MarcacionBiometricaService::class)->shouldNotReceive('porCedula');

        $this->actingAs(usuarioMarcaciones('servidor', $this->otro))
            ->getJson(($this->consulta)('0899999999'))
            ->assertForbidden();
    });

    it('un usuario sin servidor vinculado no consulta ninguna', function () {
        $this->mock(MarcacionBiometricaService::class)->shouldNotReceive('porCedula');

        $this->actingAs(usuarioMarcaciones('servidor'))
            ->getJson(($this->consulta)('0802704171'))
            ->assertForbidden();
    });

    it('el jefe de unidad tampoco consulta las de otro', function () {
        // Tiene ver-asistencia-unidad, no ver-asistencia-todos.
        $this->mock(MarcacionBiometricaService::class)->shouldNotReceive('porCedula');

        $this->actingAs(usuarioMarcaciones('jefe-unidad', $this->otro))
            ->getJson(($this->consulta)('0802704171'))
            ->assertForbidden();
    });

    it('consulta las de cualquiera quien tiene ver-asistencia-todos', function (string $rol) {
        $this->mock(MarcacionBiometricaService::class)
            ->shouldReceive('porCedula')->once()->andReturn([]);

        $this->actingAs(usuarioMarcaciones($rol))
            ->getJson(($this->consulta)('0802704173'))
            ->assertOk();
    })->with(['admin-uath', 'asistente-uath', 'auditor']);
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

it('la marcación online se registra a nombre de quien la hace, con la hora y la ubicación', function () {
    Carbon::setTestNow('2026-10-13 07:58:30');

    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('registrarMarcacion')->once()
        ->withArgs(fn ($cedula, $tipo, $momento, $latitud, $longitud) => $cedula === '0802704171'
            && $tipo === 'I'
            && $momento->format('Y-m-d H:i:s') === '2026-10-13 07:58:30'
            && $latitud === 0.968254
            && $longitud === -79.651729)
        ->andReturn(true);

    $this->actingAs($this->usuario)
        ->postJson('/api/v1/asistencia/marcaciones/online', [
            'checktype' => 'I', 'latitud' => 0.968254, 'longitud' => -79.651729,
        ])
        ->assertOk();
});

it('la marcación online sin ubicación se registra igual', function () {
    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('registrarMarcacion')->once()
        ->withArgs(fn ($cedula, $tipo, $momento, $latitud, $longitud) => $latitud === null && $longitud === null)
        ->andReturn(true);

    $this->actingAs($this->usuario)
        ->postJson('/api/v1/asistencia/marcaciones/online', ['checktype' => 'O'])
        ->assertOk();
});

it('la marcación online rechaza una ubicación incompleta o fuera de rango', function (array $ubicacion, string $campo) {
    $this->mock(MarcacionBiometricaService::class)->shouldNotReceive('registrarMarcacion');

    $this->actingAs($this->usuario)
        ->postJson('/api/v1/asistencia/marcaciones/online', ['checktype' => 'I'] + $ubicacion)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$campo], 'errores');
})->with([
    'latitud sin longitud' => [['latitud' => 0.96], 'longitud'],
    'longitud sin latitud' => [['longitud' => -79.65], 'latitud'],
    'latitud fuera de rango' => [['latitud' => 95, 'longitud' => -79.65], 'latitud'],
]);

it('la marcación online responde 404 si la cédula no está en el biométrico', function () {
    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('registrarMarcacion')->andReturn(false);

    $this->actingAs($this->usuario)
        ->postJson('/api/v1/asistencia/marcaciones/online', ['checktype' => 'O'])
        ->assertNotFound();
});

it('la marcación online responde 503 si el biométrico no responde', function () {
    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('registrarMarcacion')->andThrow(errorSqlServer(-1, 'Login timeout expired'));

    $this->actingAs($this->usuario)
        ->postJson('/api/v1/asistencia/marcaciones/online', ['checktype' => 'O'])
        ->assertStatus(503);
});

it('la marcación online responde 422 si la cédula no identifica a una sola persona', function () {
    $this->mock(MarcacionBiometricaService::class)
        ->shouldReceive('registrarMarcacion')
        ->andThrow(new ReglaNegocioException('La cédula 0802704171 está registrada en varios usuarios del biométrico.'));

    $this->actingAs($this->usuario)
        ->postJson('/api/v1/asistencia/marcaciones/online', ['checktype' => 'I'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La cédula 0802704171 está registrada en varios usuarios del biométrico.');
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

    it('registra la marcación online con el procedimiento, la fecha en ISO 8601 y la ubicación', function () {
        // Día 13: con «Y-m-d H:i:s» y DATEFORMAT dmy era el mes 13 y fallaba;
        // los días 1 a 12 se guardaban con el mes y el día cambiados.
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->once()
            ->with(
                'EXEC dbo.sp_SGTH_RegistrarMarcacionOnline ?, ?, ?, ?, ?, ?',
                ['0802704171', 'O', '2026-10-13T17:20:05', 0.968254, -79.651729, '2']
            )
            ->andReturn([(object) ['USERID' => '798', 'Resultado' => 'registrada']]);
        $conexion->shouldNotReceive('statement');

        expect(biometricoSobre($conexion)->registrarMarcacion(
            '0802704171', 'O', Carbon::parse('2026-10-13 17:20:05'), 0.968254, -79.651729,
        ))->toBeTrue();
    });

    it('el sensor sale de la configuración', function () {
        config(['services.biometrico.sensor_online' => '7']);

        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->once()
            ->withArgs(fn ($sql, $valores) => $valores[5] === '7')
            ->andReturn([(object) ['USERID' => '798', 'Resultado' => 'registrada']]);

        biometricoSobre($conexion)->registrarMarcacion('0802704171', 'I', Carbon::now());
    });

    it('sin ubicación envía las dos coordenadas vacías', function () {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->once()
            ->withArgs(fn ($sql, $valores) => $valores[3] === null && $valores[4] === null)
            ->andReturn([(object) ['USERID' => '798', 'Resultado' => 'registrada']]);

        expect(biometricoSobre($conexion)->registrarMarcacion('0802704171', 'I', Carbon::now()))
            ->toBeTrue();
    });

    it('un doble toque en el mismo segundo cuenta como registrada', function () {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')
            ->andReturn([(object) ['USERID' => '798', 'Resultado' => 'duplicada']]);

        expect(biometricoSobre($conexion)->registrarMarcacion('0802704171', 'I', Carbon::now()))
            ->toBeTrue();
    });

    it('devuelve false si la cédula no está en el biométrico', function () {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')
            ->andReturn([(object) ['USERID' => null, 'Resultado' => 'no_encontrada']]);

        expect(biometricoSobre($conexion)->registrarMarcacion('0899999999', 'I', Carbon::now()))
            ->toBeFalse();
    });

    it('el RAISERROR del registro también sale como regla de negocio', function () {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->andThrow(errorSqlServer(
            50000,
            'La cédula 0802704171 está registrada en varios usuarios del biométrico y ninguno la tiene como código. Corríjase el SSN en USERINFO.',
        ));

        biometricoSobre($conexion)->registrarMarcacion('0802704171', 'I', Carbon::now());
    })->throws(ReglaNegocioException::class, 'está registrada en varios usuarios del biométrico');

    it('un error inesperado al insertar no se disfraza de regla de negocio', function () {
        // 2627 (clave duplicada) lo absorbe el procedimiento; cualquier otro
        // error del INSERT llega con su número y termina en 503, no en 422.
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->andThrow(errorSqlServer(547, 'Conflicto con la restricción'));

        biometricoSobre($conexion)->registrarMarcacion('0802704171', 'I', Carbon::now());
    })->throws(QueryException::class);

    it('deja pasar los demás errores de SQL Server', function () {
        $conexion = Mockery::mock(ConnectionInterface::class);
        $conexion->shouldReceive('select')->andThrow(errorSqlServer(2812, 'No se encontró el procedimiento almacenado'));

        biometricoSobre($conexion)->porCedula('0802704171', Carbon::today(), Carbon::today());
    })->throws(QueryException::class);
});
