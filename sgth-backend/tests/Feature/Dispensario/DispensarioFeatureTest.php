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
        'name' => 'Dr. House',
        'email' => 'house@example.com',
        'usuario_ti' => 'house',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->pacienteUser = User::create([
        'name' => 'Juan Perez',
        'email' => 'juan@example.com',
        'usuario_ti' => 'juanp',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'U01',
        'nombre' => 'Direccion Test',
        'estado' => true,
        'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P01',
        'denominacion' => 'Analista Test',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Profesional',
        'grado_rmu' => 10,
        'rmu' => 1000.00,
        'nivel' => 1,
        'es_jefe' => false,
        'estado' => true,
    ]);

    $this->paciente = Servidor::create([
        'cedula' => '0801234561',
        'nombre' => 'Juan',
        'apellido' => 'Perez',
        'user_id' => $this->pacienteUser->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3),
        'estado' => true,
    ]);
});

test('agenda_medica_genera_permiso_de_ausencia_automaticamente', function () {
    $service = new AgendaService();
    
    $fecha = now()->addDays(2)->format('Y-m-d');
    
    $cita = $service->agendarCita([
        'servidor_id' => $this->paciente->id,
        'medico_id' => $this->medico->id,
        'fecha' => $fecha,
        'hora_inicio' => '10:00:00',
        'hora_fin' => '10:30:00',
        'motivo_solicitud' => 'Chequeo general',
        'estado' => 'agendada',
    ]);

    expect($cita)->toBeInstanceOf(AgendaMedica::class);
    expect($cita->estado)->toBe('agendada');

    // Verificar que se creó el permiso automáticamente en la tabla correcta (permisos_servidor)
    $permiso = DB::table('permisos_servidor')->where('servidor_id', $this->paciente->id)->first();
    expect($permiso)->not->toBeNull();
    expect($permiso->estado)->toBe('pendiente');
    expect($permiso->observacion)->toContain('Cita médica');
});

test('registrar_consulta_actualiza_estado_de_agenda_a_atendida', function () {
    $historia = HistoriaClinica::create([
        'servidor_id' => $this->paciente->id,
        'grupo_sanguineo' => 'O+',
        'alergias' => 'Ninguna',
        'antecedentes_familiares' => 'Ninguno',
        'antecedentes_personales' => 'Ninguno',
    ]);

    $agenda = AgendaMedica::create([
        'servidor_id' => $this->paciente->id,
        'medico_id' => $this->medico->id,
        'fecha' => now()->format('Y-m-d'),
        'hora_inicio' => '10:00:00',
        'hora_fin' => '10:30:00',
        'motivo_solicitud' => 'Chequeo',
        'estado' => 'agendada',
    ]);

    $service = new HistoriaClinicaService();
    
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
    expect($agenda->estado)->toBe('atendida');
});

test('ingresar_medicina_registra_movimiento_kardex_automatico', function () {
    $this->actingAs($this->medico, 'sanctum');

    $service = new InventarioMedicinasService();
    
    $medicina = $service->ingresarMedicina([
        'codigo' => 'MED-001',
        'nombre' => 'Paracetamol',
        'principio_activo' => 'Paracetamol',
        'concentracion' => '500mg',
        'presentacion' => 'Tabletas',
        'lote' => 'L123',
        'fecha_caducidad' => now()->addYear(),
        'stock_minimo' => 10,
        'stock_actual' => 100,
    ]);

    expect($medicina)->toBeInstanceOf(InventarioMedicina::class);

    $movimiento = MovimientoInventarioMed::where('inventario_medicina_id', $medicina->id)->first();
    
    expect($movimiento)->not->toBeNull();
    expect($movimiento->tipo_movimiento)->toBe('ingreso');
    expect($movimiento->cantidad)->toBe(100);
    expect($movimiento->stock_resultante)->toBe(100);
});

test('emitir_receta_descuenta_stock_y_registra_kardex', function () {
    $this->actingAs($this->medico, 'sanctum');

    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-002',
        'nombre' => 'Ibuprofeno',
        'principio_activo' => 'Ibuprofeno',
        'concentracion' => '400mg',
        'presentacion' => 'Tabletas',
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
    
    $receta = $service->emitirReceta([
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

    expect($receta)->toBeInstanceOf(RecetaMedica::class);

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

test('emitir_receta_falla_si_no_hay_stock_suficiente', function () {
    $this->actingAs($this->medico, 'sanctum');

    $medicina = InventarioMedicina::create([
        'codigo' => 'MED-003',
        'nombre' => 'Amoxicilina',
        'principio_activo' => 'Amoxicilina',
        'concentracion' => '500mg',
        'presentacion' => 'Capsulas',
        'lote' => 'L125',
        'fecha_caducidad' => now()->addYear(),
        'stock_minimo' => 10,
        'stock_actual' => 5, // Poco stock
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
    
    // Debería lanzar excepción porque se piden 10 y hay 5
    $service->emitirReceta([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->format('Y-m-d'),
        'indicaciones_generales' => 'Tomar',
    ], [
        [
            'inventario_medicina_id' => $medicina->id,
            'dosis' => '1',
            'frecuencia' => 'Cada 8 horas',
            'duracion' => '7 días',
            'cantidad_prescrita' => 21,
        ]
    ]);
})->throws(\Exception::class, 'Stock insuficiente');
