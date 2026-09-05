<?php

use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\MovimientoInventarioMed;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Enums\RegimenLaboral;
use App\Services\Dispensario\AgendaService;
use App\Services\Dispensario\HistoriaClinicaService;
use App\Services\Dispensario\InventarioMedicinasService;
use App\Services\Dispensario\RecetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    AgendaMedica::unguard();
    ConsultaMedica::unguard();
    HistoriaClinica::unguard();
    InventarioMedicina::unguard();
    ItemReceta::unguard();
    MovimientoInventarioMed::unguard();
    RecetaMedica::unguard();

    $this->medico = User::create([
        'email' => 'house@example.com',
        'usuario_ti' => 'house',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->pacienteUser = User::create([
        'email' => 'juan@example.com',
        'usuario_ti' => 'juanp',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = unidadDePrueba(['nombre' => 'Direccion Test']);
    $this->puesto = puestoDePrueba($this->unidad, 'Analista Test');

    $this->paciente = Servidor::create([
        'cedula' => '0801234561',
        'nombre' => 'Juan',
        'apellido' => 'Perez',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3),
        'estado' => true,
    ]);

    // La FK va de users a servidores: servidores.user_id ya no existe.
    $this->pacienteUser->update(['servidor_id' => $this->paciente->id]);
});

test('agendar_cita_deja_el_turno_en_espera', function () {
    $service = app(AgendaService::class);

    $cita = $service->agendarCita([
        'servidor_id'      => $this->paciente->id,
        'medico_id'        => $this->medico->id,
        'fecha'            => now()->addDays(2)->format('Y-m-d'),
        'hora_inicio'      => '10:00:00',
        'hora_fin'         => '10:30:00',
        'motivo_solicitud' => 'Chequeo general',
    ], $this->medico->id);

    expect($cita)->toBeInstanceOf(AgendaMedica::class);
    expect($cita->estado)->toBe('en_espera');

    // Agendar ya no crea el permiso de ausencia. Hoy lo emite
    // CertificadoMedicoService cuando el médico certifica la atención, que es
    // mejor regla: una cita agendada no prueba que el servidor faltó, y así no
    // quedaban permisos abiertos por citas a las que nadie asistió.
    expect(DB::table('permisos_servidor')->where('servidor_id', $this->paciente->id)->exists())
        ->toBeFalse();
});

test('registrar_consulta_actualiza_estado_de_agenda_a_atendida', function () {
    $historia = HistoriaClinica::create([
        'servidor_id' => $this->paciente->id,
        'grupo_sanguineo' => 'O+',
    ]);

    $agenda = AgendaMedica::create([
        'servidor_id' => $this->paciente->id,
        'medico_id' => $this->medico->id,
        'fecha' => now()->format('Y-m-d'),
        'hora_inicio' => '10:00:00',
        'hora_fin' => '10:30:00',
        'motivo_solicitud' => 'Chequeo',
        'estado' => 'agendada',
    ], $this->medico->id);

    $service = app(HistoriaClinicaService::class);
    
    $consulta = $service->registrarConsulta([
        'historia_clinica_id' => $historia->id,
        'agenda_medica_id' => $agenda->id,
        'medico_id' => $this->medico->id,
        'fecha_consulta' => now()->format('Y-m-d'),
        'hora_consulta' => '10:00:00',
        'motivo_consulta' => 'Chequeo',
        'examen_fisico' => 'Dolor de cabeza',
        'diagnostico_detallado' => 'Migraña',
    ]);

    expect($consulta)->toBeInstanceOf(ConsultaMedica::class);

    $agenda->refresh();
    expect($agenda->estado)->toBe('atendido');
});

test('alta_de_medicina_nace_sin_stock_ni_movimiento_de_kardex', function () {
    $this->actingAs($this->medico, 'sanctum');

    $service = app(InventarioMedicinasService::class);

    // Aunque el llamador insista con un stock inicial, el alta define
    // catálogo, no existencias: el stock solo entra por adquisición.
    $medicina = $service->ingresarMedicina([
        'nombre' => 'Paracetamol',
        'principio_activo' => 'Paracetamol',
        'concentracion' => '500mg',
        'presentacion' => 'tableta',
        'lote' => 'L123',
        'fecha_caducidad' => now()->addYear(),
        'stock_minimo' => 10,
        'stock_actual' => 100,
    ], $this->medico->id);

    expect($medicina)->toBeInstanceOf(InventarioMedicina::class);
    expect($medicina->stock_actual)->toBe(0);
    expect($medicina->codigo)->toBe('MED-0001');

    expect(
        MovimientoInventarioMed::where('inventario_medicina_id', $medicina->id)->count()
    )->toBe(0);
});

test('adquisicion_es_la_puerta_de_entrada_de_stock_y_deja_folio_en_el_kardex', function () {
    $this->actingAs($this->medico, 'sanctum');

    $medicina = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre' => 'Ibuprofeno',
        'principio_activo' => 'Ibuprofeno',
        'concentracion' => '400mg',
        'presentacion' => 'tableta',
        'stock_minimo' => 5,
    ], $this->medico->id);

    $adquisicion = app(App\Services\Dispensario\AdquisicionService::class)->registrar(
        [
            'tipo' => 'compra',
            'numero_documento' => 'FACT-001234',
            'proveedor_o_donante' => 'Farmaenlace S.A.',
            'fecha_adquisicion' => now()->toDateString(),
        ],
        [['inventario_medicina_id' => $medicina->id, 'cantidad' => 250]],
        $this->medico->id
    );

    $medicina->refresh();
    expect($medicina->stock_actual)->toBe(250);

    $movimiento = MovimientoInventarioMed::where(
        'inventario_medicina_id', $medicina->id
    )->first();

    expect($movimiento->tipo_movimiento)->toBe('ingreso');
    expect($movimiento->cantidad)->toBe(250);
    expect($movimiento->stock_resultante)->toBe(250);
    // El motivo es el rastro auditable: folio, documento y proveedor.
    expect($movimiento->motivo)->toContain($adquisicion->folio);
    expect($movimiento->motivo)->toContain('FACT-001234');
    expect($movimiento->motivo)->toContain('Farmaenlace S.A.');
});

test('buscar_oculta_las_agotadas_al_recetar_pero_las_muestra_al_adquirir', function () {
    $service = app(InventarioMedicinasService::class);

    $agotada = $service->ingresarMedicina([
        'nombre' => 'Amoxicilina',
        'principio_activo' => 'Amoxicilina',
        'presentacion' => 'capsula',
        'stock_minimo' => 5,
    ], $this->medico->id);

    expect($service->buscar('Amoxi')->pluck('id'))->not->toContain($agotada->id);
    expect($service->buscar('Amoxi', soloDespachables: false)->pluck('id'))
        ->toContain($agotada->id);
});

test('endpoint_de_busqueda_acepta_el_indicador_de_agotadas_de_la_query', function () {
    // La prueba de servicio no cubre la validación del request, y ahí es donde
    // el indicador se cayó: en la cadena de consulta `true` viaja como texto y
    // la regla `boolean` solo admite 1/0.
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $agotada = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre' => 'Loratadina',
        'principio_activo' => 'Loratadina',
        'presentacion' => 'tableta',
        'stock_minimo' => 5,
    ], $this->medico->id);

    $this->getJson('/api/v1/dispensario/inventario/medicinas/buscar?q=Lorat')
        ->assertOk()
        ->assertJsonMissing(['id' => $agotada->id]);

    $this->getJson('/api/v1/dispensario/inventario/medicinas/buscar?q=Lorat&incluir_agotadas=1')
        ->assertOk()
        ->assertJsonFragment(['id' => $agotada->id]);
});

test('emitir_y_despachar_receta_descuenta_stock_y_registra_kardex', function () {
    $this->actingAs($this->medico, 'sanctum');

    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-002',
        'nombre' => 'Ibuprofeno',
        'principio_activo' => 'Ibuprofeno',
        'concentracion' => '400mg',
        'presentacion' => 'tableta',
        'lote' => 'L124',
        'fecha_caducidad' => now()->addYear(),
        'stock_minimo' => 10,
        'stock_actual' => 50,
    ]);

    $historia = HistoriaClinica::create([
        'servidor_id' => $this->paciente->id,
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id,
        'fecha_consulta' => now()->format('Y-m-d'),
        'hora_consulta' => '10:00:00',
        'motivo_consulta' => 'Dolor',
        'diagnostico_detallado' => 'Inflamación',
    ]);

    $service = app(RecetaService::class);
    
    $resultado = $service->emitirReceta([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->format('Y-m-d'),
        'indicaciones_generales' => 'Tomar con comida',
    ], [
        [
            'inventario_medicina_id' => $medicina->id,
            'dosis' => '1 tableta',
            'frecuencia' => 'Cada 8 horas',
            'duracion' => '3 días',
            'cantidad_prescrita' => 9,
        ]
    ]);

    $receta = $resultado['receta'];
    expect($receta)->toBeInstanceOf(RecetaMedica::class);

    // Despachar la receta para afectar el inventario (nueva lógica Tarea 5)
    $itemReceta = ItemReceta::where('receta_medica_id', $receta->id)->first();
    $service->despacharReceta($receta->id, [
        ['item_receta_id' => $itemReceta->id, 'cantidad' => 9]
    ], $this->medico->id);

    $medicina->refresh();
    expect($medicina->stock_actual)->toBe(41); // 50 - 9

    $movimiento = MovimientoInventarioMed::where('inventario_medicina_id', $medicina->id)
        ->where('tipo_movimiento', 'egreso')
        ->first();
        
    expect($movimiento)->not->toBeNull();
    expect($movimiento->cantidad)->toBe(-9);
    expect($movimiento->stock_resultante)->toBe(41);
    expect($movimiento->referencia_receta_id)->toBe($receta->id);
});

