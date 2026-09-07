<?php

use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\OrigenBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Asignar un bien respondía «Asignación creada y Acta PDF generada» y guardaba
 * en `url_acta_pdf` una ruta compuesta a mano hacia un archivo que nadie
 * generaba. El acta es el papel que respalda la custodia del equipo: era lo
 * único que este registro tenía que producir, y era justo lo que faltaba.
 */
beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    BienInformatico::unguard();
    AsignacionBien::unguard();
    TipoBien::unguard();
    Marca::unguard();
    OrigenBien::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion Financiera']);
    $puesto = puestoDePrueba($unidad, 'Contadora');

    $this->servidor = Servidor::create([
        'cedula' => '0801234577', 'nombre' => 'Marta', 'apellido' => 'Quintero',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), 'estado' => true,
    ]);

    $this->admin = User::create([
        'email' => 'dtic@example.com', 'usuario_ti' => 'dtic',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->admin->assignRole(Role::firstOrCreate(
        ['name' => 'admin-ti', 'guard_name' => 'sanctum']
    ));

    $this->bien = BienInformatico::create([
        'codigo_qr'            => 'GADPE-077-QR',
        'codigo_institucional' => 'GADPE-077',
        'tipo_bien_id'         => TipoBien::create(['nombre' => 'Laptop', 'anios_vida_util' => 5])->id,
        'marca_id'             => Marca::create(['nombre' => 'Dell'])->id,
        'origen_bien_id'       => OrigenBien::create([
            'tipo_origen'             => 'compra',
            'identificador_documento' => 'OC-2025-014',
            'entidad_origen'          => 'GADPE',
            'fecha_adquisicion'       => now()->subYear(),
        ])->id,
        'modelo'               => 'Latitude 5440',
        'numero_serie'         => 'SN-ACTA-77',
        'estado_operativo'     => 'activo',
        'condicion_fisica'     => 'bueno',
        'caracteristicas_tecnicas' => ['memoria_ram' => '16 GB', 'disco' => 'SSD 512 GB'],
    ]);

    $this->asignacion = AsignacionBien::create([
        'bien_informatico_id' => $this->bien->id,
        'servidor_id'         => $this->servidor->id,
        'fecha_asignacion'    => '2026-03-04',
        'observaciones'       => 'Se entrega con cargador y maletín.',
        'estado'              => 'activa',
    ]);

    $this->servicio = app(InventarioTiServiceInterface::class);
});

/** El acta, en HTML, tal como entra al PDF. */
function actaRenderizada(AsignacionBien $asignacion, array $entrega = null): string
{
    return view('pdf.inventario.acta-entrega', [
        'asignacion' => $asignacion->fresh([
            'bien.tipo', 'bien.marca',
            'servidor.unidadAdministrativa', 'servidor.puesto.cargo',
        ]),
        'numero'  => 'ACT-000001',
        'entrega' => $entrega ?? ['nombre' => null, 'cargo' => 'Dirección de TIC'],
        'logo'    => public_path('images/logo-gadpe.png'),
    ])->render();
}

// ── El documento ───────────────────────────────────────────────────────────

test('el_acta_se_descarga_y_es_un_pdf_de_verdad', function () {
    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/inventario/asignaciones/{$this->asignacion->id}/acta");

    $respuesta->assertOk();
    $respuesta->assertHeader('Content-Type', 'application/pdf');

    // Un PDF empieza por su firma. Sin esto pasaría igual devolviendo texto.
    expect(substr($respuesta->getContent(), 0, 4))->toBe('%PDF');
});

test('el_acta_nombra_al_custodio_y_al_bien_que_recibe', function () {
    // Un acta que no identifica el equipo no respalda nada: el número de serie
    // es lo que distingue una laptop de otra igual.
    $html = actaRenderizada($this->asignacion);

    expect($html)
        ->toContain('Marta Quintero')
        ->toContain('0801234577')
        ->toContain('Contadora')
        ->toContain('Direccion Financiera')
        ->toContain('GADPE-077')
        ->toContain('SN-ACTA-77')
        ->toContain('Latitude 5440');
});

