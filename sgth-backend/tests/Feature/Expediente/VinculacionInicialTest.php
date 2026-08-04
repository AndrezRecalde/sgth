<?php

namespace Tests\Feature\Expediente;

use App\Enums\OrigenVinculo;
use App\Enums\Permiso;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\VinculacionInicialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate([
        'name' => Permiso::VINCULAR_SERVIDOR_INICIAL->value, 'guard_name' => 'sanctum',
    ]);
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-VI', 'nombre' => 'Dirección Administrativa', 'nivel' => 1,
    ]);

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-VI', 'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 5, 'partida_presupuestaria_id' => $this->partida->id,
    ]);

    $this->service = app(VinculacionInicialService::class);

    $this->autorizado = function (bool $conPermiso = true): User {
        $u = User::factory()->create();
        $u->assignRole('admin-uath');
        if ($conPermiso) {
            $u->givePermissionTo(Permiso::VINCULAR_SERVIDOR_INICIAL->value);
        }

        return $u;
    };

    // La ficha exige provincia y cantón de nacimiento para servidores
    // ecuatorianos, igual que el alta ordinaria.
    $this->provincia = DB::table('provincias')->insertGetId(
        ['nombre' => 'Esmeraldas', 'codigo' => '08', 'created_at' => now(), 'updated_at' => now()]
    );
    $this->canton = DB::table('cantones')->insertGetId(
        ['nombre' => 'Esmeraldas', 'provincia_id' => $this->provincia, 'created_at' => now(), 'updated_at' => now()]
    );

    $this->contador = 0;

    $this->payload = function (array $extra = [], array $vinculo = []): array {
        $this->contador++;

        return [
            'cedula'   => str_pad((string) (1100000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'   => 'Migrada',
            'apellido' => 'Histórica'.$this->contador,
            'fecha_nacimiento' => '1985-04-12',
            'genero'           => 'femenino',
            'estado_civil'     => 'casado',
            'es_extranjero'    => false,
            'provincia_nacimiento_id' => $this->provincia,
            'canton_nacimiento_id'    => $this->canton,
            'tiene_discapacidad'            => false,
            'tiene_enfermedad_catastrofica' => false,
            ...$extra,
            'vinculo' => [
                'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
                'unidad_administrativa_id' => $this->unidad->id,
                'puesto_id'                => $this->puesto->id,
                'fecha_inicio'             => '2015-03-02',
                'remuneracion'             => 1212.50,
                ...$vinculo,
            ],
        ];
    };
});

// ── El hecho se registra, el documento no se inventa ─────────────

test('crea la ficha y el contrato vigente en un solo acto', function () {
    $datos = ($this->payload)();

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($servidor->cedula)->toBe($datos['cedula'])
        ->and($contrato->estado->value)->toBe('vigente')
        ->and((float) $contrato->remuneracion)->toBe(1212.50)
        ->and($contrato->fecha_inicio->toDateString())->toBe('2015-03-02');
});

test('el vínculo queda marcado como carga inicial, no como acción de personal', function () {
    $datos = ($this->payload)();

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($contrato->origen)->toBe(OrigenVinculo::VINCULACION_INICIAL)
        ->and($contrato->origen->tieneDocumentoDeRespaldo())->toBeFalse();
});

/**
 * El motivo de existir de toda esta vía: no acuñar un código del año en curso
 * ni sellar como firmantes a las autoridades de hoy para un acto de 2015.
 */
test('no se fabrica ninguna acción de personal de ingreso', function () {
    $datos = ($this->payload)();

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    $ingresos = MovimientoPersonal::where('servidor_id', $servidor->id)
        ->where('tipo_movimiento', TipoMovimientoPersonal::INGRESO->value)
        ->count();

    expect($ingresos)->toBe(0);
});

test('queda la bitácora de novedad que respalda el alta directa', function () {
    $datos = ($this->payload)();

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    $bitacora = MovimientoPersonal::where('servidor_id', $servidor->id)
        ->where('tipo_movimiento', TipoMovimientoPersonal::NOVEDAD_CONTRATO->value)
        ->exists();

    expect($bitacora)->toBeTrue();
});

/**
 * La bitácora registra un hecho ya consumado: el contrato existe. En borrador
 * aparecía en la bandeja de Talento Humano pidiendo que alguien "aprobara" algo
 * que ya había ocurrido, y admitía editarse y anularse.
 */
test('la bitácora nace registrada, no en borrador esperando aprobación', function () {
    $datos = ($this->payload)();

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    $bitacora = MovimientoPersonal::where('servidor_id', $servidor->id)
        ->where('tipo_movimiento', TipoMovimientoPersonal::NOVEDAD_CONTRATO->value)
        ->firstOrFail();

    expect($bitacora->estado)->toBe(\App\Enums\EstadoAccionPersonal::REGISTRADA);
});

