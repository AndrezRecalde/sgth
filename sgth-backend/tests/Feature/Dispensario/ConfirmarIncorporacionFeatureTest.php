<?php

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoPostulante;
use App\Enums\Permiso;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Onboarding;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $permiso = Permission::firstOrCreate(['name' => Permiso::GESTIONAR_ONBOARDING->value, 'guard_name' => 'sanctum']);
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo($permiso);

    $this->user = User::factory()->create();
    $this->user->assignRole($rol);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->grupoOcupacional = GrupoOcupacional::create([
        'grado_codigo' => 'SP4', 'grado_numerico' => 4, 'grupo' => 'Profesional',
        'denominacion_generica' => 'Analista', 'rmu' => 1212.00,
        'regimen' => 'losep', 'activo' => true,
    ]);

    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional_id' => $this->grupoOcupacional->id,
        'plazas' => 5,
        'regimen_laboral' => 'losep',
        'activo' => true,
    ]);

    $this->convocatoria = Convocatoria::create([
        'puesto_id' => $this->puesto->id,
        'codigo' => 'CNV-2026-001',
        'titulo' => 'Analista de Talento Humano',
        'descripcion' => 'Concurso de méritos y oposición',
        'fecha_inicio' => now()->subDays(30),
        'fecha_fin' => now()->subDays(2),
        'estado' => 'finalizada',
        'tipo_proceso' => 'formal',
    ]);

    $this->postulante = Postulante::create([
        'convocatoria_id' => $this->convocatoria->id,
        'cedula' => '1751234567',
        'nombres' => 'María',
        'apellidos' => 'Torres',
        'correo' => 'maria.torres@example.com',
        'genero' => 'femenino',
        'estado_civil' => 'soltero',
        'fecha_nacimiento' => '1995-04-10',
        'tipo_sangre' => 'O+',
        'estado' => EstadoPostulante::GANADOR_POTENCIAL,
    ]);

    $this->solicitud = SolicitudCertificacionMedica::create([
        'tipo_evento' => 'ingreso',
        'origen' => 'reclutamiento',
        'postulante_id' => $this->postulante->id,
        'convocatoria_id' => $this->convocatoria->id,
        'cedula_paciente' => $this->postulante->cedula,
        'nombres_paciente' => 'María Torres',
        'solicitado_por' => $this->user->id,
        'estado' => 'completada',
        'dictamen' => 'apto',
        'fecha_limite' => now()->addDays(7),
    ]);
});

test('dictamen médico apto crea el servidor y deja el ingreso en borrador, pendiente de aprobación de RRHH', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/dispensario/solicitudes-certificacion/{$this->solicitud->id}/confirmar-incorporacion");

    $response->assertStatus(200);
    $response->assertJsonPath('mensaje', fn ($mensaje) => str_contains($mensaje, 'borrador')
        && str_contains($mensaje, 'Talento Humano')
        && !str_contains($mensaje, 'incorporado correctamente al sistema'));

    $servidorId = $response->json('datos.servidor_id');
    $movimientoId = $response->json('datos.movimiento_id');

    expect($servidorId)->not->toBeNull();
    expect($movimientoId)->not->toBeNull();

    // El servidor existe pero no tiene contrato vigente todavía.
    $respServidor = $this->getJson("/api/v1/expediente/servidores/{$servidorId}");
    $respServidor->assertStatus(200);
    expect($respServidor->json('datos.pendiente_vinculacion'))->toBeTrue();

    // El movimiento de ingreso nació en 'borrador', con los datos propuestos
    // ya poblados desde la convocatoria/puesto — no en 'registrada' como antes.
    $movimiento = MovimientoPersonal::findOrFail($movimientoId);
    expect($movimiento->tipo_movimiento->value)->toBe('ingreso');
    expect($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);
    expect($movimiento->tipo_nombramiento_propuesto->value)->toBe('nombramiento_permanente');
    expect($movimiento->puesto_destino_id)->toBe($this->puesto->id);
    expect($movimiento->unidad_destino_id)->toBe($this->unidad->id);
    expect((float) $movimiento->remuneracion_propuesta)->toBe(1212.00);

    // El reclutamiento no conoce el número de contrato: lo asigna Talento
    // Humano al revisar el borrador, y es obligatorio para poder registrar.
    app(MovimientoPersonalService::class)
        ->actualizarBorrador($movimiento, ['numero_contrato' => 'CT-2026-0021']);

    // Al transicionar manualmente (como haría Talento Humano desde el tab de
    // Movimientos) hasta 'registrada', recién ahí se materializa el vínculo.
    $stateService = app(MovimientoPersonalStateService::class);
    $movimiento = $stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::SUSCRITA);
    $stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);

    $respServidorFinal = $this->getJson("/api/v1/expediente/servidores/{$servidorId}");
    $respServidorFinal->assertStatus(200);
    expect($respServidorFinal->json('datos.pendiente_vinculacion'))->toBeFalse();
    expect($respServidorFinal->json('datos.contrato_vigente.puesto_id'))->toBe($this->puesto->id);
});

