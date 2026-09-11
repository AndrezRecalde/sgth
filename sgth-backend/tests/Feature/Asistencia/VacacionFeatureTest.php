<?php

use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Asistencia\PeriodoVacacion;
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
        'email' => 'jefe_vac@example.com',
        'usuario_ti' => 'jefe_v',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->userSubordinado = User::create([
        'email' => 'sub_vac@example.com',
        'usuario_ti' => 'sub_v',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de TI']);

    $this->puestoJefe        = puestoJefeDePrueba($this->unidad);
    $this->puestoSubordinado = puestoDePrueba($this->unidad);
});

test('la LOSEP genera 30 días con 3 años de servicio', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234561',
        'nombre' => 'Juan',
        'apellido' => 'Perez',
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

    // Art. 29 LOSEP: treinta días, sin escala por antigüedad.
    expect($diasAnuales)->toBe(30.0);
});

test('la LOSEP genera los mismos 30 días con 8 años: no hay tramos', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234562',
        'nombre' => 'Maria',
        'apellido' => 'Gomez',
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

    expect($diasAnuales)->toBe(30.0);
});

test('calculo_saldo_codigo_trabajo_incluye_dias_antiguedad', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234563',
        'nombre' => 'Carlos',
        'apellido' => 'Mena',
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
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::CODIGO_TRABAJO,
        'fecha_ingreso_institucion' => now()->subYears(5),
        'estado' => true,
    ]);

    $service = new VacacionService();

    $motorLosep = $service->obtenerMotor($servidorLosep);
    $diasLosep = $motorLosep->calcularDiasDescuento($fechaInicio, $fechaFin);
    // LOSEP también en días calendario (art. 29, confirmado con Talento
    // Humano): Jue, Vie, Sáb, Dom, Lun = 5 días, feriado incluido. Antes
    // contaba solo los hábiles y sin feriados, y daba 2.
    expect($diasLosep)->toBe(5.0);

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
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(4),
        'fecha_ingreso_sector_publico' => now()->subYears(4),
        'estado' => true,
    ]);

    // Tres períodos abiertos sin gozar: 45 días acumulados. El KPI lee el saldo
    // de los períodos. Antes este test se apoyaba en el cálculo legacy —días
    // por año de antigüedad, sin mirar lo gozado—, que se retiró porque inflaba
    // el saldo de quien ya había gozado todo.
    foreach ([2, 1, 0] as $atras) {
        $anio = now()->year - $atras;
        PeriodoVacacion::create([
            'servidor_id' => $servidor->id,
            'anio' => $anio,
            'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
            'fecha_fin_periodo' => Carbon::create($anio, 12, 31),
            'regimen' => 'losep',
            'anios_antiguedad' => 4 - $atras,
            'dias_generados' => 15,
            'dias_utilizados' => 0,
            'dias_saldo' => 15,
            'saldo_acumulado' => 15,
            'estado' => 'abierto',
        ]);
    }

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

test('solo_quien_tiene_aprobar_vacaciones_resuelve_la_solicitud', function () {
    // Antes este test se llamaba «jefe puede aprobar» y lo aprobaba un usuario
    // sin ningún rol: pasaba porque la ruta no comprobaba nada. En la matriz
    // de permisos, `aprobar-vacaciones` es de Talento Humano; el jefe de
    // unidad ve las solicitudes de su unidad pero no las resuelve.
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $servidorSubordinado = Servidor::create([
        'cedula' => '0801234567',
        'nombre' => 'Subordinado',
        'apellido' => 'Prueba',
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
        'puesto_id' => $this->puestoJefe->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $this->userJefe->update(['servidor_id' => $servidorJefe->id]);
    $this->userJefe->assignRole('jefe-unidad');

    $uath = User::create([
        'email' => 'uath_vac@example.com',
        'usuario_ti' => 'uath_v',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $uath->assignRole('admin-uath');

    $vacacion = Vacacion::create([
        'servidor_id' => $servidorSubordinado->id,
        'fecha_inicio' => now()->addDays(5)->format('Y-m-d'),
        'fecha_fin' => now()->addDays(10)->format('Y-m-d'),
        'dias_solicitados' => 4,
        'tipo_dias' => 'habiles',
        'estado' => 'pendiente',
    ]);

    $this->actingAs($this->userJefe, 'sanctum')
        ->putJson("/api/v1/asistencia/vacaciones/{$vacacion->id}", ['estado' => 'aprobada'])
        ->assertForbidden();

    expect($vacacion->fresh()->estado)->toBe('pendiente');

    $this->actingAs($uath, 'sanctum')
        ->putJson("/api/v1/asistencia/vacaciones/{$vacacion->id}", ['estado' => 'aprobada'])
        ->assertOk();

    $vacacion->refresh();
    expect($vacacion->estado)->toBe('aprobada');
    expect($vacacion->aprobado_por)->toBe($uath->id);
});
