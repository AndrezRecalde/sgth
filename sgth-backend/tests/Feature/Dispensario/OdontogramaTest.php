<?php

use App\Enums\CondicionPiezaDental;
use App\Enums\DenticionTipo;
use App\Enums\ProcedimientoOdontologico;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\Odontograma;
use App\Models\Dispensario\OdontogramaPieza;
use App\Models\Dispensario\OdontogramaProcedimiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\OdontogramaService;
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

    $this->odontologo = User::create([
        'email'        => 'muela@example.com',
        'usuario_ti'   => 'muela',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->otroOdontologo = User::create([
        'email'        => 'colega@example.com',
        'usuario_ti'   => 'colega',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $rol = Role::firstOrCreate(
        ['name' => 'odontologo', 'guard_name' => 'sanctum']
    );
    $this->odontologo->assignRole($rol);
    $this->otroOdontologo->assignRole($rol);

    $this->unidad = unidadDePrueba(['nombre' => 'Direccion Odonto']);
    $this->puesto = puestoDePrueba($this->unidad, 'Analista Odonto');

    $this->paciente = Servidor::create([
        'cedula'                    => '0801234599',
        'nombre'                    => 'Ana',
        'apellido'                  => 'Mora',
        'puesto_id'                 => $this->puesto->id,
        'unidad_administrativa_id'  => $this->unidad->id,
        'regimen_laboral'           => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3),
        'estado'                    => true,
    ]);

    $this->historia = HistoriaClinica::create([
        'numero_historia' => $this->paciente->cedula,
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);
});

/** La historia clínica de un familiar de la edad que se indique. */
function historiaDeFamiliar(int $servidorId, int $edad, string $cedula): HistoriaClinica
{
    $familiar = CargaFamiliar::create([
        'servidor_id'      => $servidorId,
        'cedula'           => $cedula,
        'nombres'          => 'Luis',
        'apellidos'        => 'Mora',
        'parentesco'       => 'hijo',
        'fecha_nacimiento' => now()->subYears($edad),
        'estado'           => true,
    ]);

    return HistoriaClinica::create([
        'numero_historia'   => $cedula,
        'cedula_paciente'   => $cedula,
        'tipo_paciente'     => 'familiar',
        'carga_familiar_id' => $familiar->id,
        'estado'            => true,
    ]);
}

/** Deja registrado un procedimiento sobre la pieza indicada. */
function procedimientoSobre(
    OdontogramaPieza $pieza,
    ProcedimientoOdontologico $cual,
    User $quien,
    array $extra = []
): OdontogramaProcedimiento {
    return app(OdontogramaService::class)->registrarProcedimiento(array_merge([
        'odontograma_pieza_id' => $pieza->id,
        'procedimiento'        => $cual->value,
    ], $extra), $quien->id);
}

// ---------------------------------------------------------------------------
// Sembrado del odontograma
// ---------------------------------------------------------------------------

test('abrir_el_odontograma_siembra_las_32_piezas_permanentes', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);

    expect($odontograma->piezas)->toHaveCount(32);

    // La nomenclatura FDI: cuadrante y número de pieza, del 11 al 48.
    expect($odontograma->piezas->pluck('numero_pieza')->all())
        ->toBe([
            11, 12, 13, 14, 15, 16, 17, 18,
            21, 22, 23, 24, 25, 26, 27, 28,
            31, 32, 33, 34, 35, 36, 37, 38,
            41, 42, 43, 44, 45, 46, 47, 48,
        ]);

    expect($odontograma->piezas->every(
        fn ($p) => $p->condicion === CondicionPiezaDental::SANO
            && $p->denticion === DenticionTipo::PERMANENTE
    ))->toBeTrue();
});

test('el_odontograma_de_un_menor_incluye_la_denticion_temporal', function () {
    $historia = historiaDeFamiliar($this->paciente->id, 8, '0899000011');

    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($historia->id, $this->odontologo->id);

    // 32 permanentes que aún han de salir + las 20 de leche que hoy tiene.
    expect($odontograma->piezas)->toHaveCount(52);

    $temporales = $odontograma->piezas
        ->where('denticion', DenticionTipo::TEMPORAL);

    expect($temporales)->toHaveCount(20);
    expect($temporales->pluck('numero_pieza')->sort()->values()->all())
        ->toBe([
            51, 52, 53, 54, 55,
            61, 62, 63, 64, 65,
            71, 72, 73, 74, 75,
            81, 82, 83, 84, 85,
        ]);
});

test('un_familiar_adulto_no_recibe_denticion_temporal', function () {
    $historia = historiaDeFamiliar($this->paciente->id, 30, '0899000022');

    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($historia->id, $this->odontologo->id);

    expect($odontograma->piezas)->toHaveCount(32);
});

