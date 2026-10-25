<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\DiagnosticoCie10;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\PdfOrdenExamenService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * El impreso de la orden de exámenes (fase 2 de docs/diseno-examenes-medicos.md):
 * lo que el paciente lleva al laboratorio, con los exámenes agrupados como en
 * la hoja que ya usaban.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Ordenes PDF']);
    $puesto = puestoDePrueba($unidad, 'Analista de Ordenes PDF');
    $servidor = fn (array $datos) => Servidor::create([
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(4), 'estado' => true,
        ...$datos,
    ]);

    $this->medico = User::create([
        'email' => 'orden-pdf@example.com', 'usuario_ti' => 'ordenpdf',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $servidor([
            'cedula' => '0812440001', 'nombre' => 'Elena', 'apellido' => 'Vera',
            'codigo_medico' => 'ACESS-55120',
        ])->id,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $paciente = $servidor([
        'cedula' => '0812440002', 'nombre' => 'Ana', 'apellido' => 'Lopez',
        'fecha_nacimiento' => now()->subYears(36)->subDays(10)->toDateString(),
        'genero' => 'femenino',
    ]);
    $this->historia = HistoriaClinica::create(['servidor_id' => $paciente->id, 'grupo_sanguineo' => 'O+']);

    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => now()->subDays(2),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);

    $this->actingAs($this->medico, 'sanctum');
});

/** Emite la orden por el API, como la pantalla, y devuelve su id. */
function emitirOrdenParaPdf(ConsultaMedica $consulta, array $examenes, array $extra = []): int
{
    return test()->postJson('/api/v1/dispensario/ordenes-examen', [
        'consulta_medica_id' => $consulta->id,
        'fecha_solicitud'    => now()->toDateString(),
        'items'              => collect($examenes)->map(fn ($nota, $nombre) => [
            'catalogo_examen_id' => CatalogoExamen::where('nombre', is_int($nombre) ? $nota : $nombre)->value('id'),
            'nota'               => is_int($nombre) ? null : $nota,
        ])->values()->all(),
        ...$extra,
    ])->assertCreated()->json('datos.id');
}

/** El HTML que va al papel, de los mismos datos que arma el servicio. */
function impresoDeOrden(int $ordenId): string
{
    $datos = app(PdfOrdenExamenService::class)->datosDelImpreso($ordenId);

    return view('pdf.dispensario.orden-examenes', $datos)->render();
}

test('el_impreso_agrupa_los_examenes_por_area_en_el_orden_de_la_hoja', function () {
    // Pedidos en desorden: hematología, orina y bioquímica.
    $id = emitirOrdenParaPdf($this->consulta, [
        'Hemograma completo',
        'Elemental y microscópico (físico-químico y sedimento)',
        'Tolerancia oral a la glucosa' => '75 g',
        'Glucosa',
    ]);

    $html = impresoDeOrden($id);
    $posicion = fn (string $texto) => mb_strpos($html, $texto);

    // Las áreas en el orden de la hoja, y dentro de cada una el del catálogo.
    expect($posicion('Bioquímica'))->toBeLessThan($posicion('Hematología'))
        ->and($posicion('Hematología'))->toBeLessThan($posicion('Orina'))
        ->and($posicion('>Glucosa<'))->toBeLessThan($posicion('Tolerancia oral a la glucosa'))
        // La nota, con el dato que pide el examen.
        ->and($html)->toContain('Gramos de glucosa: 75 g')
        // El total cierra la lista.
        ->and($html)->toMatch('/———\s+4\s+exámenes solicitados/u');
});

