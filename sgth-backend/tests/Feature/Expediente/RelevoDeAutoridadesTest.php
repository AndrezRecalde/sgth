<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Estructura\FirmanteOrganigramaService;
use App\Services\Expediente\AutoridadElectaService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| El relevo de las autoridades electas (2026-10-21). Quien entra al puesto de
| una autoridad la sucede: el período de quien lo ocupa tiene que tener fin y
| terminar a más tardar el día en que empieza el nuevo, o el sistema tendría
| dos Prefectos. Las que entran se ven antes de que rija su período, y desde
| la posesión firma y subroga la que entra.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->hoy = now()->toDateString();

    $grupo = fn (string $codigo, float $rmu) => GrupoOcupacional::create([
        'grado_codigo' => $codigo, 'grado_numerico' => 10, 'grupo' => 'NJS', 'denominacion_generica' => $codigo,
        'rmu' => $rmu, 'regimen' => 'losep', 'activo' => true,
    ]);

    $this->prefectura = UnidadAdministrativa::create([
        'codigo' => 'PREF', 'nombre' => 'Prefectura Provincial', 'nivel' => 1, 'es_maxima_autoridad' => true,
    ]);
    $this->viceprefectura = UnidadAdministrativa::create(['codigo' => 'VICE', 'nombre' => 'Viceprefectura', 'nivel' => 1]);

    $this->puestoPrefecto = Puesto::create([
        'unidad_administrativa_id' => $this->prefectura->id, 'plazas' => 1, 'es_jefe' => true,
        'grupo_ocupacional_id'     => $grupo('NJS-10', 5060)->id,
    ]);
    $this->puestoVice = Puesto::create([
        'unidad_administrativa_id' => $this->viceprefectura->id, 'plazas' => 1, 'es_jefe' => true,
        'grupo_ocupacional_id'     => $grupo('NJS-7', 4048)->id,
    ]);

    $this->contador = 0;
    $this->persona = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'          => str_pad((string) (8700000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Autoridad',
            'apellido'        => 'Relevo'.$this->contador,
            'regimen_laboral' => 'losep',
        ]);
    };

    /** Una autoridad en funciones, como la de la carga inicial: sin acto de inicio. */
    $this->enFunciones = function (Puesto $puesto, ?string $hasta, string $desde = '2023-05-15'): ContratoServidor {
        return ContratoServidor::create([
            'servidor_id'              => ($this->persona)()->id,
            'tipo_nombramiento'        => TipoNombramiento::ELECCION_POPULAR->value,
            'numero_contrato'          => 'CRED-'.$this->contador,
            'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
            'puesto_id'                => $puesto->id,
            'fecha_inicio'             => $desde,
            'fecha_fin'                => $hasta,
            'remuneracion'             => 5060,
            'estado'                   => 'vigente',
        ]);
    };

    $this->iniciar = fn (Servidor $s, string $desde, ?Puesto $puesto = null) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                 => 'inicio_periodo',
            'causal'                => 'eleccion',
            'descripcion'           => 'Posesión de la autoridad electa',
            'fecha_efectiva'        => $desde,
            'unidad_destino_id'     => ($puesto ?? $this->puestoPrefecto)->unidad_administrativa_id,
            'puesto_destino_id'     => ($puesto ?? $this->puestoPrefecto)->id,
            'credencial_cne_numero' => 'CNE-'.$s->id,
            'credencial_cne_fecha'  => now()->subDays(20)->toDateString(),
            'fecha_fin_propuesta'   => \Carbon\Carbon::parse($desde)->addYearsNoOverflow(4)->subDay()->toDateString(),
        ]
    );

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };
});

test('con el fin de la saliente corregido a la víspera, la entrante se prepara', function () {
    $saliente = ($this->enFunciones)($this->puestoPrefecto, now()->addMonths(2)->toDateString());
    $entrante = ($this->persona)();
    $posesion = now()->addMonth()->toDateString();

    ($this->iniciar)($entrante, $posesion)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ese día o después')
            && str_contains($m, 'Relevo1'));

    // Con el fin corregido a la víspera de la posesión, sí.
    $this->postJson("/api/v1/expediente/contratos/{$saliente->id}/fin-del-periodo", [
        'fecha_fin' => now()->addMonth()->subDay()->toDateString(),
    ])->assertOk();

    ($this->iniciar)($entrante, $posesion)->assertCreated();
});

test('el período del saliente termina a más tardar el día en que entra el nuevo', function () {
    $posesion = now()->addMonth();
    ($this->enFunciones)($this->puestoPrefecto, $posesion->copy()->addDays(5)->toDateString());

    ($this->iniciar)(($this->persona)(), $posesion->toDateString())
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ese día o después'));

    // El mismo día pueden coincidir: no ocupan plaza.
    $otro = ($this->enFunciones)($this->puestoVice, $posesion->toDateString());
    ($this->iniciar)(($this->persona)(), $posesion->toDateString(), $this->puestoVice)->assertCreated();

    // Y la reelegida sigue a su propio período, no a sí misma.
    ($this->iniciar)($otro->servidor, $posesion->copy()->addDay()->toDateString(), $this->puestoVice)
        ->assertCreated();
});