test('abrir_el_odontograma_dos_veces_no_vuelve_a_sembrar', function () {
    $servicio = app(OdontogramaService::class);

    $primero = $servicio->obtenerPorHistoriaClinica(
        $this->historia->id, $this->odontologo->id
    );
    $segundo = $servicio->obtenerPorHistoriaClinica(
        $this->historia->id, $this->odontologo->id
    );

    expect($segundo->id)->toBe($primero->id);
    expect(Odontograma::count())->toBe(1);
    expect(OdontogramaPieza::count())->toBe(32);
});

test('el_odontograma_conserva_lo_registrado_al_volver_a_abrirlo', function () {
    $servicio = app(OdontogramaService::class);

    $odontograma = $servicio->obtenerPorHistoriaClinica(
        $this->historia->id, $this->odontologo->id
    );
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 16);

    procedimientoSobre($pieza, ProcedimientoOdontologico::RESINA, $this->odontologo);

    $recargado = $servicio->obtenerPorHistoriaClinica(
        $this->historia->id, $this->odontologo->id
    );

    expect($recargado->piezas->firstWhere('numero_pieza', 16)->condicion)
        ->toBe(CondicionPiezaDental::OBTURADO);
    expect($recargado->piezas->firstWhere('numero_pieza', 16)->procedimientos)
        ->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// El procedimiento manda sobre la condición de la pieza
// ---------------------------------------------------------------------------

test('el_procedimiento_deja_la_pieza_en_la_condicion_que_le_corresponde',
    function (string $procedimiento, CondicionPiezaDental $esperada) {
        $odontograma = app(OdontogramaService::class)
            ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
        $pieza = $odontograma->piezas->firstWhere('numero_pieza', 21);

        procedimientoSobre(
            $pieza,
            ProcedimientoOdontologico::from($procedimiento),
            $this->odontologo
        );

        expect($pieza->fresh()->condicion)->toBe($esperada);
    }
)->with([
    // Este mapa es la regla clínica del módulo: lo que el odontólogo hace
    // decide cómo queda dibujada la pieza. Cubrirlo entero cuesta poco y evita
    // que un caso nuevo del enum se cuele sin condición pensada.
    'extracción deja ausente'   => ['extraccion',           CondicionPiezaDental::AUSENTE],
    'exodoncia deja ausente'    => ['exodoncia_quirurgica', CondicionPiezaDental::AUSENTE],
    'muda natural deja ausente' => ['muda_natural',         CondicionPiezaDental::AUSENTE],
    'resina obtura'             => ['resina',               CondicionPiezaDental::OBTURADO],
    'amalgama obtura'           => ['amalgama',             CondicionPiezaDental::OBTURADO],
    'endodoncia'                => ['endodoncia',           CondicionPiezaDental::ENDODONCIA],
    'pulpotomía'                => ['pulpotomia',           CondicionPiezaDental::ENDODONCIA],
    'recubrimiento pulpar'      => ['recubrimiento_pulpar', CondicionPiezaDental::ENDODONCIA],
    'corona'                    => ['corona',               CondicionPiezaDental::CORONA],
    'prótesis parcial'          => ['protesis_parcial',     CondicionPiezaDental::PROTESIS],
    'prótesis total'            => ['protesis_total',       CondicionPiezaDental::PROTESIS],
    'sellante'                  => ['sellante',             CondicionPiezaDental::SELLANTE],
    'profilaxis'                => ['profilaxis',           CondicionPiezaDental::EN_TRATAMIENTO],
    'examen inicial'            => ['examen_inicial',       CondicionPiezaDental::EN_TRATAMIENTO],
]);

test('el_procedimiento_queda_a_nombre_de_quien_atiende', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 36);

    $procedimiento = procedimientoSobre(
        $pieza,
        ProcedimientoOdontologico::AMALGAMA,
        $this->odontologo,
        ['superficie' => 'oclusal', 'observaciones' => 'Caries profunda']
    );

    expect($procedimiento->realizado_por)->toBe($this->odontologo->id);
    expect($procedimiento->created_by)->toBe($this->odontologo->id);
    expect($procedimiento->superficie)->toBe('oclusal');
    expect($procedimiento->fecha->toDateString())->toBe(now()->toDateString());
    // La observación clínica va cifrada en reposo, como el resto de la HCE.
    expect($procedimiento->observaciones)->toBe('Caries profunda');
    expect(
        DB::table('odontograma_procedimientos')
            ->where('id', $procedimiento->id)->value('observaciones')
    )->not->toBe('Caries profunda');
});

// ---------------------------------------------------------------------------
// Anulación: el historial no se reescribe, se corrige
// ---------------------------------------------------------------------------