test('emitir_receta_con_stock_insuficiente_incluye_alerta', function () {
    // Crear medicina sin stock
    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-003',
        'nombre' => 'Amoxicilina',
        'principio_activo' => 'Amoxicilina',
        'concentracion' => '500mg',
        'presentacion' => 'capsula',
        'lote' => 'L125',
        'fecha_caducidad' => now()->addYear(),
        'stock_minimo' => 10,
        'stock_actual' => 0,
        'estado' => true,
    ]);

    $historia = HistoriaClinica::create([
        'servidor_id' => $this->paciente->id,
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id,
        'fecha_consulta' => now()->format('Y-m-d'),
        'hora_consulta' => '10:00:00',
        'motivo_consulta' => 'Infeccion',
        'diagnostico_detallado' => 'Infeccion',
    ]);

    $service = app(RecetaService::class);
    $resultado = $service->emitirReceta(
        ['consulta_medica_id' => $consulta->id, 'fecha_emision' => now()->format('Y-m-d'), 'indicaciones_generales' => 'Ninguna'],
        [['inventario_medicina_id' => $medicina->id,
          'cantidad_prescrita' => 5, 'dosis' => '1', 'frecuencia' => '8h', 'duracion' => '5d']]
    );

    // La receta se crea correctamente aunque no haya stock
    expect($resultado['receta'])->toBeInstanceOf(RecetaMedica::class);
    
    // El sistema incluye una alerta informativa
    // (puede estar vacia si no hay alergias registradas)
    expect($resultado)->toHaveKey('alertas_alergias');
    expect($resultado['alertas_alergias'])->toBeArray();
});

test('anular_adquisicion_devuelve_el_stock_con_contrapartida_en_el_kardex', function () {
    $medicina = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre' => 'Omeprazol',
        'principio_activo' => 'Omeprazol',
        'presentacion' => 'capsula',
        'stock_minimo' => 5,
    ], $this->medico->id);

    $servicio = app(App\Services\Dispensario\AdquisicionService::class);

    $adquisicion = $servicio->registrar(
        [
            'tipo' => 'compra',
            'numero_documento' => 'FACT-9001',
            'proveedor_o_donante' => 'Difare S.A.',
            'fecha_adquisicion' => now()->toDateString(),
        ],
        [['inventario_medicina_id' => $medicina->id, 'cantidad' => 80]],
        $this->medico->id
    );

    expect($medicina->refresh()->stock_actual)->toBe(80);

    $anulada = $servicio->anular(
        $adquisicion->id, 'Error de digitación', $this->medico->id
    );

    expect($anulada->anulado_en)->not->toBeNull();
    expect($anulada->motivo_anulacion)->toBe('Error de digitación');
    expect($medicina->refresh()->stock_actual)->toBe(0);

    // El ingreso original sigue en el kardex: la anulación escribe su
    // contrapartida en vez de borrarlo.
    $movimientos = MovimientoInventarioMed::where(
        'inventario_medicina_id', $medicina->id
    )->orderBy('id')->get();

    expect($movimientos)->toHaveCount(2);
    expect($movimientos[0]->tipo_movimiento)->toBe('ingreso');
    expect($movimientos[1]->tipo_movimiento)->toBe('anulacion');
    expect($movimientos[1]->cantidad)->toBe(-80);
    expect($movimientos[1]->stock_resultante)->toBe(0);
    expect($movimientos[1]->motivo)->toContain($adquisicion->folio);
});

test('no_se_anula_una_adquisicion_cuyo_stock_ya_se_consumio', function () {
    $medicina = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre' => 'Ranitidina',
        'principio_activo' => 'Ranitidina',
        'presentacion' => 'tableta',
        'stock_minimo' => 5,
    ], $this->medico->id);

    $servicio = app(App\Services\Dispensario\AdquisicionService::class);

    $adquisicion = $servicio->registrar(
        [
            'tipo' => 'donacion',
            'numero_documento' => 'ACTA-77',
            'proveedor_o_donante' => 'Cruz Roja',
            'fecha_adquisicion' => now()->toDateString(),
        ],
        [['inventario_medicina_id' => $medicina->id, 'cantidad' => 40]],
        $this->medico->id
    );

    // Se consume parte por un ajuste, como si se hubieran despachado.
    app(InventarioMedicinasService::class)->ajustarInventario(
        $medicina->id, 30, 'Conteo físico', $this->medico->id
    );

    expect(fn () => $servicio->anular(
        $adquisicion->id, 'Error de digitación', $this->medico->id
    ))->toThrow(App\Exceptions\ReglaNegocioException::class);

    // Ni el stock ni la adquisición se tocaron.
    expect($medicina->refresh()->stock_actual)->toBe(30);
    expect($adquisicion->refresh()->anulado_en)->toBeNull();
});

test('anular_receta_parcial_la_cierra_sin_devolver_lo_ya_entregado', function () {
    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-7001',
        'nombre' => 'Losartán',
        'principio_activo' => 'Losartán',
        'presentacion' => 'tableta',
        'stock_actual' => 50,
        'stock_minimo' => 5,
        'estado' => true,
    ]);

    $historia = HistoriaClinica::create([
        'servidor_id' => $this->paciente->id,
        'grupo_sanguineo' => 'O+',
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id,
        'fecha_consulta' => now()->format('Y-m-d'),
        'hora_consulta' => '09:00:00',
        'motivo_consulta' => 'Control',
        'diagnostico_detallado' => 'Hipertensión',
    ]);

    $servicio = app(RecetaService::class);

    $receta = $servicio->emitirReceta([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->format('Y-m-d'),
    ], [[
        'inventario_medicina_id' => $medicina->id,
        'cantidad_prescrita' => 20,
        'dosis' => '1 tableta',
        'frecuencia' => 'Cada 12 horas',
        'duracion' => '10 días',
    ]])['receta'];

    $item = ItemReceta::where('receta_medica_id', $receta->id)->first();
    $servicio->despacharReceta($receta->id, [
        ['item_receta_id' => $item->id, 'cantidad' => 8],
    ], $this->medico->id);

    expect($receta->refresh()->estado)->toBe('despachada_parcial');
    expect($medicina->refresh()->stock_actual)->toBe(42);

    $anulada = $servicio->anularReceta(
        $receta->id, 'El paciente no volvió', $this->medico->id
    );

    expect($anulada->estado)->toBe('anulada');
    expect($anulada->motivo_anulacion)->toBe('El paciente no volvió');
    // Lo entregado salió del estante y su egreso sigue vigente.
    expect($medicina->refresh()->stock_actual)->toBe(42);
});

test('no_se_anula_una_receta_ya_despachada_por_completo', function () {
    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-7002',
        'nombre' => 'Metformina',
        'principio_activo' => 'Metformina',
        'presentacion' => 'tableta',
        'stock_actual' => 30,
        'stock_minimo' => 5,
        'estado' => true,
    ]);

    $historia = HistoriaClinica::create([
        'servidor_id' => $this->paciente->id,
        'grupo_sanguineo' => 'A+',
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id,
        'fecha_consulta' => now()->format('Y-m-d'),
        'hora_consulta' => '10:00:00',
        'motivo_consulta' => 'Control',
        'diagnostico_detallado' => 'Diabetes',
    ]);

    $servicio = app(RecetaService::class);

    $receta = $servicio->emitirReceta([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->format('Y-m-d'),
    ], [[
        'inventario_medicina_id' => $medicina->id,
        'cantidad_prescrita' => 10,
        'dosis' => '1 tableta',
        'frecuencia' => 'Diaria',
        'duracion' => '10 días',
    ]])['receta'];

    $item = ItemReceta::where('receta_medica_id', $receta->id)->first();
    $servicio->despacharReceta($receta->id, [
        ['item_receta_id' => $item->id, 'cantidad' => 10],
    ], $this->medico->id);

    expect($receta->refresh()->estado)->toBe('despachada_completa');

    expect(fn () => $servicio->anularReceta(
        $receta->id, 'Error', $this->medico->id
    ))->toThrow(App\Exceptions\ReglaNegocioException::class);
});

test('el_respaldo_de_una_adquisicion_se_guarda_privado_y_se_sirve_tras_la_sesion', function () {
    Illuminate\Support\Facades\Storage::fake('local');

    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $medicina = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre' => 'Enalapril',
        'principio_activo' => 'Enalapril',
        'presentacion' => 'tableta',
        'stock_minimo' => 5,
    ], $this->medico->id);

    $adquisicion = app(App\Services\Dispensario\AdquisicionService::class)->registrar(
        [
            'tipo' => 'compra',
            'numero_documento' => 'FACT-5555',
            'proveedor_o_donante' => 'Difare S.A.',
            'fecha_adquisicion' => now()->toDateString(),
        ],
        [['inventario_medicina_id' => $medicina->id, 'cantidad' => 10]],
        $this->medico->id
    );

    $this->postJson(
        "/api/v1/dispensario/adquisiciones/{$adquisicion->id}/documento",
        ['documento' => Illuminate\Http\UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf')]
    )->assertOk();

    $ruta = $adquisicion->refresh()->documento_respaldo;

    // En disco privado, no en el público servido por URL.
    expect($ruta)->not->toBeNull();
    Illuminate\Support\Facades\Storage::disk('local')->assertExists($ruta);

    // Y se recupera: antes se subía y no había forma de volver a verlo.
    $this->get("/api/v1/dispensario/adquisiciones/{$adquisicion->id}/documento")
        ->assertOk()
        ->assertHeader('Content-Disposition',
            'inline; filename="respaldo-' . $adquisicion->folio . '.pdf"');
});

test('pedir_el_respaldo_de_una_adquisicion_que_no_lo_tiene_responde_404', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $medicina = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre' => 'Captopril',
        'principio_activo' => 'Captopril',
        'presentacion' => 'tableta',
        'stock_minimo' => 5,
    ], $this->medico->id);

    $adquisicion = app(App\Services\Dispensario\AdquisicionService::class)->registrar(
        [
            'tipo' => 'donacion',
            'numero_documento' => 'ACTA-12',
            'proveedor_o_donante' => 'Cruz Roja',
            'fecha_adquisicion' => now()->toDateString(),
        ],
        [['inventario_medicina_id' => $medicina->id, 'cantidad' => 5]],
        $this->medico->id
    );

    $this->getJson("/api/v1/dispensario/adquisiciones/{$adquisicion->id}/documento")
        ->assertNotFound();
});

