<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\DiagnosticoCie10;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\EstadisticasDispensarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    ConsultaMedica::unguard();
    HistoriaClinica::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion KPI']);
    $puesto = puestoDePrueba($unidad, 'Analista KPI');

    $this->servidorMedico = Servidor::create([
        'cedula' => '0801234511', 'nombre' => 'Gregory', 'apellido' => 'House',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), 'estado' => true,
    ]);

    $this->medico = User::create([
        'email' => 'house@example.com', 'usuario_ti' => 'house',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $this->servidorMedico->id,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));

    $this->paciente = Servidor::create([
        'cedula' => '0801234522', 'nombre' => 'Ana', 'apellido' => 'Mora',
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
});

/** Una consulta de la especialidad y la fecha que se indiquen. */
function consultaDe(
    int $historiaId,
    int $medicoId,
    string $especialidad,
    ?string $creadaEn = null,
    ?int $cie10Id = null,
): ConsultaMedica {
    $consulta = ConsultaMedica::create([
        'historia_clinica_id'   => $historiaId,
        'medico_id'             => $medicoId,
        'especialidad'          => $especialidad,
        'fecha_consulta'        => now(),
        'hora_consulta'         => now()->format('H:i:s'),
        'motivo_consulta'       => 'Control',
        'diagnostico_detallado' => 'Sin hallazgos',
        'diagnostico_cie10_id'  => $cie10Id,
    ]);

    if ($creadaEn) {
        $consulta->forceFill(['created_at' => $creadaEn])->saveQuietly();
    }

    return $consulta;
}

test('los_kpis_desglosan_las_atenciones_del_mes_por_especialidad', function () {
    consultaDe($this->historia->id, $this->medico->id, 'medicina_general');
    consultaDe($this->historia->id, $this->medico->id, 'medicina_general');
    consultaDe($this->historia->id, $this->medico->id, 'odontologia');

    // De otro mes: no debe contarse en ninguna de las dos.
    consultaDe(
        $this->historia->id, $this->medico->id, 'odontologia',
        now()->subMonths(2)->toDateTimeString()
    );

    $kpis = app(EstadisticasDispensarioService::class)->obtenerKpisMensuales();

    expect($kpis['atenciones_mes_actual'])->toBe(3);
    expect($kpis['atenciones_por_especialidad'])->toBe([
        'medicina_general' => 2,
        'odontologia'      => 1,
    ]);
});

test('el_desglose_dice_cero_y_no_se_calla_cuando_una_especialidad_no_atendio', function () {
    consultaDe($this->historia->id, $this->medico->id, 'medicina_general');

    $kpis = app(EstadisticasDispensarioService::class)->obtenerKpisMensuales();

    // Que falte la clave y que valga cero no es lo mismo para quien lee el
    // tablero: lo primero parece un fallo, lo segundo es un dato.
    expect($kpis['atenciones_por_especialidad'])->toBe([
        'medicina_general' => 1,
        'odontologia'      => 0,
    ]);
});

test('las_consultas_por_medico_dicen_el_nombre_y_la_especialidad', function () {
    consultaDe($this->historia->id, $this->medico->id, 'medicina_general');
    consultaDe($this->historia->id, $this->medico->id, 'medicina_general');
    consultaDe($this->historia->id, $this->medico->id, 'odontologia');

    $kpis = app(EstadisticasDispensarioService::class)->obtenerKpisMensuales();
    $filas = collect($kpis['consultas_por_medico']);

    expect($filas)->toHaveCount(2);

    $general = $filas->firstWhere('especialidad', 'medicina_general');
    expect($general->medico)->toBe('Gregory House');
    expect((int) $general->total_consultas)->toBe(2);

    $odonto = $filas->firstWhere('especialidad', 'odontologia');
    expect((int) $odonto->total_consultas)->toBe(1);
});

