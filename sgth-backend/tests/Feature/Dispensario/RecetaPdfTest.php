<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    ConsultaMedica::unguard();
    HistoriaClinica::unguard();
    RecetaMedica::unguard();
    ItemReceta::unguard();
    InventarioMedicina::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Recetas PDF']);
    $puesto = puestoDePrueba($unidad, 'Analista de Recetas PDF');

    // El médico es un servidor: de ahí salen su cédula y su código ACESS, que
    // es lo que identifica a quien firma la receta.
    $servidorMedico = Servidor::create([
        'cedula'                    => '0802345680',
        'nombre'                    => 'Elena',
        'apellido'                  => 'Vera',
        'puesto_id'                 => $puesto->id,
        'unidad_administrativa_id'  => $unidad->id,
        'regimen_laboral'           => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(5),
        'estado'                    => true,
        'codigo_medico'             => 'ACESS-11223',
    ]);

    $this->medico = User::create([
        'email'        => 'medico-pdf@example.com',
        'usuario_ti'   => 'medpdf',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
        'servidor_id'  => $servidorMedico->id,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    ));

    $paciente = Servidor::create([
        'cedula'                    => '0802345681',
        'nombre'                    => 'Ana',
        'apellido'                  => 'Lopez',
        'puesto_id'                 => $puesto->id,
        'unidad_administrativa_id'  => $unidad->id,
        'regimen_laboral'           => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3),
        'fecha_nacimiento'          => '1990-04-12',
        'genero'                    => 'femenino',
        'estado'                    => true,
    ]);

    $this->historia = HistoriaClinica::create([
        'servidor_id'     => $paciente->id,
        'grupo_sanguineo' => 'O+',
    ]);

    $this->consulta = ConsultaMedica::create([
        'especialidad'          => 'medicina_general',
        'historia_clinica_id'   => $this->historia->id,
        'medico_id'             => $this->medico->id,
        'fecha_consulta'        => now(),
        'hora_consulta'         => now()->format('H:i:s'),
        'motivo_consulta'       => 'Control',
        'diagnostico_detallado' => 'Faringitis aguda',
    ]);

    $this->actingAs($this->medico, 'sanctum');
});

/** @param array<int, array<string, mixed>> $items */
function emitirParaPdf(array $items, ConsultaMedica $consulta): int
{
    $respuesta = test()->postJson('/api/v1/dispensario/recetas', [
        'consulta_medica_id' => $consulta->id,
        'fecha_emision'      => now()->toDateString(),
        'items'              => array_map(fn ($extra) => array_merge([
            'cantidad_prescrita' => 10,
            'dosis'              => '1 tableta',
            'frecuencia'         => 'cada 8 horas',
            'duracion'           => '3 dias',
        ], $extra), $items),
    ])->assertCreated();

    return $respuesta->json('datos.receta.id');
}

function medicinaPdf(string $codigo, string $nombre): InventarioMedicina
{
    return InventarioMedicina::create([
        'codigo'           => $codigo,
        'nombre'           => $nombre,
        'principio_activo' => strtolower($nombre),
        'presentacion'     => 'tableta',
        'concentracion'    => '500mg',
        'stock_actual'     => 0,
        'stock_minimo'     => 0,
        'estado'           => true,
    ]);
}

it('numera cada receta con un folio del anio', function () {
    $medicina = medicinaPdf('PDF-001', 'Paracetamol');

    $primera = emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    );
    $segunda = emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    );

    $anio = date('Y');

    expect(RecetaMedica::find($primera)->folio)->toBe("REC-{$anio}-00001")
        ->and(RecetaMedica::find($segunda)->folio)->toBe("REC-{$anio}-00002");
});

it('no repite el folio de una receta borrada', function () {
    $medicina = medicinaPdf('PDF-002', 'Ibuprofeno');

    $primera = emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    );
    RecetaMedica::find($primera)->delete();

    $segunda = emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    );

    // Contar filas habría devuelto el folio 00001 otra vez, y el índice único
    // lo habría rechazado; el máximo no se deja engañar por el borrado blando.
    expect(RecetaMedica::find($segunda)->folio)
        ->toBe('REC-' . date('Y') . '-00002');
});

it('genera el pdf de la receta', function () {
    $medicina = medicinaPdf('PDF-003', 'Amoxicilina');

    $recetaId = emitirParaPdf([
        ['inventario_medicina_id' => $medicina->id],
        ['medicamento_externo' => 'Rosuvastatina 20 mg'],
    ], $this->consulta);

    $folio = RecetaMedica::find($recetaId)->folio;

    $respuesta = $this->get("/api/v1/dispensario/recetas/{$recetaId}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($respuesta->headers->get('Content-Disposition'))
        ->toContain("receta-{$folio}.pdf")
        ->and($respuesta->getContent())->toStartWith("%PDF");
});

it('deja imprimir la receta a quien la despacha', function () {
    $medicina = medicinaPdf('PDF-004', 'Loratadina');

    $recetaId = emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    );

    $farmacia = User::create([
        'email'        => 'farmacia-pdf@example.com',
        'usuario_ti'   => 'farmpdf',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $farmacia->assignRole(Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));

    // El mostrador imprime la receta que va a despachar: no es una acción
    // reservada a quien la emitió.
    $this->actingAs($farmacia, 'sanctum')
        ->get("/api/v1/dispensario/recetas/{$recetaId}/pdf")
        ->assertOk();
});

it('deja fuera del impreso las alergias anuladas', function () {
    $medicina = medicinaPdf('PDF-005', 'Penicilina');

    $this->historia->alergias()->create([
        'tipo'        => 'medicamento',
        'descripcion' => 'Sulfas',
        'severidad'   => 'grave',
    ]);

    $descartada = $this->historia->alergias()->create([
        'tipo'        => 'medicamento',
        'descripcion' => 'Dipirona',
        'severidad'   => 'leve',
        'anulado_en'  => now(),
    ]);

    $recetaId = emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    );

    $datos = app(App\Services\Dispensario\PdfRecetaService::class);
    $reflexion = new ReflectionMethod($datos, 'alergiasAMedicamentos');
    $alergias = $reflexion->invoke(
        $datos,
        RecetaMedica::with('consultaMedica.historiaClinica.alergias')
            ->find($recetaId)
    );

    // Una alergia anulada es una que se descartó: arrastrarla al papel haría
    // que el paciente cargara con una advertencia que su médico ya retiró.
    expect($alergias->pluck('descripcion')->all())->toBe(['Sulfas'])
        ->and($descartada->fresh()->anulado_en)->not->toBeNull();
});
