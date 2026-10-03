<?php

use App\Enums\RegimenLaboral;
use App\Enums\TipoParentesco;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

function usuarioDeReportes(string $usuario, ?string $rol): User
{
    $user = User::forceCreate([
        'email' => "{$usuario}@example.com", 'usuario_ti' => $usuario,
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    if ($rol) {
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));
    }

    return $user;
}

function consultaDeReporte(int $historia, int $medico, string $fecha, ?int $cie10, string $tipo = 'primera_vez', string $especialidad = 'medicina_general'): int
{
    return DB::table('consultas_medicas')->insertGetId([
        'historia_clinica_id' => $historia, 'medico_id' => $medico,
        'fecha_consulta' => $fecha, 'hora_consulta' => '09:00',
        'especialidad' => $especialidad, 'tipo_atencion' => $tipo,
        'tipo_diagnostico' => 'definitivo', 'diagnostico_cie10_id' => $cie10,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->admin      = usuarioDeReportes('admrep', 'admin-dispensario');
    $this->autoridad  = usuarioDeReportes('autrep', 'maxima-autoridad');
    $this->medico     = usuarioDeReportes('medrep', 'medico');
    $this->otroMedico = usuarioDeReportes('medrep2', 'medico');
    $this->enfermera  = usuarioDeReportes('enfrep', 'enfermera');
    Role::firstOrCreate(['name' => 'odontologo', 'guard_name' => 'sanctum']);

    $unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0804444444', 'nombre' => 'Luis', 'apellido' => 'Cortez',
        'puesto_id' => puestoDePrueba($unidad, 'Inspector')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(5),
        'fecha_nacimiento' => '1990-01-15', 'genero' => 'masculino', 'estado' => true,
    ]);
    $hija = CargaFamiliar::create([
        'servidor_id' => $this->servidor->id, 'cedula' => '0804444445',
        'nombres' => 'Ana', 'apellidos' => 'Cortez', 'parentesco' => TipoParentesco::HIJO,
        'fecha_nacimiento' => '2018-06-01', 'estado' => true,
    ]);

    $this->hcServidor = DB::table('historias_clinicas')->insertGetId([
        'numero_historia' => '0804444444', 'cedula_paciente' => '0804444444',
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'estado' => true,
    ]);
    $this->hcHija = DB::table('historias_clinicas')->insertGetId([
        'numero_historia' => '0804444445', 'cedula_paciente' => '0804444445',
        'tipo_paciente' => 'familiar', 'carga_familiar_id' => $hija->id, 'estado' => true,
    ]);

    $gripe = DB::table('diagnosticos_cie10')->insertGetId(['codigo' => 'J00X', 'descripcion' => 'RINOFARINGITIS AGUDA', 'categoria' => 'J00']);
    $lumbago = DB::table('diagnosticos_cie10')->insertGetId(['codigo' => 'M545', 'descripcion' => 'LUMBAGO NO ESPECIFICADO', 'categoria' => 'M54']);

    // Tres del médico (dos de gripe), una de otro médico, y una fuera del período.
    $this->consultaServidor = consultaDeReporte($this->hcServidor, $this->medico->id, '2026-09-10', $gripe);
    consultaDeReporte($this->hcHija, $this->medico->id, '2026-09-11', $gripe, 'subsecuente');
    consultaDeReporte($this->hcServidor, $this->medico->id, '2026-09-12', $lumbago);
    consultaDeReporte($this->hcServidor, $this->otroMedico->id, '2026-09-12', $lumbago);
    consultaDeReporte($this->hcServidor, $this->medico->id, '2026-07-01', $lumbago);

    DB::table('diagnosticos_secundarios_consulta')->insert([
        'consulta_medica_id' => $this->consultaServidor, 'diagnostico_cie10_id' => $lumbago,
    ]);

    $servicio = DB::table('catalogo_servicios_enfermeria')->insertGetId(['nombre' => 'Curación', 'activo' => true]);
    foreach ([null, now()] as $anulado) {
        DB::table('atenciones_enfermeria')->insert([
            'folio' => 'ENF-T-' . uniqid(), 'enfermera_id' => $this->enfermera->id,
            'servidor_id' => $this->servidor->id, 'catalogo_servicio_id' => $servicio,
            'atendido_en' => '2026-09-10 10:00:00', 'anulado_en' => $anulado,
        ]);
    }

    $this->filtros = '?desde=2026-09-01&hasta=2026-09-30';
});