test('un_medico_sin_servidor_sale_con_su_usuario_y_no_tumba_los_kpis', function () {
    // Se leía `users.name`, que esta tabla no tiene, y los KPI devolvían un 500.
    $suplente = User::create([
        'email' => 'suplente@example.com', 'usuario_ti' => 'suplente',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);

    consultaDe($this->historia->id, $suplente->id, 'medicina_general');

    $kpis = app(EstadisticasDispensarioService::class)->obtenerKpisMensuales();

    expect(collect($kpis['consultas_por_medico'])->first()->medico)
        ->toBe('suplente');
});

test('los_pacientes_por_tipo_cuentan_solo_el_mes_en_curso', function () {
    $familiar = CargaFamiliar::create([
        'servidor_id' => $this->paciente->id, 'cedula' => '0899000044',
        'nombres' => 'Luis', 'apellidos' => 'Mora', 'parentesco' => 'hijo',
        'fecha_nacimiento' => now()->subYears(9), 'estado' => true,
    ]);
    $historiaFamiliar = HistoriaClinica::create([
        'numero_historia' => '0899000044', 'cedula_paciente' => '0899000044',
        'tipo_paciente' => 'familiar', 'carga_familiar_id' => $familiar->id,
        'estado' => true,
    ]);

    consultaDe($this->historia->id, $this->medico->id, 'medicina_general');
    consultaDe($historiaFamiliar->id, $this->medico->id, 'odontologia');

    // Del mes pasado: este conteo no filtraba por fecha y las arrastraba todas,
    // así que no cuadraba con las atenciones del mes que tenía al lado.
    consultaDe(
        $this->historia->id, $this->medico->id, 'medicina_general',
        now()->subMonths(1)->toDateTimeString()
    );

    $kpis = app(EstadisticasDispensarioService::class)->obtenerKpisMensuales();

    expect($kpis['pacientes_por_tipo'])->toBe([
        'titulares'     => 1,
        'beneficiarios' => 1,
    ]);
    expect(array_sum($kpis['pacientes_por_tipo']))
        ->toBe($kpis['atenciones_mes_actual']);
});

test('el_top_de_diagnosticos_dice_de_que_especialidad_es_cada_uno', function () {
    $cie10 = DiagnosticoCie10::create([
        'codigo' => 'K021', 'descripcion' => 'CARIES DE LA DENTINA',
        'categoria' => 'K02', 'activo' => true,
    ]);

    consultaDe(
        $this->historia->id, $this->medico->id, 'odontologia', null, $cie10->id
    );

    $kpis = app(EstadisticasDispensarioService::class)->obtenerKpisMensuales();
    $top  = collect($kpis['top_diagnosticos'])->first();

    expect($top->codigo)->toBe('K021');
    expect($top->especialidad)->toBe('odontologia');
    expect((int) $top->total)->toBe(1);
});

test('los_kpis_se_sirven_por_la_api', function () {
    consultaDe($this->historia->id, $this->medico->id, 'odontologia');

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/dashboard/kpis')
        ->assertOk();

    expect($respuesta->json('datos.atenciones_por_especialidad.odontologia'))
        ->toBe(1);
});

test('marcarse_disponible_es_solo_de_quien_atiende_pacientes', function () {
    // La guarda existía solo en la pantalla, que ocultaba el control. Por la
    // API una enfermera podía marcarse disponible: la lista de disponibles
    // filtra por rol al leer, pero la fila quedaba escrita igual.
    $enfermera = User::create([
        'email' => 'enfermera@example.com', 'usuario_ti' => 'enfermera',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $enfermera->assignRole(Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));

    $this->actingAs($enfermera, 'sanctum')
        ->postJson('/api/v1/dispensario/disponibilidad/alternar')
        ->assertStatus(403);

    expect(DB::table('disponibilidad_medicos')->count())->toBe(0);

    $medico = User::create([
        'email' => 'clinico@example.com', 'usuario_ti' => 'clinico',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $medico->assignRole(Role::firstOrCreate(
        ['name' => 'odontologo', 'guard_name' => 'sanctum']
    ));

    $this->actingAs($medico, 'sanctum')
        ->postJson('/api/v1/dispensario/disponibilidad/alternar')
        ->assertOk()
        ->assertJsonPath('datos.disponible', true);
});

/** Un profesional del rol indicado, marcado o no como disponible. */
function profesional(string $rol, string $usuario, bool $disponible): User
{
    $user = User::create([
        'email' => "{$usuario}@example.com", 'usuario_ti' => $usuario,
        'password' => bcrypt('123456'), 'primer_login' => false,
        'activo' => true,
    ]);
    $user->assignRole(Role::firstOrCreate(
        ['name' => $rol, 'guard_name' => 'sanctum']
    ));

    if ($disponible) {
        DB::table('disponibilidad_medicos')->insert([
            'user_id'        => $user->id,
            'disponible'     => true,
            'actualizado_en' => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    return $user;
}

test('el_turno_solo_ofrece_a_quien_se_marco_disponible', function () {
    // El selector pedía la lista completa del rol y la pantalla la presentaba
    // como «marcados como disponibles»: el interruptor no hacía nada.
    $disponible = profesional('medico', 'enguardia', true);
    profesional('medico', 'encasa', false);

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/disponibilidad/personal?tipo_atencion=medicina_general')
        ->assertOk();

    expect($respuesta->json('datos'))->toHaveCount(1);
    expect($respuesta->json('datos.0.id'))->toBe($disponible->id);
    expect($respuesta->json('meta.hay_disponibles'))->toBeTrue();
});

test('si_nadie_se_marco_disponible_se_ofrece_a_todos_diciendolo', function () {
    profesional('medico', 'encasa', false);
    profesional('medico', 'tambienencasa', false);

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/disponibilidad/personal?tipo_atencion=medicina_general')
        ->assertOk();

    // Filtrar en firme dejaría a Recepción sin poder abrir un turno a las ocho
    // de la mañana porque todavía nadie ha pulsado su interruptor.
    expect($respuesta->json('datos'))->toHaveCount(2);
    expect($respuesta->json('meta.hay_disponibles'))->toBeFalse();
});

test('un_odontologo_disponible_no_sale_para_medicina_general', function () {
    profesional('odontologo', 'muelas', true);
    $medico = profesional('medico', 'enguardia', true);

    $general = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/disponibilidad/personal?tipo_atencion=medicina_general')
        ->assertOk();
    expect($general->json('datos'))->toHaveCount(1);
    expect($general->json('datos.0.id'))->toBe($medico->id);

    $odonto = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/disponibilidad/personal?tipo_atencion=odontologia')
        ->assertOk();
    expect($odonto->json('datos.0.usuario_ti') ?? $odonto->json('datos'))
        ->toHaveCount(1);
});

test('enfermeria_no_figura_entre_los_profesionales_de_un_turno', function () {
    // Enfermería no atiende consultas: no debe ofrecerse para asignarle un
    // turno, ni marcada ni sin marcar.
    profesional('enfermera', 'cuidados', true);

    // Los dos roles clínicos tienen que existir: el filtro los consulta por
    // nombre y sin ellos la búsqueda ni siquiera llega a hacerse.
    foreach (['medico', 'odontologo'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }

    foreach (['medicina_general', 'odontologia'] as $tipo) {
        $respuesta = $this->actingAs($this->medico, 'sanctum')
            ->getJson("/api/v1/dispensario/disponibilidad/personal?tipo_atencion={$tipo}")
            ->assertOk();

        expect(collect($respuesta->json('datos'))->pluck('nombre_completo'))
            ->not->toContain('cuidados');
    }
});

test('el_tipo_de_atencion_tiene_que_ser_uno_de_los_dos', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/disponibilidad/personal?tipo_atencion=veterinaria')
        ->assertStatus(422);
});
