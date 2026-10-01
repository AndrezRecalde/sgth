<?php

/*
| Dos invariantes del módulo que no estaban escritas en ninguna parte.
|
| 1. Días de reposo médico sin atención médica.
|
|    `requirio_atencion_medica = false` con `dias_reposo_medico = 12` pasaba la
|    validación. Los días de reposo los prescribe un médico, así que la pareja
|    es contradictoria — y esos días son el NUMERADOR del índice de gravedad
|    del CD 513, que se reporta al IESS.
|
| 2. Las horas de una unidad retirada se mostraban como el total institucional.
|
|    `UnidadAdministrativa` usa SoftDeletes, así que la relación se filtra con
|    `deleted_at is null` y devuelve null. La pantalla pinta
|    `unidad_administrativa?.nombre ?? 'Total institucional'`, y el total
|    institucional es otra cosa: tiene su propio índice único parcial y es lo
|    que decide el alcance del denominador de los índices.
*/

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Sso\HorasTrabajadasPeriodo;
use App\Models\User;
use App\Services\Sso\SsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->gestor = User::create([
        'email' => 'sso5@gadpe.gob.ec',
        'usuario_ti' => 'sso5',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
    $this->gestor->assignRole('admin-uath');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras Públicas']);
    $this->puesto = puestoDePrueba($this->unidad, 'Operador');

    $this->servidor = Servidor::create([
        'cedula' => '0800000099',
        'nombre' => 'Ana',
        'apellido' => 'Quiñónez',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'estado' => true,
    ]);

    $this->servicio = app(SsoService::class);
});

function datosAccidente(array $atributos = []): array
{
    return array_merge([
        'servidor_id' => test()->servidor->id,
        'tipo_evento' => 'accidente',
        'fecha_accidente' => '2026-03-10',
        'hora_accidente' => '09:00',
        'lugar_accidente' => 'Patio de maquinaria',
        'descripcion_hechos' => 'Prueba',
        'gravedad' => 'leve',
        'requirio_atencion_medica' => true,
        'dias_reposo_medico' => 0,
        'estado' => true,
    ], $atributos);
}

// ── Reposo sin atención médica ────────────────────────────────────────

test('no se registran días de reposo sin atención médica', function () {
    expect(fn() => $this->servicio->registrarAccidente(datosAccidente([
        'requirio_atencion_medica' => false,
        'dias_reposo_medico' => 12,
    ])))->toThrow(ValidationException::class);
});

test('el mensaje del rechazo cae en el campo de los días', function () {
    // El formulario reparte el 422 por campo; con la clave equivocada el aviso
    // saldría suelto y sin señalar dónde corregir.
    try {
        $this->servicio->registrarAccidente(datosAccidente([
            'requirio_atencion_medica' => false,
            'dias_reposo_medico' => 3,
        ]));
        $this->fail('Se esperaba el rechazo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('dias_reposo_medico');
    }
});

test('cero días de reposo sin atención médica es válido', function () {
    // Un incidente sin lesión: ni atención ni reposo. Es el caso normal.
    $accidente = $this->servicio->registrarAccidente(datosAccidente([
        'requirio_atencion_medica' => false,
        'dias_reposo_medico' => 0,
    ]));

    expect($accidente->dias_reposo_medico)->toBe(0);
});

test('sin la clave de los días y sin atención médica es válido', function () {
    $datos = datosAccidente(['requirio_atencion_medica' => false]);
    unset($datos['dias_reposo_medico']);

    $accidente = $this->servicio->registrarAccidente($datos);

    // La columna es `NOT NULL DEFAULT 0`.
    expect($accidente->dias_reposo_medico)->toBe(0);
});

test('un null explícito en los días se normaliza a cero, no revienta', function () {
    // La regla decía `nullable` y la columna es `integer NOT NULL DEFAULT 0`,
    // así que un null explícito no daba un 422: daba un 500 con `23502`. Para
    // este campo «vacío» y «cero días» son lo mismo, así que se normaliza.
    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/sso/accidentes', datosAccidente([
            'requirio_atencion_medica' => false,
            'dias_reposo_medico' => null,
        ]))
        ->assertCreated()
        ->assertJsonPath('datos.dias_reposo_medico', 0);
});

test('días de reposo con atención médica es válido', function () {
    $accidente = $this->servicio->registrarAccidente(datosAccidente([
        'requirio_atencion_medica' => true,
        'dias_reposo_medico' => 12,
    ]));

    expect($accidente->dias_reposo_medico)->toBe(12);
});

// ── Y al actualizar, contra lo que ya está guardado ───────────────────

test('al actualizar solo los días, se comprueba contra la atención guardada', function () {
    // El caso que una regla de formulario no habría cubierto: el PATCH manda
    // solo `dias_reposo_medico`, y `requirio_atencion_medica` hay que leerlo
    // del registro. Es el mismo problema que `calcularNtp330` ya resolvía
    // para la valoración NTP 330.
    $accidente = $this->servicio->registrarAccidente(datosAccidente([
        'requirio_atencion_medica' => false,
        'dias_reposo_medico' => 0,
    ]));

    expect(fn() => $this->servicio->actualizarAccidente($accidente->id, [
        'dias_reposo_medico' => 8,
    ]))->toThrow(ValidationException::class);
});

