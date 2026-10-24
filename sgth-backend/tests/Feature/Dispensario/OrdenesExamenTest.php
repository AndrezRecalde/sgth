<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * La orden de exámenes (fase 1 de docs/diseno-examenes-medicos.md): la emite el
 * médico en su consulta, con exámenes del catálogo activo; un sensible exige
 * la constancia del consentimiento; la anula quien la emitió o la
 * administración, y vence a los 90 días sin resultados.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $usuario = function (string $alias, string $rol) {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

        return $user;
    };

    $this->medico = $usuario('orden-medico', 'medico');
    $this->colega = $usuario('orden-colega', 'medico');
    $this->enfermera = $usuario('orden-enfermera', 'enfermera');
    $this->admin = $usuario('orden-admin', 'admin-dispensario');

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Ordenes de Examen']);
    $puesto = puestoDePrueba($unidad, 'Analista de Ordenes de Examen');
    $historia = function (string $cedula) use ($unidad, $puesto) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => 'Paciente', 'apellido' => $cedula,
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
        ]);

        return HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);
    };

    $this->historia = $historia('0812430001');
    $this->otraHistoria = $historia('0812430002');
    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => now()->subDays(2),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
});

/** Ítems de una orden a partir de nombres del catálogo, con su nota si se da. */
function itemsDeOrden(array $examenes): array
{
    return collect($examenes)->map(fn ($nota, $nombre) => [
        'catalogo_examen_id' => CatalogoExamen::where('nombre', is_int($nombre) ? $nota : $nombre)->value('id'),
        'nota'               => is_int($nombre) ? null : $nota,
    ])->values()->all();
}

function datosDeOrden(ConsultaMedica $consulta, array $examenes, array $extra = []): array
{
    return [
        'consulta_medica_id' => $consulta->id,
        'fecha_solicitud'    => now()->toDateString(),
        'items'              => itemsDeOrden($examenes),
        ...$extra,
    ];
}

test('el_medico_emite_una_orden_en_su_consulta', function () {
    $orden = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, [
            'Hemograma completo', 'Tolerancia oral a la glucosa' => '75 g',
        ]))
        ->assertCreated()
        ->json('datos');

    expect($orden['folio'])->toBe('EXA-' . date('Y') . '-00001')
        ->and($orden['estado'])->toBe('emitida')
        ->and($orden['prioridad'])->toBe('rutina')
        ->and($orden['vencida'])->toBeFalse()
        ->and($orden['historia_clinica_id'])->toBe($this->historia->id)
        ->and($orden['solicitado_por'])->toBe($this->medico->id)
        ->and(collect($orden['items'])->map(fn ($i) => [$i['examen']['nombre'], $i['nota'], $i['estado']])->all())
        ->toBe([
            ['Hemograma completo', null, 'pendiente'],
            ['Tolerancia oral a la glucosa', '75 g', 'pendiente'],
        ]);

    // La siguiente sigue la numeración.
    $this->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa']))
        ->assertCreated()
        ->assertJsonPath('datos.folio', 'EXA-' . date('Y') . '-00002');
});

test('solo_quien_atendio_la_consulta_emite_la_orden', function () {
    $this->actingAs($this->colega, 'sanctum')
        ->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa']))
        ->assertForbidden();

    $this->actingAs($this->enfermera, 'sanctum')
        ->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa']))
        ->assertForbidden();

    expect(OrdenExamen::count())->toBe(0);
});

test('no_repite_examenes_ni_pide_retirados', function () {
    $this->actingAs($this->medico, 'sanctum');
    $glucosa = CatalogoExamen::where('nombre', 'Glucosa')->value('id');

    $errores = $this->postJson('/api/v1/dispensario/ordenes-examen', [
        ...datosDeOrden($this->consulta, []),
        'items' => [['catalogo_examen_id' => $glucosa], ['catalogo_examen_id' => $glucosa]],
    ])->assertStatus(422)->json('errores');
    expect($errores['items.1.catalogo_examen_id'][0] ?? null)->toBe('Ese examen ya está en la orden.');

    CatalogoExamen::where('id', $glucosa)->update(['activo' => false]);
    $errores = $this->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa']))
        ->assertStatus(422)->json('errores');
    expect($errores['items.0.catalogo_examen_id'][0] ?? null)->toBe('Ese examen no está activo en el catálogo.');
});

