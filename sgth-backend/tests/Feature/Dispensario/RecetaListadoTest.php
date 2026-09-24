<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Enums\RegimenLaboral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    ConsultaMedica::unguard();
    HistoriaClinica::unguard();
    RecetaMedica::unguard();

    $this->medico = User::create([
        'email'        => 'medico-listado@example.com',
        'usuario_ti'   => 'medlist',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->medico->assignRole(Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    ));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Recetas']);
    $puesto = puestoDePrueba($unidad, 'Analista de Recetas');

    $paciente = Servidor::create([
        'cedula'                    => '0802345671',
        'nombre'                    => 'Ana',
        'apellido'                  => 'Lopez',
        'puesto_id'                 => $puesto->id,
        'unidad_administrativa_id'  => $unidad->id,
        'regimen_laboral'           => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3),
        'estado'                    => true,
    ]);

    $historia = HistoriaClinica::create([
        'servidor_id'     => $paciente->id,
        'grupo_sanguineo' => 'O+',
    ]);

    $this->consulta = ConsultaMedica::create([
        'especialidad'        => 'medicina_general',
        'historia_clinica_id' => $historia->id,
        'medico_id'           => $this->medico->id,
        'fecha_consulta'      => now(),
        'hora_consulta'       => now()->format('H:i:s'),
        'motivo_consulta'     => 'Control',
    ]);

    $this->actingAs($this->medico, 'sanctum');
});

/**
 * Recetas creadas de un tirón, que es como salen de una consulta real: todas
 * caen dentro del mismo segundo, y `created_at` guarda segundos enteros.
 */
function recetasDelMismoSegundo(int $cuantas, string $estado = 'pendiente'): void
{
    foreach (range(1, $cuantas) as $i) {
        RecetaMedica::create([
            'consulta_medica_id' => test()->consulta->id,
            'fecha_emision'      => now(),
            'estado'             => $estado,
        ]);
    }
}

test('las_páginas_no_se_solapan_aunque_las_recetas_compartan_el_segundo', function () {
    recetasDelMismoSegundo(18);

    // Sin desempate en el ORDER BY esto pasaba o fallaba según el humor del
    // planificador: las 18 empatan en `created_at` y Postgres no promete
    // resolver dos consultas distintas con el mismo orden.
    $paginas = collect(range(1, 4))->map(
        fn (int $n) => collect(
            $this->getJson("/api/v1/dispensario/recetas?per_page=5&page={$n}")
                ->assertOk()->json('datos.data')
        )->pluck('id')
    );

    $todas = $paginas->flatten();

    expect($todas)->toHaveCount(18)
        ->and($todas->duplicates())->toBeEmpty()
        ->and($todas->unique())->toHaveCount(18);
});

test('el_paginador_tiene_techo', function () {
    recetasDelMismoSegundo(12);

    // `per_page=-1` era el que dolía: Laravel descarta los límites negativos,
    // así que la consulta salía sin LIMIT y devolvía la tabla entera con todo
    // el eager loading encima.
    $respuesta = $this->getJson('/api/v1/dispensario/recetas?per_page=-1')
        ->assertOk()->json('datos');

    expect($respuesta['per_page'])->toBeGreaterThan(0)
        ->and(count($respuesta['data']))->toBeLessThan(12);

    $tope = $this->getJson('/api/v1/dispensario/recetas?per_page=100000')
        ->assertOk()->json('datos');

    expect($tope['per_page'])->toBe(100);
});

test('una_fecha_inválida_es_un_422_y_no_un_500', function () {
    $this->getJson('/api/v1/dispensario/recetas?fecha_desde=hola')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['fecha_desde']]);
});

test('un_rango_de_fechas_al_revés_se_rechaza', function () {
    $this->getJson('/api/v1/dispensario/recetas?fecha_desde=2026-03-10&fecha_hasta=2026-03-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['fecha_hasta']]);
});

test('un_estado_que_no_existe_se_rechaza_en_vez_de_devolver_la_lista_vacía', function () {
    recetasDelMismoSegundo(3);

    $this->getJson('/api/v1/dispensario/recetas?estado=inventado')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['estado']]);

    $this->getJson('/api/v1/dispensario/recetas?estados=pendiente,inventado')
        ->assertStatus(422);
});