test('anular_el_unico_procedimiento_devuelve_la_pieza_a_sano', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 11);

    $procedimiento = procedimientoSobre(
        $pieza, ProcedimientoOdontologico::CORONA, $this->odontologo
    );
    expect($pieza->fresh()->condicion)->toBe(CondicionPiezaDental::CORONA);

    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$procedimiento->id}/anular",
            ['motivo_anulacion' => 'Pieza equivocada']
        )
        ->assertOk();

    expect($pieza->fresh()->condicion)->toBe(CondicionPiezaDental::SANO);

    // La fila se conserva marcada: el procedimiento pasó, y quién lo anuló y
    // por qué es parte de la historia del paciente.
    $procedimiento->refresh();
    expect($procedimiento->anulado_en)->not->toBeNull();
    expect($procedimiento->anulado_por)->toBe($this->odontologo->id);
    expect($procedimiento->motivo_anulacion)->toBe('Pieza equivocada');
});

test('anular_el_ultimo_procedimiento_restaura_la_condicion_del_anterior', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 46);

    procedimientoSobre(
        $pieza, ProcedimientoOdontologico::RESINA, $this->odontologo,
        ['fecha' => now()->subDays(30)->toDateString()]
    );
    $corona = procedimientoSobre(
        $pieza, ProcedimientoOdontologico::CORONA, $this->odontologo
    );

    expect($pieza->fresh()->condicion)->toBe(CondicionPiezaDental::CORONA);

    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$corona->id}/anular",
            ['motivo_anulacion' => 'Se registró en la pieza contigua']
        )
        ->assertOk();

    // No vuelve a «sano»: vuelve a lo que decía el último procedimiento que
    // sigue vigente. La resina de hace un mes sigue ahí.
    expect($pieza->fresh()->condicion)->toBe(CondicionPiezaDental::OBTURADO);
});

test('un_procedimiento_anulado_no_se_anula_dos_veces', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 12);

    $procedimiento = procedimientoSobre(
        $pieza, ProcedimientoOdontologico::SELLANTE, $this->odontologo
    );

    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$procedimiento->id}/anular",
            ['motivo_anulacion' => 'Error de registro']
        )
        ->assertOk();

    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$procedimiento->id}/anular",
            ['motivo_anulacion' => 'Otra vez']
        )
        ->assertStatus(422);

    // El motivo de la primera anulación sigue siendo el bueno.
    expect($procedimiento->fresh()->motivo_anulacion)->toBe('Error de registro');
});

test('solo_quien_registro_el_procedimiento_puede_anularlo', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 13);

    $procedimiento = procedimientoSobre(
        $pieza, ProcedimientoOdontologico::EXTRACCION, $this->odontologo
    );

    $this->actingAs($this->otroOdontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$procedimiento->id}/anular",
            ['motivo_anulacion' => 'No me consta']
        )
        ->assertStatus(403);

    expect($procedimiento->fresh()->anulado_en)->toBeNull();
    expect($pieza->fresh()->condicion)->toBe(CondicionPiezaDental::AUSENTE);
});

test('no_se_anula_un_procedimiento_de_otra_consulta', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 14);

    $consultaVieja = ConsultaMedica::create([
        'historia_clinica_id' => $this->historia->id,
        'medico_id'           => $this->odontologo->id,
        'fecha_consulta'      => now()->subMonth(),
        'hora_consulta'       => '09:00:00',
        'motivo_consulta'     => 'Control',
    ]);
    $consultaDeHoy = ConsultaMedica::create([
        'historia_clinica_id' => $this->historia->id,
        'medico_id'           => $this->odontologo->id,
        'fecha_consulta'      => now(),
        'hora_consulta'       => '10:00:00',
        'motivo_consulta'     => 'Dolor',
    ]);

    $procedimiento = procedimientoSobre(
        $pieza, ProcedimientoOdontologico::AMALGAMA, $this->odontologo,
        ['consulta_medica_id' => $consultaVieja->id]
    );

    // Corregir lo de hoy, sí; reescribir la visita del mes pasado, no.
    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$procedimiento->id}/anular",
            [
                'motivo_anulacion'   => 'Me equivoqué de pieza',
                'consulta_medica_id' => $consultaDeHoy->id,
            ]
        )
        ->assertStatus(422);

    expect($procedimiento->fresh()->anulado_en)->toBeNull();

    // Desde su propia consulta sí se puede.
    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$procedimiento->id}/anular",
            [
                'motivo_anulacion'   => 'Me equivoqué de pieza',
                'consulta_medica_id' => $consultaVieja->id,
            ]
        )
        ->assertOk();
});

test('sin_consulta_asociada_solo_se_anula_lo_registrado_hoy', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 15);

    $procedimiento = procedimientoSobre(
        $pieza, ProcedimientoOdontologico::PROFILAXIS, $this->odontologo
    );
    $procedimiento->forceFill([
        'created_at' => now()->subDays(2),
    ])->saveQuietly();

    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$procedimiento->id}/anular",
            ['motivo_anulacion' => 'Error de digitación']
        )
        ->assertStatus(422);

    expect($procedimiento->fresh()->anulado_en)->toBeNull();
});

