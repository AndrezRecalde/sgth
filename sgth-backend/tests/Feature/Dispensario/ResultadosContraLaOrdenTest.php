<?php

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/*
 * El resultado contra la orden (fase 3 de docs/diseno-examenes-medicos.md): un
 * archivo cubre uno o varios exámenes de la orden, que pasan a recibidos; lo que
 * no va a volver se marca como no realizado; la orden se recalcula sola, y
 * retirar un resultado devuelve sus exámenes a pendiente.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();
    Storage::fake('local');

    $usuario = function (string $alias, string $rol) {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

        return $user;
    };

    $this->medico = $usuario('contra-orden-medico', 'medico');
    $this->enfermera = $usuario('contra-orden-enfermera', 'enfermera');

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Resultados contra Orden']);
    $puesto = puestoDePrueba($unidad, 'Analista de Resultados contra Orden');
    $historia = function (string $cedula) use ($unidad, $puesto) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => 'Paciente', 'apellido' => $cedula,
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
        ]);

        return HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);
    };
    $consulta = fn (HistoriaClinica $h) => ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $h->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => now()->subDay(),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);

    $this->historia = $historia('0812460001');
    $this->consulta = $consulta($this->historia);
    $this->otraHistoria = $historia('0812460002');
    $this->otraConsulta = $consulta($this->otraHistoria);

    $this->actingAs($this->medico, 'sanctum');
});

/** Una orden emitida con exámenes del catálogo, por nombre. */
function ordenParaResultados(ConsultaMedica $consulta, array $nombres): OrdenExamen
{
    return app(OrdenExamenServiceInterface::class)->emitir(
        [
            'historia_clinica_id' => $consulta->historia_clinica_id,
            'consulta_medica_id'  => $consulta->id,
            'fecha_solicitud'     => now()->toDateString(),
        ],
        array_map(fn ($n) => ['catalogo_examen_id' => CatalogoExamen::where('nombre', $n)->value('id')], $nombres),
        $consulta->medico_id,
    )->load('items.examen');
}

/** El id del ítem de la orden que corresponde a ese examen. */
function itemDeOrden(OrdenExamen $orden, string $nombre): int
{
    return $orden->items->firstOrFail(fn ($i) => $i->examen->nombre === $nombre)->id;
}

function subirContraOrden(HistoriaClinica $historia, ?OrdenExamen $orden, array $items): Illuminate\Testing\TestResponse
{
    return test()->post('/api/v1/dispensario/resultados-medicos', array_filter([
        'historia_clinica_id' => $historia->id,
        'orden_examen_id'     => $orden?->id,
        'items'               => $items ?: null,
        'tipo'                => 'laboratorio',
        'descripcion'         => 'Informe del laboratorio',
        'fecha_resultado'     => now()->toDateString(),
        'archivo'             => UploadedFile::fake()->create('informe.pdf', 40, 'application/pdf'),
    ], fn ($v) => $v !== null), ['Accept' => 'application/json']);
}

/** @return array<string, string> examen => estado */
function estadosDeLaOrden(OrdenExamen $orden): array
{
    return $orden->fresh()->items()->with('examen')->get()
        ->mapWithKeys(fn ($i) => [$i->examen->nombre => $i->estado->value])->all();
}