test('el_acta_lleva_las_observaciones_y_las_caracteristicas', function () {
    // Lo que se entrega con el equipo —cargador, maletín— es lo que después se
    // reclama en la devolución.
    $html = actaRenderizada($this->asignacion);

    expect($html)
        ->toContain('cargador y maletín')
        ->toContain('16 GB')
        ->toContain('SSD 512 GB');
});

test('el_acta_dice_a_que_se_compromete_quien_firma', function () {
    expect(actaRenderizada($this->asignacion))->toContain('custodio');
});

// ── Quién entrega ──────────────────────────────────────────────────────────

test('quien_entrego_sale_de_la_asignacion_y_no_de_quien_imprime', function () {
    // Sellado al registrar: una reimpresión no puede atribuirle el acto a otra
    // persona.
    $tecnico = Servidor::create([
        'cedula' => '0801234566', 'nombre' => 'Luis', 'apellido' => 'Andrade',
        'unidad_administrativa_id' => $this->servidor->unidad_administrativa_id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(5), 'estado' => true,
    ]);
    $this->admin->update(['servidor_id' => $tecnico->id]);

    $asignacion = AsignacionBien::create([
        'bien_informatico_id' => $this->bien->id,
        'servidor_id'         => $this->servidor->id,
        'fecha_asignacion'    => now(),
        'estado'              => 'activa',
        'created_by'          => $this->admin->id,
    ]);

    $acta = $this->servicio->generarActaEntrega($asignacion->id);

    expect(substr($acta['content'], 0, 4))->toBe('%PDF');
    expect($acta['filename'])->toContain('acta-entrega-ACT-');
});

test('sin_quien_entrego_se_imprime_la_direccion_y_no_un_nombre_inventado', function () {
    // Las asignaciones anteriores a este cambio no tienen `created_by`. Es
    // preferible el rótulo del área, que es cierto, a atribuirle la entrega a
    // quien ocupe hoy el puesto.
    $html = actaRenderizada($this->asignacion, [
        'nombre' => null,
        'cargo'  => 'Dirección de Tecnologías de la Información y Comunicación',
    ]);

    expect($html)->toContain('Dirección de Tecnologías de la Información');
});

// ── Lo que ya no miente ────────────────────────────────────────────────────

test('asignar_un_bien_ya_no_anuncia_un_acta_que_no_existe', function () {
    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/inventario/asignaciones', [
            'bien_informatico_id' => $this->bien->id,
            'servidor_id'         => $this->servidor->id,
            'fecha_asignacion'    => now()->toDateString(),
            'estado'              => 'activa',
        ]);

    $respuesta->assertCreated();
    expect($respuesta->json('mensaje'))->not->toContain('Acta PDF generada');
});

test('la_columna_que_guardaba_la_ruta_inventada_ya_no_esta', function () {
    // Solo podía contener una promesa falsa: el archivo al que apuntaba no se
    // generaba nunca.
    expect(Schema::hasColumn('asignaciones_bien', 'url_acta_pdf'))->toBeFalse();
});

test('quien_entrega_lo_pone_el_sistema_y_no_el_cuerpo_de_la_peticion', function () {
    // Es el dato que después firma el acta: si lo dictara el cliente, el papel
    // podría atribuirle la entrega a cualquiera.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/inventario/asignaciones', [
            'bien_informatico_id' => $this->bien->id,
            'servidor_id'         => $this->servidor->id,
            'fecha_asignacion'    => now()->toDateString(),
            'estado'              => 'activa',
            'created_by'          => 999999,
        ])->assertCreated();

    expect(AsignacionBien::latest('id')->first()->created_by)
        ->toBe($this->admin->id);
});

// ── Casos de borde ─────────────────────────────────────────────────────────

test('un_acta_de_una_asignacion_devuelta_lo_advierte_en_el_papel', function () {
    // Reimprimir un acta de una custodia ya cerrada no puede parecer una
    // constancia de tenencia actual.
    $this->asignacion->update([
        'fecha_devolucion' => '2026-06-30',
        'estado'           => 'finalizada',
    ]);

    expect(actaRenderizada($this->asignacion))
        ->toContain('registra devolución')
        ->toContain('no acredita tenencia actual');
});

test('el_acta_de_una_asignacion_que_no_existe_da_404', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/inventario/asignaciones/999999/acta')
        ->assertNotFound();
});