test('candidato interno con vínculo vigente gana el concurso: el ingreso exige cesación previa y no duplica Servidor ni Onboarding', function () {
    $puestoActual = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional_id' => $this->grupoOcupacional->id,
        'plazas' => 5,
        'regimen_laboral' => 'losep',
        'activo' => true,
    ]);

    $servidorInterno = Servidor::create([
        'cedula' => '1751234567', 'nombre' => 'María', 'apellido' => 'Torres',
        'regimen_laboral' => 'losep', 'estado' => true,
        'puesto_id' => $puestoActual->id, 'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $vinculoAnterior = ContratoServidor::create([
        'servidor_id' => $servidorInterno->id,
        'tipo_nombramiento' => 'nombramiento_provisional',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $puestoActual->id,
        'fecha_inicio' => '2023-01-01',
        'estado' => 'vigente',
    ]);

    $this->postulante->update(['servidor_id' => $servidorInterno->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/dispensario/solicitudes-certificacion/{$this->solicitud->id}/confirmar-incorporacion");

    $response->assertStatus(200);
    expect($response->json('mensaje'))->toContain('Candidato interno');
    expect($response->json('mensaje'))->toContain('Cesación de Funciones');
    expect($response->json('datos.servidor_id'))->toBe($servidorInterno->id);

    // Sin Servidor duplicado ni Onboarding (ya trabaja en la institución).
    expect(Servidor::where('cedula', '1751234567')->count())->toBe(1);
    expect(Onboarding::where('servidor_id', $servidorInterno->id)->count())->toBe(0);

    $movimientoId = $response->json('datos.movimiento_id');
    $movimiento = MovimientoPersonal::findOrFail($movimientoId);
    expect($movimiento->tipo_movimiento->value)->toBe('ingreso');
    expect($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);
    expect($movimiento->puesto_destino_id)->toBe($this->puesto->id);

    // El número de contrato se fija mientras la acción sigue en borrador: es
    // un dato del documento, y una vez suscrita ya no se edita.
    app(MovimientoPersonalService::class)
        ->actualizarBorrador($movimiento, ['numero_contrato' => 'CT-2026-0022']);

    $stateService = app(MovimientoPersonalStateService::class);
    $movimiento = $stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::SUSCRITA);

    // Talento Humano no maneja "ascenso": el ingreso ya no cierra el vínculo
    // anterior por su cuenta, exige que se registre antes la cesación.
    expect(fn () => $stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class, 'Registre primero la Cesación de Funciones');

    $vinculoAnterior->refresh();
    expect($vinculoAnterior->estado->value)->toBe('vigente');

    // Registrada la cesación, el mismo ingreso ya puede registrarse.
    $cesacion = app(MovimientoPersonalService::class)->registrar($servidorInterno->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Cesación del puesto actual por haber ganado el concurso',
        'fecha_efectiva'     => now()->toDateString(),
    ]);
    $cesacion = $stateService->transicionar($cesacion, EstadoAccionPersonal::SUSCRITA);
    $stateService->transicionar($cesacion->fresh(), EstadoAccionPersonal::REGISTRADA);

    $vinculoAnterior->refresh();
    expect($vinculoAnterior->estado->value)->toBe('terminado');
    expect($vinculoAnterior->motivo_fin)->toContain('Renuncia');

    $stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    // El nuevo vínculo es sobre el puesto del concurso, no el anterior.
    $vinculoNuevo = ContratoServidor::where('servidor_id', $servidorInterno->id)
        ->where('estado', 'vigente')->first();
    expect($vinculoNuevo)->not->toBeNull();
    expect($vinculoNuevo->puesto_id)->toBe($this->puesto->id);

    expect($servidorInterno->fresh()->puesto_id)->toBe($this->puesto->id);
});

test('ex-servidor inactivo que reingresa vuelve a estado=true automáticamente al registrar el ingreso', function () {
    $servidorExServidor = Servidor::create([
        'cedula' => '1751234567', 'nombre' => 'María', 'apellido' => 'Torres',
        'regimen_laboral' => 'losep', 'estado' => false,
    ]);

    $this->postulante->update(['servidor_id' => $servidorExServidor->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/dispensario/solicitudes-certificacion/{$this->solicitud->id}/confirmar-incorporacion");

    $response->assertStatus(200);
    expect($servidorExServidor->fresh()->estado)->toBeFalse(); // aún no, sigue en borrador

    $movimiento = MovimientoPersonal::findOrFail($response->json('datos.movimiento_id'));

    app(MovimientoPersonalService::class)
        ->actualizarBorrador($movimiento, ['numero_contrato' => 'CT-2026-0023']);

    $stateService = app(MovimientoPersonalStateService::class);
    $movimiento = $stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::SUSCRITA);
    $stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);

    expect($servidorExServidor->fresh()->estado)->toBeTrue();
});