test('un_archivo_cubre_varios_examenes_y_la_orden_se_completa_con_el_siguiente', function () {
    $orden = ordenParaResultados($this->consulta, ['Glucosa', 'Colesterol', 'Hemograma completo']);

    $resultado = subirContraOrden($this->historia, $orden, [
        itemDeOrden($orden, 'Glucosa'), itemDeOrden($orden, 'Colesterol'),
    ])->assertCreated()->json('datos');

    expect($resultado['orden_examen']['folio'])->toBe($orden->folio)
        ->and(collect($resultado['items_orden'])->pluck('examen.nombre')->all())->toBe(['Glucosa', 'Colesterol'])
        ->and($orden->fresh()->estado->value)->toBe('parcial')
        ->and(estadosDeLaOrden($orden))->toBe([
            'Glucosa' => 'recibido', 'Colesterol' => 'recibido', 'Hemograma completo' => 'pendiente',
        ]);

    subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Hemograma completo')])->assertCreated();

    expect($orden->fresh()->estado->value)->toBe('completa');

    // El listado del paciente dice de qué orden es cada uno.
    $listado = $this->getJson("/api/v1/dispensario/resultados-medicos?historia_clinica_id={$this->historia->id}")
        ->assertOk()->json('datos');
    expect(collect($listado)->pluck('orden_examen.folio')->unique()->all())->toBe([$orden->folio]);
});

test('lo_que_no_va_a_volver_se_marca_como_no_realizado', function () {
    $orden = ordenParaResultados($this->consulta, ['Glucosa', 'Coproparasitario']);
    subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Glucosa')])->assertCreated();

    $this->patchJson(
        "/api/v1/dispensario/ordenes-examen/{$orden->id}/items/" . itemDeOrden($orden, 'Coproparasitario') . '/no-realizado',
        ['motivo_no_realizado' => 'El paciente no entregó la muestra']
    )->assertOk()->assertJsonPath('datos.estado', 'completa');

    expect(estadosDeLaOrden($orden))->toBe(['Glucosa' => 'recibido', 'Coproparasitario' => 'no_realizado'])
        ->and($orden->fresh()->items->firstWhere('estado.value', 'no_realizado')->motivo_no_realizado)
        ->toBe('El paciente no entregó la muestra');
});

test('retirar_un_resultado_devuelve_sus_examenes_a_pendiente_y_reabre_la_orden', function () {
    $orden = ordenParaResultados($this->consulta, ['Glucosa', 'Colesterol']);
    $id = subirContraOrden($this->historia, $orden, [
        itemDeOrden($orden, 'Glucosa'), itemDeOrden($orden, 'Colesterol'),
    ])->assertCreated()->json('datos.id');
    expect($orden->fresh()->estado->value)->toBe('completa');

    $this->deleteJson("/api/v1/dispensario/resultados-medicos/{$id}")->assertOk();

    expect($orden->fresh()->estado->value)->toBe('emitida')
        ->and(estadosDeLaOrden($orden))->toBe(['Glucosa' => 'pendiente', 'Colesterol' => 'pendiente']);

    // Y se puede subir el archivo bueno.
    subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Glucosa')])->assertCreated();
    expect($orden->fresh()->estado->value)->toBe('parcial');
});

test('un_examen_no_realizado_se_cubre_si_al_final_llega', function () {
    $orden = ordenParaResultados($this->consulta, ['Glucosa', 'Colesterol']);
    $this->patchJson(
        "/api/v1/dispensario/ordenes-examen/{$orden->id}/items/" . itemDeOrden($orden, 'Glucosa') . '/no-realizado',
        ['motivo_no_realizado' => 'No asistió']
    )->assertOk();

    subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Glucosa')])->assertCreated();

    expect(estadosDeLaOrden($orden)['Glucosa'])->toBe('recibido')
        ->and($orden->fresh()->items->first()->motivo_no_realizado)->toBeNull();
});

test('la_orden_y_sus_examenes_tienen_que_ser_del_paciente_y_de_la_orden', function () {
    $orden = ordenParaResultados($this->consulta, ['Glucosa']);
    $ajena = ordenParaResultados($this->otraConsulta, ['Glucosa']);

    $errores = subirContraOrden($this->historia, $ajena, [itemDeOrden($ajena, 'Glucosa')])
        ->assertStatus(422)->json('errores');
    expect($errores['orden_examen_id'][0])->toBe('La orden indicada no es de este paciente.');

    $errores = subirContraOrden($this->historia, $orden, [itemDeOrden($ajena, 'Glucosa')])
        ->assertStatus(422)->json('errores');
    expect($errores['items.0'][0])->toBe('Ese examen no es de la orden indicada.');

    $errores = subirContraOrden($this->historia, $orden, [])->assertStatus(422)->json('errores');
    expect($errores['items'][0])->toBe('Marque qué exámenes de la orden cubre el archivo.');

    $errores = subirContraOrden($this->historia, null, [itemDeOrden($orden, 'Glucosa')])
        ->assertStatus(422)->json('errores');
    expect($errores['items'][0])->toBe('Los exámenes se marcan solo cuando el resultado es de una orden.');

    expect(ResultadoMedico::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('resultados-medicos'))->toBe([]);
});