test('no_se_despacha_un_medicamento_caducado', function () {
    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-8001',
        'nombre' => 'Azitromicina',
        'principio_activo' => 'Azitromicina',
        'presentacion' => 'tableta',
        'stock_actual' => 50,
        'stock_minimo' => 5,
        'fecha_caducidad' => now()->subDay(),
        'estado' => true,
    ]);

    $historia = HistoriaClinica::create([
        'servidor_id' => $this->paciente->id,
        'grupo_sanguineo' => 'O+',
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id,
        'fecha_consulta' => now()->format('Y-m-d'),
        'hora_consulta' => '11:00:00',
        'motivo_consulta' => 'Infección',
        'diagnostico_detallado' => 'Faringitis',
    ]);

    $servicio = app(RecetaService::class);

    $receta = $servicio->emitirReceta([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->format('Y-m-d'),
    ], [[
        'inventario_medicina_id' => $medicina->id,
        'cantidad_prescrita' => 6,
        'dosis' => '1 tableta',
        'frecuencia' => 'Diaria',
        'duracion' => '6 días',
    ]])['receta'];

    $item = ItemReceta::where('receta_medica_id', $receta->id)->first();

    expect(fn () => $servicio->despacharReceta($receta->id, [
        ['item_receta_id' => $item->id, 'cantidad' => 6],
    ], $this->medico->id))->toThrow(App\Exceptions\ReglaNegocioException::class);

    // Ni salió del estante ni quedó rastro de egreso.
    expect($medicina->refresh()->stock_actual)->toBe(50);
    expect(MovimientoInventarioMed::where('inventario_medicina_id', $medicina->id)->count())
        ->toBe(0);
});

test('el_dia_de_la_caducidad_todavia_se_puede_despachar', function () {
    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-8002',
        'nombre' => 'Cefalexina',
        'principio_activo' => 'Cefalexina',
        'presentacion' => 'capsula',
        'stock_actual' => 20,
        'stock_minimo' => 5,
        'fecha_caducidad' => now(),
        'estado' => true,
    ]);

    $historia = HistoriaClinica::create([
        'servidor_id' => $this->paciente->id,
        'grupo_sanguineo' => 'O+',
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id,
        'fecha_consulta' => now()->format('Y-m-d'),
        'hora_consulta' => '12:00:00',
        'motivo_consulta' => 'Infección',
        'diagnostico_detallado' => 'Faringitis',
    ]);

    $servicio = app(RecetaService::class);

    $receta = $servicio->emitirReceta([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->format('Y-m-d'),
    ], [[
        'inventario_medicina_id' => $medicina->id,
        'cantidad_prescrita' => 4,
        'dosis' => '1 cápsula',
        'frecuencia' => 'Diaria',
        'duracion' => '4 días',
    ]])['receta'];

    $item = ItemReceta::where('receta_medica_id', $receta->id)->first();
    $servicio->despacharReceta($receta->id, [
        ['item_receta_id' => $item->id, 'cantidad' => 4],
    ], $this->medico->id);

    expect($medicina->refresh()->stock_actual)->toBe(16);
});

test('dar_de_baja_saca_las_existencias_caducadas_con_su_motivo', function () {
    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-8003',
        'nombre' => 'Dexametasona',
        'principio_activo' => 'Dexametasona',
        'presentacion' => 'inyectable',
        'stock_actual' => 30,
        'stock_minimo' => 5,
        'fecha_caducidad' => now()->subMonth(),
        'estado' => true,
    ]);

    $servicio = app(InventarioMedicinasService::class);

    $servicio->registrarBaja(
        $medicina->id, 30, 'Caducidad — lote vencido', $this->medico->id
    );

    expect($medicina->refresh()->stock_actual)->toBe(0);

    $movimiento = MovimientoInventarioMed::where(
        'inventario_medicina_id', $medicina->id
    )->latest('id')->first();

    expect($movimiento->tipo_movimiento)->toBe('baja');
    expect($movimiento->cantidad)->toBe(-30);
    expect($movimiento->stock_resultante)->toBe(0);
    expect($movimiento->motivo)->toContain('Caducidad');

    // Y no se puede dar de baja más de lo que hay.
    expect(fn () => $servicio->registrarBaja(
        $medicina->id, 1, 'Merma', $this->medico->id
    ))->toThrow(App\Exceptions\ReglaNegocioException::class);
});

test('el_kardex_rechaza_un_tipo_de_movimiento_desconocido', function () {
    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-8004',
        'nombre' => 'Salbutamol',
        'principio_activo' => 'Salbutamol',
        'presentacion' => 'spray',
        'stock_actual' => 10,
        'stock_minimo' => 2,
        'estado' => true,
    ]);

    expect(fn () => MovimientoInventarioMed::create([
        'inventario_medicina_id' => $medicina->id,
        'tipo_movimiento'        => 'inventado',
        'cantidad'               => 1,
        'stock_resultante'       => 11,
        'motivo'                 => 'Tipo que no existe',
        'registrado_por'         => $this->medico->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('recetar_no_ofrece_lo_caducado_pero_adquirir_si', function () {
    $servicio = app(InventarioMedicinasService::class);

    $caducada = InventarioMedicina::create([
        'codigo' => 'MED-8005',
        'nombre' => 'Nitrofurantoina',
        'principio_activo' => 'Nitrofurantoina',
        'presentacion' => 'capsula',
        'stock_actual' => 40,
        'stock_minimo' => 5,
        'fecha_caducidad' => now()->subWeek(),
        'estado' => true,
    ]);

    // Tiene stock de sobra, pero el despacho la rechazaría.
    expect($servicio->buscar('Nitro')->pluck('id'))->not->toContain($caducada->id);
    expect($servicio->buscar('Nitro', soloDespachables: false)->pluck('id'))
        ->toContain($caducada->id);
});

test('el_folio_no_se_repite_aunque_se_borre_una_adquisicion', function () {
    $medicina = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre' => 'Clonazepam',
        'principio_activo' => 'Clonazepam',
        'presentacion' => 'tableta',
        'stock_minimo' => 5,
    ], $this->medico->id);

    $servicio = app(App\Services\Dispensario\AdquisicionService::class);

    $registrar = fn (string $doc) => $servicio->registrar(
        [
            'tipo' => 'compra',
            'numero_documento' => $doc,
            'proveedor_o_donante' => 'Difare S.A.',
            'fecha_adquisicion' => now()->toDateString(),
        ],
        [['inventario_medicina_id' => $medicina->id, 'cantidad' => 5]],
        $this->medico->id
    );

    $primera = $registrar('F-1');
    $segunda = $registrar('F-2');

    expect($primera->folio)->toBe('ADQ-' . now()->year . '-00001');
    expect($segunda->folio)->toBe('ADQ-' . now()->year . '-00002');

    // Contar filas hacía retroceder el contador y el folio siguiente chocaba
    // contra el índice único; derivarlo del máximo lo evita.
    $segunda->delete();

    expect($registrar('F-3')->folio)->toBe('ADQ-' . now()->year . '-00003');
});

test('el_codigo_de_medicina_no_se_repite_aunque_haya_otro_formato_o_borradas', function () {
    $servicio = app(InventarioMedicinasService::class);

    $primera = $servicio->ingresarMedicina([
        'nombre' => 'Tramadol',
        'principio_activo' => 'Tramadol',
        'presentacion' => 'inyectable',
        'stock_minimo' => 5,
    ], $this->medico->id);

    expect($primera->codigo)->toBe('MED-0001');

    // Una fila con otro formato hacía que el último id se leyera como cero.
    InventarioMedicina::create([
        'codigo' => 'IMPORTADO-XYZ',
        'nombre' => 'Ketorolaco',
        'principio_activo' => 'Ketorolaco',
        'presentacion' => 'tableta',
        'stock_actual' => 0,
        'stock_minimo' => 5,
        'estado' => true,
    ]);

    $segunda = $servicio->ingresarMedicina([
        'nombre' => 'Diclofenaco',
        'principio_activo' => 'Diclofenaco',
        'presentacion' => 'tableta',
        'stock_minimo' => 5,
    ], $this->medico->id);

    expect($segunda->codigo)->toBe('MED-0002');

    // Y un código ya emitido no se reutiliza aunque su medicina se borre.
    $segunda->delete();

    expect($servicio->ingresarMedicina([
        'nombre' => 'Naproxeno',
        'principio_activo' => 'Naproxeno',
        'presentacion' => 'tableta',
        'stock_minimo' => 5,
    ], $this->medico->id)->codigo)->toBe('MED-0003');
});

test('el_conteo_de_stock_bajo_ignora_las_medicinas_retiradas_del_catalogo', function () {
    $servicio = app(InventarioMedicinasService::class);

    InventarioMedicina::create([
        'codigo' => 'MED-9001',
        'nombre' => 'Activa bajo minimo',
        'principio_activo' => 'X',
        'presentacion' => 'tableta',
        'stock_actual' => 1,
        'stock_minimo' => 10,
        'estado' => true,
    ]);

    InventarioMedicina::create([
        'codigo' => 'MED-9002',
        'nombre' => 'Retirada bajo minimo',
        'principio_activo' => 'Y',
        'presentacion' => 'tableta',
        'stock_actual' => 0,
        'stock_minimo' => 10,
        'estado' => false,
    ]);

    // La insignia del menú contaba las dos; el job de alertas y el tablero
    // solo la activa. Ahora los tres dicen lo mismo.
    expect($servicio->contarStockBajo())->toBe(1);
});

test('el_aviso_de_inventario_agrupa_caducadas_bajo_minimo_y_por_caducar', function () {
    $servicio = app(InventarioMedicinasService::class);

    // Caducada CON existencias: hay que darla de baja.
    InventarioMedicina::create([
        'codigo' => 'MED-9101', 'nombre' => 'Vencida con stock',
        'principio_activo' => 'A', 'presentacion' => 'tableta',
        'stock_actual' => 12, 'stock_minimo' => 2,
        'fecha_caducidad' => now()->subDays(3), 'estado' => true,
    ]);

    // Caducada SIN existencias: no pide ninguna acción.
    InventarioMedicina::create([
        'codigo' => 'MED-9102', 'nombre' => 'Vencida sin stock',
        'principio_activo' => 'B', 'presentacion' => 'tableta',
        'stock_actual' => 0, 'stock_minimo' => 2,
        'fecha_caducidad' => now()->subDays(3), 'estado' => true,
    ]);

    InventarioMedicina::create([
        'codigo' => 'MED-9103', 'nombre' => 'Proxima a caducar',
        'principio_activo' => 'C', 'presentacion' => 'tableta',
        'stock_actual' => 50, 'stock_minimo' => 5,
        'fecha_caducidad' => now()->addDays(15), 'estado' => true,
    ]);

    // Fuera de la ventana de aviso.
    InventarioMedicina::create([
        'codigo' => 'MED-9104', 'nombre' => 'Caduca el proximo anio',
        'principio_activo' => 'D', 'presentacion' => 'tableta',
        'stock_actual' => 50, 'stock_minimo' => 5,
        'fecha_caducidad' => now()->addMonths(10), 'estado' => true,
    ]);

    InventarioMedicina::create([
        'codigo' => 'MED-9105', 'nombre' => 'Bajo minimo',
        'principio_activo' => 'E', 'presentacion' => 'tableta',
        'stock_actual' => 1, 'stock_minimo' => 30, 'estado' => true,
    ]);

    // Retirada del catálogo: no se repone lo que ya no se despacha.
    InventarioMedicina::create([
        'codigo' => 'MED-9106', 'nombre' => 'Retirada bajo minimo',
        'principio_activo' => 'F', 'presentacion' => 'tableta',
        'stock_actual' => 0, 'stock_minimo' => 30, 'estado' => false,
    ]);

    $resumen = $servicio->resumenAlertas();

    // Caducidad va por lote, no por medicina: es el lote el que caduca, y una
    // sola fecha por ficha era justamente el error que arrastraba el módulo.
    expect($resumen['caducadas']->pluck('medicina.nombre')->all())
        ->toBe(['Vencida con stock']);
    expect($resumen['por_caducar']->pluck('medicina.nombre')->all())
        ->toBe(['Proxima a caducar']);
    expect($resumen['bajo_minimo']->pluck('nombre')->all())
        ->toContain('Bajo minimo')
        ->not->toContain('Retirada bajo minimo');
});

test('el_job_de_alertas_envia_el_resumen_a_la_administracion_del_dispensario', function () {
    Illuminate\Support\Facades\Mail::fake();

    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->medico->update(['activo' => true]);

    InventarioMedicina::create([
        'codigo' => 'MED-9201', 'nombre' => 'Bajo minimo',
        'principio_activo' => 'A', 'presentacion' => 'tableta',
        'stock_actual' => 1, 'stock_minimo' => 30, 'estado' => true,
    ]);

    app(App\Jobs\Dispensario\VerificarAlertasInventarioJob::class)
        ->handle(app(InventarioMedicinasService::class));

    Illuminate\Support\Facades\Mail::assertQueued(
        App\Mail\Dispensario\AlertasInventarioMail::class,
        fn ($mail) => $mail->hasTo($this->medico->email)
    );
});

test('sin_nada_que_avisar_el_job_no_envia_correo', function () {
    Illuminate\Support\Facades\Mail::fake();

    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));

    InventarioMedicina::create([
        'codigo' => 'MED-9301', 'nombre' => 'Todo en orden',
        'principio_activo' => 'A', 'presentacion' => 'tableta',
        'stock_actual' => 500, 'stock_minimo' => 10,
        'fecha_caducidad' => now()->addYears(2), 'estado' => true,
    ]);

    app(App\Jobs\Dispensario\VerificarAlertasInventarioJob::class)
        ->handle(app(InventarioMedicinasService::class));

    Illuminate\Support\Facades\Mail::assertNothingQueued();
});

