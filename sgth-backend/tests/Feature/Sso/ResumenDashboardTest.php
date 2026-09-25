<?php

use App\Enums\EstadoPermiso;
use App\Enums\NivelRiesgoAssist;
use App\Enums\NivelRiesgoPsicosocial;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Expediente\Servidor;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\EvaluacionAssist;
use App\Models\Sso\EvaluacionPsicosocial;
use App\Models\Sso\FactorRiesgoCatalogo;
use App\Models\Sso\RespuestaAssist;
use App\Models\Sso\RespuestaPsicosocial;
use App\Models\Sso\RiesgoLaboral;
use App\Models\User;
use App\Services\Sso\DashboardSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * El resumen del tablero SSO, contra PostgreSQL.
 *
 * Sus cifras salen de agregados SQL —`COUNT`, `SUM`, `GROUP BY`, un
 * `EXTRACT(EPOCH …)` sobre columnas `time` y una comparación `jsonb`—, y eso
 * no se puede comprobar leyendo: el SQL que no se ejecuta es una conjetura.
 * Antes el servicio traía las filas enteras y contaba en memoria, incluidas
 * las respuestas de tamizaje con sus columnas JSON.
 */

beforeEach(function () {
    Servidor::unguard();

    $this->usuario = User::create([
        'email' => 'tablero@gadpe.gob.ec',
        'usuario_ti' => 'tablero',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);

    $this->unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($this->unidad, 'Operador');

    $this->servidor = Servidor::create([
        'cedula' => '0800000001',
        'nombre' => 'Ana',
        'apellido' => 'Quiñónez',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'estado' => true,
    ]);

    $this->factor = FactorRiesgoCatalogo::create([
        'nombre' => 'Ruido continuo',
        'categoria' => 'fisico',
        'activo' => true,
    ]);
});

function resumenDelTablero(string $periodo = '2026'): array
{
    return app(DashboardSsoService::class)->resumen($periodo);
}

function riesgoDePrueba(array $atributos): RiesgoLaboral
{
    return RiesgoLaboral::create(array_merge([
        'puesto_id' => test()->puesto->id,
        'factor_riesgo_id' => test()->factor->id,
        'descripcion' => 'Riesgo de prueba',
        'estado' => true,
    ], $atributos));
}

// ── Riesgos ───────────────────────────────────────────────────────────

test('los riesgos se cuentan y se agrupan por nivel de intervención', function () {
    riesgoDePrueba(['nivel_intervencion' => 'iii', 'nivel_riesgo_valor' => 100]);
    riesgoDePrueba(['nivel_intervencion' => 'i', 'nivel_riesgo_valor' => 1440]);
    riesgoDePrueba(['nivel_intervencion' => 'i', 'nivel_riesgo_valor' => 600]);
    // Uno inactivo, que no debe contar.
    riesgoDePrueba(['nivel_intervencion' => 'ii', 'estado' => false]);

    $riesgos = resumenDelTablero()['riesgos'];

    expect($riesgos['total_activos'])->toBe(3);
    // Ordenados por nivel, no por orden de creación: i antes que iii.
    expect($riesgos['por_nivel_intervencion']->toArray())->toBe(['i' => 2, 'iii' => 1]);
});

test('un riesgo sin valorar se agrupa bajo la clave vacía', function () {
    // Los anteriores a la matriz NTP 330 tienen el nivel en NULL. Agrupar por
    // null en PHP daba la clave vacía; el GROUP BY de SQL tiene que dar lo
    // mismo para que el tablero no cambie de forma.
    riesgoDePrueba(['nivel_intervencion' => null]);
    riesgoDePrueba(['nivel_intervencion' => 'i']);

    $riesgos = resumenDelTablero()['riesgos'];

    expect($riesgos['total_activos'])->toBe(2);
    // Los sin valorar van al final aunque se hayan creado primero.
    expect($riesgos['por_nivel_intervencion']->toArray())->toBe(['i' => 1, '' => 1]);
});

test('sin riesgos el tablero devuelve cero y ningún nivel', function () {
    $riesgos = resumenDelTablero()['riesgos'];

    expect($riesgos['total_activos'])->toBe(0);
    expect($riesgos['por_nivel_intervencion']->toArray())->toBe([]);
});

// ── Accidentes ────────────────────────────────────────────────────────

test('los accidentes del período se cuentan con su reposo', function () {
    $accidente = fn(string $fecha, bool $atencion, int $reposo) => AccidenteTrabajo::create([
        'servidor_id' => $this->servidor->id,
        'tipo_evento' => 'accidente',
        'fecha_accidente' => $fecha,
        'hora_accidente' => '09:00',
        'lugar_accidente' => 'Patio de maquinaria',
        'descripcion_hechos' => 'Prueba',
        'gravedad' => 'leve',
        'requirio_atencion_medica' => $atencion,
        'dias_reposo_medico' => $reposo,
        'estado' => true,
    ]);

    $accidente('2026-03-10', true, 5);
    $accidente('2026-07-01', false, 0);
    $accidente('2026-11-20', true, 12);
    // Fuera del período: no cuenta.
    $accidente('2025-12-31', true, 30);

    $accidentes = resumenDelTablero()['accidentes'];

    expect($accidentes['total'])->toBe(3);
    expect($accidentes['con_atencion_medica'])->toBe(2);
    expect($accidentes['dias_reposo_total'])->toBe(17);
});

test('sin accidentes las tres cifras son cero y no nulas', function () {
    // `SUM` de cero filas devuelve NULL en SQL: sin el COALESCE, «días de
    // reposo» llegaría como null al tablero.
    $accidentes = resumenDelTablero()['accidentes'];

    expect($accidentes['total'])->toBe(0);
    expect($accidentes['con_atencion_medica'])->toBe(0);
    expect($accidentes['dias_reposo_total'])->toBe(0);
});

// ── Tamizajes ─────────────────────────────────────────────────────────

test('el psicosocial cuenta campañas activas, respuestas y riesgo alto', function () {
    $campania = fn(bool $activa) => EvaluacionPsicosocial::create([
        'periodo' => '2026',
        'codigo_acceso' => strtoupper(uniqid()),
        'fecha_apertura' => '2026-01-15',
        'activa' => $activa,
        'creado_por' => $this->usuario->id,
    ]);

    $abierta = $campania(true);
    $cerrada = $campania(false);

    $respuesta = fn(EvaluacionPsicosocial $c, string $nivel) => RespuestaPsicosocial::create([
        'evaluacion_psicosocial_id' => $c->id,
        'respuestas' => array_fill_keys(range(1, 58), 2),
        'puntajes_dimensiones' => [],
        'puntaje_global' => 116,
        'nivel_riesgo_global' => $nivel,
    ]);

    $respuesta($abierta, NivelRiesgoPsicosocial::ALTO->value);
    $respuesta($abierta, NivelRiesgoPsicosocial::MEDIO->value);
    $respuesta($cerrada, NivelRiesgoPsicosocial::ALTO->value);

    $psicosocial = resumenDelTablero()['psicosocial'];

    expect($psicosocial['campanias_activas'])->toBe(1);
    // Las tres respuestas del período, de campañas abiertas y cerradas.
    expect($psicosocial['total_respuestas'])->toBe(3);
    expect($psicosocial['riesgo_alto'])->toBe(2);
});

test('el ASSIST distingue el riesgo alto de quien no reporta consumo', function () {
    $campania = EvaluacionAssist::create([
        'periodo' => '2026',
        'codigo_acceso' => strtoupper(uniqid()),
        'fecha_apertura' => '2026-02-01',
        'activa' => true,
        'creado_por' => $this->usuario->id,
    ]);

    $respuesta = fn(array $niveles, string $maximo) => RespuestaAssist::create([
        'evaluacion_assist_id' => $campania->id,
        'respuestas' => [],
        'puntajes' => [],
        'niveles_riesgo' => $niveles,
        'nivel_riesgo_maximo' => $maximo,
        'uso_inyectable' => 'no_nunca',
    ]);

    $respuesta(['alcohol' => 'alto'], NivelRiesgoAssist::ALTO->value);
    $respuesta(['tabaco' => 'moderado'], NivelRiesgoAssist::MODERADO->value);
    // Sin consumo: el objeto JSON viaja vacío.
    $respuesta([], NivelRiesgoAssist::BAJO->value);
    $respuesta([], NivelRiesgoAssist::BAJO->value);

    $assist = resumenDelTablero()['assist'];

    expect($assist['campanias_activas'])->toBe(1);
    expect($assist['total_respuestas'])->toBe(4);
    expect($assist['riesgo_alto'])->toBe(1);
    expect($assist['sin_consumo_reportado'])->toBe(2);
});

test('las respuestas de otro período no entran en el resumen', function () {
    $otra = EvaluacionAssist::create([
        'periodo' => '2025',
        'codigo_acceso' => strtoupper(uniqid()),
        'fecha_apertura' => '2025-02-01',
        'activa' => true,
        'creado_por' => $this->usuario->id,
    ]);

    RespuestaAssist::create([
        'evaluacion_assist_id' => $otra->id,
        'respuestas' => [], 'puntajes' => [], 'niveles_riesgo' => [],
        'nivel_riesgo_maximo' => NivelRiesgoAssist::BAJO->value,
        'uso_inyectable' => 'no_nunca',
    ]);

    expect(resumenDelTablero('2026')['assist']['total_respuestas'])->toBe(0);
});

// ── Ausentismo ────────────────────────────────────────────────────────

test('el ausentismo suma las horas de los permisos por enfermedad', function () {
    $permiso = fn(string $fecha, string $desde, string $hasta, TipoPermiso $tipo, EstadoPermiso $estado) => PermisoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo' => $tipo,
        'fecha' => $fecha,
        'hora_inicio' => $desde,
        'hora_fin' => $hasta,
        'observacion' => 'Prueba',
        'estado' => $estado,
        'vence_en' => "{$fecha} 23:59:59",
    ]);

    // Ocho horas: una jornada completa.
    $permiso('2026-04-02', '08:00:00', '16:00:00', TipoPermiso::ENFERMEDAD, EstadoPermiso::ACTIVO);
    // Cuatro horas: media jornada.
    $permiso('2026-04-03', '08:00:00', '12:00:00', TipoPermiso::ENFERMEDAD, EstadoPermiso::ACTIVO);
    // Los anulados y los pendientes no cuentan.
    $permiso('2026-04-04', '08:00:00', '16:00:00', TipoPermiso::ENFERMEDAD, EstadoPermiso::ANULADO);
    $permiso('2026-04-06', '08:00:00', '16:00:00', TipoPermiso::ENFERMEDAD, EstadoPermiso::PENDIENTE);
    // Tampoco los de otro tipo.
    $permiso('2026-04-05', '08:00:00', '16:00:00', TipoPermiso::PERSONAL, EstadoPermiso::ACTIVO);

    $ausentismo = resumenDelTablero()['ausentismo'];

    expect($ausentismo['total_permisos'])->toBe(2);
    expect($ausentismo['servidores_afectados'])->toBe(1);
    // 12 horas sobre una jornada de 8 = 1,5 días.
    expect($ausentismo['total_dias'])->toBe(1.5);
});

test('sin permisos por enfermedad el ausentismo es cero', function () {
    $ausentismo = resumenDelTablero()['ausentismo'];

    expect($ausentismo['total_permisos'])->toBe(0);
    expect($ausentismo['servidores_afectados'])->toBe(0);
    expect($ausentismo['total_dias'])->toBe(0.0);
});

// ── EPP ───────────────────────────────────────────────────────────────

test('el EPP cuenta los equipos activos del catálogo', function () {
    EquipoProteccion::create(['codigo' => 'EPP-1', 'nombre' => 'Casco', 'tipo' => 'cabeza', 'estado' => true]);
    EquipoProteccion::create(['codigo' => 'EPP-2', 'nombre' => 'Guantes', 'tipo' => 'manos', 'estado' => true]);
    EquipoProteccion::create(['codigo' => 'EPP-3', 'nombre' => 'Botas viejas', 'tipo' => 'pies', 'estado' => false]);

    expect(resumenDelTablero()['epp']['equipos_activos'])->toBe(2);
});
