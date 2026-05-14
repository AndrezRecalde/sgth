<?php

use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Asistencia\FeriadoInstitucional;
use App\Models\User;
use App\Services\Asistencia\VacacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Vacacion::unguard();
    if (class_exists(FeriadoInstitucional::class)) {
        FeriadoInstitucional::unguard();
    }

    $this->userJefe = User::create([
        'name' => 'Jefe Unidad',
        'email' => 'jefe_vac@example.com',
        'usuario_ti' => 'jefe_v',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->userSubordinado = User::create([
        'name' => 'Subordinado',
        'email' => 'sub_vac@example.com',
        'usuario_ti' => 'sub_v',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'U01_'.uniqid(),
        'nombre' => 'Dirección de TI',
        'estado' => true,
        'nivel' => 1,
    ]);

    $this->puestoJefe = Puesto::create([
        'codigo' => 'P01_'.uniqid(),
        'denominacion' => 'Director',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Directivo',
        'grado_rmu' => 15,
        'rmu' => 2000.00,
        'nivel' => 1,
        'es_jefe' => true,
        'estado' => true,
    ]);

    $this->puestoSubordinado = Puesto::create([
        'codigo' => 'P02_'.uniqid(),
        'denominacion' => 'Analista',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Profesional',
        'grado_rmu' => 10,
        'rmu' => 1000.00,
        'nivel' => 1,
        'es_jefe' => false,
        'estado' => true,
    ]);
});

test('calculo_vacaciones_losep_tramo_1_a_5_anios', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234561',
        'nombre' => 'Juan',
        'apellido' => 'Perez',
        'user_id' => $this->userSubordinado->id,
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), // Hace 3 años
        'fecha_ingreso_sector_publico' => now()->subYears(3), // Hace 3 años
        'estado' => true,
    ]);

    $service = new VacacionService();
    $motor = $service->obtenerMotor($servidor);
    $diasAnuales = $motor->calcularDiasGanadosAnuales($servidor);

    expect($diasAnuales)->toBe(15.0);
});

test('calculo_vacaciones_losep_tramo_6_a_10_anios', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234562',
        'nombre' => 'Maria',
        'apellido' => 'Gomez',
        'user_id' => $this->userSubordinado->id, // Use valid user_id
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(8), // Hace 8 años
        'fecha_ingreso_sector_publico' => now()->subYears(8), // Hace 8 años
        'estado' => true,
    ]);

    $service = new VacacionService();
    $motor = $service->obtenerMotor($servidor);
    $diasAnuales = $motor->calcularDiasGanadosAnuales($servidor);

    expect($diasAnuales)->toBe(20.0);
});

test('calculo_saldo_codigo_trabajo_incluye_dias_antiguedad', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234563',
        'nombre' => 'Carlos',
        'apellido' => 'Mena',
        'user_id' => $this->userSubordinado->id,
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::CODIGO_TRABAJO,
        'fecha_ingreso_institucion' => now()->subYears(8), // Hace 8 años
        'estado' => true,
    ]);

    $service = new VacacionService();
    $motor = $service->obtenerMotor($servidor);
    $diasAnuales = $motor->calcularDiasGanadosAnuales($servidor);

    // 15 días base + (8 - 5) días adicionales = 18 días
    expect($diasAnuales)->toBe(18.0);
});

