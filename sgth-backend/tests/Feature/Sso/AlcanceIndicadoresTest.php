<?php

/*
| El alcance de los indicadores: sobre qué población está cada cifra.
|
| `calcularIndicadoresProactivos()` y `DashboardSsoService::resumen()` reciben
| una unidad administrativa. El primero se la pasaba a uno de sus tres
| indicadores; el segundo, a dos de sus nueve bloques. Los demás la ignoraban
| en silencio y la respuesta se devolvía igual, así que pedir el tablero de una
| dirección daba cifras institucionales presentadas como si fueran de esa
| dirección. Es el tipo de número que acaba en un informe al Ministerio.
|
| La regla ahora: filtra quien puede, y quien no puede lo DICE.
*/

use App\Models\Expediente\Servidor;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\CapacitacionSso;
use App\Models\Sso\EppEntrega;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\EvaluacionPsicosocial;
use App\Models\Sso\FactorRiesgoCatalogo;
use App\Models\Sso\InspeccionSso;
use App\Models\Sso\PuestoEpp;
use App\Models\Sso\RiesgoLaboral;
use App\Models\User;
use App\Services\Sso\DashboardSsoService;
use App\Services\Sso\SsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();

    $this->usuario = User::create([
        'email' => 'alcance@gadpe.gob.ec',
        'usuario_ti' => 'alcance',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);

    // Dos unidades, para que «de la unidad» se distinga de «de todo».
    $this->obras = unidadDePrueba(['nombre' => 'Dirección de Obras Públicas']);
    $this->financiero = unidadDePrueba(['nombre' => 'Dirección Financiera']);

    $this->puestoObras = puestoDePrueba($this->obras, 'Operador de maquinaria');
    $this->puestoFinanciero = puestoDePrueba($this->financiero, 'Analista');

    $this->anaObras = Servidor::create([
        'cedula' => '0800000071', 'nombre' => 'Ana', 'apellido' => 'Obras',
        'unidad_administrativa_id' => $this->obras->id,
        'puesto_id' => $this->puestoObras->id, 'estado' => true,
    ]);

    $this->betoFinanciero = Servidor::create([
        'cedula' => '0800000072', 'nombre' => 'Beto', 'apellido' => 'Financiero',
        'unidad_administrativa_id' => $this->financiero->id,
        'puesto_id' => $this->puestoFinanciero->id, 'estado' => true,
    ]);

    $this->factor = FactorRiesgoCatalogo::create([
        'nombre' => 'Ruido continuo', 'categoria' => 'fisico', 'activo' => true,
    ]);

    $this->sso = app(SsoService::class);
    $this->tablero = app(DashboardSsoService::class);
});

function riesgoEn(int $puestoId): RiesgoLaboral
{
    return RiesgoLaboral::create([
        'puesto_id' => $puestoId,
        'factor_riesgo_id' => test()->factor->id,
        'descripcion' => 'Riesgo de prueba',
        'nivel_deficiencia' => 'mejorable',
        'nivel_exposicion' => 'ocasional',
        'nivel_consecuencias' => 'leve',
        'nivel_intervencion' => 'iv',
        'nivel_riesgo_valor' => 40,
        'estado' => true,
    ]);
}

// ── Lo que sí puede filtrar, y ahora filtra ───────────────────────────

test('la cobertura de EPP se calcula sobre los puestos de la unidad pedida', function () {
    // Antes salía institucional sin motivo: `puesto_epp` llega a la unidad por
    // `puestos.unidad_administrativa_id`.
    $casco = EquipoProteccion::create([
        'codigo' => 'EPP-001', 'nombre' => 'Casco', 'tipo' => 'craneal', 'estado' => true,
    ]);

    PuestoEpp::create([
        'puesto_id' => $this->puestoObras->id,
        'equipo_proteccion_id' => $casco->id,
        'cantidad_requerida' => 1,
    ]);
    PuestoEpp::create([
        'puesto_id' => $this->puestoFinanciero->id,
        'equipo_proteccion_id' => $casco->id,
        'cantidad_requerida' => 1,
    ]);

    // Solo el de Obras recibió el suyo.
    EppEntrega::create([
        'servidor_id' => $this->anaObras->id,
        'equipo_proteccion_id' => $casco->id,
        'fecha_entrega' => '2026-03-10',
        'cantidad' => 1, 'motivo' => 'entrega',
        'entregado_por' => $this->usuario->id,
    ]);

    // Institucional: dos puestos requieren, uno recibió → 50 %.
    $todo = $this->sso->calcularIndicadoresProactivos('2026');
    expect($todo['cobertura_epp']['total_puestos_con_epp_requerido'])->toBe(2);
    expect($todo['cobertura_epp']['porcentaje'])->toBe(50.0);

    // De Obras: un puesto requiere, ese recibió → 100 %.
    $deObras = $this->sso->calcularIndicadoresProactivos('2026', $this->obras->id);
    expect($deObras['cobertura_epp']['total_puestos_con_epp_requerido'])->toBe(1);
    expect($deObras['cobertura_epp']['porcentaje'])->toBe(100.0);

    // De Financiero: un puesto requiere, no recibió → 0 %.
    $deFinanciero = $this->sso->calcularIndicadoresProactivos('2026', $this->financiero->id);
    expect($deFinanciero['cobertura_epp']['porcentaje'])->toBe(0.0);
});

