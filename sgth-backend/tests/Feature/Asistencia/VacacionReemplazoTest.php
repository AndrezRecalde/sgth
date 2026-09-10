<?php

/*
| La persona que reemplaza y la observación de una solicitud.
|
| El formulario pedía los dos y la API los validaba, pero `solicitar()` no los
| guardaba —y la observación ni siquiera tenía columna—: se descartaban sin
| aviso y el PDF salía siempre con «—».
|
| Guardado, el reemplazo tiene que tener sentido: otra persona, activa, y que
| no esté ella misma fuera en esas fechas.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Vacacion::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();
    $puesto = puestoDePrueba($unidad);

    $cedula = 800001000;
    $this->servidor = function (array $atributos = []) use ($unidad, $puesto, &$cedula) {
        return Servidor::create(array_merge([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Rita',
            'apellido'                 => 'Reemplazo',
            'puesto_id'                => $puesto->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ], $atributos));
    };

    $this->titular   = ($this->servidor)(['nombre' => 'Tito', 'apellido' => 'Titular']);
    $this->reemplazo = ($this->servidor)();

    $uath = User::create([
        'email'        => 'uath-reemplazo@example.com',
        'usuario_ti'   => 'uathree',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    // «Matrimonio» no descuenta: estas pruebas no necesitan períodos.
    $this->lunes = now()->addWeeks(3)->next(Carbon::MONDAY);
    $this->pedir = fn (array $extra = []) => $this->postJson('/api/v1/asistencia/vacaciones', array_merge([
        'servidor_id'      => $this->titular->id,
        'motivo'           => 'matrimonio',
        'fecha_inicio'     => $this->lunes->toDateString(),
        'fecha_fin'        => $this->lunes->copy()->addDays(2)->toDateString(),
        'dias_solicitados' => 3,
        'tipo_dias'        => 'habiles',
    ], $extra));
});

test('se guardan la persona que reemplaza y la observación', function () {
    $respuesta = ($this->pedir)([
        'persona_reemplaza_id' => $this->reemplazo->id,
        'observacion'          => '  Cubre la ventanilla de atención  ',
    ]);

    $respuesta->assertCreated();

    $vacacion = Vacacion::find($respuesta->json('datos.id'));
    expect($vacacion->persona_reemplaza_id)->toBe($this->reemplazo->id)
        ->and($vacacion->observacion)->toBe('Cubre la ventanilla de atención')
        ->and($respuesta->json('datos.persona_reemplaza.id'))->toBe($this->reemplazo->id);
});

test('una observación en blanco se guarda vacía, no como espacios', function () {
    $respuesta = ($this->pedir)(['observacion' => '   ']);

    expect(Vacacion::find($respuesta->json('datos.id'))->observacion)->toBeNull();
});

test('nadie se reemplaza a sí mismo', function () {
    $respuesta = ($this->pedir)(['persona_reemplaza_id' => $this->titular->id]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('mismo servidor');
});

test('un servidor inactivo no reemplaza', function () {
    $this->reemplazo->update(['estado' => false]);

    $respuesta = ($this->pedir)(['persona_reemplaza_id' => $this->reemplazo->id]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('no está activo');
});

test('quien reemplaza no puede estar de vacaciones esas fechas; si se las rechazaron, sí puede', function () {
    $suyas = Vacacion::create([
        'servidor_id'      => $this->reemplazo->id,
        'fecha_inicio'     => $this->lunes->copy()->addDay()->toDateString(),
        'fecha_fin'        => $this->lunes->copy()->addDays(4)->toDateString(),
        'dias_solicitados' => 4,
        'tipo_dias'        => 'habiles',
        'estado'           => 'aprobada',
        'motivo'           => 'vacaciones_anuales',
        'folio'            => 'VAC-2099-00001',
    ]);

    $respuesta = ($this->pedir)(['persona_reemplaza_id' => $this->reemplazo->id]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('VAC-2099-00001')
        ->and(Vacacion::where('servidor_id', $this->titular->id)->count())->toBe(0);

    $suyas->update(['estado' => 'rechazada']);

    ($this->pedir)(['persona_reemplaza_id' => $this->reemplazo->id])->assertCreated();
});

test('el QR guardado lleva a la pantalla de vacaciones, no a una ruta que no existe', function () {
    $respuesta = ($this->pedir)();
    $folio = $respuesta->json('datos.folio');

    expect($respuesta->json('datos.codigo_qr'))
        ->toBe(rtrim(config('app.frontend_url'), '/')."/sgth/asistencia/vacaciones?folio={$folio}")
        ->not->toContain('verificar');
});
