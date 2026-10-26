<?php

/*
| Los datos clínicos, acotados a quien los necesita (docs/plan-roles-y-accesos.md,
| PR 5; decisión 5b del 2026-10-25).
|
| Antes las recetas, las notas de consulta y la bandeja de exámenes se servían
| enteras a todo el Dispensario. Ahora:
| - Recetas: el médico y el odontólogo, las suyas; Enfermería y la
|   administración, todas, para despacharlas. Y sin las notas de la consulta.
| - Notas de consulta: el médico y el odontólogo, las suyas y las de los
|   pacientes que atienden; Enfermería y la administración, ninguna.
| - Exámenes: cada profesional, los de su especialidad; la administración, todos.
*/

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\EstadoItemOrdenExamen;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->conRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->medico = ($this->conRol)('medico');
    $this->colega = ($this->conRol)('medico');
    $this->odontologo = ($this->conRol)('odontologo');

    // Una persona con su historia, y una consulta de quien se indique.
    $this->historiaDe = function (string $cedula): HistoriaClinica {
        $servidor = Servidor::forceCreate(['cedula' => $cedula, 'nombre' => 'Paciente', 'apellido' => $cedula]);

        return HistoriaClinica::forceCreate(['servidor_id' => $servidor->id]);
    };

    $this->consultaDe = fn (User $quien, HistoriaClinica $historia, string $especialidad = 'medicina_general') => ConsultaMedica::forceCreate([
        'especialidad' => $especialidad,
        'historia_clinica_id' => $historia->id,
        'medico_id' => $quien->id,
        'fecha_consulta' => now()->toDateString(),
        'hora_consulta' => '09:00:00',
        'motivo_consulta' => 'Dolor de cabeza',
        'enfermedad_actual' => 'Tres días de cefalea',
        'examen_fisico' => 'Sin hallazgos',
        'diagnostico_detallado' => 'Cefalea tensional',
        'plan_tratamiento' => 'Reposo',
        'notas_medico' => 'Nota privada del médico',
    ]);

    $this->recetaDe = fn (ConsultaMedica $consulta) => RecetaMedica::forceCreate([
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->toDateString(),
        'estado' => 'pendiente',
    ]);

    $this->ordenDe = fn (ConsultaMedica $consulta) => app(OrdenExamenServiceInterface::class)->emitir(
        [
            'historia_clinica_id' => $consulta->historia_clinica_id,
            'consulta_medica_id'  => $consulta->id,
            'fecha_solicitud'     => now()->toDateString(),
        ],
        [['catalogo_examen_id' => CatalogoExamen::where('nombre', 'Glucosa')->value('id')]],
        $consulta->medico_id,
    );
});

// ── Recetas ─────────────────────────────────────────────────────

it('el médico ve en el listado solo sus recetas', function () {
    $suya = ($this->recetaDe)(($this->consultaDe)($this->medico, ($this->historiaDe)('0809191901')));
    ($this->recetaDe)(($this->consultaDe)($this->colega, ($this->historiaDe)('0809191902')));

    $ids = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/recetas')
        ->assertOk()
        ->collect('datos.data')->pluck('id')->all();

    expect($ids)->toBe([$suya->id]);
});

it('quien despacha ve todas las recetas', function (string $rol) {
    ($this->recetaDe)(($this->consultaDe)($this->medico, ($this->historiaDe)('0809191901')));
    ($this->recetaDe)(($this->consultaDe)($this->colega, ($this->historiaDe)('0809191902')));

    $this->actingAs(($this->conRol)($rol), 'sanctum')
        ->getJson('/api/v1/dispensario/recetas')
        ->assertOk()
        ->assertJsonCount(2, 'datos.data');
})->with(['enfermera', 'admin-dispensario']);

it('el médico no abre ni imprime la receta de otro', function () {
    $ajena = ($this->recetaDe)(($this->consultaDe)($this->colega, ($this->historiaDe)('0809191902')));
    $suya = ($this->recetaDe)(($this->consultaDe)($this->medico, ($this->historiaDe)('0809191901')));

    $this->actingAs($this->medico, 'sanctum');

    $this->getJson("/api/v1/dispensario/recetas/{$ajena->id}")->assertForbidden();
    $this->getJson("/api/v1/dispensario/recetas/{$ajena->id}/pdf")->assertForbidden();
    $this->getJson("/api/v1/dispensario/recetas/{$suya->id}")->assertOk();
    $this->getJson('/api/v1/dispensario/recetas/999999')->assertNotFound();
});