test('el denominador de la cobertura también se filtra, no solo el numerador', function () {
    // Filtrar solo uno de los dos daría los puestos de una unidad sobre los
    // puestos de toda la institución: un porcentaje sin sentido, y el error
    // que invita a cometer una cobertura calculada a medias.
    $casco = EquipoProteccion::create([
        'codigo' => 'EPP-002', 'nombre' => 'Casco', 'tipo' => 'craneal', 'estado' => true,
    ]);

    foreach ([$this->puestoObras->id, $this->puestoFinanciero->id] as $puestoId) {
        PuestoEpp::create([
            'puesto_id' => $puestoId,
            'equipo_proteccion_id' => $casco->id,
            'cantidad_requerida' => 1,
        ]);
    }

    $deObras = $this->sso->calcularIndicadoresProactivos('2026', $this->obras->id);

    expect($deObras['cobertura_epp']['total_puestos_con_epp_requerido'])->toBe(1);
});

test('las inspecciones siguen filtrando por unidad', function () {
    $inspeccion = fn(int $unidadId) => InspeccionSso::create([
        'unidad_administrativa_id' => $unidadId,
        'fecha_inspeccion' => '2026-03-10',
        'tipo_inspeccion' => 'Planificada',
        'inspector_id' => $this->usuario->id,
        'estado' => true,
    ]);

    $inspeccion($this->obras->id);
    $inspeccion($this->obras->id);
    $inspeccion($this->financiero->id);

    expect($this->sso->calcularIndicadoresProactivos('2026')['inspecciones_realizadas'])->toBe(3);
    expect($this->sso->calcularIndicadoresProactivos('2026', $this->obras->id)['inspecciones_realizadas'])->toBe(2);
});

// ── Lo que no puede filtrar, y ahora lo dice ──────────────────────────

test('las capacitaciones declaran que son institucionales, y explican por qué', function () {
    // `capacitaciones_sso` no tiene columna de unidad. Eso no se inventa: se
    // declara, y la pantalla lo muestra.
    CapacitacionSso::create([
        'tema' => 'Uso de EPP', 'fecha' => '2026-03-01',
        'duracion_horas' => 2, 'instructor' => 'Instructor', 'estado' => true,
    ]);

    $deObras = $this->sso->calcularIndicadoresProactivos('2026', $this->obras->id);

    // La cifra sigue siendo la institucional, que es la única que hay.
    expect($deObras['capacitaciones_realizadas'])->toBe(1);
    expect($deObras['alcances']['capacitaciones']['alcance'])->toBe('institucional');
    expect($deObras['alcances']['capacitaciones']['nota'])->toContain('no se registran por unidad');

    // Y los otros dos sí son de la unidad.
    expect($deObras['alcances']['inspecciones']['alcance'])->toBe('unidad');
    expect($deObras['alcances']['cobertura_epp']['alcance'])->toBe('unidad');
});

test('sin unidad pedida no hay nada que advertir', function () {
    // Todo es institucional y la nota sobraría: avisar de lo que nadie pidió
    // es ruido.
    $todo = $this->sso->calcularIndicadoresProactivos('2026');

    foreach (['inspecciones', 'capacitaciones', 'cobertura_epp'] as $indicador) {
        expect($todo['alcances'][$indicador]['alcance'])->toBe('institucional');
        expect($todo['alcances'][$indicador]['nota'])->toBeNull();
    }
});

// ── El tablero, bloque por bloque ─────────────────────────────────────

test('los riesgos del tablero se filtran por la unidad del puesto', function () {
    riesgoEn($this->puestoObras->id);
    riesgoEn($this->puestoObras->id);
    riesgoEn($this->puestoFinanciero->id);

    expect($this->tablero->resumen('2026')['riesgos']['total_activos'])->toBe(3);
    expect($this->tablero->resumen('2026', $this->obras->id)['riesgos']['total_activos'])->toBe(2);
});

