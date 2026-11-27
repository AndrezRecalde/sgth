<?php

namespace Tests\Feature\Expediente;

use App\Enums\TipoNombramiento;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Le quita al rol el permiso de los actos de autoridad, y limpia la caché. */
function sinElPermisoDeActosDeAutoridad(string $rol): void
{
    Role::where('name', $rol)->where('guard_name', 'sanctum')->first()
        ?->revokePermissionTo('registrar-acto-autoridad');

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

/*
| Decisión D3 del diseño de «Autoridades electas», respondida el 2026-11-27:
| los actos del Prefecto y del Viceprefecto los registran **admin-uath y
| asistente-uath, con un permiso propio**.
|
| Hasta aquí iban con `preparar-accion-personal`, el mismo de cualquier acción
| de personal: no había forma de quitarle a alguien los actos de autoridad sin
| quitarle también el resto. Hoy los dos roles lo tienen —nadie gana ni pierde
| acceso—, y lo que se gana es poder separarlo desde Usuarios.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->prefectura = UnidadAdministrativa::create([
        'codigo' => 'PREF', 'nombre' => 'Prefectura Provincial', 'nivel' => 2, 'es_maxima_autoridad' => true,
    ]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->prefectura->id, 'plazas' => 1, 'es_jefe' => true,
        'grupo_ocupacional_id'     => GrupoOcupacional::create([
            'grado_codigo' => 'NJS-10', 'grado_numerico' => 10, 'grupo' => 'NJS',
            'denominacion_generica' => 'Prefecto/a', 'rmu' => 5060, 'regimen' => 'losep',
            'activo' => true, 'rol_puesto' => 'dignatario',
        ])->id,
    ]);

    $this->contador = 0;
    $this->persona = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'          => str_pad((string) (8700000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Autoridad',
            'apellido'        => 'Electa'.$this->contador,
            'regimen_laboral' => 'losep',
        ]);
    };

    $this->comoRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);
        $this->actingAs($usuario, 'sanctum');

        return $usuario;
    };

    /** El inicio del período: el acto de autoridad más sencillo de pedir. */
    $this->iniciarPeriodo = fn (Servidor $s) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                 => 'inicio_periodo',
            'causal'                => 'eleccion',
            'descripcion'           => 'Posesión del Prefecto Provincial electo',
            'fecha_efectiva'        => now()->toDateString(),
            'unidad_destino_id'     => $this->prefectura->id,
            'puesto_destino_id'     => $this->puesto->id,
            'credencial_cne_numero' => 'CNE-2026-0147',
            'credencial_cne_fecha'  => now()->subDays(20)->toDateString(),
            'fecha_fin_propuesta'   => now()->addYearsNoOverflow(4)->subDay()->toDateString(),
        ]
    );
});

test('los dos roles de Talento Humano registran los actos de autoridad', function () {
    foreach (['admin-uath', 'asistente-uath'] as $rol) {
        ($this->comoRol)($rol);

        expect(auth('sanctum')->user()->can('registrar-acto-autoridad'))->toBeTrue($rol);

        ($this->iniciarPeriodo)(($this->persona)())
            ->assertCreated()
            ->assertJsonPath('datos.instrumento', 'acto_autoridad');

        $this->getJson('/api/v1/expediente/autoridades-electas')->assertOk();
    }
});

test('sin el permiso no se registra un acto de autoridad, aunque prepare acciones', function () {
    // El caso que D3 hace posible: alguien que prepara acciones de personal y
    // a quien se le quitó el de los actos de autoridad. Antes no se podía
    // separar, porque los dos eran `preparar-accion-personal`.
    // Se le quita al rol y no al usuario: el permiso le llega por el rol, y
    // revocárselo al usuario no toca lo que hereda.
    $usuario = ($this->comoRol)('admin-uath');
    sinElPermisoDeActosDeAutoridad('admin-uath');

    expect($usuario->fresh()->can('preparar-accion-personal'))->toBeTrue()
        ->and($usuario->fresh()->can('registrar-acto-autoridad'))->toBeFalse();

    ($this->iniciarPeriodo)(($this->persona)())->assertForbidden();
    $this->getJson('/api/v1/expediente/autoridades-electas')->assertForbidden();
    $this->getJson('/api/v1/expediente/autoridades-electas/dignidades')->assertForbidden();
});

test('una acción de personal corriente no pide el permiso de los actos de autoridad', function () {
    // La otra mitad: separar los permisos no puede estorbar lo de siempre.
    ($this->comoRol)('admin-uath');
    sinElPermisoDeActosDeAutoridad('admin-uath');

    $servidor = ($this->persona)();

    $this->postJson("/api/v1/expediente/servidores/{$servidor->id}/movimientos", [
        'clase'                       => 'ingreso',
        'descripcion'                 => 'Ingreso ordinario',
        'fecha_efectiva'              => now()->toDateString(),
        'tipo_nombramiento_propuesto' => 'nombramiento_permanente',
        'unidad_destino_id'           => $this->prefectura->id,
        'puesto_destino_id'           => $this->puesto->id,
    ])
        // 422 y no 403: lo rechaza la regla de la dignidad, que es otra cosa.
        // Lo que importa es que no se quedó en la puerta por el permiso.
        ->assertStatus(422);
});

test('las vacaciones del Consejo ya no piden además aprobar-vacaciones', function () {
    // Hasta D3 pedían los dos permisos, y asistente-uath no tiene el segundo:
    // le quedaba fuera uno de los actos que sí le corresponden.
    $asistente = ($this->comoRol)('asistente-uath');

    expect($asistente->can('registrar-acto-autoridad'))->toBeTrue()
        ->and($asistente->can('aprobar-vacaciones'))->toBeFalse();

    $servidor = ($this->persona)();
    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::ELECCION_POPULAR->value,
        'numero_contrato'          => 'CRED-D3',
        'unidad_administrativa_id' => $this->prefectura->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2023-05-15',
        'fecha_fin'                => now()->addYear()->toDateString(),
        'remuneracion'             => 5060,
        'estado'                   => 'vigente',
    ]);

    // Pasa la puerta: lo que lo detiene es que no tiene período del que
    // descontar, que es una regla de negocio y no un permiso.
    $this->postJson('/api/v1/expediente/autoridades-electas/vacaciones', [
        'servidor_id'       => $servidor->id,
        'fecha_inicio'      => now()->addMonth()->toDateString(),
        'fecha_fin'         => now()->addMonth()->addDays(4)->toDateString(),
        'resolucion_numero' => 'RES-CP-2026-120',
    ])->assertStatus(422);
});

test('el permiso existe y lo tienen los dos roles, sin tocar a los demás', function () {
    expect(Permission::where('name', 'registrar-acto-autoridad')->where('guard_name', 'sanctum')->exists())
        ->toBeTrue();

    foreach (['admin-uath', 'asistente-uath'] as $rol) {
        expect(Role::where('name', $rol)->first()->hasPermissionTo('registrar-acto-autoridad'))
            ->toBeTrue($rol);
    }

    // Un rol cualquiera de fuera de Talento Humano no lo recibe.
    $otro = Role::firstOrCreate(['name' => 'tecnico-asistencia', 'guard_name' => 'sanctum']);
    expect($otro->hasPermissionTo('registrar-acto-autoridad'))->toBeFalse();
});
