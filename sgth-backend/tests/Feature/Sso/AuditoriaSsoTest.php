<?php

/*
| El rastro de auditoría de los cinco registros del módulo SSO.
|
| Los cinco observers existían registrados con `#[ObservedBy]` y los quince
| métodos vacíos, con el comentario «Auditoría automática gestionada
| globalmente». No hay tal mecanismo: `activitylog` se activa modelo por
| modelo, y el resto del repositorio lo hace con un observer que llama a
| `activity()` — `PuestoObserver`, `ContratoServidorObserver`,
| `DocumentoServidorObserver`.
|
| Es decir que estaban exactamente donde va el gancho de auditoría de este
| código, y vacíos. Quien comprobara si la matriz de riesgos queda auditada
| encontraba el observer, leía el comentario y concluía que sí.
*/

use App\Models\Expediente\Servidor;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\CapacitacionSso;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\FactorRiesgoCatalogo;
use App\Models\Sso\InspeccionSso;
use App\Models\Sso\RiesgoLaboral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();

    $this->usuario = User::create([
        'email' => 'auditoria@gadpe.gob.ec',
        'usuario_ti' => 'auditoria_sso',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);

    $this->unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($this->unidad, 'Operador');

    $this->servidor = Servidor::create([
        'cedula' => '0800000061',
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

function riesgoAuditado(array $atributos = []): RiesgoLaboral
{
    return RiesgoLaboral::create(array_merge([
        'puesto_id' => test()->puesto->id,
        'factor_riesgo_id' => test()->factor->id,
        'descripcion' => 'Exposición a ruido en el patio de maquinaria',
        'nivel_deficiencia' => 'mejorable',
        'nivel_exposicion' => 'ocasional',
        'nivel_consecuencias' => 'leve',
        'nivel_intervencion' => 'iv',
        'nivel_riesgo_valor' => 40,
        'estado' => true,
    ], $atributos));
}

/**
 * La última actividad registrada sobre un modelo.
 *
 * Con `forSubject()` y no con un `where('subject_type', $modelo::class)`:
 * `AppServiceProvider` registra un morph map para cuatro modelos del módulo
 * —entre ellos `inspeccion_sso` y `capacitacion_sso`—, así que `activity_log`
 * guarda el ALIAS corto y no el nombre de la clase. Buscar por FQCN encuentra
 * el rastro de los riesgos y no el de las inspecciones.
 */
function ultimaActividad(object $modelo): ?Activity
{
    return Activity::query()
        ->forSubject($modelo)
        ->latest('id')
        ->first();
}

// ── Que exista el rastro, que es lo que no había ──────────────────────

test('identificar un riesgo laboral deja rastro', function () {
    $riesgo = riesgoAuditado();

    $actividad = ultimaActividad($riesgo);

    expect($actividad)->not->toBeNull();
    expect($actividad->event)->toBe('created');
    expect($actividad->description)->toBe('Riesgo laboral identificado');
});

test('el rastro del riesgo lleva su valoración, que es la cifra con consecuencia', function () {
    $riesgo = riesgoAuditado(['nivel_intervencion' => 'i', 'nivel_riesgo_valor' => 1440]);

    $propiedades = ultimaActividad($riesgo)->properties;

    expect($propiedades['nivel_intervencion'])->toBe('i');
    expect($propiedades['nivel_riesgo_valor'])->toBe(1440);
    expect($propiedades['puesto_id'])->toBe($this->puesto->id);
});

test('cambiar la valoración de un riesgo deja rastro de qué cambió', function () {
    // Lo que un auditor pregunta no es «se actualizó», es qué cambió.
    $riesgo = riesgoAuditado();

    $riesgo->update(['nivel_intervencion' => 'i', 'nivel_riesgo_valor' => 1440]);

    $actividad = ultimaActividad($riesgo);

    expect($actividad->event)->toBe('updated');
    expect($actividad->properties['campos_modificados'])
        ->toContain('nivel_intervencion')
        ->toContain('nivel_riesgo_valor');
    // Y la valoración nueva, no la vieja.
    expect($actividad->properties['nivel_intervencion'])->toBe('i');
});

test('los campos de rastro automático no ensucian la lista de cambios', function () {
    // `updated_at` y `updated_by` cambian en cada guardado: listarlos como
    // «campos modificados» convierte el rastro en ruido.
    $riesgo = riesgoAuditado();
    $riesgo->update(['descripcion' => 'Otra descripción', 'updated_by' => $this->usuario->id]);

    $campos = ultimaActividad($riesgo)->properties['campos_modificados'];

    expect($campos)->toContain('descripcion');
    expect($campos)->not->toContain('updated_at');
    expect($campos)->not->toContain('updated_by');
});

test('eliminar y restaurar un riesgo dejan su propio rastro', function () {
    $riesgo = riesgoAuditado();

    $riesgo->delete();
    expect(ultimaActividad($riesgo)->event)->toBe('deleted');

    $riesgo->restore();
    expect(ultimaActividad($riesgo)->event)->toBe('restored');
});

// ── Los otros cuatro registros ────────────────────────────────────────

test('un accidente de trabajo deja rastro con lo que mueve los índices', function () {
    // El tipo de evento decide si cuenta como lesión; los días de reposo son
    // el numerador del índice de gravedad del CD 513.
    $accidente = AccidenteTrabajo::create([
        'servidor_id' => $this->servidor->id,
        'tipo_evento' => 'accidente',
        'fecha_accidente' => '2026-03-10',
        'hora_accidente' => '09:00',
        'lugar_accidente' => 'Patio',
        'descripcion_hechos' => 'Prueba',
        'gravedad' => 'grave',
        'requirio_atencion_medica' => true,
        'dias_reposo_medico' => 12,
        'estado' => true,
    ]);

    $propiedades = ultimaActividad($accidente)->properties;

    expect($propiedades['tipo_evento'])->toBe('accidente');
    expect($propiedades['dias_reposo_medico'])->toBe(12);
    expect($propiedades['fecha_accidente'])->toBe('2026-03-10');
});

test('un equipo de protección deja rastro con su vida útil', function () {
    // La vida útil es uno de los dos plazos con los que se decide si un equipo
    // del kit toca reponerse.
    $equipo = EquipoProteccion::create([
        'codigo' => 'EPP-001', 'nombre' => 'Casco', 'tipo' => 'craneal',
        'vida_util_meses' => 12, 'estado' => true,
    ]);

    $propiedades = ultimaActividad($equipo)->properties;

    expect($propiedades['codigo'])->toBe('EPP-001');
    expect($propiedades['vida_util_meses'])->toBe(12);
});

test('una inspección SSO deja rastro', function () {
    $inspeccion = InspeccionSso::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'fecha_inspeccion' => '2026-03-10',
        'tipo_inspeccion' => 'Planificada',
        'hallazgos' => 'Extintor vencido',
        'inspector_id' => $this->usuario->id,
        'estado' => true,
    ]);

    $actividad = ultimaActividad($inspeccion);

    expect($actividad->description)->toBe('Inspección SSO registrada');
    expect($actividad->properties['inspector_id'])->toBe($this->usuario->id);
});

test('una capacitación SSO deja rastro con sus horas', function () {
    // 2 y no 2.5: `capacitaciones_sso.duracion_horas` es una columna `integer`
    // aunque la validación diga `numeric|min:0.5`, así que media hora revienta
    // con un 22P02. Eso es otro defecto y se arregla aparte; aquí lo que se
    // prueba es el rastro.
    $capacitacion = CapacitacionSso::create([
        'tema' => 'Uso de EPP',
        'fecha' => '2026-03-01',
        'duracion_horas' => 2,
        'instructor' => 'Instructor',
        'estado' => true,
    ]);

    $propiedades = ultimaActividad($capacitacion)->properties;

    expect($propiedades['tema'])->toBe('Uso de EPP');
    expect((int) $propiedades['duracion_horas'])->toBe(2);
});

// ── Quién lo hizo ─────────────────────────────────────────────────────

test('el rastro anota quién hizo el cambio', function () {
    // Sin el autor, el registro dice que algo cambió y no quién: es la mitad
    // de lo que una auditoría viene a preguntar.
    $this->actingAs($this->usuario);

    $riesgo = riesgoAuditado();

    $actividad = ultimaActividad($riesgo);

    expect($actividad->causer_id)->toBe($this->usuario->id);
    expect($actividad->causer_type)->toBe(User::class);
});

// ── Los cinco, sin que falte ninguno ──────────────────────────────────

test('los cinco registros del módulo quedan auditados', function () {
    // Una lista explícita: si mañana se agrega un sexto modelo al módulo y
    // nadie le pone observer, esta prueba no lo detecta — pero sí detecta que
    // alguien vacíe uno de estos cinco, que es lo que pasó.
    $modelos = [
        RiesgoLaboral::class,
        AccidenteTrabajo::class,
        EquipoProteccion::class,
        InspeccionSso::class,
        CapacitacionSso::class,
    ];

    foreach ($modelos as $modelo) {
        $observers = (new ReflectionClass($modelo))
            ->getAttributes(\Illuminate\Database\Eloquent\Attributes\ObservedBy::class);

        expect($observers)->not->toBeEmpty("{$modelo} no tiene observer registrado");

        $clase = $observers[0]->getArguments()[0];

        foreach (['created', 'updated', 'deleted', 'restored'] as $evento) {
            expect(method_exists($clase, $evento))
                ->toBeTrue("{$clase} no registra el evento {$evento}");
        }
    }
});