test('la_nota_solo_va_en_los_examenes_que_la_llevan', function () {
    $errores = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa' => 'en ayunas']))
        ->assertStatus(422)->json('errores');

    expect($errores['items.0.nota'][0] ?? null)->toBe('Glucosa no lleva nota.');
});

test('un_examen_sensible_exige_la_constancia_del_consentimiento', function () {
    $this->actingAs($this->medico, 'sanctum');

    $errores = $this->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, [
        'Hemograma completo', 'HIV 1/2/0',
    ]))->assertStatus(422)->json('errores');

    expect($errores['consentimiento_firmado'][0] ?? null)
        ->toBe('La orden lleva exámenes sensibles (HIV 1/2/0): confirme que el paciente firmó el consentimiento.');

    $this->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, [
        'Hemograma completo', 'HIV 1/2/0',
    ], ['consentimiento_firmado' => true]))
        ->assertCreated()
        ->assertJsonPath('datos.consentimiento_firmado', true);
});

test('la_fecha_no_es_futura_ni_anterior_a_la_consulta', function () {
    $this->actingAs($this->medico, 'sanctum');

    $futura = $this->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa'], [
        'fecha_solicitud' => now()->addDay()->toDateString(),
    ]))->assertStatus(422)->json('errores');
    expect($futura['fecha_solicitud'][0] ?? null)->toBe('La orden no puede tener fecha futura.');

    $anterior = $this->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa'], [
        'fecha_solicitud' => now()->subDays(3)->toDateString(),
    ]))->assertStatus(422)->json('errores');
    expect($anterior['fecha_solicitud'][0] ?? null)->toBe('La orden no puede ser anterior a la consulta que la origina.');
});

test('la_anula_quien_la_emitio_o_la_administracion_y_una_sola_vez', function () {
    $orden = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa']))
        ->json('datos');
    $url = "/api/v1/dispensario/ordenes-examen/{$orden['id']}/anular";

    $this->actingAs($this->colega, 'sanctum')
        ->patchJson($url, ['motivo_anulacion' => 'No era'])
        ->assertForbidden();

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson($url, ['motivo_anulacion' => 'Paciente equivocado'])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'anulada')
        ->assertJsonPath('datos.motivo_anulacion', 'Paciente equivocado');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson($url, ['motivo_anulacion' => 'Otra vez'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', "La orden {$orden['folio']} ya fue anulada.");
});

test('una_orden_completa_no_se_anula', function () {
    $orden = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa']))
        ->json('datos');
    OrdenExamen::where('id', $orden['id'])->update(['estado' => 'completa']);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/dispensario/ordenes-examen/{$orden['id']}/anular", ['motivo_anulacion' => 'Tarde'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', "La orden {$orden['folio']} ya tiene todos sus resultados: no se anula.");
});

test('el_listado_exige_el_paciente_y_marca_las_vencidas', function () {
    $this->actingAs($this->medico, 'sanctum');
    $vieja = $this->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Glucosa']))
        ->json('datos');
    $nueva = $this->postJson('/api/v1/dispensario/ordenes-examen', datosDeOrden($this->consulta, ['Urea']))
        ->json('datos');
    // Una de hace 91 días sin resultados: vencida.
    OrdenExamen::where('id', $vieja['id'])->update(['fecha_solicitud' => now()->subDays(91)->toDateString()]);

    $this->getJson('/api/v1/dispensario/ordenes-examen')->assertStatus(422);

    // Enfermería no las lista desde el 2026-10-24.
    $this->actingAs($this->enfermera, 'sanctum')
        ->getJson("/api/v1/dispensario/ordenes-examen?historia_clinica_id={$this->historia->id}")
        ->assertForbidden();

    $this->actingAs($this->medico, 'sanctum');
    $datos = $this->getJson("/api/v1/dispensario/ordenes-examen?historia_clinica_id={$this->historia->id}")
        ->assertOk()->json('datos');

    expect(collect($datos)->map(fn ($o) => [$o['id'], $o['vencida']])->all())
        ->toBe([[$nueva['id'], false], [$vieja['id'], true]])
        ->and($this->getJson("/api/v1/dispensario/ordenes-examen?historia_clinica_id={$this->otraHistoria->id}")->json('datos'))
        ->toBe([]);
});
