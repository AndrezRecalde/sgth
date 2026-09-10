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
use Illuminate\Support\Facades\View;
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

/**
 * @param array<int, array<string, mixed>> $items
 * @param array<string, mixed> $deLaReceta lo que no es de un ítem concreto
 */
function emitirParaPdf(
    array $items,
    ConsultaMedica $consulta,
    array $deLaReceta = []
): int {
    $respuesta = test()->postJson('/api/v1/dispensario/recetas', [
        'consulta_medica_id' => $consulta->id,
        'fecha_emision'      => now()->toDateString(),
        'items'              => array_map(fn ($extra) => array_merge([
            'cantidad_prescrita' => 10,
            'dosis'              => '1 tableta',
            'frecuencia'         => 'cada 8 horas',
            'duracion'           => '3 dias',
        ], $extra), $items),
        ...$deLaReceta,
    ])->assertCreated();

    return $respuesta->json('datos.receta.id');
}

/**
 * El HTML que va al papel.
 *
 * Sale de los mismos datos que compone el servicio, no de una reconstrucción a
 * mano: si el servicio cambiara lo que manda a la plantilla, esta prueba lo
 * seguiría. El PDF ya sale comprimido y su texto no se puede rastrear ahí.
 */
function impresoDe(int $recetaId): string
{
    $datos = app(App\Services\Dispensario\PdfRecetaService::class)
        ->datosDelImpreso($recetaId);

    return view('pdf.dispensario.receta-medica', $datos)->render();
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

it('imprime las alergias con la salvedad de que pueden estar incompletas', function () {
    $medicina = medicinaPdf('PDF-006', 'Naproxeno');

    $this->historia->alergias()->create([
        'tipo'        => 'medicamento',
        'descripcion' => 'Penicilina',
        'severidad'   => 'grave',
    ]);

    $html = impresoDe(emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    ));

    expect($html)->toContain('Penicilina')
        // Un impreso institucional invita a confiar, y el registro de alergias
        // nunca está completo: la salvedad evita que se lea como una lista
        // cerrada.
        ->and($html)->toContain('Según lo registrado en la historia clínica');
});

it('dice que no hay alergias registradas en vez de callar', function () {
    $medicina = medicinaPdf('PDF-007', 'Diclofenaco');

    $html = impresoDe(emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    ));

    // Sin el bloque, quien recibía la receta no podía distinguir «no tiene
    // ninguna registrada» de «este impreso no trae ese dato».
    expect($html)->toContain('sin alergias registradas')
        ->and($html)->toContain('no significa que no existan')
        // Y no debe alarmar: el rojo se reserva para cuando hay algo que mirar.
        ->and($html)->toContain('alergias neutra');
});

it('no delata al paciente cuando el medico omite las alergias', function () {
    $medicina = medicinaPdf('PDF-008', 'Omeprazol');

    $this->historia->alergias()->create([
        'tipo'        => 'medicamento',
        'descripcion' => 'Efavirenz',
        'severidad'   => 'grave',
    ]);

    $recetaId = emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]],
        $this->consulta,
        ['omitir_alergias' => true]
    );

    expect(RecetaMedica::find($recetaId)->omitir_alergias)->toBeTrue();

    $html = impresoDe($recetaId);

    expect($html)->not->toContain('Efavirenz')
        // Lo que NUNCA puede decir: afirmar que no tiene alergias cuando las
        // tiene convertiría una medida de privacidad en un peligro clínico.
        ->and($html)->not->toContain('sin alergias registradas')
        ->and($html)->toContain('Consúltelas en el Dispensario');
});

it('imprime las alergias por defecto, sin que nadie lo pida', function () {
    $medicina = medicinaPdf('PDF-009', 'Ranitidina');

    $recetaId = emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    );

    // Omitir es la excepción y se pide a mano: si el campo no viaja, el
    // impreso protege por defecto.
    expect(RecetaMedica::find($recetaId)->omitir_alergias)->toBeFalse();
});

it('marca con un asterisco lo que el paciente compra fuera', function () {
    $medicina = medicinaPdf('PDF-010', 'Metformina');

    $html = impresoDe(emitirParaPdf([
        ['inventario_medicina_id' => $medicina->id],
        ['medicamento_externo' => 'Empagliflozina 10 mg'],
    ], $this->consulta));

    // Sin la marca, el paciente sale del mostrador sin saber cuál de los dos
    // tiene que ir a comprar.
    expect($html)->toContain('Empagliflozina 10 mg</span><span class="marca-externo">*</span>')
        ->and($html)->toContain('No se entrega en el Dispensario Médico del GADPE')
        // Y lo del catálogo no se marca. Se busca por el principio activo, que
        // es lo que el impreso pone en negrita al recetar en genérico.
        ->and($html)->toContain('metformina</span>')
        ->and($html)->not->toContain('metformina</span><span class="marca-externo">');
});

it('no pone la nota al pie si todo sale del dispensario', function () {
    $medicina = medicinaPdf('PDF-011', 'Enalapril');

    $html = impresoDe(emitirParaPdf(
        [['inventario_medicina_id' => $medicina->id]], $this->consulta
    ));

    // Se busca la etiqueta y no la clase: el nombre de la clase está siempre
    // en la hoja de estilos, así que buscarlo a secas nunca fallaría.
    expect($html)->not->toContain('<span class="marca-externo">')
        ->and($html)->not->toContain('No se entrega en el Dispensario');
});

it('no marca lo que esta en el catalogo aunque hoy este agotado', function () {
    // Agotado no es externo: mañana puede haber, y la receta se despacha
    // después. Marcarlo diría del fármaco algo que solo es cierto hoy.
    $agotada = medicinaPdf('PDF-012', 'Salbutamol');

    expect($agotada->stock_actual)->toBe(0);

    $html = impresoDe(emitirParaPdf(
        [['inventario_medicina_id' => $agotada->id]], $this->consulta
    ));

    expect($html)->not->toContain('<span class="marca-externo">');
});
