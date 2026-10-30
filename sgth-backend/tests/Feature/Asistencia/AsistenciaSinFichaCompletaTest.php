<?php

/*
| Permisos y vacaciones con lo justo de las personas (revisión de las cuentas de
| prueba, 2026-10-30).
|
| Cargaban `servidor`, `jefe` y `creadoPor` sin columnas, y `Servidor` no oculta
| nada: a quien abría el listado le llegaba la ficha entera de todos —celular,
| correo, dirección, contacto de emergencia, discapacidad—, y a un servidor que
| pedía vacaciones, la de su jefe y la de quien lo reemplaza.
|
| Se comparan las claves exactas y no una lista de lo prohibido: una columna
| nueva en `servidores` no entra sola en estas respuestas.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

const CLAVES_SERVIDOR_EN_LISTADO_PERMISOS = ['id', 'nombre', 'apellido', 'unidad_administrativa_id'];
const CLAVES_SERVIDOR_EN_DETALLE_PERMISO = ['id', 'nombre', 'apellido', 'cedula', 'unidad_administrativa_id'];
const CLAVES_PERSONA_EN_VACACIONES = ['id', 'nombre', 'apellido'];

function servidorAsistenciaSinFicha(UnidadAdministrativa $unidad, string $cedula, string $nombre, bool $esJefe = false): Servidor
{
    return Servidor::create([
        'cedula'                   => $cedula,
        'nombre'                   => $nombre,
        'apellido'                 => 'Prueba',
        'puesto_id'                => ($esJefe ? puestoJefeDePrueba($unidad) : puestoDePrueba($unidad))->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'telefono_celular'         => '0999999999',
        'correo_personal'          => "{$cedula}@correo.example",
        'estado'                   => true,
    ]);
}

function usuarioAsistenciaSinFicha(string $rol, ?Servidor $servidor = null): User
{
    $usuario = User::factory()->create(['servidor_id' => $servidor?->id]);
    $usuario->assignRole($rol);

    return $usuario->fresh();
}

function permisoAsistenciaSinFicha(Servidor $servidor, Servidor $jefe, User $creador, array $extra = []): PermisoServidor
{
    static $secuencial = 0;
    $secuencial++;

    return PermisoServidor::create(array_merge([
        'servidor_id'              => $servidor->id,
        'jefe_id'                  => $jefe->id,
        'creado_por'               => $creador->id,
        'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
        'tipo'                     => TipoPermiso::ENFERMEDAD->value,
        'fecha'                    => now()->addDay()->format('Y-m-d'),
        'hora_inicio'              => '08:00',
        'hora_fin'                 => '10:00',
        'observacion'              => 'Motivo reservado',
        'estado'                   => EstadoPermiso::PENDIENTE->value,
        'vence_en'                 => now()->addDays(4),
        'folio'                    => sprintf('PER-2026-%05d', 80000 + $secuencial),
    ], $extra));
}

beforeEach(function () {
    Servidor::unguard();
    UnidadAdministrativa::unguard();
    PermisoServidor::unguard();
    Vacacion::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    config(['services.biometrico.aprobacion_permisos_desde' => null]);

    $unidad = unidadDePrueba(['nombre' => 'Dirección de la ficha']);

    $this->titular = servidorAsistenciaSinFicha($unidad, '0801111111', 'Ana');
    $this->jefe = servidorAsistenciaSinFicha($unidad, '0803333333', 'Carla', esJefe: true);
    $this->reemplazo = servidorAsistenciaSinFicha($unidad, '0804444444', 'Rita');

    $this->uath = usuarioAsistenciaSinFicha('admin-uath');
    $this->recepcion = usuarioAsistenciaSinFicha('recepcion');
    $this->trabajoSocial = usuarioAsistenciaSinFicha('trabajo-social');
    $this->delTitular = usuarioAsistenciaSinFicha('servidor', $this->titular);
    $this->delJefe = usuarioAsistenciaSinFicha('jefe-unidad', $this->jefe);
});

// ── Permisos: listado y detalle ──────────────────────────────────────

test('el listado de permisos lleva solo el nombre del servidor', function (string $quien) {
    permisoAsistenciaSinFicha($this->titular, $this->jefe, $this->uath);

    $fila = $this->actingAs($this->{$quien}, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos')
        ->assertOk()
        ->json('datos.data.0');

    expect(array_keys($fila['servidor']))->toEqualCanonicalizing(CLAVES_SERVIDOR_EN_LISTADO_PERMISOS)
        ->and($fila)->not->toHaveKey('jefe')
        // La relación ya no pisa la columna con el usuario entero.
        ->and($fila['creado_por'])->toBe($this->uath->id);
})->with(['uath', 'trabajoSocial', 'delTitular', 'delJefe']);

test('el detalle del permiso añade la cédula y nada más', function (string $ruta) {
    $permiso = permisoAsistenciaSinFicha($this->titular, $this->jefe, $this->uath);

    $datos = $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos/'.($ruta === 'folio' ? "folio/{$permiso->folio}" : $permiso->id))
        ->assertOk()
        ->json('datos');

    expect(array_keys($datos['servidor']))->toEqualCanonicalizing(CLAVES_SERVIDOR_EN_DETALLE_PERMISO)
        ->and($datos['servidor']['cedula'])->toBe('0801111111')
        ->and($datos['unidad_administrativa'])->toBe([
            'id' => $this->titular->unidad_administrativa_id,
            'nombre' => 'Dirección de la ficha',
        ])
        ->and($datos)->not->toHaveKey('jefe');
})->with(['folio', 'id']);

test('un permiso que nació sin unidad sigue perteneciendo a la unidad del servidor', function () {
    // `PermisoServidorPolicy` cae a `servidor.unidad_administrativa_id`. Si esa
    // columna saliera de la carga, el jefe dejaría de ver el motivo en silencio.
    permisoAsistenciaSinFicha($this->titular, $this->jefe, $this->uath, [
        'unidad_administrativa_id' => null,
        'tipo'                     => TipoPermiso::OFICIAL->value,
        'observacion'              => 'Diligencia institucional',
    ]);

    $this->actingAs($this->delJefe, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos')
        ->assertOk()
        ->assertJsonPath('datos.data.0.observacion', 'Diligencia institucional');
});

// ── Permisos: lo que devuelven las acciones ──────────────────────────

test('confirmar no devuelve la ficha ni le cuenta el motivo a Recepción', function () {
    $permiso = permisoAsistenciaSinFicha($this->titular, $this->jefe, $this->uath);

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso->folio}")
        ->assertOk()
        ->assertJsonPath('datos.estado', EstadoPermiso::ACTIVO->value)
        ->assertJsonMissingPath('datos.servidor')
        ->assertJsonMissingPath('datos.observacion');

    expect($permiso->fresh()->observacion)->toBe('Motivo reservado');
});

test('rechazar tampoco le cuenta el motivo a Recepción', function () {
    $permiso = permisoAsistenciaSinFicha($this->titular, $this->jefe, $this->uath);

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permiso->id}/rechazar", ['motivo' => 'El papel llegó sin firma'])
        ->assertOk()
        ->assertJsonMissingPath('datos.servidor')
        ->assertJsonMissingPath('datos.observacion');
});

test('revertir no devuelve la ficha, y Talento Humano sigue leyendo el motivo', function () {
    $permiso = permisoAsistenciaSinFicha($this->titular, $this->jefe, $this->uath, [
        'estado'        => EstadoPermiso::ACTIVO->value,
        'confirmado_en' => now(),
    ]);

    $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permiso->id}/revertir-confirmacion", ['motivo' => 'Se confirmó por error'])
        ->assertOk()
        ->assertJsonMissingPath('datos.servidor')
        ->assertJsonPath('datos.observacion', 'Motivo reservado');
});

test('validar no devuelve la ficha, y Trabajo Social sigue leyendo el motivo', function () {
    $permiso = permisoAsistenciaSinFicha($this->titular, $this->jefe, $this->uath, [
        'estado'        => EstadoPermiso::ACTIVO->value,
        'confirmado_en' => now(),
    ]);

    $this->actingAs($this->trabajoSocial, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permiso->id}/validar-ts")
        ->assertOk()
        ->assertJsonMissingPath('datos.servidor')
        ->assertJsonPath('datos.observacion', 'Motivo reservado');
});

test('el titular anula el suyo, sin ficha y con su motivo', function () {
    $permiso = permisoAsistenciaSinFicha($this->titular, $this->jefe, $this->uath);

    $this->actingAs($this->delTitular, 'sanctum')
        ->putJson("/api/v1/asistencia/permisos/{$permiso->id}/anular", ['motivo' => 'Ya no lo necesito'])
        ->assertOk()
        ->assertJsonMissingPath('datos.servidor')
        ->assertJsonPath('datos.observacion', 'Motivo reservado');
});

// ── Vacaciones ───────────────────────────────────────────────────────

test('el servidor que pide vacaciones no recibe la ficha de su jefe ni la de quien lo reemplaza', function () {
    periodoVacacionesDePrueba($this->titular);
    $lunes = now()->addWeeks(3)->next(Carbon::MONDAY);

    $datos = $this->actingAs($this->delTitular, 'sanctum')
        ->postJson('/api/v1/asistencia/vacaciones', [
            'servidor_id'          => $this->titular->id,
            'jefe_id'              => $this->jefe->id,
            'persona_reemplaza_id' => $this->reemplazo->id,
            'motivo'               => 'vacaciones_anuales',
            'fecha_inicio'         => $lunes->toDateString(),
            'fecha_fin'            => $lunes->copy()->addDays(2)->toDateString(),
            'dias_solicitados'     => 3,
            'tipo_dias'            => 'habiles',
        ])
        ->assertCreated()
        ->json('datos');

    expect(array_keys($datos['servidor']))->toEqualCanonicalizing(CLAVES_PERSONA_EN_VACACIONES)
        ->and(array_keys($datos['persona_reemplaza']))->toEqualCanonicalizing(CLAVES_PERSONA_EN_VACACIONES)
        ->and($datos)->not->toHaveKey('jefe')
        ->and($datos['jefe_id'])->toBe($this->jefe->id)
        ->and($datos['creado_por'])->toBe($this->delTitular->id);
});

/** Una solicitud pendiente, con todas las personas que pueden filtrarse. */
function vacacionAsistenciaSinFicha(Servidor $titular, Servidor $jefe, Servidor $reemplazo, User $creador): Vacacion
{
    return Vacacion::create([
        'servidor_id'              => $titular->id,
        'jefe_id'                  => $jefe->id,
        'persona_reemplaza_id'     => $reemplazo->id,
        'creado_por'               => $creador->id,
        'unidad_administrativa_id' => $titular->unidad_administrativa_id,
        'motivo'                   => 'vacaciones_anuales',
        'fecha_inicio'             => now()->addWeeks(3)->toDateString(),
        'fecha_fin'                => now()->addWeeks(3)->addDays(2)->toDateString(),
        'fecha_emision'            => now()->toDateString(),
        'dias_solicitados'         => 3,
        'tipo_dias'                => 'habiles',
        'estado'                   => 'pendiente',
        'folio'                    => 'VAC-2026-80001',
    ]);
}

