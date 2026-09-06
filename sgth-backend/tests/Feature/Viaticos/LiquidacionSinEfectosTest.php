<?php

use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Viatico::unguard();
    LiquidacionViatico::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion Viaticos']);
    $puesto = puestoDePrueba($unidad);

    $this->servidor = Servidor::create([
        'cedula' => '0801234566', 'nombre' => 'Pedro', 'apellido' => 'Gomez',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);

    $this->usuario = User::create([
        'email' => 'viaticos@example.com', 'usuario_ti' => 'viaticos',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $this->servidor->id,
    ]);
    $this->usuario->assignRole(Role::firstOrCreate(
        ['name' => 'admin-uath', 'guard_name' => 'sanctum']
    ));

    $this->viatico = Viatico::create([
        'servidor_id'      => $this->servidor->id,
        'zona'             => 'dentro_provincia',
        'datetime_salida'  => now()->subDays(3),
        'datetime_llegada' => now()->subDays(1),
        'total_dias'       => 2,
        'justificacion'    => 'Reunión de trabajo en Quito',
        'estado'           => 'pendiente_liquidacion',
        'monto_calculado'  => 100,
        'created_by'       => $this->usuario->id,
    ]);
});

test('listar_las_actividades_no_crea_la_liquidacion', function () {
    // Los dos listados usaban el mismo `firstOrCreate` que el alta, así que
    // pedir la lista abría la liquidación: con solo mirar la pantalla quedaba
    // una fila aunque no se registrara nada. Un GET no cambia el estado.
    $respuesta = $this->actingAs($this->usuario, 'sanctum')
        ->getJson("/api/v1/viaticos/{$this->viatico->id}/liquidacion/actividades")
        ->assertOk();

    expect($respuesta->json('datos'))->toBe([]);
    expect(LiquidacionViatico::count())->toBe(0);
});

test('listar_las_facturas_tampoco_crea_la_liquidacion', function () {
    $respuesta = $this->actingAs($this->usuario, 'sanctum')
        ->getJson("/api/v1/viaticos/{$this->viatico->id}/liquidacion/facturas")
        ->assertOk();

    expect($respuesta->json('datos'))->toBe([]);
    expect(LiquidacionViatico::count())->toBe(0);
});

test('un_viatico_que_no_existe_da_404_y_no_una_traza_de_postgres', function () {
    // Sin comprobar que el viático exista, el `firstOrCreate` intentaba
    // escribir y reventaba con una violación de clave foránea: un 500 con la
    // traza de la base de datos en la cara.
    foreach (['actividades', 'facturas', ''] as $sufijo) {
        $this->actingAs($this->usuario, 'sanctum')
            ->getJson('/api/v1/viaticos/999999/liquidacion/' . $sufijo)
            ->assertNotFound();
    }

    expect(LiquidacionViatico::count())->toBe(0);
});

test('abrir_la_liquidacion_si_la_crea_porque_para_eso_esta', function () {
    // La ruta que la pantalla sí usa. Aquí crear es lo correcto: es la acción
    // de abrir la liquidación, no un listado.
    $this->actingAs($this->usuario, 'sanctum')
        ->getJson("/api/v1/viaticos/{$this->viatico->id}/liquidacion")
        ->assertOk();

    expect(LiquidacionViatico::count())->toBe(1);
});

test('confirmar_sin_liquidacion_se_rechaza_sin_dejar_fila', function () {
    $respuesta = $this->actingAs($this->usuario, 'sanctum')
        ->postJson("/api/v1/viaticos/{$this->viatico->id}/liquidacion/confirmar")
        ->assertStatus(422);

    // Antes abría la liquidación para acto seguido negarse a cerrarla, y la
    // fila se quedaba ahí.
    expect(LiquidacionViatico::count())->toBe(0);
    expect($respuesta->json('mensaje'))
        ->toBe('Debe registrar al menos una actividad.');
    // El código iba en el sitio de los errores y se colaba en el cuerpo.
    expect($respuesta->json('errores'))->toBeNull();
});

test('guardar_actividades_si_abre_la_liquidacion', function () {
    $this->actingAs($this->usuario, 'sanctum')
        ->postJson(
            "/api/v1/viaticos/{$this->viatico->id}/liquidacion/actividades",
            ['actividades' => [[
                'fecha'       => now()->subDays(2)->toDateString(),
                'descripcion' => 'Reunión con la contraparte',
                'lugar'       => 'Quito',
            ]]]
        )
        ->assertOk();

    expect(LiquidacionViatico::count())->toBe(1);

    // Y ahora el listado sí la encuentra, sin haberla creado él.
    $respuesta = $this->actingAs($this->usuario, 'sanctum')
        ->getJson("/api/v1/viaticos/{$this->viatico->id}/liquidacion/actividades")
        ->assertOk();

    expect($respuesta->json('datos'))->toHaveCount(1);
    expect(LiquidacionViatico::count())->toBe(1);
});