test('la_valoracion_de_signos_vitales_distingue_normal_atencion_y_critico', function () {
    $normales = [
        'presion_sistolica' => 120, 'presion_diastolica' => 75,
        'frecuencia_cardiaca' => 72, 'frecuencia_respiratoria' => 16,
        'temperatura_c' => 36.6, 'saturacion_oxigeno' => 98,
    ];

    $evaluar = fn (array $cambios = [], ?int $edad = 40) =>
        App\Services\Dispensario\ValoracionSignosVitales::evaluar(
            array_merge($normales, $cambios), $edad
        );

    expect($evaluar()['nivel'])->toBe(App\Enums\NivelAlertaTriaje::NORMAL);
    expect($evaluar()['hallazgos'])->toBeEmpty();

    // Fuera de rango pero no crítico.
    expect($evaluar(['temperatura_c' => 37.8])['nivel'])
        ->toBe(App\Enums\NivelAlertaTriaje::ATENCION);

    // Una saturación de 85 es lo que no puede pasar desapercibido.
    $critico = $evaluar(['saturacion_oxigeno' => 85]);
    expect($critico['nivel'])->toBe(App\Enums\NivelAlertaTriaje::CRITICO);
    expect($critico['hallazgos'][0]['constante'])->toBe('saturacion_oxigeno');
    expect($critico['hallazgos'][0]['etiqueta'])->toBe('Saturación de oxígeno');

    // El peor hallazgo manda sobre el resto.
    $mixto = $evaluar(['temperatura_c' => 37.8, 'presion_sistolica' => 200]);
    expect($mixto['nivel'])->toBe(App\Enums\NivelAlertaTriaje::CRITICO);
    expect($mixto['hallazgos'])->toHaveCount(2);

    // Una constante ausente no inventa un hallazgo.
    expect($evaluar(['glucosa' => null])['nivel'])
        ->toBe(App\Enums\NivelAlertaTriaje::NORMAL);
});

test('en_un_menor_no_se_emite_juicio_sobre_los_signos_vitales', function () {
    // Una frecuencia de 120 es normal en un niño y crítica en un adulto:
    // aplicar la tabla de adulto llenaría la cola de falsos críticos.
    $constantes = [
        'presion_sistolica' => 100, 'presion_diastolica' => 65,
        'frecuencia_cardiaca' => 120, 'frecuencia_respiratoria' => 24,
        'temperatura_c' => 36.8, 'saturacion_oxigeno' => 98,
    ];

    $enNino = App\Services\Dispensario\ValoracionSignosVitales::evaluar($constantes, 6);
    expect($enNino['nivel'])->toBe(App\Enums\NivelAlertaTriaje::NO_EVALUADO);
    expect($enNino['hallazgos'])->toBeEmpty();

    $enAdulto = App\Services\Dispensario\ValoracionSignosVitales::evaluar($constantes, 40);
    expect($enAdulto['nivel'])->not->toBe(App\Enums\NivelAlertaTriaje::NO_EVALUADO);

    // Sin fecha de nacimiento se valora como adulto.
    expect(App\Services\Dispensario\ValoracionSignosVitales::evaluar($constantes, null)['nivel'])
        ->toBe($enAdulto['nivel']);
});

test('registrar_un_triaje_critico_guarda_el_nivel_y_lo_expone_en_la_cola', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    HistoriaClinica::create([
        'numero_historia' => 'HC-TRIAJE-1',
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);

    $agenda = AgendaMedica::create([
        'servidor_id'      => $this->paciente->id,
        'medico_id'        => $this->medico->id,
        'fecha'            => now()->format('Y-m-d'),
        'hora_inicio'      => '08:00:00',
        'hora_fin'         => '08:30:00',
        'motivo_solicitud' => 'Malestar',
        'estado'           => 'en_espera',
        'requiere_triaje'  => true,
        'registrado_en'    => now(),
    ], $this->medico->id);

    $this->postJson("/api/v1/dispensario/agenda/{$agenda->id}/triaje", [
        'presion_sistolica' => 200, 'presion_diastolica' => 120,
        'frecuencia_cardiaca' => 95, 'frecuencia_respiratoria' => 18,
        'temperatura_c' => 36.8, 'saturacion_oxigeno' => 88,
        'peso_kg' => 80, 'talla_cm' => 170,
    ])->assertCreated();

    $triaje = App\Models\Dispensario\Triaje::where('agenda_medica_id', $agenda->id)->first();

    expect($triaje->nivel_alerta)->toBe(App\Enums\NivelAlertaTriaje::CRITICO);
    expect(collect($triaje->hallazgos_alerta)->pluck('constante'))
        ->toContain('presion_sistolica', 'saturacion_oxigeno');

    // Tomar el triaje saca el turno de la lista de pendientes y lo pasa a sala.
    expect($agenda->refresh()->estado)->toBe('en_sala');

    // Y la cola lo entrega con su nivel, que es donde se ve la urgencia.
    $cola = $this->getJson('/api/v1/dispensario/agenda?fecha=' . now()->toDateString())
        ->assertOk()
        ->json('datos.data');

    $turno = collect($cola)->firstWhere('id', $agenda->id);
    expect($turno['triaje']['nivel_alerta'])->toBe('critico');
});

test('rehacer_un_triaje_conserva_la_toma_anterior', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    HistoriaClinica::create([
        'numero_historia' => 'HC-REHACER-1',
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);

    $agenda = AgendaMedica::create([
        'servidor_id' => $this->paciente->id, 'medico_id' => $this->medico->id,
        'fecha' => now()->format('Y-m-d'), 'hora_inicio' => '10:00:00',
        'hora_fin' => '10:30:00', 'motivo_solicitud' => 'Control',
        'estado' => 'en_espera', 'requiere_triaje' => true,
        'registrado_en' => now(),
    ], $this->medico->id);

    $tomar = fn (float $temperatura) => $this->postJson(
        "/api/v1/dispensario/agenda/{$agenda->id}/triaje",
        [
            'presion_sistolica' => 120, 'presion_diastolica' => 75,
            'frecuencia_cardiaca' => 70, 'frecuencia_respiratoria' => 16,
            'temperatura_c' => $temperatura, 'saturacion_oxigeno' => 98,
            'peso_kg' => 70, 'talla_cm' => 170,
        ]
    );

    // Primera toma: una fiebre que luego resulta ser un error de digitación.
    $tomar(39.5)->assertCreated();
    // Segunda toma, corrigiendo.
    $tomar(36.5)->assertCreated();

    $tomas = App\Models\Dispensario\Triaje::where('agenda_medica_id', $agenda->id)
        ->orderBy('id')->get();

    // Las dos quedan: antes la segunda pisaba la primera y nadie podía saber
    // que se había registrado una fiebre.
    expect($tomas)->toHaveCount(2);
    expect((float) $tomas[0]->temperatura_c)->toBe(39.5);
    expect((float) $tomas[1]->temperatura_c)->toBe(36.5);
    expect($tomas[0]->nivel_alerta)->toBe(App\Enums\NivelAlertaTriaje::CRITICO);
    expect($tomas[1]->nivel_alerta)->toBe(App\Enums\NivelAlertaTriaje::NORMAL);

    // La vigente es la última: es la que ve el médico y la que marca la cola.
    expect((float) $agenda->fresh()->triaje->temperatura_c)->toBe(36.5);

    // Y el historial las devuelve en orden.
    $historial = $this->getJson("/api/v1/dispensario/agenda/{$agenda->id}/triaje/historial")
        ->assertOk()->json('datos');
    expect($historial)->toHaveCount(2);

    // El turno tampoco reaparece como pendiente por tener varias tomas.
    $pendientes = $this->getJson('/api/v1/dispensario/triaje/pendientes')
        ->assertOk()->json('datos');
    expect(collect($pendientes)->pluck('id'))->not->toContain($agenda->id);
});