test('los accidentes del tablero se filtran por la unidad del servidor', function () {
    $accidente = fn(int $servidorId) => AccidenteTrabajo::create([
        'servidor_id' => $servidorId,
        'tipo_evento' => 'accidente',
        'fecha_accidente' => '2026-03-10',
        'hora_accidente' => '09:00',
        'lugar_accidente' => 'Patio',
        'descripcion_hechos' => 'Prueba',
        'gravedad' => 'leve',
        'requirio_atencion_medica' => true,
        'dias_reposo_medico' => 3,
        'estado' => true,
    ]);

    $accidente($this->anaObras->id);
    $accidente($this->betoFinanciero->id);

    expect($this->tablero->resumen('2026')['accidentes']['total'])->toBe(2);

    $deObras = $this->tablero->resumen('2026', $this->obras->id)['accidentes'];
    expect($deObras['total'])->toBe(1);
    // Y el reposo acompaña al conteo: es el numerador del índice de gravedad.
    expect($deObras['dias_reposo_total'])->toBe(3);
});

test('las campañas de tamizaje se filtran por su propia unidad', function () {
    $campania = fn(?int $unidadId) => EvaluacionPsicosocial::create([
        'periodo' => '2026',
        'unidad_administrativa_id' => $unidadId,
        'codigo_acceso' => strtoupper(Str::random(8)),
        'fecha_apertura' => '2026-01-15',
        'activa' => true,
        'creado_por' => $this->usuario->id,
    ]);

    $campania($this->obras->id);
    $campania($this->financiero->id);
    // Una de toda la institución: no es de ninguna unidad en particular.
    $campania(null);

    expect($this->tablero->resumen('2026')['psicosocial']['campanias_activas'])->toBe(3);
    expect($this->tablero->resumen('2026', $this->obras->id)['psicosocial']['campanias_activas'])->toBe(1);
});

test('el tablero declara el alcance de sus nueve bloques', function () {
    $resumen = $this->tablero->resumen('2026', $this->obras->id);

    $deLaUnidad = ['riesgos', 'accidentes', 'psicosocial', 'assist', 'ausentismo'];
    $institucionales = ['epp', 'cumplimiento', 'programa_drogas'];

    foreach ($deLaUnidad as $bloque) {
        expect($resumen['alcances'][$bloque]['alcance'])->toBe('unidad', "{$bloque} debería ser de la unidad");
    }

    foreach ($institucionales as $bloque) {
        expect($resumen['alcances'][$bloque]['alcance'])->toBe('institucional', "{$bloque} debería ser institucional");
        // Y con el motivo, porque se pidió una unidad y no se puede dar.
        expect($resumen['alcances'][$bloque]['nota'])->toBeString();
    }
});

test('el tablero devuelve la unidad que se le pidió', function () {
    // Sin esto, la pantalla no puede titular el resumen con la unidad sin
    // suponer que el backend la respetó.
    expect($this->tablero->resumen('2026', $this->obras->id)['unidad_administrativa_id'])
        ->toBe($this->obras->id);
    expect($this->tablero->resumen('2026')['unidad_administrativa_id'])->toBeNull();
});

// ── La columna que no aceptaba medias horas ───────────────────────────

test('una capacitación de hora y media se registra y suma como tal', function () {
    // `duracion_horas` era `integer` y la validación decía `numeric|min:0.5`:
    // 1.5 daba un 500 con `22P02`. Y esta cifra se suma en
    // `horas_capacitacion_total`, así que redondear desviaba el total.
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    $this->usuario->assignRole('admin-uath');

    $this->actingAs($this->usuario, 'sanctum')
        ->postJson('/api/v1/sso/capacitaciones', [
            'tema' => 'Uso de EPP',
            'fecha' => '2026-03-01',
            'duracion_horas' => 1.5,
            'instructor' => 'Instructor',
        ])
        ->assertCreated()
        ->assertJsonPath('datos.duracion_horas', 1.5);

    expect($this->sso->calcularIndicadoresProactivos('2026')['horas_capacitacion_total'])
        ->toBe(1.5);
});

test('las horas fraccionarias se suman sin redondear el total del período', function () {
    foreach ([1.5, 2.25, 0.5] as $horas) {
        CapacitacionSso::create([
            'tema' => "Capacitación de {$horas} h",
            'fecha' => '2026-03-01',
            'duracion_horas' => $horas,
            'instructor' => 'Instructor',
            'estado' => true,
        ]);
    }

    expect($this->sso->calcularIndicadoresProactivos('2026')['horas_capacitacion_total'])
        ->toBe(4.25);
});