test('al actualizar se puede añadir el reposo si se añade la atención', function () {
    $accidente = $this->servicio->registrarAccidente(datosAccidente([
        'requirio_atencion_medica' => false,
        'dias_reposo_medico' => 0,
    ]));

    $actualizado = $this->servicio->actualizarAccidente($accidente->id, [
        'requirio_atencion_medica' => true,
        'dias_reposo_medico' => 8,
    ]);

    expect($actualizado->dias_reposo_medico)->toBe(8);
});

test('al actualizar no se puede quitar la atención dejando el reposo', function () {
    // El mismo callejón por la otra puerta: el registro tiene 8 días y el
    // PATCH apaga la atención médica.
    $accidente = $this->servicio->registrarAccidente(datosAccidente([
        'requirio_atencion_medica' => true,
        'dias_reposo_medico' => 8,
    ]));

    expect(fn() => $this->servicio->actualizarAccidente($accidente->id, [
        'requirio_atencion_medica' => false,
    ]))->toThrow(ValidationException::class);
});

test('el rechazo llega como 422 por el API y no como 500', function () {
    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/sso/accidentes', datosAccidente([
            'requirio_atencion_medica' => false,
            'dias_reposo_medico' => 12,
        ]))
        ->assertStatus(422)
        ->assertJsonPath('errores.dias_reposo_medico.0', fn ($m) => is_string($m));
});

// ── Las horas de una unidad retirada ──────────────────────────────────

test('las horas de una unidad retirada no se muestran como total institucional', function () {
    // `UnidadAdministrativa` usa SoftDeletes, así que la relación se filtra
    // con `deleted_at is null` y devolvía null — y la pantalla pinta
    // `?? 'Total institucional'` para el null.
    HorasTrabajadasPeriodo::create([
        'periodo' => '2026',
        'unidad_administrativa_id' => $this->unidad->id,
        'total_horas' => 120000,
        'registrado_por' => $this->gestor->id,
    ]);

    $this->unidad->delete();
    expect(UnidadAdministrativa::find($this->unidad->id))->toBeNull();

    $respuesta = $this->actingAs($this->gestor, 'sanctum')
        ->getJson('/api/v1/sso/horas-trabajadas')
        ->assertOk();

    // La fila sigue siendo de su unidad, con nombre y todo.
    expect($respuesta->json('datos.0.unidad_administrativa_id'))->toBe($this->unidad->id);
    expect($respuesta->json('datos.0.unidad_administrativa.nombre'))
        ->toBe('Dirección de Obras Públicas');
});

test('el total institucional de verdad sigue llegando sin unidad', function () {
    // El otro lado: una fila sin unidad es el total institucional, y tiene que
    // seguir distinguiéndose de la anterior.
    HorasTrabajadasPeriodo::create([
        'periodo' => '2026',
        'unidad_administrativa_id' => null,
        'total_horas' => 500000,
        'registrado_por' => $this->gestor->id,
    ]);

    $respuesta = $this->actingAs($this->gestor, 'sanctum')
        ->getJson('/api/v1/sso/horas-trabajadas')
        ->assertOk();

    expect($respuesta->json('datos.0.unidad_administrativa_id'))->toBeNull();
    expect($respuesta->json('datos.0.unidad_administrativa'))->toBeNull();
});

// ── El PUT que ya no existe ───────────────────────────────────────────

test('ya no se puede sobrescribir un período de horas por PUT', function () {
    // `registrarHorasTrabajadas` rechaza el duplicado a propósito: ese total
    // es el denominador de los tres índices del CD 513 y cambiarlo sin dejar
    // rastro movía los tres. El PUT lo pisaba en silencio, y no lo llamaba
    // ninguna pantalla.
    $registro = HorasTrabajadasPeriodo::create([
        'periodo' => '2026-07',
        'unidad_administrativa_id' => null,
        'total_horas' => 160000,
        'registrado_por' => $this->gestor->id,
    ]);

    $this->actingAs($this->gestor, 'sanctum')
        ->putJson("/api/v1/sso/horas-trabajadas/{$registro->id}", ['total_horas' => 1])
        ->assertStatus(405);

    expect($registro->fresh()->total_horas)->toBe(160000);
});

test('para corregir un período se borra y se vuelve a cargar', function () {
    // La salida que queda, y que sí es una decisión deliberada.
    $registro = HorasTrabajadasPeriodo::create([
        'periodo' => '2026-07',
        'unidad_administrativa_id' => null,
        'total_horas' => 160000,
        'registrado_por' => $this->gestor->id,
    ]);

    $this->actingAs($this->gestor, 'sanctum')
        ->deleteJson("/api/v1/sso/horas-trabajadas/{$registro->id}")
        ->assertOk();

    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/sso/horas-trabajadas', [
            'periodo' => '2026-07',
            'total_horas' => 158400,
        ])
        ->assertCreated();

    expect(HorasTrabajadasPeriodo::where('periodo', '2026-07')->sole()->total_horas)
        ->toBe(158400);
});
