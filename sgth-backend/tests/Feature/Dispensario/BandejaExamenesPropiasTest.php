<?php

/*
| La bandeja de exámenes de cada médico, con lo suyo (decisión del 2026-10-29).
|
| Hasta entonces cada uno veía las órdenes y los resultados de su
| especialidad. Ahora ve lo que pidió; la administración, todo. Lo de un
| colega que no está se atiende desde la consulta del paciente: allí salen sus
| órdenes abiertas, para registrarles el resultado, y todos sus resultados,
| para leerlos y revisarlos.
*/

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

function usuarioBandejaPropia(string $alias, string $rol): User
{
    $usuario = User::create([
        'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $usuario->assignRole($rol);

    return $usuario;
}

function ordenBandejaPropia(ConsultaMedica $consulta): OrdenExamen
{
    return app(OrdenExamenServiceInterface::class)->emitir(
        ['historia_clinica_id' => $consulta->historia_clinica_id, 'consulta_medica_id' => $consulta->id,
            'fecha_solicitud' => now()->toDateString()],
        [['catalogo_examen_id' => CatalogoExamen::where('nombre', 'Glucosa')->value('id')]],
        $consulta->medico_id,
    );
}

/** Los folios de una respuesta con las órdenes en `datos` o en `datos.data`. */
function foliosBandejaPropia(array $ordenes): array
{
    return collect($ordenes)->pluck('folio')->sort()->values()->all();
}

beforeEach(function () {
    Model::unguard();

    $this->medicoA = usuarioBandejaPropia('propias-a', 'medico');
    $this->medicoB = usuarioBandejaPropia('propias-b', 'medico');
    $this->odontologo = usuarioBandejaPropia('propias-odonto', 'odontologo');
    $this->administracion = usuarioBandejaPropia('propias-admin', 'admin-dispensario');

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Bandejas Propias']);
    $this->servidor = Servidor::create([
        'cedula' => '0812610001', 'nombre' => 'Rosa', 'apellido' => 'Cortez',
        'puesto_id' => puestoDePrueba($unidad, 'Analista de Bandejas Propias')->id,
        'unidad_administrativa_id' => $unidad->id, 'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $this->historia = HistoriaClinica::create(['servidor_id' => $this->servidor->id, 'grupo_sanguineo' => 'O+']);

    $consulta = fn (User $quien, string $especialidad = 'medicina_general') => ConsultaMedica::create([
        'especialidad' => $especialidad, 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $quien->id, 'fecha_consulta' => now()->subDays(2),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
    $this->consultaA = $consulta($this->medicoA);
    $this->consultaB = $consulta($this->medicoB);
    $this->consultaOdonto = $consulta($this->odontologo, 'odontologia');

    $this->ordenA = ordenBandejaPropia($this->consultaA);
    $this->ordenB = ordenBandejaPropia($this->consultaB);
    $this->ordenOdonto = ordenBandejaPropia($this->consultaOdonto);
});

test('la bandeja de cada médico trae solo las órdenes que pidió; la administración, todas', function () {
    $bandeja = fn (User $quien) => $this->actingAs($quien, 'sanctum')
        ->getJson('/api/v1/dispensario/examenes/bandeja')->assertOk();

    $deA = $bandeja($this->medicoA);
    expect(foliosBandejaPropia($deA->json('datos.data')))->toBe([$this->ordenA->folio])
        ->and($deA->json('meta.resumen.pendientes'))->toBe(1);

    expect(foliosBandejaPropia($bandeja($this->odontologo)->json('datos.data')))->toBe([$this->ordenOdonto->folio])
        ->and(foliosBandejaPropia($bandeja($this->administracion)->json('datos.data')))
        ->toBe(foliosBandejaPropia([$this->ordenA, $this->ordenB, $this->ordenOdonto]));
});

test('en la consulta del paciente salen las órdenes abiertas de un colega, de su especialidad y sin las de la FEMO', function () {
    // Una orden de la evaluación ocupacional del mismo paciente: es de la ficha.
    $ficha = FichaSaludOcupacional::create([
        'servidor_id' => $this->servidor->id, 'evaluador_id' => $this->medicoA->id,
        'fecha_evaluacion' => now()->toDateString(), 'tipo_ficha' => 'periodica', 'estado' => true,
    ]);
    $deLaFicha = ordenBandejaPropia($this->consultaA);
    DB::table('ordenes_examen')->where('id', $deLaFicha->id)
        ->update(['consulta_medica_id' => null, 'ficha_salud_ocupacional_id' => $ficha->id]);
    // Y una ya cerrada, que no espera nada.
    $cerrada = ordenBandejaPropia($this->consultaA);
    DB::table('ordenes_examen')->where('id', $cerrada->id)->update(['estado' => 'completa']);

    $abiertas = fn (User $quien) => $this->actingAs($quien, 'sanctum')
        ->getJson("/api/v1/dispensario/ordenes-examen?historia_clinica_id={$this->historia->id}&abiertas=1")
        ->assertOk()->json('datos');

    expect(foliosBandejaPropia($abiertas($this->medicoB)))->toBe(foliosBandejaPropia([$this->ordenA, $this->ordenB]))
        ->and(foliosBandejaPropia($abiertas($this->odontologo)))->toBe([$this->ordenOdonto->folio]);

    // Sin `abiertas`, el listado del paciente sigue entero.
    $todas = $this->actingAs($this->medicoB, 'sanctum')
        ->getJson("/api/v1/dispensario/ordenes-examen?historia_clinica_id={$this->historia->id}")->json('datos');
    expect($todas)->toHaveCount(5);
});

test('un colega registra el resultado de la orden de otro y responde quien la pidió', function () {
    Storage::fake('local');
    $item = $this->ordenA->items()->value('id');

    $this->actingAs($this->medicoB, 'sanctum')
        ->post('/api/v1/dispensario/resultados-medicos', [
            'historia_clinica_id' => $this->historia->id,
            'consulta_medica_id'  => $this->consultaA->id,
            'orden_examen_id'     => $this->ordenA->id,
            'items'               => [$item],
            'tipo'                => 'laboratorio',
            'descripcion'         => 'Glucosa en ayunas',
            'fecha_resultado'     => now()->toDateString(),
            'archivo'             => UploadedFile::fake()->create('glucosa.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $porRevisar = fn (User $quien) => collect($this->actingAs($quien, 'sanctum')
        ->getJson('/api/v1/dispensario/examenes/bandeja/por-revisar')->assertOk()->json('datos.data'))
        ->pluck('descripcion')->all();

    expect($porRevisar($this->medicoA))->toBe(['Glucosa en ayunas'])
        ->and($porRevisar($this->medicoB))->toBe([]);
});

test('desde la historia, un médico ve y revisa el resultado que pidió un colega', function () {
    $resultado = ResultadoMedico::create([
        'historia_clinica_id' => $this->historia->id, 'consulta_medica_id' => $this->consultaA->id,
        'orden_examen_id' => $this->ordenA->id, 'subido_por' => $this->medicoA->id,
        'tipo' => 'laboratorio', 'descripcion' => 'Glucosa de A',
        'archivo' => 'resultados-medicos/glucosa-a.pdf', 'fecha_resultado' => now()->toDateString(),
        'estado_revision' => 'por_revisar',
    ]);

    $this->actingAs($this->medicoB, 'sanctum');

    expect(collect($this->getJson("/api/v1/dispensario/resultados-medicos?historia_clinica_id={$this->historia->id}")
        ->assertOk()->json('datos'))->pluck('descripcion')->all())->toContain('Glucosa de A');

    $this->patchJson("/api/v1/dispensario/resultados-medicos/{$resultado->id}/revisar", ['interpretacion' => 'normal'])
        ->assertOk();

    expect($resultado->fresh()->estado_revision->value)->toBe('revisado');
});