test('lleva_el_folio_el_paciente_el_diagnostico_y_quien_lo_pide', function () {
    $cie10 = DiagnosticoCie10::create([
        'codigo' => 'E11X', 'descripcion' => 'DIABETES MELLITUS TIPO 2', 'categoria' => 'E11', 'activo' => true,
    ]);
    $id = emitirOrdenParaPdf($this->consulta, ['Glucosa'], [
        'diagnostico_cie10_id' => $cie10->id,
        'prioridad'            => 'urgente',
        'laboratorio_destino'  => 'Laboratorio Clínico Esmeraldas',
        'indicaciones'         => 'Ayuno de 8 horas.',
    ]);

    $html = impresoDeOrden($id);

    expect($html)->toContain('EXA-' . date('Y') . '-00001')
        ->toContain('Ana Lopez')->toContain('0812440002')->toContain('36 años')
        ->toContain('E11X · DIABETES MELLITUS TIPO 2')
        ->toContain('URGENTE')
        ->toContain('Laboratorio Clínico Esmeraldas')
        ->toContain('Ayuno de 8 horas.')
        ->toContain('Medicina general')
        ->toContain('Elena Vera')->toContain('ACESS-55120')
        // Vale 90 días (D5): el laboratorio sabe hasta cuándo aceptarla.
        ->toContain(now()->addDays(90)->format('d/m/Y'));
});

test('el_pie_dice_las_dos_fechas_cuando_no_coinciden', function () {
    $hoy = emitirOrdenParaPdf($this->consulta, ['Glucosa']);
    expect(impresoDeOrden($hoy))->not->toContain('registrada el');

    // Pedida el día de la consulta y registrada hoy.
    $antes = emitirOrdenParaPdf($this->consulta, ['Glucosa'], [
        'fecha_solicitud' => now()->subDays(2)->toDateString(),
    ]);
    expect(impresoDeOrden($antes))
        ->toContain('emitida el ' . now()->subDays(2)->format('d/m/Y'))
        ->toContain('registrada el ' . now()->format('d/m/Y'));
});

test('el_consentimiento_solo_consta_si_hay_sensibles', function () {
    $sin = emitirOrdenParaPdf($this->consulta, ['Glucosa']);
    $con = emitirOrdenParaPdf($this->consulta, ['HIV 1/2/0'], ['consentimiento_firmado' => true]);

    expect(impresoDeOrden($sin))->not->toContain('consentimiento informado')
        ->and(impresoDeOrden($con))->toContain('consentimiento informado');
});

test('la_anulada_sale_marcada_con_su_motivo', function () {
    $id = emitirOrdenParaPdf($this->consulta, ['Glucosa']);
    $this->patchJson("/api/v1/dispensario/ordenes-examen/{$id}/anular", [
        'motivo_anulacion' => 'Examen equivocado.',
    ])->assertOk();

    expect(impresoDeOrden($id))
        ->toContain('ANULADA')
        ->toContain('Esta orden fue anulada')
        // Sin el punto doble: el motivo trae el suyo.
        ->toContain('Motivo: Examen equivocado.')->not->toContain('equivocado..')
        ->toContain('No debe procesarse.');
});

test('el_pdf_lo_descargan_quienes_piden_examenes_y_la_administracion', function () {
    $id = emitirOrdenParaPdf($this->consulta, ['Glucosa']);

    $this->get("/api/v1/dispensario/ordenes-examen/{$id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="orden-examenes-EXA-' . date('Y') . '-00001.pdf"');

    $admin = User::create([
        'email' => 'orden-pdf-admin@example.com', 'usuario_ti' => 'ordenpdfadmin',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $admin->assignRole(Role::firstOrCreate(['name' => 'admin-dispensario', 'guard_name' => 'sanctum']));
    $this->actingAs($admin, 'sanctum')->get("/api/v1/dispensario/ordenes-examen/{$id}/pdf")->assertOk();

    // Enfermería dejó de descargarlo el 2026-10-24.
    $enfermera = User::create([
        'email' => 'orden-pdf-enf@example.com', 'usuario_ti' => 'ordenpdfenf',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $enfermera->assignRole(Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'sanctum']));
    $this->actingAs($enfermera, 'sanctum')->getJson("/api/v1/dispensario/ordenes-examen/{$id}/pdf")->assertForbidden();

    $ajeno = User::create([
        'email' => 'orden-pdf-uath@example.com', 'usuario_ti' => 'ordenpdfuath',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $ajeno->assignRole(Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']));
    $this->actingAs($ajeno, 'sanctum')->getJson("/api/v1/dispensario/ordenes-examen/{$id}/pdf")->assertForbidden();

    $this->actingAs($this->medico, 'sanctum')->getJson('/api/v1/dispensario/ordenes-examen/999999/pdf')->assertNotFound();
});