test('triaje_de_paciente_sin_historia_clinica_le_abre_una', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    // El turno se crea sin que el paciente tenga historia: antes esto mataba el
    // guardado con «null value in column historia_clinica_id».
    $agenda = AgendaMedica::create([
        'servidor_id' => $this->paciente->id, 'medico_id' => $this->medico->id,
        'fecha' => now()->format('Y-m-d'), 'hora_inicio' => '09:00:00',
        'hora_fin' => '09:30:00', 'motivo_solicitud' => 'Malestar',
        'estado' => 'en_espera', 'requiere_triaje' => true,
        'registrado_en' => now(),
    ], $this->medico->id);

    expect(HistoriaClinica::where('servidor_id', $this->paciente->id)->exists())
        ->toBeFalse();

    $this->postJson("/api/v1/dispensario/agenda/{$agenda->id}/triaje", [
        'presion_sistolica' => 120, 'presion_diastolica' => 75,
        'frecuencia_cardiaca' => 70, 'frecuencia_respiratoria' => 16,
        'temperatura_c' => 36.5, 'saturacion_oxigeno' => 98,
        'peso_kg' => 70, 'talla_cm' => 170,
    ])->assertCreated();

    // Se abrió una, numerada por la cédula y a nombre del servidor.
    $historia = HistoriaClinica::where('servidor_id', $this->paciente->id)
        ->firstOrFail();
    expect($historia->cedula_paciente)->toBe($this->paciente->cedula);
    expect($historia->numero_historia)->toBe($this->paciente->cedula);
    expect($historia->tipo_paciente)->toBe('servidor');

    // Y el triaje quedó colgando de ella, no suelto.
    $triaje = App\Models\Dispensario\Triaje::where(
        'agenda_medica_id', $agenda->id
    )->firstOrFail();
    expect($triaje->historia_clinica_id)->toBe($historia->id);
});

test('abrir_la_historia_en_el_triaje_no_duplica_la_que_ya_existe', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $turno = fn (string $hora) => AgendaMedica::create([
        'servidor_id' => $this->paciente->id, 'medico_id' => $this->medico->id,
        'fecha' => now()->format('Y-m-d'), 'hora_inicio' => $hora,
        'hora_fin' => '23:30:00', 'motivo_solicitud' => 'Control',
        'estado' => 'en_espera', 'requiere_triaje' => true,
        'registrado_en' => now(),
    ], $this->medico->id);

    $tomar = fn (int $agendaId) => $this->postJson(
        "/api/v1/dispensario/agenda/{$agendaId}/triaje",
        [
            'presion_sistolica' => 118, 'presion_diastolica' => 76,
            'frecuencia_cardiaca' => 72, 'frecuencia_respiratoria' => 16,
            'temperatura_c' => 36.6, 'saturacion_oxigeno' => 98,
            'peso_kg' => 70, 'talla_cm' => 170,
        ]
    );

    $tomar($turno('09:00:00')->id)->assertCreated();
    $tomar($turno('11:00:00')->id)->assertCreated();

    // Dos turnos, dos triajes, una sola historia: la segunda vez la encuentra.
    expect(HistoriaClinica::where('servidor_id', $this->paciente->id)->count())
        ->toBe(1);
});

test('el_triaje_engancha_la_historia_que_quedo_del_preocupacional', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    // Así queda una historia abierta en un preocupacional: a nombre de la
    // cédula, sin dueño todavía porque quien se evaluó aún no era servidor.
    $delPreocupacional = HistoriaClinica::create([
        'numero_historia' => $this->paciente->cedula,
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'candidato',
        'estado'          => true,
    ]);

    $agenda = AgendaMedica::create([
        'servidor_id' => $this->paciente->id, 'medico_id' => $this->medico->id,
        'fecha' => now()->format('Y-m-d'), 'hora_inicio' => '09:00:00',
        'hora_fin' => '09:30:00', 'motivo_solicitud' => 'Malestar',
        'estado' => 'en_espera', 'requiere_triaje' => true,
        'registrado_en' => now(),
    ], $this->medico->id);

    $this->postJson("/api/v1/dispensario/agenda/{$agenda->id}/triaje", [
        'presion_sistolica' => 118, 'presion_diastolica' => 76,
        'frecuencia_cardiaca' => 72, 'frecuencia_respiratoria' => 16,
        'temperatura_c' => 36.6, 'saturacion_oxigeno' => 98,
        'peso_kg' => 70, 'talla_cm' => 170,
    ])->assertCreated();

    // No se le abre una segunda —chocaría contra el único por cédula—: se le
    // engancha la que ya tenía, que es además donde está su historial.
    expect(HistoriaClinica::where(
        'cedula_paciente', $this->paciente->cedula
    )->count())->toBe(1);

    $delPreocupacional->refresh();
    expect($delPreocupacional->servidor_id)->toBe($this->paciente->id);
    expect($delPreocupacional->tipo_paciente)->toBe('servidor');
});

test('un_familiar_tambien_puede_tener_historia_clinica', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $hija = App\Models\Expediente\CargaFamiliar::create([
        'servidor_id'      => $this->paciente->id,
        'cedula'           => '0801234562',
        'nombres'          => 'Ana',
        'apellidos'        => 'Perez',
        'parentesco'       => App\Enums\TipoParentesco::HIJO,
        'fecha_nacimiento' => now()->subYears(30),
        'estado'           => true,
    ]);

    // El campo se llama carga_familiar_id. El frontend mandaba
    // `beneficiario_id`, que no existe en la tabla desde que se renombró: la
    // petición moría en validación y un familiar no podía tener historia.
    $historia = $this->postJson('/api/v1/dispensario/historias-clinicas', [
        'carga_familiar_id' => $hija->id,
    ])->assertCreated()->json('datos');

    expect($historia['carga_familiar_id'])->toBe($hija->id);

    $agenda = AgendaMedica::create([
        'carga_familiar_id' => $hija->id, 'medico_id' => $this->medico->id,
        'fecha' => now()->format('Y-m-d'), 'hora_inicio' => '09:00:00',
        'hora_fin' => '09:30:00', 'motivo_solicitud' => 'Fiebre',
        'estado' => 'en_espera', 'requiere_triaje' => true,
        'registrado_en' => now(),
    ], $this->medico->id);

    $this->postJson("/api/v1/dispensario/agenda/{$agenda->id}/triaje", [
        'presion_sistolica' => 118, 'presion_diastolica' => 76,
        'frecuencia_cardiaca' => 72, 'frecuencia_respiratoria' => 16,
        'temperatura_c' => 36.6, 'saturacion_oxigeno' => 98,
        'peso_kg' => 60, 'talla_cm' => 160,
    ])->assertCreated();

    $triaje = App\Models\Dispensario\Triaje::where(
        'agenda_medica_id', $agenda->id
    )->firstOrFail();
    expect($triaje->historia_clinica_id)->toBe($historia['id']);
});

test('crear_la_historia_de_un_familiar_no_confunde_la_de_un_candidato', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    // Una historia de preocupacional: sin servidor, porque quien se evaluó
    // todavía no lo era. No tiene nada que ver con el familiar de abajo.
    HistoriaClinica::create([
        'numero_historia' => '0899999999',
        'cedula_paciente' => '0899999999',
        'tipo_paciente'   => 'candidato',
        'estado'          => true,
    ]);

    $hija = App\Models\Expediente\CargaFamiliar::create([
        'servidor_id'      => $this->paciente->id,
        'cedula'           => '0801234563',
        'nombres'          => 'Camila',
        'apellidos'        => 'Perez',
        'parentesco'       => App\Enums\TipoParentesco::HIJO,
        'fecha_nacimiento' => now()->subYears(12),
        'estado'           => true,
    ]);

    // El guardia de duplicados comparaba contra servidor_id y carga_familiar_id
    // a la vez, y `where(col, null)` en Eloquent se vuelve `col IS NULL`: la
    // historia del candidato hacía saltar «ya cuenta con una historia clínica»
    // a un familiar que no tenía ninguna.
    $historia = $this->postJson('/api/v1/dispensario/historias-clinicas', [
        'carga_familiar_id' => $hija->id,
    ])->assertCreated()->json('datos');

    expect($historia['carga_familiar_id'])->toBe($hija->id);
    expect($historia['tipo_paciente'])->toBe('familiar');

    // Y queda numerada por su cédula aunque la petición solo mandara el id:
    // es como se la busca después.
    expect($historia['cedula_paciente'])->toBe($hija->cedula);
    expect($historia['numero_historia'])->toBe($hija->cedula);

    // El mismo paciente dos veces no abre dos historias.
    $otraVez = $this->postJson('/api/v1/dispensario/historias-clinicas', [
        'carga_familiar_id' => $hija->id,
    ])->assertCreated()->json('datos');

    expect($otraVez['id'])->toBe($historia['id']);
    expect(HistoriaClinica::where('carga_familiar_id', $hija->id)->count())
        ->toBe(1);
});

