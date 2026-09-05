<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\BorradorConsulta;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\HistoriaClinicaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    AgendaMedica::unguard();
    ConsultaMedica::unguard();
    HistoriaClinica::unguard();

    $rol = Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']);

    $this->medico = User::create([
        'email' => 'house@example.com', 'usuario_ti' => 'house',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->colega = User::create([
        'email' => 'wilson@example.com', 'usuario_ti' => 'wilson',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole($rol);
    $this->colega->assignRole($rol);

    $unidad = unidadDePrueba(['nombre' => 'Direccion Borrador']);
    $puesto = puestoDePrueba($unidad, 'Analista Borrador');

    $this->paciente = Servidor::create([
        'cedula' => '0801234577', 'nombre' => 'Ana', 'apellido' => 'Mora',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), 'estado' => true,
    ]);

    $this->historia = HistoriaClinica::create([
        'numero_historia' => $this->paciente->cedula,
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);

    $this->turno = AgendaMedica::create([
        'medico_id'     => $this->medico->id,
        'servidor_id'   => $this->paciente->id,
        'folio'         => 'TUR-BORRADOR-1',
        'tipo_atencion' => 'medicina_general',
        'fecha'         => now()->toDateString(),
        'hora_inicio'   => '09:00:00',
        'hora_fin'      => '09:30:00',
        'estado'        => 'en_espera',
    ]);
});

/** El contenido de un borrador cualquiera. */
function contenidoDeBorrador(string $motivo = 'Dolor abdominal'): array
{
    return [
        'tipo_atencion'         => 'primera_vez',
        'tipo_diagnostico'      => 'presuntivo',
        'motivo_consulta'       => $motivo,
        'enfermedad_actual'     => '<p>Comenzó hace tres días</p>',
        'diagnostico_detallado' => 'Gastritis probable',
    ];
}

test('lo_escrito_se_guarda_y_se_recupera', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => contenidoDeBorrador(),
        ])
        ->assertOk();

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->getJson(
            '/api/v1/dispensario/consultas/borrador?agenda_medica_id='
            . $this->turno->id
        )
        ->assertOk();

    expect($respuesta->json('datos.contenido.motivo_consulta'))
        ->toBe('Dolor abdominal');
    expect($respuesta->json('datos.contenido.enfermedad_actual'))
        ->toBe('<p>Comenzó hace tres días</p>');
});

test('guardar_de_nuevo_reemplaza_el_borrador_en_vez_de_acumularlo', function () {
    foreach (['Primera versión', 'Lo que quedó al final'] as $motivo) {
        $this->actingAs($this->medico, 'sanctum')
            ->putJson('/api/v1/dispensario/consultas/borrador', [
                'agenda_medica_id' => $this->turno->id,
                'contenido'        => contenidoDeBorrador($motivo),
            ])
            ->assertOk();
    }

    expect(BorradorConsulta::count())->toBe(1);
    expect(BorradorConsulta::first()->contenido['motivo_consulta'])
        ->toBe('Lo que quedó al final');
});

test('el_borrador_se_guarda_cifrado', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => contenidoDeBorrador('Cefalea intensa'),
        ])
        ->assertOk();

    // La consulta guarda sus campos clínicos cifrados; un borrador de esa misma
    // nota no puede quedar en claro por ser provisional.
    $enBruto = DB::table('borradores_consulta')->value('contenido');

    expect($enBruto)->not->toContain('Cefalea intensa');
});

test('el_borrador_es_de_quien_lo_escribe', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => contenidoDeBorrador('Lo mío'),
        ])
        ->assertOk();

    // Un colega abre el mismo turno y no ve nada: una nota a medias no es
    // historia clínica, y no hay pantalla donde se lea la de otro.
    $respuesta = $this->actingAs($this->colega, 'sanctum')
        ->getJson(
            '/api/v1/dispensario/consultas/borrador?agenda_medica_id='
            . $this->turno->id
        )
        ->assertOk();

    expect($respuesta->json('datos'))->toBeNull();
});