test('convocatoria formal siempre produce PERMANENTE, incluso si el puesto es código de trabajo (ya no se infiere del régimen del puesto)', function () {
    $puestoCodigoTrabajo = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional_id' => $this->grupoOcupacional->id,
        'plazas' => 5,
        'regimen_laboral' => 'codigo_trabajo', // a propósito: la heurística vieja habría dado CODIGO_TRABAJO
        'activo' => true,
    ]);

    $convocatoriaFormal = Convocatoria::create([
        'puesto_id' => $puestoCodigoTrabajo->id,
        'codigo' => 'CNV-2026-002',
        'titulo' => 'Obrero de Mantenimiento',
        'descripcion' => 'Concurso de méritos y oposición',
        'fecha_inicio' => now()->subDays(30),
        'fecha_fin' => now()->subDays(2),
        'estado' => 'finalizada',
        'tipo_proceso' => 'formal',
    ]);

    $postulante = Postulante::create([
        'convocatoria_id' => $convocatoriaFormal->id,
        'cedula' => '1799999999',
        'nombres' => 'Pedro', 'apellidos' => 'Vera',
        'correo' => 'pedro.vera@example.com',
        'genero' => 'masculino', 'estado_civil' => 'soltero',
        'fecha_nacimiento' => '1990-01-01', 'tipo_sangre' => 'O+',
        'estado' => EstadoPostulante::GANADOR_POTENCIAL,
    ]);

    $solicitud = SolicitudCertificacionMedica::create([
        'tipo_evento' => 'ingreso', 'origen' => 'reclutamiento',
        'postulante_id' => $postulante->id, 'convocatoria_id' => $convocatoriaFormal->id,
        'cedula_paciente' => $postulante->cedula, 'nombres_paciente' => 'Pedro Vera',
        'solicitado_por' => $this->user->id, 'estado' => 'completada', 'dictamen' => 'apto',
        'fecha_limite' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/confirmar-incorporacion");

    $response->assertStatus(200);
    $movimiento = MovimientoPersonal::findOrFail($response->json('datos.movimiento_id'));
    expect($movimiento->tipo_nombramiento_propuesto)->toBe(TipoNombramiento::PERMANENTE);
});

test('convocatoria express usa tipo_nombramiento_previsto directamente, sin inferir nada del puesto', function () {
    $puestoLosep = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional_id' => $this->grupoOcupacional->id,
        'plazas' => 5,
        'regimen_laboral' => 'losep', // a propósito: si se infiriera del puesto, daría PERMANENTE
        'activo' => true,
    ]);

    $convocatoriaExpress = Convocatoria::create([
        'puesto_id' => $puestoLosep->id,
        'codigo' => 'CNV-2026-003',
        'titulo' => 'Analista Temporal',
        'descripcion' => 'Reclutamiento Express',
        'fecha_inicio' => now(),
        'fecha_fin' => now(),
        'estado' => 'finalizada',
        'tipo_proceso' => 'express',
        'tipo_nombramiento_previsto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
    ]);

    $postulante = Postulante::create([
        'convocatoria_id' => $convocatoriaExpress->id,
        'cedula' => '1788888888',
        'nombres' => 'Ana', 'apellidos' => 'Ruiz',
        'correo' => 'ana.ruiz@example.com',
        'genero' => 'femenino', 'estado_civil' => 'soltero',
        'fecha_nacimiento' => '1992-01-01', 'tipo_sangre' => 'O+',
        'estado' => EstadoPostulante::GANADOR_POTENCIAL,
    ]);

    $solicitud = SolicitudCertificacionMedica::create([
        'tipo_evento' => 'ingreso', 'origen' => 'reclutamiento',
        'postulante_id' => $postulante->id, 'convocatoria_id' => $convocatoriaExpress->id,
        'cedula_paciente' => $postulante->cedula, 'nombres_paciente' => 'Ana Ruiz',
        'solicitado_por' => $this->user->id, 'estado' => 'completada', 'dictamen' => 'apto',
        'fecha_limite' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/confirmar-incorporacion");

    $response->assertStatus(200);
    $movimiento = MovimientoPersonal::findOrFail($response->json('datos.movimiento_id'));
    expect($movimiento->tipo_nombramiento_propuesto)->toBe(TipoNombramiento::SERVICIOS_OCASIONALES);
});