test('anular_exige_un_motivo', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 17);

    $procedimiento = procedimientoSobre(
        $pieza, ProcedimientoOdontologico::RESINA, $this->odontologo
    );

    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$procedimiento->id}/anular",
            []
        )
        ->assertStatus(422)
        // La API envuelve los errores en «errores», no en el «errors» de Laravel.
        ->assertJsonStructure(['errores' => ['motivo_anulacion']]);
});

// ---------------------------------------------------------------------------
// Historial de la pieza
// ---------------------------------------------------------------------------

test('el_historial_de_la_pieza_conserva_los_procedimientos_anulados', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 26);

    procedimientoSobre(
        $pieza, ProcedimientoOdontologico::RESINA, $this->odontologo,
        ['fecha' => now()->subDays(60)->toDateString()]
    );
    $anulado = procedimientoSobre(
        $pieza, ProcedimientoOdontologico::ENDODONCIA, $this->odontologo
    );

    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/odontograma/procedimientos/{$anulado->id}/anular",
            ['motivo_anulacion' => 'Se registró de más']
        )
        ->assertOk();

    $respuesta = $this->actingAs($this->odontologo, 'sanctum')
        ->getJson("/api/v1/dispensario/odontograma/piezas/{$pieza->id}/historial")
        ->assertOk();

    // Los dos siguen ahí: el vigente y el anulado con su motivo. El historial
    // clínico no se acorta, se anota.
    expect($respuesta->json('datos'))->toHaveCount(2);

    $anuladoEnRespuesta = collect($respuesta->json('datos'))
        ->firstWhere('id', $anulado->id);
    expect($anuladoEnRespuesta['motivo_anulacion'])->toBe('Se registró de más');
    expect($anuladoEnRespuesta['anulado_en'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------------

test('el_odontograma_no_se_abre_sin_rol_del_dispensario', function () {
    $ajeno = User::create([
        'email'        => 'ajeno@example.com',
        'usuario_ti'   => 'ajeno',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->actingAs($ajeno, 'sanctum')
        ->getJson("/api/v1/dispensario/odontograma/historia-clinica/{$this->historia->id}")
        ->assertStatus(403);

    expect(Odontograma::count())->toBe(0);
});

test('abrir_el_odontograma_por_la_api_devuelve_las_piezas_sembradas', function () {
    $respuesta = $this->actingAs($this->odontologo, 'sanctum')
        ->getJson("/api/v1/dispensario/odontograma/historia-clinica/{$this->historia->id}")
        ->assertOk();

    expect($respuesta->json('datos.piezas'))->toHaveCount(32);
    expect($respuesta->json('datos.historia_clinica_id'))
        ->toBe($this->historia->id);
});

// ---------------------------------------------------------------------------
// La pieza se dibuja por fecha clínica, no por orden de escritura
// ---------------------------------------------------------------------------

test('un_procedimiento_atrasado_no_pisa_la_condicion_vigente', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 27);

    // Hoy se pone una corona.
    procedimientoSobre($pieza, ProcedimientoOdontologico::CORONA, $this->odontologo);
    expect($pieza->fresh()->condicion)->toBe(CondicionPiezaDental::CORONA);

    // Después se carga una resina de hace un mes que faltaba por registrar.
    procedimientoSobre(
        $pieza, ProcedimientoOdontologico::RESINA, $this->odontologo,
        ['fecha' => now()->subDays(30)->toDateString()]
    );

    expect($pieza->fresh()->condicion)->toBe(CondicionPiezaDental::CORONA);
});

test('el_procedimiento_no_acepta_la_consulta_de_otro_paciente', function () {
    $odontograma = app(OdontogramaService::class)
        ->obtenerPorHistoriaClinica($this->historia->id, $this->odontologo->id);
    $pieza = $odontograma->piezas->firstWhere('numero_pieza', 37);

    $otraHistoria = historiaDeFamiliar($this->paciente->id, 40, '0899000033');
    $consultaAjena = ConsultaMedica::create([
        'historia_clinica_id' => $otraHistoria->id,
        'medico_id'           => $this->odontologo->id,
        'fecha_consulta'      => now(),
        'hora_consulta'       => '11:00:00',
        'motivo_consulta'     => 'Otro paciente',
    ]);

    $this->actingAs($this->odontologo, 'sanctum')
        ->postJson('/api/v1/dispensario/odontograma/procedimientos', [
            'odontograma_pieza_id' => $pieza->id,
            'consulta_medica_id'   => $consultaAjena->id,
            'procedimiento'        => 'resina',
        ])
        ->assertStatus(422);
});
