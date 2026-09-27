<?php

namespace Tests\Feature\Expediente;

use App\Models\Asistencia\PermisoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Ausentismo por salud en el expediente. Aquí queda fijado QUÉ cuenta, que es
| lo único que puede equivocarse en silencio: una cifra mal contada se lee como
| si fuera verdad.
|
| La unidad la eligió la UATH el 2026-09-26: permisos, no días. Un permiso
| guarda fecha + hora de inicio + fin, no un rango, así que «días» no se puede
| calcular sin inventar una jornada.
|
| Qué estados cuentan es criterio propio a falta de respuesta suya.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin-uath', 'servidor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }

    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $unidad = unidadDePrueba(['codigo' => 'UATH-AUS', 'nombre' => 'Gestión Administrativa']);
    $puesto = puestoDePrueba($unidad);

    $this->servidor = Servidor::create([
        'cedula' => '1730000001', 'nombre' => 'Ana', 'apellido' => 'Pérez',
        'regimen_laboral' => 'losep', 'estado' => true,
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'fecha_ingreso_institucion' => '2020-01-01',
    ]);

    $this->unidad = $unidad;

    $this->permiso = function (array $atributos = []) use ($unidad) {
        static $n = 0;
        $n++;

        return PermisoServidor::create(array_merge([
            'servidor_id'              => $this->servidor->id,
            'tipo'                     => 'enfermedad',
            'fecha'                    => now()->subMonths(2)->toDateString(),
            'hora_inicio'              => '08:00:00',
            'hora_fin'                 => '12:00:00',
            'estado'                   => 'activo',
            'vence_en'                 => now()->addDays(3),
            'folio'                    => 'PER-TEST-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'unidad_administrativa_id' => $unidad->id,
        ], $atributos));
    };

    $this->consultar = fn () => $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$this->servidor->id}/ausentismo-salud");
});

test('cuenta los permisos por enfermedad del último año', function () {
    ($this->permiso)();
    ($this->permiso)();

    ($this->consultar)()
        ->assertOk()
        ->assertJsonPath('datos.permisos', 2)
        ->assertJsonPath('datos.meses', 12);
});

test('los permisos que no se concedieron no cuentan', function () {
    ($this->permiso)();
    ($this->permiso)(['estado' => 'anulado']);
    ($this->permiso)(['estado' => 'rechazado']);
    // Pedido pero sin confirmar: puede no llegar a ocurrir.
    ($this->permiso)(['estado' => 'pendiente']);

    ($this->consultar)()->assertJsonPath('datos.permisos', 1);
});

test('una falta injustificada no es ausentismo por salud', function () {
    // La persona sí faltó, pero el sistema dice que NO por una enfermedad
    // justificada. Contarla mezclaría lo disciplinario con lo médico.
    ($this->permiso)();
    ($this->permiso)(['estado' => 'falta_injustificada']);

    ($this->consultar)()->assertJsonPath('datos.permisos', 1);
});

test('el validado por trabajo social sí cuenta: se concedió', function () {
    ($this->permiso)(['estado' => 'validado_trabajo_social']);

    ($this->consultar)()->assertJsonPath('datos.permisos', 1);
});

test('solo los de enfermedad, y solo del último año', function () {
    ($this->permiso)();
    // Otro motivo: no es ausentismo por salud.
    ($this->permiso)(['tipo' => 'calamidad']);
    // Más viejo que la ventana.
    ($this->permiso)(['fecha' => now()->subMonths(14)->toDateString()]);

    ($this->consultar)()->assertJsonPath('datos.permisos', 1);
});

test('la cifra no arrastra el motivo que alguien escribió en el permiso', function () {
    // `observacion` es texto libre y puede llevar un diagnóstico. La UATH
    // eligió el resumen sin detalle justamente por eso.
    ($this->permiso)(['observacion' => 'Diagnóstico reservado del paciente']);

    $cuerpo = ($this->consultar)()->assertOk()->getContent();

    expect($cuerpo)->not->toContain('Diagnóstico')
        ->and($cuerpo)->not->toContain('observacion');
});

test('sin rol de Talento Humano no se consulta el ausentismo de nadie', function () {
    $otro = User::factory()->create();
    $otro->assignRole('servidor');

    $this->actingAs($otro, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$this->servidor->id}/ausentismo-salud")
        ->assertForbidden();
});