test('cada perfil ve su catálogo', function () {
    $claves = fn (User $u) => collect($this->actingAs($u, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->assertOk()->json('datos.reportes'))->pluck('clave')->all();

    expect($claves($this->admin))->toBe([
        'atenciones', 'morbilidad', 'produccion', 'ausentismo',
        'farmacia_movimiento', 'farmacia_existencias', 'enfermeria',
    ]);
    // La autoridad, nada con nombres de pacientes.
    expect($claves($this->autoridad))->toBe([
        'morbilidad', 'produccion', 'ausentismo',
        'farmacia_movimiento', 'farmacia_existencias', 'enfermeria',
    ]);
    // Cada profesional, lo suyo: ni ausentismo por unidad ni farmacia.
    expect($claves($this->medico))->toBe(['atenciones', 'morbilidad', 'produccion']);
    expect($claves($this->enfermera))->toBe(['produccion', 'enfermeria']);

    $this->actingAs(usuarioDeReportes('nadierep', null), 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->assertForbidden();
});

test('quien solo ve lo suyo no elige profesional', function () {
    $reportes = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->json('datos.reportes');

    expect(collect($reportes)->firstWhere('clave', 'atenciones')['filtros'])->not->toContain('profesional');
});

test('la autoridad no saca el registro nominal ni por la URL', function () {
    $this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/atenciones' . $this->filtros)->assertForbidden();
    $this->actingAs($this->autoridad, 'sanctum')
        ->get('/api/v1/dispensario/reportes/atenciones/excel' . $this->filtros)->assertForbidden();
});

test('el registro de atenciones trae al paciente con su edad, sexo, unidad y diagnósticos', function () {
    $filas = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/atenciones' . $this->filtros)
        ->assertOk()->json('datos.filas'));

    expect($filas)->toHaveCount(4);

    $primera = $filas->first();
    expect($primera)->toMatchArray([
        'fecha' => '2026-09-10', 'cedula' => '0804444444', 'paciente' => 'Luis Cortez',
        'tipo_paciente' => 'Servidor', 'sexo' => 'Hombre', 'edad' => 36,
        'unidad' => 'Dirección de Obras', 'cie10' => 'J00X', 'secundarios' => 'M545',
    ]);

    // La hija: la unidad es la del titular, y el sexo no consta.
    $hija = $filas->firstWhere('paciente', 'Ana Cortez');
    expect($hija)->toMatchArray([
        'tipo_paciente' => 'Familiar', 'sexo' => 'Sin dato', 'edad' => 8,
        'unidad' => 'Dirección de Obras', 'tipo_atencion' => 'Subsecuente',
    ]);
});

test('el médico solo ve sus consultas aunque pida las de otro', function () {
    $filas = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/atenciones' . $this->filtros . '&profesional_id=' . $this->otroMedico->id)
        ->assertOk()->json('datos.filas');

    expect($filas)->toHaveCount(3);
});

test('la morbilidad cuenta el diagnóstico principal por sexo y edad', function () {
    $filas = collect($this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/morbilidad' . $this->filtros)
        ->assertOk()->json('datos.filas'));

    expect($filas->pluck('cie10')->all())->toBe(['J00X', 'M545']);
    expect($filas->first())->toMatchArray([
        'total' => 2, 'porcentaje' => 50, 'primera_vez' => 1,
        'hombres' => 1, 'sexo_sin_dato' => 1,
        'edad_menor_de_15' => 1, 'edad_30_a_44' => 1,
    ]);
    // Ni rastro de pacientes en un reporte agregado.
    expect(json_encode($filas))->not->toContain('Cortez');
});

test('la producción no cuenta lo anulado y agrupa por profesional o por día', function () {
    $porProfesional = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/produccion' . $this->filtros)
        ->assertOk()->json('datos.filas'))->keyBy('profesional');

    expect($porProfesional['medrep']['consultas_medicina'])->toBe(3);
    expect($porProfesional['medrep2']['consultas_medicina'])->toBe(1);
    expect($porProfesional['enfrep']['servicios_enfermeria'])->toBe(1);

    $propio = $this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/produccion' . $this->filtros . '&agrupacion=dia')
        ->assertOk()->json('datos.filas');

    expect($propio)->toBe([[
        'fecha' => '2026-09-10', 'consultas_medicina' => 0, 'consultas_odontologia' => 0,
        'primera_vez' => 0, 'procedimientos' => 0, 'servicios_enfermeria' => 1, 'triajes' => 0,
        'signos_sso' => 0, 'certificados' => 0, 'dias_reposo' => 0, 'recetas' => 0, 'total' => 1,
    ]]);
});

test('el excel sale con su encabezado y sus filas', function () {
    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/dispensario/reportes/morbilidad/excel' . $this->filtros)
        ->assertOk();

    expect($respuesta->headers->get('content-disposition'))->toContain('morbilidad_20260901_20260930.xlsx');

    $hoja = PhpOffice\PhpSpreadsheet\IOFactory::load($respuesta->getFile()->getPathname())->getActiveSheet();
    expect($hoja->getCell('A2')->getValue())->toBe('Morbilidad');
    expect($hoja->getCell('A3')->getValue())->toContain('01/09/2026 al 30/09/2026');
    expect($hoja->getCell('A6')->getValue())->toBe('CIE-10');
    expect($hoja->getCell('A7')->getValue())->toBe('J00X');
});

test('el período se valida', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/morbilidad?desde=2025-01-01&hasta=2026-09-30')
        ->assertUnprocessable()->assertJsonPath('errores.hasta.0', 'El período no puede pasar de un año.');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/morbilidad?desde=2026-09-30&hasta=2026-09-01')
        ->assertUnprocessable();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/no-existe' . $this->filtros)->assertNotFound();
});

test('el catálogo trae las opciones de los filtros solo a quien ve todo', function () {
    $opciones = $this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->json('datos.opciones');

    expect(collect($opciones['profesionales'])->pluck('nombre')->all())
        ->toContain('medrep', 'medrep2', 'enfrep')->not->toContain('admrep');
    expect(collect($opciones['unidades'])->pluck('nombre')->all())->toContain('Dirección de Obras');

    $propias = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->json('datos.opciones');
    expect($propias)->toBe(['profesionales' => [], 'unidades' => []]);
});
