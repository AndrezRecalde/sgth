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

/*
 * Ausentismo por enfermedad, movimiento de medicamentos, existencias y
 * Enfermería: la segunda tanda de reportes del Dispensario.
 */

function usuarioSegundaTanda(string $usuario, string $rol): User
{
    $user = User::forceCreate([
        'email' => "{$usuario}@example.com", 'usuario_ti' => $usuario,
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $user;
}

function servidorSegundaTanda(string $cedula, string $apellido, int $unidad): Servidor
{
    return Servidor::forceCreate([
        'cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => $apellido,
        'puesto_id' => puestoDePrueba(App\Models\Estructura\UnidadAdministrativa::find($unidad), "Puesto {$apellido}")->id,
        'unidad_administrativa_id' => $unidad,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), 'estado' => true,
    ]);
}

/** Un certificado con su consulta y su historia. */
function certificadoSegundaTanda(array $historia, int $medico, int $dias, string $inicio, ?int $cie10, bool $anulado = false): void
{
    // Una historia por paciente: la cédula es única.
    $hc = DB::table('historias_clinicas')->where('cedula_paciente', $historia['cedula_paciente'])->value('id')
        ?? DB::table('historias_clinicas')->insertGetId([...$historia, 'estado' => true]);
    $consulta = DB::table('consultas_medicas')->insertGetId([
        'historia_clinica_id' => $hc, 'medico_id' => $medico, 'fecha_consulta' => $inicio,
        'hora_consulta' => '09:00', 'especialidad' => 'medicina_general',
    ]);
    DB::table('certificados_medicos')->insert([
        'consulta_medica_id' => $consulta, 'emitido_por' => $medico, 'dias_reposo' => $dias,
        'fecha_inicio' => $inicio, 'fecha_fin' => $inicio, 'diagnostico_cie10_id' => $cie10,
        'tipo_paciente' => $historia['tipo_paciente'], 'anulado_en' => $anulado ? now() : null,
    ]);
}

beforeEach(function () {
    $this->admin     = usuarioSegundaTanda('admin2', 'admin-dispensario');
    $this->autoridad = usuarioSegundaTanda('aut2', 'maxima-autoridad');
    $this->medico    = usuarioSegundaTanda('med2', 'medico');
    $this->enfermera = usuarioSegundaTanda('enf2', 'enfermera');
    $this->otraEnf   = usuarioSegundaTanda('enf3', 'enfermera');

    $obras  = unidadDePrueba(['nombre' => 'Obras Públicas'])->id;
    $salud  = unidadDePrueba(['nombre' => 'Talento Humano'])->id;
    $s1 = $this->s1 = servidorSegundaTanda('0805555551', 'Uno', $obras);
    $s2 = servidorSegundaTanda('0805555552', 'Dos', $obras);
    $s3 = servidorSegundaTanda('0805555553', 'Tres', $salud);
    $hijo = CargaFamiliar::create([
        'servidor_id' => $s1->id, 'cedula' => '0805555554', 'nombres' => 'Hijo', 'apellidos' => 'Uno',
        'parentesco' => TipoParentesco::HIJO, 'fecha_nacimiento' => '2016-01-01', 'estado' => true,
    ]);

    $gripe = DB::table('diagnosticos_cie10')->insertGetId(['codigo' => 'J00X', 'descripcion' => 'RINOFARINGITIS', 'categoria' => 'J00']);
    $lumbago = DB::table('diagnosticos_cie10')->insertGetId(['codigo' => 'M545', 'descripcion' => 'LUMBAGO', 'categoria' => 'M54']);

    $servidor = fn (Servidor $s) => ['tipo_paciente' => 'servidor', 'servidor_id' => $s->id, 'cedula_paciente' => $s->cedula, 'numero_historia' => $s->cedula];
    certificadoSegundaTanda($servidor($s1), $this->medico->id, 3, '2026-09-05', $gripe);
    certificadoSegundaTanda($servidor($s2), $this->medico->id, 5, '2026-09-06', $lumbago);
    certificadoSegundaTanda($servidor($s3), $this->medico->id, 2, '2026-09-07', $gripe);
    // No cuentan: anulado, fuera del período, y el de un familiar.
    certificadoSegundaTanda($servidor($s3), $this->medico->id, 9, '2026-09-08', $gripe, anulado: true);
    certificadoSegundaTanda($servidor($s3), $this->medico->id, 9, '2026-08-01', $gripe);
    certificadoSegundaTanda(['tipo_paciente' => 'familiar', 'carga_familiar_id' => $hijo->id, 'cedula_paciente' => '0805555554', 'numero_historia' => '0805555554'],
        $this->medico->id, 4, '2026-09-09', $gripe);

    $this->filtros = '?desde=2026-09-01&hasta=2026-09-30';
});

test('el ausentismo cuenta solo a los servidores, sin lo anulado, por unidad y por diagnóstico', function () {
    $porUnidad = collect($this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/ausentismo' . $this->filtros)
        ->assertOk()->json('datos.filas'))->keyBy('unidad');

    expect($porUnidad['Obras Públicas'])->toMatchArray(['certificados' => 2, 'servidores' => 2, 'dias_reposo' => 8, 'promedio_dias' => 4]);
    expect($porUnidad['Talento Humano'])->toMatchArray(['certificados' => 1, 'dias_reposo' => 2]);

    $porDiagnostico = collect($this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/ausentismo' . $this->filtros . '&agrupacion=diagnostico')
        ->assertOk()->json('datos.filas'))->keyBy('cie10');

    expect($porDiagnostico['J00X']['dias_reposo'])->toBe(5);
    expect($porDiagnostico['M545']['dias_reposo'])->toBe(5);
});

test('los profesionales no sacan el ausentismo ni la farmacia', function () {
    foreach (['ausentismo', 'farmacia_movimiento', 'farmacia_existencias'] as $clave) {
        $this->actingAs($this->medico, 'sanctum')
            ->getJson("/api/v1/dispensario/reportes/{$clave}" . $this->filtros)->assertForbidden();
    }
});

test('el movimiento de medicamentos resume el kardex del período', function () {
    $med = DB::table('inventario_medicinas')->insertGetId([
        'codigo' => 'MED-T1', 'nombre' => 'Paracetamol', 'principio_activo' => 'Paracetamol',
        'presentacion' => 'tableta', 'concentracion' => '500mg', 'stock_actual' => 75,
    ]);
    $mover = fn (string $tipo, int $cantidad, ?int $receta = null, string $cuando = '2026-09-10 10:00')
        => DB::table('movimientos_inventario_med')->insert([
            'inventario_medicina_id' => $med, 'tipo_movimiento' => $tipo, 'cantidad' => $cantidad,
            'stock_resultante' => 0, 'motivo' => 'prueba', 'registrado_por' => $this->admin->id,
            'referencia_receta_id' => $receta, 'created_at' => $cuando,
        ]);
    // Dos recetas de verdad: el kardex las referencia con clave foránea.
    $consulta = DB::table('consultas_medicas')->value('id');
    [$r1, $r2] = array_map(fn () => DB::table('recetas_medicas')->insertGetId([
        'consulta_medica_id' => $consulta, 'fecha_emision' => '2026-09-10',
    ]), [1, 2]);
    $mover('ingreso', 100);
    $mover('egreso', -10, $r1);
    $mover('egreso', -5, $r2);
    $mover('baja', -8);
    $mover('ajuste', -2);
    $mover('ingreso', 999, null, '2026-08-01 10:00');

    $fila = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/farmacia_movimiento' . $this->filtros)
        ->assertOk()->json('datos.filas.0');

    expect($fila)->toMatchArray([
        'medicamento' => 'Paracetamol 500mg', 'ingresos' => 100, 'despachado' => 15,
        'recetas' => 2, 'bajas' => 8, 'ajustes' => -2, 'stock_actual' => 75,
    ]);
});

test('las existencias van por lote, con su estado de caducidad y su valor', function () {
    $med = DB::table('inventario_medicinas')->insertGetId([
        'codigo' => 'MED-T2', 'nombre' => 'Loratadina', 'principio_activo' => 'Loratadina',
        'presentacion' => 'tableta', 'stock_actual' => 30,
    ]);
    $adq = DB::table('adquisiciones_medicamentos')->insertGetId([
        'tipo' => 'compra', 'numero_documento' => 'F-1', 'proveedor_o_donante' => 'Prov',
        'fecha_adquisicion' => '2026-09-01', 'registrado_por' => $this->admin->id,
    ]);
    $item = DB::table('items_adquisicion')->insertGetId([
        'adquisicion_id' => $adq, 'inventario_medicina_id' => $med, 'cantidad' => 10, 'precio_unitario' => 0.25,
    ]);
    $lote = fn (string $codigo, ?string $caduca, int $stock, ?int $item = null) => DB::table('lotes_medicina')->insert([
        'inventario_medicina_id' => $med, 'codigo_lote' => $codigo, 'fecha_caducidad' => $caduca,
        'cantidad_ingresada' => $stock, 'stock_actual' => $stock, 'item_adquisicion_id' => $item,
    ]);
    $lote('VENCIDO', now()->subDay()->toDateString(), 5);
    $lote('PRONTO', now()->addDays(10)->toDateString(), 10, $item);
    $lote('LEJOS', now()->addYear()->toDateString(), 15);
    $lote('AGOTADO', now()->addYear()->toDateString(), 0);

    $filas = collect($this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/farmacia_existencias' . $this->filtros)
        ->assertOk()->json('datos.filas'))->keyBy('lote');

    expect($filas->keys()->all())->toBe(['VENCIDO', 'PRONTO', 'LEJOS']);
    expect($filas['VENCIDO']['estado'])->toBe('Caducado');
    expect($filas['PRONTO'])->toMatchArray(['estado' => 'Por caducar', 'precio_unitario' => 0.25, 'valor' => 2.5]);
    expect($filas['LEJOS'])->toMatchArray(['estado' => 'Vigente', 'valor' => null]);

    // Es una foto de hoy: el catálogo lo dice para que la pantalla no pida fechas.
    $catalogo = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->json('datos.reportes'))->keyBy('clave');
    expect($catalogo['farmacia_existencias']['periodo'])->toBeFalse();
    expect($catalogo['ausentismo']['agrupaciones'])->toBe([
        ['value' => 'unidad', 'label' => 'Unidad'], ['value' => 'diagnostico', 'label' => 'Diagnóstico'],
    ]);
});