test('la bitácora no tiene documento imprimible', function () {
    $datos = ($this->payload)();

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    $bitacora = MovimientoPersonal::where('servidor_id', $servidor->id)
        ->where('tipo_movimiento', TipoMovimientoPersonal::NOVEDAD_CONTRATO->value)
        ->firstOrFail();

    expect(fn () => app(\App\Services\Expediente\AccionPersonalPdfService::class)
        ->generarContent($bitacora->id))
        ->toThrow(ReglaNegocioException::class, 'no tiene documento imprimible');
});

test('la antigüedad se toma del contrato cuando no se declara fecha de ingreso', function () {
    $datos = ($this->payload)();

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    expect($servidor->fecha_ingreso_institucion->toDateString())->toBe('2015-03-02');
});

test('una fecha de ingreso declarada manda sobre la del contrato', function () {
    $datos = ($this->payload)(['fecha_ingreso_institucion' => '2009-01-15']);

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    expect($servidor->fecha_ingreso_institucion->toDateString())->toBe('2009-01-15');
});

test('un contrato de servicios ocasionales exige su fecha de término', function () {
    $datos = ($this->payload)([], [
        'tipo_nombramiento' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'fecha_fin'         => null,
    ]);

    expect(fn () => $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    ))->toThrow(ReglaNegocioException::class, 'necesita fecha de término');
});

test('nada se guarda si el vínculo falla: la ficha no queda huérfana', function () {
    $datos = ($this->payload)([], [
        'tipo_nombramiento' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'fecha_fin'         => null,
    ]);

    try {
        $this->service->registrar(
            collect($datos)->except('vinculo')->all(),
            $datos['vinculo'],
        );
    } catch (ReglaNegocioException) {
        // Esperado.
    }

    expect(Servidor::where('cedula', $datos['cedula'])->exists())->toBeFalse();
});

// ── El permiso es la puerta ─────────────────────────────────────

test('sin el permiso la ruta responde 403', function () {
    $this->actingAs(($this->autorizado)(false), 'sanctum');

    $this->postJson('/api/v1/expediente/vinculacion-inicial', ($this->payload)())
        ->assertForbidden();

    expect(Servidor::count())->toBe(0);
});

test('con el permiso la ruta registra al servidor', function () {
    $this->actingAs(($this->autorizado)(), 'sanctum');

    $datos = ($this->payload)();

    $this->postJson('/api/v1/expediente/vinculacion-inicial', $datos)
        ->assertCreated();

    expect(Servidor::where('cedula', $datos['cedula'])->exists())->toBeTrue();
});

test('una cédula ya registrada se rechaza con un mensaje que dice qué hacer', function () {
    $this->actingAs(($this->autorizado)(), 'sanctum');

    $datos = ($this->payload)();
    $this->postJson('/api/v1/expediente/vinculacion-inicial', $datos)->assertCreated();

    $this->postJson('/api/v1/expediente/vinculacion-inicial', $datos)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['cedula']]);
});

test('el listado devuelve solo la cohorte migrada', function () {
    $this->actingAs(($this->autorizado)(), 'sanctum');

    $datos = ($this->payload)();
    $this->postJson('/api/v1/expediente/vinculacion-inicial', $datos)->assertCreated();

    $respuesta = $this->getJson('/api/v1/expediente/vinculacion-inicial')
        ->assertOk()
        ->json('datos');

    expect($respuesta)->toHaveCount(1)
        ->and($respuesta[0]['origen'])->toBe(OrigenVinculo::VINCULACION_INICIAL->value);
});

// ── Lo que viene después sigue el flujo formal ──────────────────

test('el vínculo migrado sirve de situación actual para la siguiente acción', function () {
    $datos = ($this->payload)();

    $servidor = $this->service->registrar(
        collect($datos)->except('vinculo')->all(),
        $datos['vinculo'],
    );

    $movimiento = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
            'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::TRASPASO->value,
            'descripcion'        => 'Traspaso posterior a la migración',
            'fecha_efectiva'     => '2026-08-04',
            'unidad_destino_id'  => $this->unidad->id,
            'puesto_destino_id'  => $this->puesto->id,
        ]);

    // La situación actual se congela desde el contrato migrado.
    expect($movimiento->puesto_origen_id)->toBe($this->puesto->id)
        ->and((float) $movimiento->remuneracion_origen)->toBe(1212.50)
        ->and($movimiento->partida_origen_id)->toBe($this->partida->id);
});
