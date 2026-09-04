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

    $service = new RecetaService();
    
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

    $service = new RecetaService();
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