test('la_lista_de_estados_tolera_el_espacio_después_de_la_coma', function () {
    recetasDelMismoSegundo(4);
    recetasDelMismoSegundo(3, 'anulada');
    recetasDelMismoSegundo(2, 'despachada_completa');

    // «pendiente, anulada» perdía el segundo valor: el explode dejaba
    // ' anulada' con el espacio delante y no casaba con ninguna fila.
    $datos = $this->getJson('/api/v1/dispensario/recetas?estados=pendiente,%20anulada')
        ->assertOk()->json('datos');

    expect($datos['total'])->toBe(7);
});

test('los_contadores_de_la_cabecera_cuentan_todas_las_recetas_no_solo_la_página', function () {
    recetasDelMismoSegundo(12);
    recetasDelMismoSegundo(6, 'anulada');

    $respuesta = $this->getJson('/api/v1/dispensario/recetas?per_page=5')
        ->assertOk()->json();

    expect($respuesta['datos']['data'])->toHaveCount(5)
        ->and($respuesta['meta']['resumen'])->toBe([
            'anulada'   => 6,
            'pendiente' => 12,
        ]);
});

/**
 * El despacho enseña la fecha de EMISIÓN, así que por ahí filtra y por ahí
 * ordena. Antes lo hacía por `created_at`, que es cuándo se registró: una
 * receta emitida el 28 de agosto y registrada el 5 de septiembre no salía al
 * filtrar agosto —el mes parecía vacío— y en el listado se colaba por encima
 * de otra emitida tres días después.
 */
function recetaEmitidaYRegistrada(string $emision, string $registro): RecetaMedica
{
    $receta = RecetaMedica::create([
        'consulta_medica_id' => test()->consulta->id,
        'fecha_emision'      => $emision,
        'estado'             => 'pendiente',
    ]);

    // `created_at` lo pone Eloquent; para separarlo de la emisión hay que
    // escribirlo después, sin tocar `updated_at`.
    RecetaMedica::withoutTimestamps(
        fn () => $receta->forceFill(['created_at' => $registro])->save()
    );

    return $receta->refresh();
}

test('el_filtro_de_fechas_va_por_la_emisión_y_no_por_cuándo_se_registró', function () {
    $tardia = recetaEmitidaYRegistrada('2026-08-28', '2026-09-05 10:00:00');
    recetaEmitidaYRegistrada('2026-09-05', '2026-09-05 11:00:00');

    $agosto = collect(
        $this->getJson('/api/v1/dispensario/recetas?fecha_desde=2026-08-01&fecha_hasta=2026-08-31')
            ->assertOk()->json('datos.data')
    )->pluck('id');

    expect($agosto)->toContain($tardia->id)
        ->and($agosto)->toHaveCount(1);
});

test('la_registrada_tarde_no_aparece_en_el_mes_en_que_se_registró', function () {
    $tardia = recetaEmitidaYRegistrada('2026-08-28', '2026-09-05 10:00:00');

    $septiembre = collect(
        $this->getJson('/api/v1/dispensario/recetas?fecha_desde=2026-09-01&fecha_hasta=2026-09-30')
            ->assertOk()->json('datos.data')
    )->pluck('id');

    expect($septiembre)->not->toContain($tardia->id);
});

test('el_listado_se_ordena_por_la_fecha_que_enseña', function () {
    $agosto     = recetaEmitidaYRegistrada('2026-08-28', '2026-09-05 10:00:00');
    $septiembre = recetaEmitidaYRegistrada('2026-09-02', '2026-09-03 09:00:00');

    $orden = collect(
        $this->getJson('/api/v1/dispensario/recetas')->assertOk()->json('datos.data')
    )->pluck('id');

    // La de septiembre va primero aunque se registrara ANTES que la otra.
    expect($orden->search($septiembre->id))
        ->toBeLessThan($orden->search($agosto->id));
});