test('crear_la_historia_conserva_el_grupo_sanguineo_que_se_envio', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    // Se resolvía por cédula y se devolvía sin más, así que el grupo sanguíneo
    // enviado en el alta se perdía por el camino sin decir nada.
    $historia = $this->postJson('/api/v1/dispensario/historias-clinicas', [
        'servidor_id'     => $this->paciente->id,
        'grupo_sanguineo' => 'O+',
    ])->assertCreated()->json('datos');

    expect(HistoriaClinica::find($historia['id'])->grupo_sanguineo)->toBe('O+');
});

test('anular_una_atencion_de_enfermeria_la_marca_sin_borrarla', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $servicio = App\Models\Dispensario\CatalogoServicioEnfermeria::create([
        'nombre' => 'Inyección intramuscular', 'activo' => true,
    ]);

    $atencion = $this->postJson('/api/v1/dispensario/atenciones-enfermeria', [
        'servidor_id'          => $this->paciente->id,
        'catalogo_servicio_id' => $servicio->id,
        'descripcion'          => 'Ketorolaco 60mg',
    ])->assertCreated()->json('datos');

    $this->patchJson(
        "/api/v1/dispensario/atenciones-enfermeria/{$atencion['id']}/anular",
        ['motivo_anulacion' => 'Paciente incorrecto']
    )->assertOk();

    // La fila sigue ahí: es un registro clínico, dice que a alguien se le puso
    // una inyección. Lo que cambia es que queda marcada y con el motivo.
    $enBase = App\Models\Dispensario\AtencionEnfermeria::find($atencion['id']);
    expect($enBase)->not->toBeNull();
    expect($enBase->anulado_en)->not->toBeNull();
    expect($enBase->anulado_por)->toBe($this->medico->id);
    expect($enBase->motivo_anulacion)->toBe('Paciente incorrecto');

    // Anularla dos veces no cuela.
    $this->patchJson(
        "/api/v1/dispensario/atenciones-enfermeria/{$atencion['id']}/anular",
        ['motivo_anulacion' => 'Otra vez']
    )->assertStatus(422);

    // Y el listado la sigue mostrando, que es de lo que va la trazabilidad.
    $listado = $this->getJson('/api/v1/dispensario/atenciones-enfermeria')
        ->assertOk()->json('datos.data');
    expect(collect($listado)->pluck('id'))->toContain($atencion['id']);

    // Salvo que se pidan solo las vigentes.
    $vigentes = $this->getJson(
        '/api/v1/dispensario/atenciones-enfermeria?solo_vigentes=1'
    )->assertOk()->json('datos.data');
    expect(collect($vigentes)->pluck('id'))->not->toContain($atencion['id']);
});

test('el_folio_de_enfermeria_sale_del_mayor_no_de_contar_filas', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'enfermera', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $servicio = App\Models\Dispensario\CatalogoServicioEnfermeria::create([
        'nombre' => 'Curación', 'activo' => true,
    ]);

    $registrar = fn () => $this->postJson(
        '/api/v1/dispensario/atenciones-enfermeria',
        [
            'servidor_id'          => $this->paciente->id,
            'catalogo_servicio_id' => $servicio->id,
        ]
    )->assertCreated()->json('datos');

    $anio = now()->year;

    expect($registrar()['folio'])->toBe("ENF-{$anio}-00001");
    $segunda = $registrar();
    expect($segunda['folio'])->toBe("ENF-{$anio}-00002");

    // La tabla borra en blando. Contando filas, retirar una bajaba el conteo y
    // el siguiente folio repetía uno ya emitido, que choca contra el índice
    // único porque el borrado en blando no libera el valor.
    App\Models\Dispensario\AtencionEnfermeria::find($segunda['id'])->delete();

    expect($registrar()['folio'])->toBe("ENF-{$anio}-00003");
});

test('listar_recetas_de_un_medico_sin_servidor_no_tumba_la_pantalla', function () {
    // El médico de la suite no tiene servidor asociado, que es el caso que
    // rompía: `nombre_completo` va en #[Appends] y para un usuario sin servidor
    // cae al correo, que el `with(...)` parcial dejaba fuera del select.
    expect($this->medico->servidor_id)->toBeNull();

    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $historia = HistoriaClinica::create([
        'numero_historia' => $this->paciente->cedula,
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id'           => $this->medico->id,
        'fecha_consulta'      => now(),
        'hora_consulta'       => now()->format('H:i:s'),
        'motivo_consulta'     => 'Dolor',
    ]);

    RecetaMedica::create([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision'      => now(),
        'estado'             => 'pendiente',
    ]);

    $recetas = $this->getJson('/api/v1/dispensario/recetas')
        ->assertOk()->json('datos.data');

    // Y el médico llega con nombre: el correo, que es el respaldo cuando no
    // hay servidor. Antes esto era un 500 con «Return value must be of type
    // string, null returned».
    expect($recetas)->toHaveCount(1);
    expect($recetas[0]['consulta_medica']['medico']['nombre_completo'])
        ->toBe($this->medico->email);
});

test('nombre_completo_no_devuelve_null_aunque_falte_el_correo', function () {
    // La red debajo: cualquier `with` que se olvide de `email` mañana.
    $medico = App\Models\User::query()
        ->select('id', 'usuario_ti', 'servidor_id')
        ->findOrFail($this->medico->id);

    expect($medico->nombre_completo)->toBe($this->medico->usuario_ti);
    expect(fn () => $medico->toArray())->not->toThrow(TypeError::class);
});

test('el_folio_del_turno_sale_del_mayor_no_de_contar_filas', function () {
    $service = app(AgendaService::class);

    $agendar = fn () => $service->agendarCita([
        'servidor_id'      => $this->paciente->id,
        'medico_id'        => $this->medico->id,
        'tipo_atencion'    => 'medicina_general',
        'motivo_solicitud' => 'Control',
    ], $this->medico->id);

    $anio = now()->year;

    expect($agendar()->folio)->toBe("TUR-{$anio}-00001");
    $segundo = $agendar();
    expect($segundo->folio)->toBe("TUR-{$anio}-00002");

    // La tabla borra en blando. Contando filas, retirar una bajaba el conteo y
    // el siguiente turno repetía un folio ya emitido, que el índice único
    // rechaza porque el borrado en blando no libera el valor.
    $segundo->delete();

    expect($agendar()->folio)->toBe("TUR-{$anio}-00003");
});

test('el_listado_de_recetas_pagina_y_cuenta_los_estados_completos', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $historia = HistoriaClinica::create([
        'numero_historia' => $this->paciente->cedula,
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id'           => $this->medico->id,
        'fecha_consulta'      => now(),
        'hora_consulta'       => now()->format('H:i:s'),
        'motivo_consulta'     => 'Dolor',
    ]);

    foreach (range(1, 18) as $i) {
        RecetaMedica::create([
            'consulta_medica_id' => $consulta->id,
            'fecha_emision'      => now(),
            'estado'             => $i <= 12 ? 'pendiente' : 'anulada',
        ]);
    }

    $respuesta = $this->getJson('/api/v1/dispensario/recetas?per_page=5')
        ->assertOk()->json();

    // La página trae cinco, no las dieciocho.
    expect($respuesta['datos']['data'])->toHaveCount(5);
    expect($respuesta['datos']['total'])->toBe(18);
    expect($respuesta['datos']['last_page'])->toBe(4);

    // Pero los contadores de la cabecera cuentan todas, no solo la página:
    // con la lista recortada dirían «5 pendientes» habiendo doce.
    expect($respuesta['meta']['resumen'])->toBe([
        'anulada'   => 6,
        'pendiente' => 12,
    ]);

    // Y la segunda página no repite la primera.
    $primera = collect($respuesta['datos']['data'])->pluck('id');
    $segunda = collect(
        $this->getJson('/api/v1/dispensario/recetas?per_page=5&page=2')
            ->assertOk()->json('datos.data')
    )->pluck('id');

    expect($primera->intersect($segunda))->toBeEmpty();
});

test('el_kardex_se_pagina', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-KARDEX', 'nombre' => 'Paracetamol',
        'principio_activo' => 'Paracetamol', 'concentracion' => '500mg',
        'presentacion' => 'tableta', 'lote' => 'LK-1',
        'fecha_caducidad' => now()->addYear(),
        'stock_minimo' => 5, 'stock_actual' => 0,
    ]);

    foreach (range(1, 25) as $i) {
        MovimientoInventarioMed::create([
            'inventario_medicina_id' => $medicina->id,
            'tipo_movimiento'        => 'ingreso',
            'cantidad'               => 1,
            'stock_resultante'       => $i,
            'motivo'                 => "Movimiento {$i}",
            'registrado_por'         => $this->medico->id,
        ]);
    }

    // El kardex no deja de crecer —ninguna fila se borra— así que traerlo
    // entero deja de ser barato solo.
    $respuesta = $this->getJson(
        "/api/v1/dispensario/inventario/medicinas/{$medicina->id}/kardex?per_page=10"
    )->assertOk()->json('datos');

    expect($respuesta['data'])->toHaveCount(10);
    expect($respuesta['total'])->toBe(25);
    expect($respuesta['last_page'])->toBe(3);
});

test('el_historial_de_consultas_se_puede_acotar_por_fechas', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $historia = HistoriaClinica::create([
        'numero_historia' => $this->paciente->cedula,
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);

    $consultaEn = function (string $fecha) use ($historia) {
        return ConsultaMedica::create([
            'historia_clinica_id' => $historia->id,
            'medico_id'           => $this->medico->id,
            'fecha_consulta'      => $fecha,
            'hora_consulta'       => '09:00:00',
            'motivo_consulta'     => "Consulta de {$fecha}",
        ]);
    };

    $consultaEn('2024-03-10');
    $vigente = $consultaEn('2026-08-20');
    $consultaEn('2026-09-01');

    $enRango = $this->getJson(
        '/api/v1/dispensario/consultas'
        . "?historia_clinica_id={$historia->id}"
        . '&fecha_desde=2026-08-01&fecha_hasta=2026-08-31'
    )->assertOk()->json('datos.data');

    expect($enRango)->toHaveCount(1);
    expect($enRango[0]['id'])->toBe($vigente->id);

    // Sin filtros siguen estando las tres.
    $todas = $this->getJson(
        "/api/v1/dispensario/consultas?historia_clinica_id={$historia->id}"
    )->assertOk()->json('datos.data');

    expect($todas)->toHaveCount(3);
});