it('la receta ya no trae las notas de la consulta', function () {
    ($this->recetaDe)(($this->consultaDe)($this->medico, ($this->historiaDe)('0809191901')));

    $consulta = $this->actingAs(($this->conRol)('enfermera'), 'sanctum')
        ->getJson('/api/v1/dispensario/recetas')
        ->assertOk()
        ->json('datos.data.0.consulta_medica');

    expect($consulta)->toHaveKeys(['id', 'medico_id', 'fecha_consulta', 'historia_clinica', 'medico'])
        ->and($consulta)->not->toHaveKeys([
            'motivo_consulta', 'enfermedad_actual', 'examen_fisico',
            'diagnostico_detallado', 'plan_tratamiento', 'notas_medico',
        ])
        ->and($consulta['historia_clinica']['servidor']['cedula'])->toBe('0809191901');
});

// ── Notas de consulta ───────────────────────────────────────────

it('Enfermería y la administración no leen notas de consulta', function (string $rol) {
    $consulta = ($this->consultaDe)($this->medico, ($this->historiaDe)('0809191901'));

    $this->actingAs(($this->conRol)($rol), 'sanctum');

    $this->getJson('/api/v1/dispensario/consultas')->assertForbidden();
    $this->getJson("/api/v1/dispensario/consultas/{$consulta->id}")->assertForbidden();
})->with(['enfermera', 'admin-dispensario']);

it('sin paciente, el médico lista solo sus consultas', function () {
    $suya = ($this->consultaDe)($this->medico, ($this->historiaDe)('0809191901'));
    ($this->consultaDe)($this->colega, ($this->historiaDe)('0809191902'));

    $ids = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/consultas')
        ->assertOk()
        ->collect('datos.data')->pluck('id')->all();

    expect($ids)->toBe([$suya->id]);
});

it('el historial de un paciente lo lee quien lo atiende', function () {
    $historia = ($this->historiaDe)('0809191902');
    $delColega = ($this->consultaDe)($this->colega, $historia);

    $this->actingAs($this->medico, 'sanctum');

    // Todavía no lo atiende.
    $this->getJson("/api/v1/dispensario/consultas?historia_clinica_id={$historia->id}")->assertForbidden();
    $this->getJson("/api/v1/dispensario/consultas/{$delColega->id}")->assertForbidden();

    // Con un turno suyo para ese paciente, sí: es su historia clínica.
    AgendaMedica::forceCreate([
        'medico_id' => $this->medico->id,
        'servidor_id' => $historia->servidor_id,
        'fecha' => now()->toDateString(),
        'tipo_atencion' => 'medicina_general',
        'estado' => 'en_espera',
    ]);

    $this->getJson("/api/v1/dispensario/consultas?historia_clinica_id={$historia->id}")
        ->assertOk()
        ->assertJsonPath('datos.data.0.id', $delColega->id);
    $this->getJson("/api/v1/dispensario/consultas/{$delColega->id}")->assertOk();
});

// ── Exámenes ────────────────────────────────────────────────────

it('cada profesional ve en la bandeja los exámenes de su especialidad', function () {
    $deMedicina = ($this->ordenDe)(($this->consultaDe)($this->medico, ($this->historiaDe)('0809191901')));
    $deOdontologia = ($this->ordenDe)(($this->consultaDe)($this->odontologo, ($this->historiaDe)('0809191902'), 'odontologia'));

    $folios = fn (User $quien) => $this->actingAs($quien, 'sanctum')
        ->getJson('/api/v1/dispensario/examenes/bandeja')
        ->assertOk()
        ->collect('datos.data')->pluck('id')->sort()->values()->all();

    expect($folios($this->medico))->toBe([$deMedicina->id])
        ->and($folios($this->odontologo))->toBe([$deOdontologia->id])
        ->and($folios(($this->conRol)('admin-dispensario')))->toBe(collect([$deMedicina->id, $deOdontologia->id])->sort()->values()->all());
});

it('el odontólogo no marca como no realizado un examen de medicina', function () {
    $orden = ($this->ordenDe)(($this->consultaDe)($this->medico, ($this->historiaDe)('0809191901')));
    $item = $orden->items()->value('id');

    $this->actingAs($this->odontologo, 'sanctum')
        ->patchJson("/api/v1/dispensario/ordenes-examen/{$orden->id}/items/{$item}/no-realizado", [
            'motivo_no_realizado' => 'No vino',
        ])
        ->assertForbidden();

    expect(OrdenExamen::findOrFail($orden->id)->items()->value('estado'))->toBe(EstadoItemOrdenExamen::PENDIENTE);
});

it('el registro de un resultado de su bandeja le abre el historial del paciente', function () {
    // Desde la bandeja, el médico registra el resultado de una orden de
    // medicina de un paciente que no atendió: el modal lista sus consultas.
    $historia = ($this->historiaDe)('0809191902');
    ($this->ordenDe)(($this->consultaDe)($this->colega, $historia));

    $this->actingAs($this->medico, 'sanctum')
        ->getJson("/api/v1/dispensario/consultas?historia_clinica_id={$historia->id}")
        ->assertOk();

    $this->actingAs($this->odontologo, 'sanctum')
        ->getJson("/api/v1/dispensario/consultas?historia_clinica_id={$historia->id}")
        ->assertForbidden();
});