test('una_orden_cerrada_no_admite_resultados_ni_un_examen_se_cubre_dos_veces', function () {
    $orden = ordenParaResultados($this->consulta, ['Glucosa', 'Colesterol']);
    subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Glucosa')])->assertCreated();

    $errores = subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Glucosa')])
        ->assertStatus(422)->json('errores');
    expect($errores['items'][0])->toBe('Ya tienen resultado: Glucosa. Si se subió el archivo equivocado, retírelo antes.');

    $this->patchJson("/api/v1/dispensario/ordenes-examen/{$orden->id}/anular", ['motivo_anulacion' => 'Duplicada'])
        ->assertOk();

    $errores = subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Colesterol')])
        ->assertStatus(422)->json('errores');
    expect($errores['orden_examen_id'][0])
        ->toBe("La orden {$orden->folio} está anulada: suba el resultado sin orden o contra una orden nueva.");

    // Sin orden sí: el resultado tardío se sube suelto (regla 2).
    subirContraOrden($this->historia, null, [])->assertCreated();
});

test('anular_la_orden_no_toca_sus_resultados_ni_retirarlos_la_reabre', function () {
    $orden = ordenParaResultados($this->consulta, ['Glucosa', 'Colesterol']);
    $id = subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Glucosa')])
        ->assertCreated()->json('datos.id');

    $this->patchJson("/api/v1/dispensario/ordenes-examen/{$orden->id}/anular", ['motivo_anulacion' => 'Duplicada'])
        ->assertOk();
    expect(ResultadoMedico::count())->toBe(1);

    $this->deleteJson("/api/v1/dispensario/resultados-medicos/{$id}")->assertOk();
    expect($orden->fresh()->estado->value)->toBe('anulada');
});

test('no_realizado_lo_marca_quien_registra_y_solo_sobre_un_pendiente', function () {
    $orden = ordenParaResultados($this->consulta, ['Glucosa', 'Colesterol']);
    $otra = ordenParaResultados($this->consulta, ['Hemograma completo']);
    $url = fn (OrdenExamen $o, int $item) => "/api/v1/dispensario/ordenes-examen/{$o->id}/items/{$item}/no-realizado";

    $this->actingAs($this->enfermera, 'sanctum')
        ->patchJson($url($orden, itemDeOrden($orden, 'Colesterol')), ['motivo_no_realizado' => 'No asistió'])
        ->assertForbidden();

    $this->actingAs($this->medico, 'sanctum');

    $this->patchJson($url($orden, itemDeOrden($orden, 'Colesterol')), [])
        ->assertStatus(422)
        ->assertJsonPath('errores.motivo_no_realizado.0', 'Indique por qué no se realizó.');

    // Un examen de otra orden no se encuentra en esta.
    $this->patchJson($url($orden, itemDeOrden($otra, 'Hemograma completo')), ['motivo_no_realizado' => 'x'])
        ->assertNotFound();

    subirContraOrden($this->historia, $orden, [itemDeOrden($orden, 'Glucosa')])->assertCreated();
    $this->patchJson($url($orden, itemDeOrden($orden, 'Glucosa')), ['motivo_no_realizado' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', "Glucosa ya no está pendiente en la orden {$orden->folio}.");

    expect(estadosDeLaOrden($orden)['Colesterol'])->toBe('pendiente');
});