/**
 * Una medicina de catálogo, sin existencias: el stock entra por adquisiciones.
 */
function medicinaVacia(int $creadaPor, string $nombre = 'Amoxicilina'): InventarioMedicina
{
    return app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre'           => $nombre,
        'principio_activo' => $nombre,
        'concentracion'    => '500mg',
        'presentacion'     => 'capsula',
        'stock_minimo'     => 5,
    ], $creadaPor);
}

/** Registra una entrada con su lote y su caducidad. */
function entrada(
    InventarioMedicina $medicina,
    int $cantidad,
    ?string $lote,
    ?string $caduca,
    int $porQuien,
    string $documento = 'FACT-1'
): App\Models\Dispensario\AdquisicionMedicamento {
    return app(App\Services\Dispensario\AdquisicionService::class)->registrar(
        [
            'tipo'                => 'compra',
            'numero_documento'    => $documento,
            'proveedor_o_donante' => 'Farmaenlace S.A.',
            'fecha_adquisicion'   => now()->toDateString(),
        ],
        [[
            'inventario_medicina_id' => $medicina->id,
            'cantidad'               => $cantidad,
            'lote'                   => $lote,
            'fecha_caducidad'        => $caduca,
        ]],
        $porQuien
    );
}

test('cada_entrada_abre_su_lote_y_la_suma_cuadra_con_el_stock', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaVacia($this->medico->id);

    entrada($medicina, 100, 'L-MARZO', '2027-03-31', $this->medico->id);
    entrada($medicina, 50,  'L-DICIEMBRE', '2027-12-31', $this->medico->id, 'FACT-2');

    $lotes = $medicina->lotes()->orderBy('id')->get();

    // Antes esto era una sola fila que decía «150, caduca en diciembre», y las
    // cien de marzo quedaban invisibles.
    expect($lotes)->toHaveCount(2);
    expect($lotes[0]->codigo_lote)->toBe('L-MARZO');
    expect($lotes[0]->stock_actual)->toBe(100);
    expect($lotes[1]->codigo_lote)->toBe('L-DICIEMBRE');
    expect($lotes[1]->stock_actual)->toBe(50);

    // El invariante que sostiene todo lo demás.
    expect($medicina->refresh()->stock_actual)->toBe(150);
    expect(app(App\Services\Dispensario\StockPorLotes::class)->cuadra($medicina))
        ->toBeTrue();

    // Y cada ingreso del kardex apunta a su lote.
    $ingresos = MovimientoInventarioMed::where('inventario_medicina_id', $medicina->id)
        ->where('tipo_movimiento', 'ingreso')->orderBy('id')->get();
    expect($ingresos->pluck('lote_id')->all())
        ->toBe($lotes->pluck('id')->all());
});

test('el_despacho_saca_primero_lo_que_caduca_antes', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaVacia($this->medico->id);

    // El de diciembre entra primero: si mandara el orden de llegada en vez de
    // la caducidad, saldría este.
    entrada($medicina, 40, 'L-DICIEMBRE', '2027-12-31', $this->medico->id);
    entrada($medicina, 40, 'L-MARZO', '2027-03-31', $this->medico->id, 'FACT-2');

    $reparto = app(App\Services\Dispensario\StockPorLotes::class)
        ->consumirFefo($medicina->refresh(), 10);

    expect($reparto)->toHaveCount(1);
    expect($reparto[0]['lote']->codigo_lote)->toBe('L-MARZO');
    expect($reparto[0]['cantidad'])->toBe(10);

    $porLote = $medicina->lotes()->pluck('stock_actual', 'codigo_lote');
    expect($porLote['L-MARZO'])->toBe(30);
    expect($porLote['L-DICIEMBRE'])->toBe(40);
});

test('un_despacho_puede_repartirse_entre_lotes_y_el_kardex_lo_dice', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaVacia($this->medico->id);

    entrada($medicina, 20, 'L-MARZO', '2027-03-31', $this->medico->id);
    entrada($medicina, 30, 'L-DICIEMBRE', '2027-12-31', $this->medico->id, 'FACT-2');

    $historia = HistoriaClinica::create([
        'numero_historia' => $this->paciente->cedula,
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);

    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id,
        'medico_id'           => $this->medico->id,
        'fecha_consulta'      => now(),
        'hora_consulta'       => now()->format('H:i:s'),
        'motivo_consulta'     => 'Infección',
    ]);

    $receta = RecetaMedica::create([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision'      => now(),
        'estado'             => 'pendiente',
    ]);

    $item = ItemReceta::create([
        'receta_medica_id'       => $receta->id,
        'inventario_medicina_id' => $medicina->id,
        'cantidad_prescrita'     => 30,
        'cantidad_despachada'    => 0,
        'dosis'                  => '1 cápsula',
        'frecuencia'             => 'cada 8 horas',
        'duracion'               => '10 días',
        'estado'                 => 'pendiente',
    ]);

    // Treinta unidades no caben en el lote de marzo: veinte salen de ahí y
    // diez del siguiente. Eso antes no se podía ni expresar.
    app(RecetaService::class)->despacharReceta(
        $receta->id,
        [['item_receta_id' => $item->id, 'cantidad' => 30]],
        $this->medico->id
    );

    $egresos = MovimientoInventarioMed::where('inventario_medicina_id', $medicina->id)
        ->where('tipo_movimiento', 'egreso')->orderBy('id')->get();

    expect($egresos)->toHaveCount(2);
    expect($egresos[0]->lote->codigo_lote)->toBe('L-MARZO');
    expect($egresos[0]->cantidad)->toBe(-20);
    expect($egresos[0]->stock_resultante)->toBe(30);
    expect($egresos[1]->lote->codigo_lote)->toBe('L-DICIEMBRE');
    expect($egresos[1]->cantidad)->toBe(-10);
    expect($egresos[1]->stock_resultante)->toBe(20);

    $medicina->refresh();
    expect($medicina->stock_actual)->toBe(20);
    expect($medicina->lotes()->where('codigo_lote', 'L-MARZO')->value('stock_actual'))
        ->toBe(0);
    expect(app(App\Services\Dispensario\StockPorLotes::class)->cuadra($medicina))
        ->toBeTrue();
});

test('anular_una_entrada_mira_su_propio_lote_y_no_el_stock_total', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaVacia($this->medico->id);

    $servicio = app(App\Services\Dispensario\AdquisicionService::class);
    $stock    = app(App\Services\Dispensario\StockPorLotes::class);

    // Entra un lote y se despacha entero.
    $primera = entrada($medicina, 20, 'L-UNO', '2027-03-31', $this->medico->id);
    $stock->consumirFefo($medicina->refresh(), 20);

    // Entra otro lote después, con más unidades. El stock total vuelve a estar
    // alto, y con la comprobación anterior —que miraba el total— la anulación
    // de la primera entrada colaba, afirmando que volvían al estante veinte
    // unidades que ya se habían entregado.
    entrada($medicina->refresh(), 50, 'L-DOS', '2027-12-31', $this->medico->id, 'FACT-2');
    expect($medicina->refresh()->stock_actual)->toBe(50);

    expect(fn () => $servicio->anular(
        $primera->id, 'Error de digitación', $this->medico->id
    ))->toThrow(App\Exceptions\ReglaNegocioException::class);

    // El stock no se movió por el intento.
    expect($medicina->refresh()->stock_actual)->toBe(50);
    expect($stock->cuadra($medicina))->toBeTrue();
});

test('anular_una_entrada_intacta_devuelve_su_lote', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaVacia($this->medico->id);

    $adquisicion = entrada($medicina, 20, 'L-UNO', '2027-03-31', $this->medico->id);

    app(App\Services\Dispensario\AdquisicionService::class)->anular(
        $adquisicion->id, 'Error de digitación', $this->medico->id
    );

    $medicina->refresh();
    expect($medicina->stock_actual)->toBe(0);
    expect($medicina->lotes()->where('codigo_lote', 'L-UNO')->value('stock_actual'))
        ->toBe(0);
    expect(app(App\Services\Dispensario\StockPorLotes::class)->cuadra($medicina))
        ->toBeTrue();
});

test('el_ajuste_a_la_baja_retira_lo_que_caduca_mas_tarde', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaVacia($this->medico->id);

    entrada($medicina, 30, 'L-MARZO', '2027-03-31', $this->medico->id);
    entrada($medicina, 30, 'L-DICIEMBRE', '2027-12-31', $this->medico->id, 'FACT-2');

    // Faltan diez y nadie sabe de cuál lote. Se retiran del que caduca más
    // tarde a propósito: dejar en los libros las de caducidad próxima hace que
    // el sistema siga avisando de ellas. Entre avisar de más y callar, una
    // farmacia avisa de más.
    app(InventarioMedicinasService::class)->ajustarInventario(
        $medicina->id, 50, 'Recuento físico', $this->medico->id
    );

    $porLote = $medicina->refresh()->lotes()->pluck('stock_actual', 'codigo_lote');
    expect($porLote['L-MARZO'])->toBe(30);
    expect($porLote['L-DICIEMBRE'])->toBe(20);
    expect(app(App\Services\Dispensario\StockPorLotes::class)->cuadra($medicina))
        ->toBeTrue();
});

test('el_ajuste_al_alza_abre_un_lote_sin_identificar', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaVacia($this->medico->id);

    entrada($medicina, 30, 'L-MARZO', '2027-03-31', $this->medico->id);

    app(InventarioMedicinasService::class)->ajustarInventario(
        $medicina->id, 45, 'Aparecieron en bodega', $this->medico->id
    );

    // Las quince de más no se le cuelgan al lote de marzo: nadie sabe de cuál
    // son, y decir que son de ese sería inventarlo.
    $sinIdentificar = $medicina->lotes()->whereNull('codigo_lote')->first();
    expect($sinIdentificar)->not->toBeNull();
    expect($sinIdentificar->stock_actual)->toBe(15);
    expect($medicina->lotes()->where('codigo_lote', 'L-MARZO')->value('stock_actual'))
        ->toBe(30);
    expect(app(App\Services\Dispensario\StockPorLotes::class)->cuadra($medicina))
        ->toBeTrue();
});

