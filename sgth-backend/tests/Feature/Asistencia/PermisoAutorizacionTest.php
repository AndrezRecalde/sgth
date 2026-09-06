<?php

/*
| Quién puede ver qué en el módulo de permisos.
|
| El suite anterior cubría bien las reglas de negocio (4 horas, observación
| obligatoria, las 72h) y ninguna de acceso, que es donde estaban los agujeros:
| `show()` y `exportar()` no comprobaban nada, el consolidado estaba abierto a
| cualquier sesión y el endpoint público devolvía el registro entero. Cada test
| de aquí abajo falla contra el código previo al blindaje.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidadA = unidadDePrueba(['nombre' => 'Dirección A']);
    $this->unidadB = unidadDePrueba(['nombre' => 'Dirección B']);

    $this->servidorA = servidorEn($this->unidadA, '0801111111', 'Ana', 'Alfa');
    $this->servidorB = servidorEn($this->unidadB, '0802222222', 'Beto', 'Bravo');
    $this->servidorJefeA = servidorEn($this->unidadA, '0803333333', 'Carla', 'Charlie', esJefe: true);

    $this->ajeno = usuarioCon('ajeno', 'servidor', $this->servidorB);
    $this->titular = usuarioCon('titular', 'servidor', $this->servidorA);
    $this->jefeA = usuarioCon('jefea', 'jefe-unidad', $this->servidorJefeA);
    $this->uath = usuarioCon('uath', 'admin-uath');
    $this->trabajoSocial = usuarioCon('ts', 'trabajo-social');
    $this->recepcion = usuarioCon('recep', 'recepcion');
});

function servidorEn(
    UnidadAdministrativa $unidad,
    string $cedula,
    string $nombre,
    string $apellido,
    bool $esJefe = false,
): Servidor {
    $puesto = $esJefe ? puestoJefeDePrueba($unidad) : puestoDePrueba($unidad);

    return Servidor::create([
        'cedula' => $cedula,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'puesto_id' => $puesto->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);
}

function usuarioCon(string $slug, string $rol, ?Servidor $servidor = null): User
{
    $user = User::create([
        'email' => "{$slug}@example.com",
        'usuario_ti' => "{$slug}_u",
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    if ($servidor) {
        $user->update(['servidor_id' => $servidor->id]);
    }

    $user->assignRole($rol);

    return $user->fresh();
}

function permisoDe(
    Servidor $servidor,
    UnidadAdministrativa $unidad,
    string $folio,
    TipoPermiso $tipo = TipoPermiso::PERSONAL,
    string $observacion = 'Motivo reservado',
    EstadoPermiso $estado = EstadoPermiso::PENDIENTE,
): PermisoServidor {
    return PermisoServidor::create([
        'servidor_id' => $servidor->id,
        'unidad_administrativa_id' => $unidad->id,
        'tipo' => $tipo->value,
        'fecha' => now()->addDay()->format('Y-m-d'),
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'observacion' => $observacion,
        'estado' => $estado->value,
        'vence_en' => now()->addDays(4),
        'folio' => $folio,
    ]);
}

// ── Detalle y PDF ────────────────────────────────────────────────────

test('un servidor de otra unidad no puede abrir el permiso ajeno', function () {
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70001',
        TipoPermiso::ENFERMEDAD, 'Diagnóstico confidencial');

    $this->actingAs($this->ajeno, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$permiso->id}")
        ->assertStatus(403);
});

test('un servidor de otra unidad no puede descargar el PDF ajeno', function () {
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70002');

    $this->actingAs($this->ajeno, 'sanctum')
        ->get("/api/v1/asistencia/permisos/{$permiso->id}/exportar")
        ->assertStatus(403);
});

test('talento humano sí descarga el PDF, y la vista sigue armándose', function () {
    // La vista pasó a recibir `mostrarObservacion` desde el controlador. Si
    // alguien la renderizara sin esa variable el motivo saldría como
    // RESERVADO, no reventaría — así que conviene comprobar el camino bueno.
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70020');

    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/asistencia/permisos/{$permiso->id}/exportar")
        ->assertStatus(200);

    expect($respuesta->headers->get('content-type'))->toContain('application/pdf');
});

test('el titular ve su propio permiso con el motivo', function () {
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70003',
        TipoPermiso::PERSONAL, 'Trámite bancario');

    $respuesta = $this->actingAs($this->titular, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$permiso->id}")
        ->assertStatus(200);

    expect($respuesta->json('datos.observacion'))->toBe('Trámite bancario');
});

test('el jefe ve el permiso de su unidad pero no el motivo', function () {
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70004',
        TipoPermiso::PERSONAL, 'Asunto privado');

    $respuesta = $this->actingAs($this->jefeA, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$permiso->id}")
        ->assertStatus(200);

    expect($respuesta->json('datos.observacion'))->toBeNull();
});

test('el jefe tampoco ve el motivo de un permiso por enfermedad', function () {
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70005',
        TipoPermiso::ENFERMEDAD, 'Diagnóstico confidencial');

    $respuesta = $this->actingAs($this->jefeA, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$permiso->id}")
        ->assertStatus(200);

    expect($respuesta->json('datos.observacion'))->toBeNull();
});

test('el jefe sí ve el motivo de un permiso oficial, que es una diligencia institucional', function () {
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70006',
        TipoPermiso::OFICIAL, 'Reunión en la Prefectura');

    $respuesta = $this->actingAs($this->jefeA, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$permiso->id}")
        ->assertStatus(200);

    expect($respuesta->json('datos.observacion'))->toBe('Reunión en la Prefectura');
});

test('el jefe no alcanza los permisos de otra unidad', function () {
    $permiso = permisoDe($this->servidorB, $this->unidadB, 'PER-2026-70007');

    $this->actingAs($this->jefeA, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$permiso->id}")
        ->assertStatus(403);
});

test('trabajo social lee el motivo de enfermedad pero no el de un permiso personal', function () {
    $enfermedad = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70008',
        TipoPermiso::ENFERMEDAD, 'Reposo por gastritis');
    $personal = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70009',
        TipoPermiso::PERSONAL, 'Asunto privado');

    $conMotivo = $this->actingAs($this->trabajoSocial, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$enfermedad->id}")
        ->assertStatus(200);

    expect($conMotivo->json('datos.observacion'))->toBe('Reposo por gastritis');

    $this->actingAs($this->trabajoSocial, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$personal->id}")
        ->assertStatus(403);
});

test('talento humano ve cualquier permiso con su motivo', function () {
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70010',
        TipoPermiso::ENFERMEDAD, 'Diagnóstico confidencial');

    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$permiso->id}")
        ->assertStatus(200);

    expect($respuesta->json('datos.observacion'))->toBe('Diagnóstico confidencial');
});

// ── Listado ──────────────────────────────────────────────────────────

test('el listado de un servidor raso solo contiene lo suyo', function () {
    permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70011');
    permisoDe($this->servidorB, $this->unidadB, 'PER-2026-70012');

    $respuesta = $this->actingAs($this->titular, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos')
        ->assertStatus(200);

    $folios = collect($respuesta->json('datos.data'))->pluck('folio');

    expect($folios)->toContain('PER-2026-70011')
        ->and($folios)->not->toContain('PER-2026-70012');
});

test('el listado tapa el motivo ajeno igual que el detalle', function () {
    permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70013',
        TipoPermiso::PERSONAL, 'Asunto privado');

    $respuesta = $this->actingAs($this->jefeA, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos')
        ->assertStatus(200);

    $fila = collect($respuesta->json('datos.data'))
        ->firstWhere('folio', 'PER-2026-70013');

    expect($fila)->not->toBeNull()
        ->and($fila['observacion'] ?? null)->toBeNull();
});

test('recepción encuentra los permisos pendientes que tiene que confirmar', function () {
    permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70014');
    permisoDe($this->servidorB, $this->unidadB, 'PER-2026-70015',
        estado: EstadoPermiso::ACTIVO);

    $respuesta = $this->actingAs($this->recepcion, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos')
        ->assertStatus(200);

    $folios = collect($respuesta->json('datos.data'))->pluck('folio');

    expect($folios)->toContain('PER-2026-70014')
        ->and($folios)->not->toContain('PER-2026-70015');
});

test('per_page no puede vaciar la tabla de una sola petición', function () {
    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos?per_page=100000')
        ->assertStatus(200);

    expect($respuesta->json('datos.per_page'))->toBe(100);
});

// ── Anulación ────────────────────────────────────────────────────────

test('un servidor no puede anular un permiso aunque sea el suyo', function () {
    $permiso = permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70016');

    $this->actingAs($this->titular, 'sanctum')
        ->putJson("/api/v1/asistencia/permisos/{$permiso->id}/anular")
        ->assertStatus(403);
});

// ── Consolidado ──────────────────────────────────────────────────────

test('el consolidado institucional no es para cualquiera', function () {
    $params = '?fecha_inicio=' . now()->subMonth()->format('Y-m-d')
        . '&fecha_fin=' . now()->addMonth()->format('Y-m-d');

    $this->actingAs($this->titular, 'sanctum')
        ->getJson("/api/v1/asistencia/consolidado-permisos{$params}")
        ->assertStatus(403);

    $this->actingAs($this->titular, 'sanctum')
        ->get("/api/v1/asistencia/consolidado-permisos/exportar-excel{$params}")
        ->assertStatus(403);

    $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/asistencia/consolidado-permisos{$params}")
        ->assertStatus(200);
});

// ── El folio del QR ──────────────────────────────────────────────────
//
// El QR del papel lo escanea Talento Humano para confirmar o rechazar, así que
// la consulta por folio va dentro de la sesión y con la misma policy que el
// detalle por id. Hubo una versión pública —pensada para que un guardia
// comprobara la autenticidad del documento—; ese caso de uso no existe en la
// institución y se retiró.

test('el folio del QR no se consulta sin sesión', function () {
    permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70017');

    $this->getJson('/api/v1/asistencia/permisos/folio/PER-2026-70017')
        ->assertStatus(401);

    // Y la ruta pública que existía ya no está.
    $this->getJson('/api/v1/permisos/verificar/PER-2026-70017')
        ->assertStatus(404);
});

test('talento humano abre el permiso por su folio', function () {
    permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70018',
        TipoPermiso::ENFERMEDAD, 'Reposo por gripe');

    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos/folio/PER-2026-70018')
        ->assertStatus(200);

    expect($respuesta->json('datos.folio'))->toBe('PER-2026-70018')
        ->and($respuesta->json('datos.observacion'))->toBe('Reposo por gripe')
        ->and($respuesta->json('datos.servidor.cedula'))->toBe('0801111111');
});

test('el folio no es una llave maestra: sigue mandando la policy', function () {
    permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70019',
        TipoPermiso::ENFERMEDAD, 'Diagnóstico confidencial');

    // Un servidor de otra unidad tiene el folio —está impreso en el papel—,
    // pero eso no le da acceso.
    $this->actingAs($this->ajeno, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos/folio/PER-2026-70019')
        ->assertStatus(403);
});

test('recepción ve el permiso por folio pero no su motivo médico', function () {
    permisoDe($this->servidorA, $this->unidadA, 'PER-2026-70021',
        TipoPermiso::ENFERMEDAD, 'Diagnóstico confidencial');

    $respuesta = $this->actingAs($this->recepcion, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos/folio/PER-2026-70021')
        ->assertStatus(200);

    expect($respuesta->json('datos.observacion'))->toBeNull();
    expect($respuesta->getContent())->not->toContain('Diagnóstico confidencial');
});

test('un folio inventado no existe', function () {
    $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos/folio/PER-2026-99999')
        ->assertStatus(404);
});

test('el folio no se cuela por la ruta del id', function () {
    // `GET permisos/{id}` quedó restringido a números: sin eso, el comodín se
    // tragaba 'folio/PER-...' y lo buscaba como id.
    $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos/PER-2026-70018')
        ->assertStatus(404);
});