test('solicitud_vacacion_descuenta_dias_correctamente', function () {
    // Para asegurar el test de feriado, creamos uno si la clase existe
    $fechaInicio = now()->next('Thursday')->startOfDay();
    $fechaFin = $fechaInicio->copy()->addDays(4); // Thursday, Friday, Saturday, Sunday, Monday (5 days total)
    
    if (class_exists(FeriadoInstitucional::class)) {
        $fechaFeriado = $fechaInicio->copy()->addDay();
        FeriadoInstitucional::create([
            'fecha' => $fechaFeriado,
            'mes' => $fechaFeriado->month,
            'dia' => $fechaFeriado->day,
            'descripcion' => 'Feriado de prueba',
            'es_nacional' => true,
            'es_movil' => false,
        ]);
    }

    $servidorLosep = Servidor::create([
        'cedula' => '0801234564',
        'nombre' => 'Ana',
        'apellido' => 'Losep',
        'user_id' => $this->userSubordinado->id,
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(5),
        'estado' => true,
    ]);

    $servidorCT = Servidor::create([
        'cedula' => '0801234565',
        'nombre' => 'Luis',
        'apellido' => 'CodigoT',
        'user_id' => $this->userJefe->id, // just to have another valid user
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::CODIGO_TRABAJO,
        'fecha_ingreso_institucion' => now()->subYears(5),
        'estado' => true,
    ]);

    $service = new VacacionService();

    $motorLosep = $service->obtenerMotor($servidorLosep);
    $diasLosep = $motorLosep->calcularDiasDescuento($fechaInicio, $fechaFin);
    // LOSEP Hábiles: Jueves(1), Viernes(Feriado->0), Sabado(0), Domingo(0), Lunes(1) = 2 días
    // O si no cuenta feriado aún, sería 3 días (Jue, Vie, Lun)
    // Según requerimiento DEBE descontar excluyendo feriados
    expect($diasLosep)->toBe(2.0);

    $motorCT = $service->obtenerMotor($servidorCT);
    $diasCT = $motorCT->calcularDiasDescuento($fechaInicio, $fechaFin);
    // CT Calendario: Jue, Vie, Sab, Dom, Lun = 5 días
    expect($diasCT)->toBe(5.0);
});

test('validacion_acumulacion_losep_falla_si_supera_60_dias', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234566',
        'nombre' => 'Rosa',
        'apellido' => 'Limite',
        'user_id' => $this->userSubordinado->id,
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(4), // Acumuló 4 años = 60 días aprox
        'fecha_ingreso_sector_publico' => now()->subYears(4),
        'estado' => true,
    ]);

    // Assign role to bypass middleware
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'director', 'guard_name' => 'sanctum']);
    $this->userJefe->assignRole('director');

    // Simularemos que hacemos una solicitud de dashboard KPI
    // Como el user especificó verificar en el KPI vacaciones_proximas_vencer:
    $this->withoutExceptionHandling();
    $response = $this->actingAs($this->userJefe, 'sanctum')->getJson('/api/v1/reporteria/dashboard');
    $response->assertStatus(200);

    // Assert que el servidor está en el KPI de alerta
    $kpis = $response->json('datos');
    
    // Suponiendo que el KPI devuelve una lista de alertas o un contador
    $alertaEncontrada = collect($kpis['asistencia']['vacaciones_proximas_vencer'] ?? [])->contains('servidor_id', $servidor->id);
    expect($alertaEncontrada)->toBeTrue();
});

test('jefe_puede_aprobar_o_rechazar_vacacion_de_subordinado', function () {
    $servidorSubordinado = Servidor::create([
        'cedula' => '0801234567',
        'nombre' => 'Subordinado',
        'apellido' => 'Prueba',
        'user_id' => $this->userSubordinado->id,
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(5),
        'estado' => true,
    ]);
    
    $servidorJefe = Servidor::create([
        'cedula' => '0801234568',
        'nombre' => 'Jefe',
        'apellido' => 'Prueba',
        'user_id' => $this->userJefe->id,
        'puesto_id' => $this->puestoJefe->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $vacacion = Vacacion::create([
        'servidor_id' => $servidorSubordinado->id,
        'fecha_inicio' => now()->addDays(5)->format('Y-m-d'),
        'fecha_fin' => now()->addDays(10)->format('Y-m-d'),
        'dias_solicitados' => 4,
        'tipo_dias' => 'habiles',
        'estado' => 'pendiente',
    ]);

    // El jefe intenta aprobar
    $response = $this->actingAs($this->userJefe, 'sanctum')->putJson("/api/v1/asistencia/vacaciones/{$vacacion->id}", [
        'estado' => 'aprobada'
    ]);

    $response->assertStatus(200);

    $vacacion->refresh();
    expect($vacacion->estado)->toBe('aprobada');
    expect($vacacion->aprobado_por)->toBe($this->userJefe->id);
});
