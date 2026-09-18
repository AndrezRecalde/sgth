<?php

/*
| La bandeja de Financiero.
|
| Financiero trabajaba sobre el mismo listado que el servidor, filtrando por
| estado, y no había forma de ver qué liquidaciones estaban fuera de plazo.
| La bandeja agrupa por lo que hay que hacer, suma el dinero de cada etapa y
| calcula el plazo con la misma regla que el bloqueo.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\CatalogoTransporte;
use App\Models\Viatico\EmpresaTransporte;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Martes 6 de octubre de 2026.
    Carbon::setTestNow('2026-10-06 10:00:00');
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidadA = unidadDePrueba(['codigo' => 'GFIN', 'nombre' => 'Gestión Financiera']);
    $this->unidadB = unidadDePrueba(['codigo' => 'GOBR', 'nombre' => 'Obras Públicas']);
    $cedula = 800008000;

    $this->servidor = function ($unidad, string $nombre = 'Ana', string $apellido = 'Pérez') use (&$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => $nombre,
            'apellido'                 => $apellido,
            'puesto_id'                => puestoDePrueba($unidad)->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };

    $this->usuario = function (string $rol, ?Servidor $servidor = null) {
        $u = User::factory()->create(['servidor_id' => ($servidor ?? ($this->servidor)($this->unidadA))->id]);
        $u->assignRole($rol);

        return $u;
    };

    $this->ana = ($this->servidor)($this->unidadA, 'Ana', 'Pérez');
    $this->luis = ($this->servidor)($this->unidadB, 'Luis', 'Mora');

    $this->financiero = ($this->usuario)('financiero');
    $this->th = ($this->usuario)('admin-uath');
    $this->deAna = ($this->usuario)('servidor', $this->ana);

    $this->viatico = fn (Servidor $s, EstadoViatico $estado, array $extra = []) => Viatico::create(array_merge([
        'servidor_id'        => $s->id,
        'zona'               => 'dentro_provincia',
        'datetime_salida'    => '2026-10-10 08:00',
        'datetime_llegada'   => '2026-10-11 18:00',
        'noches'             => 2,
        'justificacion'      => 'Comisión de servicio',
        'estado'             => $estado,
        'monto_calculado'    => 100,
        'monto_anticipo'     => 0,
        'modalidad_anticipo' => 'total',
    ], $extra));

    $this->get = fn (User $quien, string $url) => $this->actingAs($quien, 'sanctum')->getJson($url);
});

afterEach(fn () => Carbon::setTestNow());

it('solo la ve quien ve los viáticos de todos', function () {
    ($this->get)($this->deAna, '/api/v1/viaticos/bandeja/resumen')->assertForbidden();
    ($this->get)($this->deAna, '/api/v1/viaticos/bandeja?etapa=por_aprobar')->assertForbidden();

    ($this->get)($this->financiero, '/api/v1/viaticos/bandeja/resumen')->assertOk();
    ($this->get)($this->th, '/api/v1/viaticos/bandeja?etapa=por_aprobar')->assertOk();
});

it('cuenta cada etapa según lo que hay que hacer', function () {
    ($this->viatico)($this->ana, EstadoViatico::SOLICITADO);
    ($this->viatico)($this->ana, EstadoViatico::APROBADO);                                        // por anticipo
    ($this->viatico)($this->ana, EstadoViatico::APROBADO, ['modalidad_anticipo' => 'sin_anticipo']); // por iniciar
    ($this->viatico)($this->luis, EstadoViatico::CON_ANTICIPO, ['monto_anticipo' => 70]);          // por iniciar
    ($this->viatico)($this->luis, EstadoViatico::EN_COMISION, ['monto_anticipo' => 70]);
    ($this->viatico)($this->luis, EstadoViatico::PENDIENTE_LIQUIDACION, ['datetime_llegada' => '2026-10-05 18:00']);
    ($this->viatico)($this->luis, EstadoViatico::LIQUIDADO, ['monto_calculado' => 250]);
    ($this->viatico)($this->luis, EstadoViatico::CONTABILIZADO);
    ($this->viatico)($this->luis, EstadoViatico::RECHAZADO);

    $conVuelo = ($this->viatico)($this->ana, EstadoViatico::SOLICITADO);
    $avion = CatalogoTransporte::create(['nombre' => 'Aéreo', 'codigo' => 'AER', 'tipo_vehiculo' => 'aereo', 'requiere_autorizacion' => true]);
    TramoViatico::create([
        'viatico_id' => $conVuelo->id, 'origen_tipo' => 'nacional', 'origen_ciudad' => 'Esmeraldas',
        'destino_tipo' => 'nacional', 'destino_ciudad' => 'Quito',
        'empresa_transporte_id' => EmpresaTransporte::create(['catalogo_transporte_id' => $avion->id, 'nombre' => 'Avianca', 'codigo' => 'AV'])->id,
        'datetime_salida' => '2026-10-10 08:00', 'datetime_llegada' => '2026-10-10 09:00',
    ]);

    $resumen = ($this->get)($this->financiero, '/api/v1/viaticos/bandeja/resumen')->assertOk()->json('datos');

    expect($resumen['conteos'])->toBe([
        'por_aprobar' => 2, 'por_anticipo' => 1, 'por_iniciar' => 2, 'en_comision' => 1,
        'por_liquidar' => 1, 'por_revisar' => 1, 'cerrados' => 2, 'vuelos' => 1,
    ])
        // En JSON, 750.0 viaja como 750.
        ->and($resumen['montos'])->toEqual([
            'comprometido' => 750, 'anticipos_entregados' => 140, 'por_contabilizar' => 250,
        ])
        ->and(collect($resumen['unidades'])->pluck('nombre')->all())->toBe(['Gestión Financiera', 'Obras Públicas'])
        ->and(AutorizacionVuelo::count())->toBe(1);
});

it('los filtros se aplican a los contadores y a la lista', function () {
    ($this->viatico)($this->ana, EstadoViatico::SOLICITADO, ['datetime_salida' => '2026-10-20 08:00']);
    ($this->viatico)($this->luis, EstadoViatico::SOLICITADO, ['datetime_salida' => '2026-11-20 08:00']);

    $porUnidad = "unidad_id={$this->unidadB->id}";
    expect(($this->get)($this->financiero, "/api/v1/viaticos/bandeja/resumen?{$porUnidad}")->json('datos.conteos.por_aprobar'))->toBe(1);
    ($this->get)($this->financiero, "/api/v1/viaticos/bandeja?etapa=por_aprobar&{$porUnidad}")
        ->assertOk()
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.servidor.apellido', 'Mora')
        ->assertJsonPath('datos.0.servidor.unidad', 'Obras Públicas');

    ($this->get)($this->financiero, '/api/v1/viaticos/bandeja?etapa=por_aprobar&desde=2026-11-01')
        ->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.servidor.nombre', 'Luis');

    // La búsqueda encuentra por nombre en cualquier orden y sin distinguir
    // mayúsculas. Las tildes sí cuentan: la base no tiene `unaccent`.
    ($this->get)($this->financiero, '/api/v1/viaticos/bandeja?etapa=por_aprobar&search=perez ana')
        ->assertJsonCount(0, 'datos');
    ($this->get)($this->financiero, '/api/v1/viaticos/bandeja?etapa=por_aprobar&search=PÉREZ ANA')
        ->assertJsonCount(1, 'datos');
    ($this->get)($this->financiero, '/api/v1/viaticos/bandeja?etapa=por_aprobar&search=ana pérez')
        ->assertJsonCount(1, 'datos');
});

it('en por liquidar cada fila trae su plazo, ordenadas por lo que vence antes', function () {
    // Hoy es martes 6. Volvió el jueves 1: 4 días hábiles, límite el miércoles
    // 7 a las 18:00 → queda 1 día hábil (el 7).
    ($this->viatico)($this->ana, EstadoViatico::PENDIENTE_LIQUIDACION, ['datetime_llegada' => '2026-10-01 18:00']);
    // Volvió el lunes 28/09: límite el viernes 2/10 → vencida.
    ($this->viatico)($this->luis, EstadoViatico::PENDIENTE_LIQUIDACION, ['datetime_llegada' => '2026-09-28 18:00']);

    $filas = ($this->get)($this->financiero, '/api/v1/viaticos/bandeja?etapa=por_liquidar')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->json('datos');

    expect($filas[0]['servidor']['nombre'])->toBe('Luis')
        ->and($filas[0]['plazo']['vencida'])->toBeTrue()
        ->and($filas[0]['plazo']['dias_habiles_restantes'])->toBe(0)
        ->and($filas[1]['plazo']['vencida'])->toBeFalse()
        ->and($filas[1]['plazo']['dias_habiles_restantes'])->toBe(1)
        ->and(substr($filas[1]['plazo']['fecha_limite'], 0, 10))->toBe('2026-10-07');

    ($this->get)($this->financiero, '/api/v1/viaticos/bandeja?etapa=por_liquidar&vencidas=1')
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('datos.0.servidor.nombre', 'Luis');

    expect(($this->get)($this->financiero, '/api/v1/viaticos/bandeja/resumen')->json('datos.vencidas'))->toBe(1);
});

it('una etapa desconocida se rechaza', function () {
    ($this->get)($this->financiero, '/api/v1/viaticos/bandeja?etapa=todo')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['etapa']]);
});

it('«Mis viáticos» muestra solo los propios también a Financiero', function () {
    $deFinanciero = ($this->viatico)(Servidor::findOrFail($this->financiero->servidor_id), EstadoViatico::SOLICITADO);
    ($this->viatico)($this->ana, EstadoViatico::SOLICITADO);

    ($this->get)($this->financiero, '/api/v1/viaticos?propios=1')
        ->assertOk()
        ->assertJsonCount(1, 'datos.data')
        ->assertJsonPath('datos.data.0.id', $deFinanciero->id);

    ($this->get)($this->financiero, '/api/v1/viaticos')->assertJsonCount(2, 'datos.data');
});

it('«Mis viáticos» filtra por estado y zona, y busca el código sin distinguir mayúsculas', function () {
    $mio = Servidor::findOrFail($this->financiero->servidor_id);
    $solicitado = ($this->viatico)($mio, EstadoViatico::SOLICITADO);
    ($this->viatico)($mio, EstadoViatico::APROBADO, ['zona' => 'fuera_provincia']);

    $codigo = $solicitado->fresh()->codigo_viatico;
    expect($codigo)->not->toBeNull();

    ($this->get)($this->financiero, '/api/v1/viaticos?propios=1&search='.urlencode(mb_strtolower($codigo)))
        ->assertJsonCount(1, 'datos.data')
        ->assertJsonPath('datos.data.0.id', $solicitado->id);

    ($this->get)($this->financiero, '/api/v1/viaticos?propios=1&estado=aprobado')
        ->assertJsonCount(1, 'datos.data')
        ->assertJsonPath('datos.data.0.zona', 'fuera_provincia');

    ($this->get)($this->financiero, '/api/v1/viaticos?propios=1&zona=dentro_provincia')
        ->assertJsonCount(1, 'datos.data')
        ->assertJsonPath('datos.data.0.id', $solicitado->id);
});