test('enfermería: servicios por tipo, triajes por nivel, y cada enfermera lo suyo', function () {
    $curacion = DB::table('catalogo_servicios_enfermeria')->insertGetId(['nombre' => 'Curación', 'activo' => true]);
    foreach ([[$this->enfermera, null], [$this->enfermera, null], [$this->enfermera, now()], [$this->otraEnf, null]] as [$quien, $anulado]) {
        DB::table('atenciones_enfermeria')->insert([
            'folio' => 'ENF-' . uniqid(), 'enfermera_id' => $quien->id, 'catalogo_servicio_id' => $curacion,
            'atendido_en' => '2026-09-10 10:00', 'anulado_en' => $anulado,
        ]);
    }
    $agenda = DB::table('agendas_medicas')->insertGetId(['medico_id' => $this->medico->id, 'servidor_id' => $this->s1->id, 'fecha' => '2026-09-10']);
    $hc = DB::table('historias_clinicas')->where('servidor_id', $this->s1->id)->value('id');
    foreach (['critico', 'normal', 'normal'] as $nivel) {
        DB::table('triajes')->insert([
            'agenda_medica_id' => $agenda, 'historia_clinica_id' => $hc, 'enfermera_id' => $this->enfermera->id,
            'nivel_alerta' => $nivel, 'registrado_en' => '2026-09-10 09:00',
        ]);
    }

    $propio = $this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/enfermeria' . $this->filtros . '&profesional_id=' . $this->otraEnf->id)
        ->assertOk()->json('datos.filas');

    expect($propio)->toBe([
        ['grupo' => 'Servicio de enfermería', 'detalle' => 'Curación', 'cantidad' => 2],
        ['grupo' => 'Triaje', 'detalle' => 'Normal', 'cantidad' => 2],
        ['grupo' => 'Triaje', 'detalle' => 'Crítico', 'cantidad' => 1],
    ]);

    $todos = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/enfermeria' . $this->filtros)->json('datos.filas'));
    expect($todos->first()['cantidad'])->toBe(3);
});

test('el excel de existencias dice la fecha de la foto, no un período', function () {
    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/dispensario/reportes/farmacia_existencias/excel' . $this->filtros)
        ->assertOk();

    expect($respuesta->headers->get('content-disposition'))
        ->toContain('farmacia_existencias_' . now()->format('Ymd') . '.xlsx');

    $hoja = PhpOffice\PhpSpreadsheet\IOFactory::load($respuesta->getFile()->getPathname())->getActiveSheet();
    expect($hoja->getCell('A3')->getValue())->toStartWith('Existencias al ' . now()->format('d/m/Y'));
});