test('el registro vuelve a mirar al saliente: dos inicios no se montan en el mismo puesto', function () {
    $primera = ($this->persona)();
    $segunda = ($this->persona)();

    // Sin nadie en el puesto, los dos borradores pasan.
    $uno = ($this->iniciar)($primera, $this->hoy)->assertCreated()->json('datos.id');
    $dos = ($this->iniciar)($segunda, now()->addDays(10)->toDateString())->assertCreated()->json('datos.id');

    // La primera entra hoy; la segunda ya no cabe encima de ella.
    ($this->registrar)($uno);

    expect(fn () => ($this->registrar)($dos))
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'ese día o después');
});

test('al saliente no se le fija un fin después de que entra quien lo sucede', function () {
    $posesion = now()->addMonth();
    $saliente = ($this->enFunciones)($this->puestoPrefecto, $posesion->toDateString());
    ($this->registrar)(($this->iniciar)(($this->persona)(), $posesion->toDateString())->json('datos.id'));
    $url = "/api/v1/expediente/contratos/{$saliente->id}/fin-del-periodo";

    $this->postJson($url, ['fecha_fin' => $posesion->copy()->addMonth()->toDateString()])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'empieza su período en este puesto'));

    $this->postJson($url, ['fecha_fin' => $posesion->copy()->subDay()->toDateString()])->assertOk();
});

test('las autoridades que entran se ven antes de que rija su período, con a quién suceden', function () {
    $posesion = now()->addMonth();
    $saliente = ($this->enFunciones)($this->puestoPrefecto, $posesion->copy()->subDay()->toDateString());
    $entrante = ($this->persona)();
    $inicio = ($this->registrar)(($this->iniciar)($entrante, $posesion->toDateString())->json('datos.id'));

    $fila = $this->getJson('/api/v1/expediente/autoridades-electas/entrantes')->assertOk()->json('datos.0');

    expect($fila['servidor']['id'])->toBe($entrante->id)
        ->and($fila['desde'])->toBe($posesion->toDateString())
        ->and($fila['credencial'])->toBe('CNE-'.$entrante->id)
        ->and($fila['inicio']['codigo_registro'])->toBe($inicio->codigo_registro)
        ->and($fila['sucede_a']['contrato_id'])->toBe($saliente->id)
        ->and($fila['sucede_a']['hasta'])->toBe($posesion->copy()->subDay()->toDateString())
        ->and($fila['aviso'])->toBeNull();

    // En la fila de la saliente, quien la sucede.
    $suya = collect($this->getJson('/api/v1/expediente/autoridades-electas')->json('datos'))
        ->firstWhere('contrato_id', $saliente->id);
    expect($suya['sucesor']['id'])->toBe($inicio->id)
        ->and($suya['sucesor']['nombre'])->toBe('Relevo2 Autoridad');

    // Lo registrado antes de la regla, con la saliente terminando después: lo
    // dice el aviso.
    $saliente->update(['fecha_fin' => $posesion->copy()->addDays(3)->toDateString()]);
    expect($this->getJson('/api/v1/expediente/autoridades-electas/entrantes')->json('datos.0.aviso'))
        ->toContain('ese día o después');

    // Cuando rige, ya está en funciones y deja de ser entrante.
    $saliente->update(['fecha_fin' => $posesion->copy()->subDay()->toDateString()]);
    $this->estados->aplicarVigentes($posesion->toDateString(), $entrante->id);
    expect($inicio->fresh()->efecto_aplicado_en)->not->toBeNull();
    expect($this->getJson('/api/v1/expediente/autoridades-electas/entrantes')->json('datos'))->toBe([]);
});

test('el día del relevo firma y subroga la autoridad que entra', function () {
    $prefectoSaliente = ($this->enFunciones)($this->puestoPrefecto, $this->hoy);
    $viceSaliente = ($this->enFunciones)($this->puestoVice, $this->hoy);

    $prefecta = ($this->persona)();
    $vice = ($this->persona)();
    ($this->registrar)(($this->iniciar)($prefecta, $this->hoy)->json('datos.id'));
    ($this->registrar)(($this->iniciar)($vice, $this->hoy, $this->puestoVice)->json('datos.id'));

    $autoridades = app(AutoridadElectaService::class);

    // Hoy están las cuatro en funciones: mandan las que entran.
    expect(ContratoServidor::where('tipo_nombramiento', 'eleccion_popular')->where('estado', 'vigente')->count())->toBe(4)
        ->and($autoridades->prefecto()?->servidor_id)->toBe($prefecta->id)
        ->and($autoridades->viceprefecto()?->servidor_id)->toBe($vice->id)
        ->and(app(FirmanteOrganigramaService::class)->titularDe($this->puestoPrefecto->id)?->id)->toBe($prefecta->id);

    // Mañana el comando cierra a las salientes, y todo sigue igual.
    $autoridades->cerrarPeriodosTerminados(now()->addDay()->toDateString());

    expect($prefectoSaliente->fresh()->estado->value)->not->toBe('vigente')
        ->and($viceSaliente->fresh()->estado->value)->not->toBe('vigente')
        ->and($autoridades->prefecto()?->servidor_id)->toBe($prefecta->id)
        ->and($autoridades->viceprefecto()?->servidor_id)->toBe($vice->id);
});
