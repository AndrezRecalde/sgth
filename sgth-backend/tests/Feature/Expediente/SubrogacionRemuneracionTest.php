<?php

namespace Tests\Feature\Expediente;

use App\Enums\TipoSubrogacion;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\SubrogacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'TIC-01', 'nombre' => 'Tecnologías de la Información', 'nivel' => 2,
    ]);

    $this->partidaOrigen = PartidaPresupuestaria::create([
        'codigo' => '510105', 'descripcion' => 'Remuneraciones unificadas',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->partidaDestino = PartidaPresupuestaria::create([
        'codigo' => '510106', 'descripcion' => 'Subrogaciones',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    // El grupo ocupacional es donde vive la R.M.U.: Puesto::rmu es un accesor
    // que la lee de aquí. Por eso una revisión salarial cambia, de golpe, la
    // cifra de todos los puestos del grado.
    $this->grupoJefe = GrupoOcupacional::create([
        'grado_codigo' => 'SP7', 'grado_numerico' => 7, 'grupo' => 'Servidor Público 7',
        'denominacion_generica' => 'Servidor Público 7', 'rmu' => 1412.00,
        'regimen' => 'losep', 'activo' => true,
    ]);

    $this->grupoAsistente = GrupoOcupacional::create([
        'grado_codigo' => 'SPA1', 'grado_numerico' => 1, 'grupo' => 'Servidor Público de Apoyo 1',
        'denominacion_generica' => 'Servidor Público de Apoyo 1', 'rmu' => 585.00,
        'regimen' => 'losep', 'activo' => true,
    ]);

    $this->puestoJefe = Puesto::create([
        'codigo' => 'P-JEFE-TIC',
        'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 1, 'es_jefe' => true,
        'cargo_id' => Cargo::firstOrCreate(
            ['nombre' => 'Jefe de Tecnologías'], ['clasificacion_personal' => 'empleado']
        )->id,
        'grupo_ocupacional_id' => $this->grupoJefe->id,
        'partida_presupuestaria_id' => $this->partidaDestino->id,
    ]);

    $this->puestoAsistente = Puesto::create([
        'codigo' => 'P-ASIST-TIC',
        'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 1,
        'cargo_id' => Cargo::firstOrCreate(
            ['nombre' => 'Asistente Administrativo'], ['clasificacion_personal' => 'empleado']
        )->id,
        'grupo_ocupacional_id' => $this->grupoAsistente->id,
        'partida_presupuestaria_id' => $this->partidaOrigen->id,
    ]);

    $this->contador = 0;

    $this->servidorCon = function (?Puesto $puesto, ?float $remuneracion = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'   => str_pad((string) (7000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'   => 'Servidor',
            'apellido' => 'Rmu'.$this->contador,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        if ($puesto) {
            ContratoServidor::create([
                'servidor_id'              => $servidor->id,
                'tipo_nombramiento'        => 'nombramiento_permanente',
                'unidad_administrativa_id' => $this->unidad->id,
                'puesto_id'                => $puesto->id,
                'remuneracion'             => $remuneracion,
                'fecha_inicio'             => '2018-01-01',
                'estado'                   => 'vigente',
            ]);
        }

        return $servidor->fresh();
    };

    $this->service = app(SubrogacionService::class);

    $this->registrar = function (Servidor $subrogante, ?Servidor $titular) {
        return $this->service->registrar([
            'tipo'                     => $titular
                ? TipoSubrogacion::SUBROGACION->value
                : TipoSubrogacion::ENCARGO->value,
            'servidor_subrogante_id'   => $subrogante->id,
            'servidor_subrogado_id'    => $titular?->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_subrogado_id'      => $this->puestoJefe->id,
            'fecha_inicio'             => now()->toDateString(),
            'fecha_fin'                => now()->addMonth()->toDateString(),
            'motivo'                   => 'vacaciones',
        ]);
    };
});

test('la acción congela ambas remuneraciones y la situación de origen', function () {
    $titular    = ($this->servidorCon)($this->puestoJefe);
    $subrogante = ($this->servidorCon)($this->puestoAsistente);

    $movimiento = ($this->registrar)($subrogante, $titular)->movimientoPersonal;

    expect((float) $movimiento->remuneracion_origen)->toBe(585.00)
        ->and((float) $movimiento->remuneracion_propuesta)->toBe(1412.00)
        ->and($movimiento->partida_origen_id)->toBe($this->partidaOrigen->id)
        ->and($movimiento->puesto_origen_id)->toBe($this->puestoAsistente->id)
        ->and($movimiento->unidad_origen_id)->toBe($this->unidad->id);
});

/**
 * Esta aserción decía antes que la partida propuesta era la del puesto
 * subrogado. Estaba mal: la subrogación no paga la remuneración de ese puesto
 * —su titular la sigue cobrando— sino la diferencia, y la Dirección Financiera
 * confirmó que esa se imputa a la 510512. Cargarla al puesto habría duplicado
 * el gasto sobre una plaza ya pagada.
 */
test('la diferencia se imputa a la partida de subrogaciones, no a la del puesto', function () {
    $this->seed(\Database\Seeders\PartidaPresupuestariaSeeder::class);

    $titular    = ($this->servidorCon)($this->puestoJefe);
    $subrogante = ($this->servidorCon)($this->puestoAsistente);

    $movimiento = ($this->registrar)($subrogante, $titular)->movimientoPersonal;

    expect($movimiento->partidaPresupuestaria?->codigo)->toBe('510512')
        ->and($movimiento->partida_presupuestaria_id)->not->toBe($this->partidaDestino->id);
});

/**
 * El punto de congelar: la diferencia autorizada es la que se calculó el día
 * del acto. Si se derivara del grupo ocupacional al imprimir, una revisión
 * salarial reescribiría retroactivamente lo que la autoridad aprobó.
 */
test('una revisión salarial posterior no altera la diferencia autorizada', function () {
    $titular    = ($this->servidorCon)($this->puestoJefe);
    $subrogante = ($this->servidorCon)($this->puestoAsistente);

    $movimiento = ($this->registrar)($subrogante, $titular)->movimientoPersonal;

    $diferenciaAutorizada = (float) $movimiento->remuneracion_propuesta
        - (float) $movimiento->remuneracion_origen;

    $this->grupoJefe->update(['rmu' => 1676.00]);
    $this->grupoAsistente->update(['rmu' => 630.00]);

    $movimiento->refresh();

    expect($diferenciaAutorizada)->toBe(827.00)
        ->and((float) $movimiento->remuneracion_propuesta - (float) $movimiento->remuneracion_origen)
        ->toBe(827.00)
        // Y el puesto sí refleja la revisión: la acción quedó congelada, el
        // catálogo no.
        ->and($this->puestoJefe->fresh()->rmu)->toBe(1676.00);
});

/**
 * La R.M.U. del contrato manda sobre la del grupo ocupacional: en Código del
 * Trabajo el puesto no define ninguna, y en LOSEP el contrato puede llevar un
 * monto ajustado que es el que la persona realmente percibe.
 */
test('la remuneración de origen sale del contrato cuando lo trae', function () {
    $titular    = ($this->servidorCon)($this->puestoJefe);
    $subrogante = ($this->servidorCon)($this->puestoAsistente, 700.00);

    $movimiento = ($this->registrar)($subrogante, $titular)->movimientoPersonal;

    expect((float) $movimiento->remuneracion_origen)->toBe(700.00);
});

test('un encargo sin titular también congela su situación', function () {
    $subrogante = ($this->servidorCon)($this->puestoAsistente);

    $movimiento = ($this->registrar)($subrogante, null)->movimientoPersonal;

    expect((float) $movimiento->remuneracion_origen)->toBe(585.00)
        ->and((float) $movimiento->remuneracion_propuesta)->toBe(1412.00);
});
