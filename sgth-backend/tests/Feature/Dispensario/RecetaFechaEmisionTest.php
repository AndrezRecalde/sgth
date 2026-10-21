<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\PdfRecetaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * La pantalla manda la fecha de hoy, pero el servidor aceptaba cualquiera: una
 * receta fechada la semana próxima, o anterior a la consulta que la origina. Y
 * el pie del impreso decía la fecha del registro, no la de emisión, así que una
 * receta emitida otro día llevaba dos fechas distintas en el mismo papel.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Fechas de Receta']);
    $puesto = puestoDePrueba($unidad, 'Analista de Fechas de Receta');
    $servidor = fn (string $cedula, string $nombre) => Servidor::create([
        'cedula' => $cedula, 'nombre' => $nombre, 'apellido' => 'Prueba',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);

    $this->medico = User::create([
        'email' => 'receta-fecha@example.com', 'usuario_ti' => 'receta-fecha',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $servidor('0812370001', 'Elena')->id,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $historia = HistoriaClinica::create([
        'servidor_id' => $servidor('0812370002', 'Ana')->id, 'grupo_sanguineo' => 'O+',
    ]);

    // Una consulta de hace tres días: la receta puede ser de ese día en
    // adelante, hasta hoy.
    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => now()->subDays(3),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);

    $this->actingAs($this->medico, 'sanctum');
});

function recetaFechada(ConsultaMedica $consulta, string $fecha): Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/dispensario/recetas', [
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => $fecha,
        'items' => [[
            'medicamento_externo' => 'Rosuvastatina 20 mg', 'cantidad_prescrita' => 30,
            'dosis' => '1 tableta', 'frecuencia' => 'cada 24 horas', 'duracion' => '30 dias',
        ]],
    ]);
}

function pieDelImpreso(int $recetaId): string
{
    $html = view(
        'pdf.dispensario.receta-medica',
        app(PdfRecetaService::class)->datosDelImpreso($recetaId)
    )->render();

    return preg_replace('/\s+/', ' ', strip_tags($html));
}

test('una_receta_con_fecha_futura_no_se_emite', function () {
    recetaFechada($this->consulta, now()->addDay()->toDateString())
        ->assertStatus(422)
        ->assertJsonPath('errores.fecha_emision.0', 'La receta no puede tener fecha futura.');
});

test('una_receta_anterior_a_su_consulta_no_se_emite', function () {
    recetaFechada($this->consulta, now()->subDays(4)->toDateString())
        ->assertStatus(422)
        ->assertJsonPath('errores.fecha_emision.0', 'La receta no puede ser anterior a la consulta que la origina.');
});

test('el_dia_de_la_consulta_y_hoy_valen', function () {
    recetaFechada($this->consulta, now()->subDays(3)->toDateString())->assertCreated();
    recetaFechada($this->consulta, now()->toDateString())->assertCreated();
});

test('el_pie_del_impreso_dice_la_fecha_de_emision_y_la_del_registro_si_no_coinciden', function () {
    $id = recetaFechada($this->consulta, now()->subDays(2)->toDateString())
        ->assertCreated()
        ->json('datos.receta.id');

    expect(pieDelImpreso($id))
        ->toContain('emitida el ' . now()->subDays(2)->format('d/m/Y') . ' (registrada el ' . now()->format('d/m/Y'));
});

test('el_pie_de_una_receta_de_hoy_dice_una_sola_fecha', function () {
    $id = recetaFechada($this->consulta, now()->toDateString())
        ->assertCreated()
        ->json('datos.receta.id');

    expect(pieDelImpreso($id))
        ->toContain('emitida el ' . now()->format('d/m/Y') . ' a las')
        ->not->toContain('registrada el');
});
