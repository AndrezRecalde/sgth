<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
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

    $this->medico = User::create([
        'email'        => 'medico-listado-consulta@example.com',
        'usuario_ti'   => 'medlistcon',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    ));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Consultas']);
    $puesto = puestoDePrueba($unidad, 'Analista de Consultas');

    $paciente = Servidor::create([
        'cedula'                    => '0802345674',
        'nombre'                    => 'Ana',
        'apellido'                  => 'Lopez',
        'puesto_id'                 => $puesto->id,
        'unidad_administrativa_id'  => $unidad->id,
        'regimen_laboral'           => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3),
        'estado'                    => true,
    ]);

    $this->historia = HistoriaClinica::create([
        'servidor_id'     => $paciente->id,
        'grupo_sanguineo' => 'O+',
    ]);

    $this->actingAs($this->medico, 'sanctum');
});

/**
 * Consultas del MISMO día, que es como se acumulan en un dispensario: una por
 * la mañana y otra por la tarde, o medicina y odontología el mismo martes.
 * `fecha_consulta` es un `date`, así que todas empatan en el orden.
 *
 * Se crean de la más temprana a la más tardía, al revés de como deben salir:
 * así, si el orden se cayera, el listado devolvería el orden de inserción y la
 * comprobación se daría cuenta.
 *
 * @return list<int> los ids, de la consulta más reciente a la más antigua
 */
function consultasElMismoDia(int $cuantas, HistoriaClinica $historia, User $medico): array
{
    $ids = [];

    foreach (range(1, $cuantas) as $i) {
        $ids[] = ConsultaMedica::create([
            'especialidad'          => 'medicina_general',
            'historia_clinica_id'   => $historia->id,
            'medico_id'             => $medico->id,
            'fecha_consulta'        => '2026-09-01',
            'hora_consulta'         => sprintf('%02d:00:00', 7 + $i),
            'motivo_consulta'       => "Control {$i}",
            'diagnostico_detallado' => "Diagnostico {$i}",
        ])->id;
    }

    return array_reverse($ids);
}

it('no devuelve la tabla entera cuando se pide per_page negativo', function () {
    consultasElMismoDia(12, $this->historia, $this->medico);

    $respuesta = $this->getJson('/api/v1/dispensario/consultas?per_page=-1')
        ->assertOk();

    // `limit()` de Laravel ignora los negativos: sin techo esto salía sin LIMIT
    // y traía las doce —y con ellas las notas clínicas de todos los pacientes
    // descifradas en una sola respuesta—.
    expect($respuesta->json('datos.per_page'))->toBeGreaterThanOrEqual(1)
        ->and($respuesta->json('datos.data'))->toHaveCount(
            min(12, $respuesta->json('datos.per_page'))
        );
});

it('acota per_page a su techo', function () {
    $respuesta = $this->getJson('/api/v1/dispensario/consultas?per_page=5000')
        ->assertOk();

    expect($respuesta->json('datos.per_page'))->toBe(100);
});

it('rechaza una fecha que no es una fecha en vez de reventar', function () {
    // Sin validar, esto llegaba tal cual a Postgres y volvía como un 500:
    // «invalid input syntax for type date».
    $this->getJson('/api/v1/dispensario/consultas?fecha_desde=hola')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['fecha_desde']]);
});

it('rechaza un rango de fechas al reves', function () {
    $this->getJson('/api/v1/dispensario/consultas?fecha_desde=2026-09-30&fecha_hasta=2026-09-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['fecha_hasta']]);
});

it('rechaza una especialidad que no existe en vez de decir que no hay consultas', function () {
    consultasElMismoDia(2, $this->historia, $this->medico);

    // Respondía 200 con la lista vacía, así que quien escribía mal la
    // especialidad concluía que el paciente no tenía consultas.
    $this->getJson('/api/v1/dispensario/consultas?especialidad=inventada')
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['especialidad']]);
});

it('acepta los filtros bien escritos', function () {
    consultasElMismoDia(2, $this->historia, $this->medico);

    $respuesta = $this->getJson(
        '/api/v1/dispensario/consultas'
        . "?historia_clinica_id={$this->historia->id}"
        . '&especialidad=medicina_general'
        . '&fecha_desde=2026-09-01&fecha_hasta=2026-09-30'
    )->assertOk();

    expect($respuesta->json('datos.data'))->toHaveCount(2);
});

it('pagina el historial sin repetir ni saltarse consultas del mismo dia', function () {
    $esperados = consultasElMismoDia(12, $this->historia, $this->medico);

    $url = "/api/v1/dispensario/consultas?historia_clinica_id={$this->historia->id}&per_page=5";

    $ids = collect([1, 2, 3])->flatMap(
        fn ($pagina) => $this->getJson("{$url}&page={$pagina}")
            ->assertOk()
            ->json('datos.data.*.id')
    )->all();

    // Ordenar solo por `fecha_consulta` dejaba empatadas todas las del mismo
    // día, y ahí el orden lo decide Postgres: las páginas no tenían por qué
    // coincidir entre sí, así que la segunda repetía filas de la primera y las
    // que desplazaba no salían en ninguna. Con la hora y el id desempatando,
    // las tres páginas encajan y salen de la más reciente a la más antigua.
    expect($ids)->toBe($esperados);
});