test('dar_de_baja_retira_primero_lo_que_caduca_antes', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaVacia($this->medico->id);

    entrada($medicina, 20, 'L-DICIEMBRE', '2027-12-31', $this->medico->id);
    entrada($medicina, 20, 'L-MARZO', '2027-03-31', $this->medico->id, 'FACT-2');

    // Dar de baja es lo que se hace con lo caducado, así que sale por FEFO.
    app(InventarioMedicinasService::class)->registrarBaja(
        $medicina->id, 20, 'Caducado', $this->medico->id
    );

    $porLote = $medicina->refresh()->lotes()->pluck('stock_actual', 'codigo_lote');
    expect($porLote['L-MARZO'])->toBe(0);
    expect($porLote['L-DICIEMBRE'])->toBe(20);
    expect(app(App\Services\Dispensario\StockPorLotes::class)->cuadra($medicina))
        ->toBeTrue();
});

/**
 * El caso que resume la entrega: una medicina con un lote vencido y otro bueno.
 * Con un solo campo de caducidad en la ficha era indescriptible, y el sistema
 * se equivocaba en una dirección o en la otra según cuál hubiera entrado
 * último.
 */
function medicinaConDosLotes(int $creadaPor): InventarioMedicina
{
    $medicina = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre'           => 'Cefalexina',
        'principio_activo' => 'Cefalexina',
        'concentracion'    => '500mg',
        'presentacion'     => 'capsula',
        'stock_minimo'     => 5,
    ], $creadaPor);

    $stock = app(App\Services\Dispensario\StockPorLotes::class);

    // Primero el vencido, para que FEFO lo encuentre antes.
    $stock->ingresar($medicina, 40, 'L-VENCIDO', now()->subDays(10)->toDateString());
    $stock->ingresar($medicina->refresh(), 60, 'L-BUENO', now()->addYear()->toDateString());

    return $medicina->refresh();
}

test('se_despacha_lo_bueno_aunque_haya_un_lote_vencido_al_lado', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaConDosLotes($this->medico->id);

    // Antes el bloqueo miraba la fecha de la ficha. Con el lote vencido por
    // medio, o paraba el despacho entero teniendo sesenta unidades buenas, o
    // dejaba salir las vencidas. Ahora sale lo bueno y solo lo bueno.
    $reparto = app(App\Services\Dispensario\StockPorLotes::class)
        ->consumirParaDespacho($medicina, 10);

    expect($reparto)->toHaveCount(1);
    expect($reparto[0]['lote']->codigo_lote)->toBe('L-BUENO');

    $porLote = $medicina->refresh()->lotes()->pluck('stock_actual', 'codigo_lote');
    expect($porLote['L-VENCIDO'])->toBe(40);
    expect($porLote['L-BUENO'])->toBe(50);
});

test('el_despacho_se_frena_cuando_lo_bueno_no_alcanza_y_dice_cuanto_hay_vencido', function () {
    $this->actingAs($this->medico, 'sanctum');
    $medicina = medicinaConDosLotes($this->medico->id);

    // Hay cien unidades en total, pero solo sesenta entregables. El rechazo
    // tiene que nombrar las cuarenta inmovilizadas, o quien está en el
    // mostrador no entiende por qué el sistema dice que no hay.
    expect(fn () => app(App\Services\Dispensario\StockPorLotes::class)
        ->consumirParaDespacho($medicina, 80))
        ->toThrow(
            App\Exceptions\ReglaNegocioException::class,
            'No hay existencias entregables de Cefalexina: se piden 80 '
            . 'unidades y quedan 60 sin caducar, más 40 vencidas que deben '
            . 'darse de baja.'
        );

    // Y no se movió nada.
    expect($medicina->refresh()->stock_actual)->toBe(100);
});

test('el_bajo_minimo_no_cuenta_las_unidades_vencidas', function () {
    $this->actingAs($this->medico, 'sanctum');

    $medicina = app(InventarioMedicinasService::class)->ingresarMedicina([
        'nombre'           => 'Ranitidina',
        'principio_activo' => 'Ranitidina',
        'concentracion'    => '150mg',
        'presentacion'     => 'tableta',
        'stock_minimo'     => 30,
    ], $this->medico->id);

    // Ochenta unidades, todas vencidas. El stock de la ficha dice ochenta, así
    // que con la regla anterior esta medicina no aparecía en la alerta de
    // reposición: parecía llena estando vacía de hecho.
    app(App\Services\Dispensario\StockPorLotes::class)->ingresar(
        $medicina, 80, 'L-VIEJO', now()->subMonth()->toDateString()
    );

    $servicio = app(InventarioMedicinasService::class);

    expect($servicio->resumenAlertas()['bajo_minimo']->pluck('nombre'))
        ->toContain('Ranitidina');

    // Y el buscador de quien receta tampoco la ofrece: no hay nada que entregar.
    expect($servicio->buscar('Ranitidina')->pluck('nombre'))
        ->not->toContain('Ranitidina');

    // Quien registra una adquisición sí la ve, que es justo lo que va a reponer.
    expect($servicio->buscar('Ranitidina', soloDespachables: false)->pluck('nombre'))
        ->toContain('Ranitidina');
});

test('el_listado_dice_cuanto_se_puede_entregar_y_cuanto_esta_vencido', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');
    medicinaConDosLotes($this->medico->id);

    $fila = collect(
        $this->getJson('/api/v1/dispensario/inventario/medicinas?search=Cefalexina')
            ->assertOk()->json('datos.data')
    )->firstWhere('nombre', 'Cefalexina');

    // La ficha sigue diciendo cien, que es lo que hay físicamente en el
    // estante, pero ahora se sabe qué parte de esas cien sirve.
    expect($fila['stock_actual'])->toBe(100);
    expect((int) $fila['stock_despachable'])->toBe(60);
    expect((int) $fila['stock_caducado'])->toBe(40);

    // Y la caducidad que manda es la del lote que saldría primero.
    expect($fila['proxima_caducidad'])->toStartWith(
        now()->subDays(10)->format('Y-m-d')
    );
});
test('dar_de_baja_puede_apuntar_a_un_lote_concreto', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $medicina = medicinaVacia($this->medico->id, 'Cefalexina');
    $stock = app(App\Services\Dispensario\StockPorLotes::class);

    $pronto = $stock->ingresar(
        $medicina, 30, 'L-PRONTO', now()->addMonth()->toDateString()
    );
    $tarde = $stock->ingresar(
        $medicina->refresh(), 40, 'L-TARDE', now()->addYear()->toDateString()
    );

    // Una caja rota es de un lote en particular. Por FEFO habrían salido del
    // que caduca antes, y el kardex diría algo que no pasó.
    $this->postJson(
        "/api/v1/dispensario/inventario/medicinas/{$medicina->id}/baja",
        ['cantidad' => 10, 'motivo' => 'Rotura', 'lote_id' => $tarde->id]
    )->assertOk();

    $porLote = $medicina->refresh()->lotes()->pluck('stock_actual', 'codigo_lote');
    expect($porLote['L-PRONTO'])->toBe(30);
    expect($porLote['L-TARDE'])->toBe(30);

    // Y el movimiento apunta al lote del que salió.
    $baja = MovimientoInventarioMed::where('inventario_medicina_id', $medicina->id)
        ->where('tipo_movimiento', 'baja')->latest('id')->firstOrFail();
    expect($baja->lote_id)->toBe($tarde->id);
    expect($baja->lote->codigo_lote)->toBe('L-TARDE');
    // El motivo se queda limpio: el lote va en su columna, no en el texto.
    expect($baja->motivo)->toBe('Rotura');

    expect($pronto->refresh()->stock_actual)->toBe(30);
    expect(app(App\Services\Dispensario\StockPorLotes::class)->cuadra($medicina))
        ->toBeTrue();
});

test('no_se_da_de_baja_mas_de_lo_que_tiene_el_lote_elegido', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $medicina = medicinaVacia($this->medico->id, 'Cefalexina');
    $stock = app(App\Services\Dispensario\StockPorLotes::class);

    $pequeno = $stock->ingresar(
        $medicina, 5, 'L-PEQUENO', now()->addMonth()->toDateString()
    );
    $stock->ingresar(
        $medicina->refresh(), 100, 'L-GRANDE', now()->addYear()->toDateString()
    );

    // Hay 105 en total, pero el lote elegido solo tiene 5: el rechazo mira el
    // lote y no el montón, que es lo que hace útil elegirlo.
    $this->postJson(
        "/api/v1/dispensario/inventario/medicinas/{$medicina->id}/baja",
        ['cantidad' => 20, 'motivo' => 'Rotura', 'lote_id' => $pequeno->id]
    )->assertStatus(422);

    expect($medicina->refresh()->stock_actual)->toBe(105);
});

test('sin_lote_elegido_la_baja_sigue_saliendo_por_fefo', function () {
    $this->medico->assignRole(Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));
    $this->actingAs($this->medico, 'sanctum');

    $medicina = medicinaVacia($this->medico->id, 'Cefalexina');
    $stock = app(App\Services\Dispensario\StockPorLotes::class);

    $stock->ingresar($medicina, 20, 'L-TARDE', now()->addYear()->toDateString());
    $stock->ingresar(
        $medicina->refresh(), 20, 'L-VENCIDO', now()->subDay()->toDateString()
    );

    // Es el caso que da nombre a la operación: tirar lo vencido.
    $this->postJson(
        "/api/v1/dispensario/inventario/medicinas/{$medicina->id}/baja",
        ['cantidad' => 20, 'motivo' => 'Caducidad']
    )->assertOk();

    $porLote = $medicina->refresh()->lotes()->pluck('stock_actual', 'codigo_lote');
    expect($porLote['L-VENCIDO'])->toBe(0);
    expect($porLote['L-TARDE'])->toBe(20);
});