test('el listado de vacaciones lleva solo el nombre del servidor', function (string $quien) {
    vacacionAsistenciaSinFicha($this->titular, $this->jefe, $this->reemplazo, $this->uath);

    $fila = $this->actingAs($this->{$quien}, 'sanctum')
        ->getJson('/api/v1/asistencia/vacaciones')
        ->assertOk()
        ->json('datos.data.0');

    expect(array_keys($fila['servidor']))->toEqualCanonicalizing(CLAVES_PERSONA_EN_VACACIONES)
        ->and($fila)->not->toHaveKey('jefe')
        ->and($fila['creado_por'])->toBe($this->uath->id);
})->with(['uath', 'delJefe']);

test('resolver y anular una solicitud no devuelven la ficha', function () {
    $rechazada = vacacionAsistenciaSinFicha($this->titular, $this->jefe, $this->reemplazo, $this->uath);

    $resuelta = $this->actingAs($this->uath, 'sanctum')
        ->putJson("/api/v1/asistencia/vacaciones/{$rechazada->id}", ['estado' => 'rechazada'])
        ->assertOk()
        ->json('datos');

    expect(array_keys($resuelta['servidor']))->toEqualCanonicalizing(CLAVES_PERSONA_EN_VACACIONES)
        ->and($resuelta)->not->toHaveKey('jefe');

    $pendiente = Vacacion::create([
        ...$rechazada->only([
            'servidor_id', 'jefe_id', 'persona_reemplaza_id', 'creado_por', 'unidad_administrativa_id',
            'motivo', 'fecha_emision', 'dias_solicitados', 'tipo_dias',
        ]),
        'fecha_inicio' => now()->addWeeks(5)->toDateString(),
        'fecha_fin'    => now()->addWeeks(5)->addDays(2)->toDateString(),
        'estado'       => 'pendiente',
        'folio'        => 'VAC-2026-80002',
    ]);

    $anulada = $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/vacaciones/{$pendiente->id}/anular", ['motivo' => 'Se pidió por error'])
        ->assertOk()
        ->json('datos');

    expect(array_keys($anulada['servidor']))->toEqualCanonicalizing(CLAVES_PERSONA_EN_VACACIONES)
        ->and($anulada)->not->toHaveKey('jefe');
});