test('cada_medico_tiene_su_propio_borrador_del_mismo_turno', function () {
    foreach ([[$this->medico, 'Lo del titular'], [$this->colega, 'Lo del colega']] as [$quien, $texto]) {
        $this->actingAs($quien, 'sanctum')
            ->putJson('/api/v1/dispensario/consultas/borrador', [
                'agenda_medica_id' => $this->turno->id,
                'contenido'        => contenidoDeBorrador($texto),
            ])
            ->assertOk();
    }

    expect(BorradorConsulta::count())->toBe(2);

    $respuesta = $this->actingAs($this->colega, 'sanctum')
        ->getJson(
            '/api/v1/dispensario/consultas/borrador?agenda_medica_id='
            . $this->turno->id
        )
        ->assertOk();

    expect($respuesta->json('datos.contenido.motivo_consulta'))
        ->toBe('Lo del colega');
});

test('guardar_la_consulta_retira_su_borrador', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => contenidoDeBorrador(),
        ])
        ->assertOk();

    app(HistoriaClinicaService::class)->registrarConsulta([
        'historia_clinica_id'   => $this->historia->id,
        'agenda_medica_id'      => $this->turno->id,
        'medico_id'             => $this->medico->id,
        'fecha_consulta'        => now()->toDateString(),
        'hora_consulta'         => now()->format('H:i'),
        'tipo_atencion'         => 'primera_vez',
        'tipo_diagnostico'      => 'presuntivo',
        'motivo_consulta'       => 'Dolor abdominal',
        'diagnostico_detallado' => 'Gastritis',
    ]);

    // Ya está en la historia clínica: si el borrador siguiera ahí, al reabrir
    // el turno se ofrecería recuperar una nota que ya se guardó.
    expect(BorradorConsulta::count())->toBe(0);
});

test('guardar_la_consulta_no_toca_el_borrador_de_otro_medico', function () {
    $this->actingAs($this->colega, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => contenidoDeBorrador('Lo del colega'),
        ])
        ->assertOk();

    app(HistoriaClinicaService::class)->registrarConsulta([
        'historia_clinica_id'   => $this->historia->id,
        'agenda_medica_id'      => $this->turno->id,
        'medico_id'             => $this->medico->id,
        'fecha_consulta'        => now()->toDateString(),
        'hora_consulta'         => now()->format('H:i'),
        'tipo_atencion'         => 'primera_vez',
        'tipo_diagnostico'      => 'presuntivo',
        'motivo_consulta'       => 'Dolor abdominal',
        'diagnostico_detallado' => 'Gastritis',
    ]);

    expect(BorradorConsulta::count())->toBe(1);
});

test('el_borrador_se_puede_descartar', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => contenidoDeBorrador(),
        ])
        ->assertOk();

    $this->actingAs($this->medico, 'sanctum')
        ->deleteJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
        ])
        ->assertOk();

    expect(BorradorConsulta::count())->toBe(0);
});

test('si_el_turno_desaparece_su_borrador_tambien', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => contenidoDeBorrador(),
        ])
        ->assertOk();

    $this->turno->forceDelete();

    expect(BorradorConsulta::count())->toBe(0);
});

test('un_borrador_desmedido_no_se_guarda', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => [
                'motivo_consulta' => str_repeat('a', 41_000),
            ],
        ])
        ->assertStatus(422);

    expect(BorradorConsulta::count())->toBe(0);
});

test('el_borrador_no_es_para_quien_no_atiende', function () {
    $ajeno = User::create([
        'email' => 'ajeno@example.com', 'usuario_ti' => 'ajeno',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);

    $this->actingAs($ajeno, 'sanctum')
        ->putJson('/api/v1/dispensario/consultas/borrador', [
            'agenda_medica_id' => $this->turno->id,
            'contenido'        => contenidoDeBorrador(),
        ])
        ->assertStatus(403);
});
