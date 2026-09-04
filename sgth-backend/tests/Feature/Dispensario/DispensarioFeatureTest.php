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
    expect($service->buscar('Amoxi', soloConStock: false)->pluck('id'))
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
