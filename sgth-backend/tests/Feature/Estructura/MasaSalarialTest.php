<?php

namespace Tests\Feature\Estructura;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| La masa salarial referencial (docs/diseno-masa-salarial.md, PR 1): la suma
| de la remuneración registrada en cada vínculo vigente a una fecha de corte, y
| esa suma por doce. No es la nómina: Talento Humano y Financiero aceptaron
| mostrarla con un aviso fijo el 2026-11-17.
|
| Hoy es el martes 17 de noviembre de 2026. El organigrama de prueba:
|
|   Prefectura (1)
|   ├── Dirección de Obras (2)
|   │   └── Jefatura de Vías (3)
|   └── Dirección Administrativa (2)
*/
beforeEach(function () {
    foreach (['admin-uath', 'asistente-uath', 'auditor', 'financiero', 'servidor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }
    (require database_path('migrations/2026_12_29_000000_permiso_masa_salarial.php'))->up();
    $this->travelTo(Carbon::parse('2026-11-17 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->prefectura = unidadDePrueba(['nombre' => 'Prefectura', 'nivel' => 1]);
    $this->obras = unidadDePrueba(['nombre' => 'Dirección de Obras', 'nivel' => 2, 'unidad_padre_id' => $this->prefectura->id]);
    $this->vias = unidadDePrueba(['nombre' => 'Jefatura de Vías', 'nivel' => 3, 'unidad_padre_id' => $this->obras->id]);
    $this->administrativa = unidadDePrueba(['nombre' => 'Dirección Administrativa', 'nivel' => 2, 'unidad_padre_id' => $this->prefectura->id]);

    $this->contador = 0;

    /** Un servidor con su vínculo. El puesto es del grado SP10, de 1000. */
    $this->servidor = function (UnidadAdministrativa $unidad, array $contrato = [], array $servidor = []): Servidor {
        $this->contador++;
        $puesto = puestoDePrueba($unidad, 'Analista '.$this->contador);
        $s = Servidor::create([
            'cedula' => str_pad((string) (8100000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Masa'.str_pad((string) $this->contador, 2, '0', STR_PAD_LEFT),
            'regimen_laboral' => 'losep', 'fecha_ingreso_institucion' => '2018-01-02', 'estado' => true,
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
            ...$servidor,
        ]);
        if ($contrato !== ['sin_vinculo' => true]) {
            ContratoServidor::create([
                'servidor_id' => $s->id, 'tipo_nombramiento' => 'nombramiento_permanente',
                'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $puesto->id,
                'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => 1000,
                ...$contrato,
            ]);
        }

        return $s->fresh();
    };

    /** Un acto ya registrado, como lo deja el trámite. */
    $this->acto = fn (Servidor $s, array $datos) => MovimientoPersonal::create([
        'servidor_id' => $s->id,
        'categoria' => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado' => EstadoAccionPersonal::REGISTRADA,
        'descripcion' => 'Acto de prueba para la masa salarial.',
        'codigo_registro' => 'AP-2026-'.str_pad((string) (++$this->contador), 4, '0', STR_PAD_LEFT),
        'autorizado_por' => $this->user->id,
        ...$datos,
    ]);

    $this->masa = fn (string $consulta = '') => $this->getJson('/api/v1/estructura/masa-salarial'.($consulta ? "?{$consulta}" : ''))
        ->assertOk()
        ->json('datos');

    /** Los cinco de casi todas las pruebas: 6900 en relación de dependencia y 1600 en honorarios. */
    $this->institucion = function () {
        ($this->servidor)($this->vias, ['remuneracion' => 1200]);
        ($this->servidor)($this->obras, ['tipo_nombramiento' => 'servicios_ocasionales', 'remuneracion' => 900, 'fecha_fin' => '2026-12-31']);
        ($this->servidor)($this->administrativa, ['tipo_nombramiento' => 'codigo_trabajo', 'remuneracion' => 600], ['regimen_laboral' => 'codigo_trabajo']);
        ($this->servidor)($this->administrativa, ['tipo_nombramiento' => 'servicios_profesionales', 'remuneracion' => 1600, 'fecha_fin' => '2026-12-31'], ['regimen_laboral' => 'servicios_profesionales']);
        ($this->servidor)($this->prefectura, [
            'tipo_nombramiento' => 'eleccion_popular', 'remuneracion' => 4200, 'fecha_inicio' => '2023-05-14', 'fecha_fin' => '2027-05-13',
        ]);
    };
});

// ── Lo que se suma ──────────────────────────────────────────────

test('suma la remuneración del vínculo y no la del grado, con Servicios Profesionales aparte y el aviso', function () {
    ($this->institucion)();

    $masa = ($this->masa)();

    expect($masa['corte'])->toBe('2026-11-17')
        ->and($masa['aviso'])->toStartWith('Monto referencial:')
        ->and($masa['totales']['dependencia'])->toMatchArray(['personas' => 4, 'mensual' => 6900, 'anual' => 82800])
        ->and($masa['totales']['servicios_profesionales'])->toMatchArray(['personas' => 1, 'mensual' => 1600, 'anual' => 19200])
        ->and($masa['totales']['general'])->toMatchArray(['personas' => 5, 'mensual' => 8500, 'porcentaje' => 100])
        ->and($masa['totales']['general']['promedio'])->toEqual(1700);

    // El permanente cobra 1200 aunque su grado diga 1000.
    $permanente = collect($masa['detalle'])->firstWhere('tipo_nombramiento', 'nombramiento_permanente');
    expect($permanente)->toMatchArray([
        'remuneracion' => 1200, 'grado' => 'SP10', 'regimen' => 'losep', 'unidad' => 'Jefatura de Vías', 'situacion' => 'suma',
    ])->and($permanente)->not->toHaveKey('centavos');
});

test('por tipo de nombramiento y por régimen salen todos los casos, también los que están en cero', function () {
    ($this->institucion)();

    $masa = ($this->masa)();
    $porTipo = collect($masa['vistas']['tipo_nombramiento'])->keyBy('clave');
    $porRegimen = collect($masa['vistas']['regimen'])->keyBy('clave');

    expect($porTipo)->toHaveCount(7)
        ->and($porTipo['nombramiento_provisional'])->toMatchArray(['personas' => 0, 'mensual' => 0, 'porcentaje' => 0])
        ->and($porTipo['eleccion_popular'])->toMatchArray(['etiqueta' => 'Elección Popular', 'mensual' => 4200])
        ->and($porTipo['servicios_profesionales']['mensual'])->toEqual(1600)
        ->and($porRegimen->keys()->all())->toBe(['losep', 'codigo_trabajo', 'servicios_profesionales'])
        // LOSEP: el permanente, el ocasional y la autoridad electa.
        ->and($porRegimen['losep'])->toMatchArray(['personas' => 3, 'mensual' => 6300, 'anual' => 75600])
        ->and($porRegimen['losep']['porcentaje'])->toEqual(74.1)
        ->and($porRegimen['codigo_trabajo']['mensual'])->toEqual(600);
});

test('cada unidad suma lo que cuelga de ella, y lo que más pesa va primero', function () {
    ($this->institucion)();

    [$raiz] = ($this->masa)()['vistas']['unidad'];

    expect($raiz)->toMatchArray(['nombre' => 'Prefectura', 'nivel' => 1, 'personas' => 5, 'mensual' => 8500])
        ->and($raiz['propias'])->toMatchArray(['personas' => 1, 'mensual' => 4200])
        ->and(array_column($raiz['hijas'], 'nombre'))->toBe(['Dirección Administrativa', 'Dirección de Obras'])
        ->and($raiz['hijas'][1])->toMatchArray(['mensual' => 2100, 'personas' => 2])
        ->and($raiz['hijas'][1]['propias']['mensual'])->toEqual(900)
        ->and($raiz['hijas'][1]['hijas'][0])->toMatchArray(['nombre' => 'Jefatura de Vías', 'mensual' => 1200, 'hijas' => []]);
});

test('los filtros: la unidad con sus dependencias, el régimen y el tipo', function () {
    ($this->institucion)();

    $obras = ($this->masa)("unidad_id={$this->obras->id}");
    expect($obras['totales']['general']['mensual'])->toEqual(2100)
        ->and($obras['filtros'])->toMatchArray(['unidad_id' => $this->obras->id, 'unidad' => 'Dirección de Obras'])
        ->and($obras['vistas']['unidad'])->toHaveCount(1)
        ->and($obras['vistas']['unidad'][0]['nombre'])->toBe('Dirección de Obras');

    $codigo = ($this->masa)('regimen=codigo_trabajo');
    expect($codigo['totales']['general'])->toMatchArray(['personas' => 1, 'mensual' => 600])
        ->and($codigo['detalle'])->toHaveCount(1);

    $profesionales = ($this->masa)('tipo_nombramiento=servicios_profesionales');
    expect($profesionales['totales']['dependencia']['mensual'])->toEqual(0)
        ->and($profesionales['totales']['servicios_profesionales']['mensual'])->toEqual(1600);
});

// ── La fecha de corte ───────────────────────────────────────────

test('en una fecha pasada cuenta la remuneración de entonces y los vínculos de entonces', function () {
    $revisado = ($this->servidor)($this->obras, ['remuneracion' => 1150]);
    ($this->acto)($revisado, [
        'tipo_movimiento' => 'revision_clasificacion', 'fecha_efectiva' => '2026-10-01',
        'remuneracion_origen' => 1000, 'remuneracion_propuesta' => 1150, 'efecto_aplicado_en' => now(),
    ]);
    // Renunció el 15 de octubre.
    ($this->servidor)($this->obras, ['remuneracion' => 700, 'estado' => 'terminado', 'fecha_fin' => '2026-10-15']);
    // Terminado sin fecha de fin: no se sabe hasta cuándo, y no cuenta nunca.
    ($this->servidor)($this->obras, ['remuneracion' => 500, 'estado' => 'terminado']);
    // Cancelado: nunca existió.
    ($this->servidor)($this->obras, ['remuneracion' => 300, 'estado' => 'cancelado']);
    // Empieza el lunes que viene.
    ($this->servidor)($this->obras, ['remuneracion' => 2000, 'fecha_inicio' => '2026-11-23']);

    expect(($this->masa)()['totales']['general'])->toMatchArray(['personas' => 1, 'mensual' => 1150])
        ->and(($this->masa)('corte=2026-09-30')['totales']['general'])->toMatchArray(['personas' => 2, 'mensual' => 1700])
        ->and(($this->masa)('corte=2026-10-15')['totales']['general']['mensual'])->toEqual(1850);
});

test('la fecha de corte no puede ser posterior a hoy', function () {
    $this->getJson('/api/v1/estructura/masa-salarial?corte=2026-11-18')
        ->assertStatus(422)
        ->assertJsonPath('errores.corte.0', 'La fecha de corte no puede ser posterior a hoy.');
});

// ── Lo que va aparte ────────────────────────────────────────────

test('quien está sin remuneración ese día sale del total y va aparte, y su reemplazo suma', function () {
    $titular = ($this->servidor)($this->obras, ['remuneracion' => 1000]);
    $licencia = ($this->acto)($titular, [
        'tipo_movimiento' => 'licencia_sin_remuneracion', 'subtipo_movimiento' => 'asuntos_particulares',
        'fecha_efectiva' => '2026-11-01', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-12-31',
        'remuneracion_origen' => 1000,
    ]);
    ($this->servidor)($this->obras, [
        'tipo_nombramiento' => 'servicios_ocasionales', 'remuneracion' => 800, 'fecha_inicio' => '2026-11-01',
        'fecha_fin' => '2026-12-31', 'cubre_movimiento_id' => $licencia->id,
    ]);

    $masa = ($this->masa)();

    expect($masa['totales']['general'])->toMatchArray(['personas' => 1, 'mensual' => 800])
        ->and($masa['aparte']['sin_remuneracion_en_la_fecha'])->toMatchArray(['personas' => 1, 'mensual' => 1000])
        ->and(collect($masa['detalle'])->firstWhere('servidor_id', $titular->id)['situacion'])->toBe('sin_remuneracion_en_la_fecha');

    // Antes de la licencia cobraba, y el reemplazo aún no estaba.
    expect(($this->masa)('corte=2026-10-31')['totales']['general'])->toMatchArray(['personas' => 1, 'mensual' => 1000]);
});

test('la diferencia de la subrogación va aparte y no entra en el total, hasta la víspera de cancelarse', function () {
    $titular = ($this->servidor)($this->obras, ['remuneracion' => 1400]);
    $subrogante = ($this->servidor)($this->obras, ['remuneracion' => 800]);
    $accion = ($this->acto)($subrogante, [
        'tipo_movimiento' => 'subrogacion', 'fecha_efectiva' => '2026-11-10', 'fecha_inicio' => '2026-11-10',
        'fecha_fin' => '2026-11-30', 'remuneracion_origen' => 800, 'remuneracion_propuesta' => 1400,
    ]);
    $subrogacion = Subrogacion::create([
        'tipo' => 'subrogacion', 'servidor_subrogante_id' => $subrogante->id, 'servidor_subrogado_id' => $titular->id,
        'unidad_administrativa_id' => $this->obras->id, 'puesto_subrogado_id' => $titular->puesto_id,
        'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-11-30', 'motivo' => 'vacaciones',
        'estado' => EstadoSubrogacion::ACTIVA, 'registrado_por' => $this->user->id, 'movimiento_personal_id' => $accion->id,
    ]);

    $masa = ($this->masa)();

    expect($masa['totales']['general']['mensual'])->toEqual(2200)
        ->and($masa['aparte']['subrogaciones'])->toMatchArray(['casos' => 1, 'mensual' => 600])
        ->and($masa['aparte']['subrogaciones']['detalle'][0])->toMatchArray([
            'servidor_id' => $subrogante->id, 'tipo' => 'subrogacion', 'puesto_del_que_asume' => 1400, 'la_suya' => 800, 'diferencia' => 600,
        ]);

    // Cancelada hoy: surtió efecto hasta ayer.
    $subrogacion->forceFill(['estado' => EstadoSubrogacion::CANCELADA, 'cancelada_en' => now()])->save();

    expect(($this->masa)()['aparte']['subrogaciones']['casos'])->toBe(0)
        ->and(($this->masa)('corte=2026-11-16')['aparte']['subrogaciones']['casos'])->toBe(1);
});

test('lo que falta por completar: el vínculo sin remuneración y el servidor activo sin vínculo', function () {
    ($this->servidor)($this->obras, ['remuneracion' => 1000]);
    ($this->servidor)($this->obras, ['remuneracion' => null]);
    $sinVinculo = ($this->servidor)($this->vias, ['sin_vinculo' => true]);
    ($this->servidor)($this->vias, ['sin_vinculo' => true], ['estado' => false]);

    $masa = ($this->masa)();

    expect($masa['totales']['general']['personas'])->toBe(1)
        ->and($masa['por_completar']['vinculos_sin_remuneracion'])->toBe(1)
        ->and($masa['por_completar']['vinculos_sin_unidad'])->toBe(0)
        ->and($masa['por_completar']['servidores_sin_vinculo'])->toBe([
            ['id' => $sinVinculo->id, 'servidor' => "Masa03 Servidor", 'cedula' => $sinVinculo->cedula],
        ]);

    // Solo se sabe para hoy, y sin filtro de régimen ni de tipo, que salen del vínculo que no tiene.
    expect(($this->masa)('corte=2026-11-16')['por_completar']['servidores_sin_vinculo'])->toBeNull()
        ->and(($this->masa)('regimen=losep')['por_completar']['servidores_sin_vinculo'])->toBeNull()
        ->and(($this->masa)("unidad_id={$this->administrativa->id}")['por_completar']['servidores_sin_vinculo'])->toBe([]);
});

// ── Quién la ve ─────────────────────────────────────────────────

test('la ven admin-uath, el auditor y financiero; asistente-uath y el servidor no', function (string $rol, int $estado) {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/estructura/masa-salarial')
        ->assertStatus($estado);
})->with([
    'admin-uath'     => ['admin-uath', 200],
    'auditor'        => ['auditor', 200],
    'financiero'     => ['financiero', 200],
    'asistente-uath' => ['asistente-uath', 403],
    'servidor'       => ['servidor', 403],
]);

test('el seeder da el permiso a los mismos roles que la migración, y la migración vuelve atrás', function () {
    $this->seed(RolPermisoSeeder::class);

    $conElPermiso = Role::where('guard_name', 'sanctum')->get()
        ->filter(fn (Role $r) => $r->hasPermissionTo('ver-masa-salarial'))
        ->pluck('name')->sort()->values()->all();
    expect($conElPermiso)->toBe(['admin-uath', 'auditor', 'financiero']);

    (require database_path('migrations/2026_12_29_000000_permiso_masa_salarial.php'))->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::where('name', 'ver-masa-salarial')->exists())->toBeFalse();
});
