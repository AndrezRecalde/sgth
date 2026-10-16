<?php

use App\Contracts\Comunicacion\CredencialServiceInterface;
use App\Contracts\Comunicacion\FotografiaServiceInterface;
use App\Enums\EstadoCredencial;
use App\Enums\OrigenFotografia;
use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Models\Comunicacion\Credencial;
use App\Models\Comunicacion\ImpresionCredencial;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/*
| La credencial de los servidores (docs/diseno-comunicacion-social.md, 6;
| fase 3.1): nace sola con la foto, sigue al vínculo —vence con el contrato y
| se anula cuando termina—, se revoca y se repone, se imprime guardando lo
| impreso, y la verifica cualquiera sin sesión.
|
| Hoy es el lunes 19 de octubre de 2026.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

function jpegDePruebaCredencial(): string
{
    $imagen = imagecreatetruecolor(900, 1200);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 180, 190, 210));
    $ruta = tempnam(sys_get_temp_dir(), 'cred').'.jpg';
    imagejpeg($imagen, $ruta, 90);

    return $ruta;
}

function servidorDePruebaCredencial(object $prueba, string $cedula, array $atributos = []): Servidor
{
    return Servidor::create(array_merge([
        'cedula' => $cedula, 'nombre' => 'Ana', 'segundo_nombre' => 'Lucía', 'apellido' => 'Cortez',
        'segundo_apellido' => 'Valencia', 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
        'puesto_id' => $prueba->puesto->id, 'unidad_administrativa_id' => $prueba->unidad->id,
        'tipo_sangre' => 'O+',
        'contacto_emergencia_nombre' => 'Pedro Cortez', 'contacto_emergencia_parentesco' => 'Hermano',
        'contacto_emergencia_telefono' => '0991112233',
    ], $atributos));
}

function contratoDePruebaCredencial(object $prueba, Servidor $servidor, ?string $fin = null, TipoNombramiento $tipo = TipoNombramiento::PERMANENTE): ContratoServidor
{
    return ContratoServidor::create([
        'servidor_id' => $servidor->id, 'tipo_nombramiento' => $tipo->value,
        'unidad_administrativa_id' => $prueba->unidad->id, 'puesto_id' => $prueba->puesto->id,
        'fecha_inicio' => '2024-01-02', 'fecha_fin' => $fin, 'estado' => 'vigente', 'remuneracion' => 1000,
    ]);
}

function fotoDePruebaCredencial(object $prueba, Servidor $servidor): void
{
    app(FotografiaServiceInterface::class)->subir($servidor, jpegDePruebaCredencial(), $prueba->comunicacion, OrigenFotografia::INDIVIDUAL);
}

function usuarioDePruebaCredencial(string $rol, ?int $servidorId = null): User
{
    $usuario = User::factory()->create(['servidor_id' => $servidorId]);
    $usuario->assignRole($rol);

    return $usuario;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-19 10:00'));

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Comunicación']);
    $this->puesto = puestoDePrueba($this->unidad, 'Analista de Comunicación', ['plazas' => 10]);
    $this->comunicacion = usuarioDePruebaCredencial('comunicacion-social');
    $this->servicio = app(CredencialServiceInterface::class);

    $this->ana = servidorDePruebaCredencial($this, '0801111111');
    contratoDePruebaCredencial($this, $this->ana);
});

// ── Cuándo nace y cómo sigue al vínculo ────────────────────────────────────

test('nace sola al subir la foto de un servidor activo, con un código largo y sin fecha de fin', function () {
    expect(Credencial::count())->toBe(0);

    fotoDePruebaCredencial($this, $this->ana);

    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();
    expect($credencial->estado)->toBe(EstadoCredencial::VIGENTE)
        ->and(strlen($credencial->codigo))->toBe(40)
        ->and($credencial->valida_hasta)->toBeNull()
        ->and($credencial->estaVigente())->toBeTrue();

    // Una foto nueva no crea otra credencial: es la misma.
    fotoDePruebaCredencial($this, $this->ana);
    expect(Credencial::where('servidor_id', $this->ana->id)->count())->toBe(1);
});

test('sin foto, o sin vínculo, no hay credencial', function () {
    $inactivo = servidorDePruebaCredencial($this, '0803333333', ['estado' => false]);
    fotoDePruebaCredencial($this, $inactivo);

    expect($this->servicio->sincronizar($this->ana->id))->toBeNull()
        ->and(Credencial::where('servidor_id', $inactivo->id)->exists())->toBeFalse();
});

test('un expediente activo sin su contrato cargado también la recibe', function () {
    $antiguo = servidorDePruebaCredencial($this, '0804444444');
    fotoDePruebaCredencial($this, $antiguo);

    expect(Credencial::where('servidor_id', $antiguo->id)->sole()->estado)->toBe(EstadoCredencial::VIGENTE);
});

test('con contrato a plazo vale hasta su fin, vence con la tarea de la noche y vuelve con la prórroga', function () {
    $luis = servidorDePruebaCredencial($this, '0802222222');
    $contrato = contratoDePruebaCredencial($this, $luis, '2026-10-31', TipoNombramiento::SERVICIOS_OCASIONALES);
    fotoDePruebaCredencial($this, $luis);

    $credencial = Credencial::where('servidor_id', $luis->id)->sole();
    expect($credencial->valida_hasta->toDateString())->toBe('2026-10-31');

    $this->travelTo(Carbon::parse('2026-11-01 00:45'));
    $this->artisan('sgth:credenciales:conciliar')->assertSuccessful();

    expect($credencial->fresh()->estado)->toBe(EstadoCredencial::VENCIDA);
    $this->getJson("/api/v1/credenciales/verificar/{$credencial->codigo}")
        ->assertOk()
        ->assertExactJson(['exito' => true, 'mensaje' => 'Esta credencial no está vigente.', 'datos' => ['vigente' => false], 'meta' => null]);

    // Se prorroga el contrato: la misma credencial, con el mismo código, vuelve a valer.
    $contrato->update(['fecha_fin' => '2027-03-31']);
    expect($this->servicio->conciliar())->toMatchArray(['reactivadas' => 1]);

    $credencial->refresh();
    expect($credencial->estado)->toBe(EstadoCredencial::VIGENTE)
        ->and($credencial->valida_hasta->toDateString())->toBe('2027-03-31')
        ->and(Credencial::where('servidor_id', $luis->id)->count())->toBe(1);
});

test('aunque la tarea de la noche no corra, una vencida no pasa por vigente', function () {
    $luis = servidorDePruebaCredencial($this, '0802222222');
    contratoDePruebaCredencial($this, $luis, '2026-10-31', TipoNombramiento::SERVICIOS_OCASIONALES);
    fotoDePruebaCredencial($this, $luis);
    $credencial = Credencial::where('servidor_id', $luis->id)->sole();

    $this->travelTo(Carbon::parse('2026-11-02 09:00'));

    expect($credencial->fresh()->estado)->toBe(EstadoCredencial::VIGENTE)
        ->and($credencial->fresh()->estaVigente())->toBeFalse();
    $this->getJson("/api/v1/credenciales/verificar/{$credencial->codigo}")->assertJsonPath('datos.vigente', false);
});

test('cuando el vínculo termina se anula, y no vuelve aunque reingrese', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $anterior = Credencial::where('servidor_id', $this->ana->id)->sole();

    ContratoServidor::where('servidor_id', $this->ana->id)->update(['estado' => 'terminado', 'fecha_fin' => '2026-10-19']);
    app(ContratoServidorService::class)->sincronizarPuestoDesdeVinculo($this->ana->id);

    $anterior->refresh();
    expect($anterior->estado)->toBe(EstadoCredencial::ANULADA)
        ->and($anterior->anulada_en)->not->toBeNull();

    // Reingresa: credencial nueva, con otro código; la anulada no se revive.
    contratoDePruebaCredencial($this, $this->ana);
    app(ContratoServidorService::class)->sincronizarPuestoDesdeVinculo($this->ana->id);

    $nueva = Credencial::where('servidor_id', $this->ana->id)->vivas()->sole();
    expect($nueva->id)->not->toBe($anterior->id)
        ->and($nueva->codigo)->not->toBe($anterior->codigo)
        ->and($anterior->fresh()->estado)->toBe(EstadoCredencial::ANULADA);
});

test('la base no admite dos credenciales vivas para el mismo servidor', function () {
    fotoDePruebaCredencial($this, $this->ana);

    expect(fn () => Credencial::create([
        'servidor_id' => $this->ana->id, 'codigo' => str_repeat('a', 40), 'estado' => EstadoCredencial::VENCIDA,
    ]))->toThrow(QueryException::class);
});

// ── Revocar y reponer ──────────────────────────────────────────────────────

test('revocar repone con otro código, y el de la revocada deja de validar', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $perdida = Credencial::where('servidor_id', $this->ana->id)->sole();

    $respuesta = $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson("/api/v1/comunicacion/credenciales/{$perdida->id}/revocar", ['motivo' => 'perdida'])
        ->assertOk()
        ->assertJsonPath('datos.revocada.estado', 'revocada')
        ->assertJsonPath('datos.revocada.motivo_etiqueta', 'Pérdida')
        ->assertJsonPath('datos.repuesta.estado', 'vigente')
        ->assertJsonPath('datos.repuesta.reemplaza_a_id', $perdida->id);

    $repuesta = Credencial::findOrFail($respuesta->json('datos.repuesta.id'));
    expect($repuesta->codigo)->not->toBe($perdida->codigo)
        ->and($perdida->fresh()->revocada_por)->toBe($this->comunicacion->id);

    $this->getJson("/api/v1/credenciales/verificar/{$perdida->codigo}")->assertJsonPath('datos.vigente', false);
    $this->getJson("/api/v1/credenciales/verificar/{$repuesta->codigo}")->assertJsonPath('datos.vigente', true);

    // Una revocada no se vuelve a revocar.
    $this->postJson("/api/v1/comunicacion/credenciales/{$perdida->id}/revocar", ['motivo' => 'robo'])
        ->assertStatus(422);
});

test('«otro» como motivo pide el detalle', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();

    $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson("/api/v1/comunicacion/credenciales/{$credencial->id}/revocar", ['motivo' => 'otro'])
        ->assertStatus(422)
        ->assertJsonPath('errores.detalle.0', 'Diga por qué se revoca la credencial.');

    expect($credencial->fresh()->estado)->toBe(EstadoCredencial::VIGENTE);
});

// ── Imprimir ───────────────────────────────────────────────────────────────

test('imprimir guarda lo impreso, y la tarjeta queda desactualizada si cambia el contacto de emergencia', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();

    $pdf = $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson('/api/v1/comunicacion/credenciales/imprimir', ['credenciales' => [$credencial->id], 'formato' => 'cr80'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // Anverso y reverso, del tamaño de la tarjeta.
    expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF')
        ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf->getContent()))->toBe(2);

    $impresion = ImpresionCredencial::where('credencial_id', $credencial->id)->sole();
    expect($impresion->lote)->toBeNull()
        ->and($impresion->impresa_por)->toBe($this->comunicacion->id)
        ->and($impresion->datos_impresos)->toMatchArray([
            'nombres' => 'Ana Lucía', 'cargo' => 'Analista de Comunicación',
            'unidad' => 'Dirección de Comunicación', 'tipo_sangre' => 'O+',
        ]);

    $fila = fn () => collect($this->getJson('/api/v1/comunicacion/credenciales')->assertOk()->json('datos'))
        ->firstWhere('id', $this->ana->id);
    expect($fila()['situacion'])->toBe('al_dia');

    $this->ana->update(['contacto_emergencia_telefono' => '0998887766']);

    expect($fila()['situacion'])->toBe('desactualizada')
        ->and($fila()['credencial']['cambios_desde_impresion'])->toBe(['Contacto de emergencia']);

    $this->getJson('/api/v1/comunicacion/credenciales?situacion=desactualizada')
        ->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.id', $this->ana->id);
    $this->getJson('/api/v1/comunicacion/credenciales?situacion=al_dia')
        ->assertOk()->assertJsonCount(0, 'datos');
});

test('por unidad sale en A4, nueve por cara, en un mismo lote', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $luis = servidorDePruebaCredencial($this, '0802222222');
    fotoDePruebaCredencial($this, $luis);
    // Sin foto: no tiene credencial que imprimir.
    servidorDePruebaCredencial($this, '0805555555');

    $pdf = $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson('/api/v1/comunicacion/credenciales/imprimir', ['unidad_administrativa_id' => $this->unidad->id, 'formato' => 'a4'])
        ->assertOk();

    // Una hoja de anversos y otra de reversos.
    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf->getContent()))->toBe(2);

    $impresiones = ImpresionCredencial::all();
    expect($impresiones)->toHaveCount(2)
        ->and($impresiones->pluck('lote')->unique())->toHaveCount(1)
        ->and($impresiones->first()->lote)->not->toBeNull();

    $this->getJson('/api/v1/comunicacion/credenciales?situacion=sin_foto')
        ->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.cedula', '0805555555');
});

test('no se imprime una credencial que no está vigente', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();
    $this->servicio->revocar($credencial, \App\Enums\MotivoRevocacionCredencial::DATOS_ERRONEOS, null, $this->comunicacion);

    $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson('/api/v1/comunicacion/credenciales/imprimir', ['credenciales' => [$credencial->id], 'formato' => 'cr80'])
        ->assertStatus(422);

    expect(ImpresionCredencial::count())->toBe(0);
});

// ── Verificación pública ───────────────────────────────────────────────────

test('la verificación enseña foto, nombre, cargo y unidad; nunca la cédula, la sangre ni el contacto', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();

    $respuesta = $this->getJson("/api/v1/credenciales/verificar/{$credencial->codigo}")
        ->assertOk()
        ->assertJsonPath('datos.vigente', true)
        ->assertJsonPath('datos.nombre', 'Ana Lucía Cortez Valencia')
        ->assertJsonPath('datos.cargo', 'Analista de Comunicación')
        ->assertJsonPath('datos.unidad', 'Dirección de Comunicación');

    expect(array_keys($respuesta->json('datos')))->toBe(['vigente', 'nombre', 'cargo', 'unidad', 'foto'])
        ->and($respuesta->json('datos.foto'))->toStartWith('data:image/jpeg;base64,')
        ->and($respuesta->getContent())->not->toContain('0801111111"')
        ->and($respuesta->getContent())->not->toContain('O+')
        ->and($respuesta->getContent())->not->toContain('0991112233');

    $this->getJson('/api/v1/credenciales/verificar/'.str_repeat('0', 40))->assertNotFound();
    // Un código corto ni siquiera llega a buscarse.
    $this->getJson('/api/v1/credenciales/verificar/abc')->assertNotFound();
});

// ── Mi credencial ──────────────────────────────────────────────────────────

test('«Mi credencial» es la propia, con la sangre, el contacto y el QR, y se descarga en PDF', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $usuario = usuarioDePruebaCredencial('servidor', $this->ana->id);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-credencial')
        ->assertOk()
        ->assertJsonPath('datos.vigente', true)
        ->assertJsonPath('datos.datos.tipo_sangre', 'O+')
        ->assertJsonPath('datos.datos.contacto_emergencia.telefono', '0991112233')
        ->assertJsonPath('datos.datos.cedula', '0801111111');

    expect($this->getJson('/api/v1/autoservicio/mi-credencial')->json('datos.qr'))->toStartWith('data:image/svg+xml;base64,');

    $pdf = $this->get('/api/v1/autoservicio/mi-credencial/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf->getContent()))->toBe(2)
        // La digital no cuenta como impresión de Comunicación.
        ->and(ImpresionCredencial::count())->toBe(0);
});

test('sin foto, «Mi credencial» dice por qué no la tiene', function () {
    $usuario = usuarioDePruebaCredencial('servidor', $this->ana->id);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-credencial')
        ->assertOk()
        ->assertJsonPath('datos', null)
        ->assertJsonPath('mensaje', 'Se crea cuando Comunicación Social registre su foto.');

    $this->get('/api/v1/autoservicio/mi-credencial/pdf')->assertStatus(422);
});

// ── Permisos ───────────────────────────────────────────────────────────────

test('Talento Humano y Auditoría consultan; solo Comunicación imprime, revoca y recibe', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();

    foreach (['admin-uath', 'auditor'] as $rol) {
        $usuario = usuarioDePruebaCredencial($rol);
        $this->actingAs($usuario, 'sanctum')->getJson('/api/v1/comunicacion/credenciales')->assertOk();
        $this->getJson("/api/v1/comunicacion/servidores/{$this->ana->id}/credenciales")->assertOk();
        $this->postJson('/api/v1/comunicacion/credenciales/imprimir', ['credenciales' => [$credencial->id], 'formato' => 'cr80'])
            ->assertForbidden();
        $this->postJson("/api/v1/comunicacion/credenciales/{$credencial->id}/revocar", ['motivo' => 'perdida'])
            ->assertForbidden();
    }

    $servidor = usuarioDePruebaCredencial('servidor', $this->ana->id);
    $this->actingAs($servidor, 'sanctum')->getJson('/api/v1/comunicacion/credenciales')->assertForbidden();

    // El responsable de identidad cambia los logos, no las credenciales.
    $identidad = usuarioDePruebaCredencial('responsable-identidad');
    $this->actingAs($identidad, 'sanctum')->getJson('/api/v1/comunicacion/credenciales')->assertForbidden();
});

// ── La devolución de la tarjeta ────────────────────────────────────────────

test('la tarjeta se recibe cuando la credencial ya no vale y solo si se imprimió', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();
    $recibir = fn (array $datos = ['fecha' => '2026-10-19']) => $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson("/api/v1/comunicacion/credenciales/{$credencial->id}/devolucion", $datos);

    $recibir()->assertStatus(422)->assertJsonPath('mensaje', 'La credencial sigue viva: la tarjeta se recibe cuando se anula o se revoca.');

    $this->ana->update(['estado' => false]);
    $this->servicio->sincronizar($this->ana->id);
    $recibir()->assertStatus(422)->assertJsonPath('mensaje', 'Esta credencial nunca se imprimió: no hay tarjeta que recibir.');

    ImpresionCredencial::create([
        'credencial_id' => $credencial->id, 'impresa_por' => $this->comunicacion->id, 'formato' => 'cr80', 'datos_impresos' => [],
    ]);
    $recibir(['fecha' => '2026-10-20'])->assertStatus(422);
    $recibir(['fecha' => '2026-10-19', 'nota' => 'La entregó en Comunicación.'])
        ->assertOk()
        ->assertJsonPath('datos.devuelta_en', '2026-10-19')
        ->assertJsonPath('datos.devolucion_registrada_por.id', $this->comunicacion->id);

    $recibir()->assertStatus(422)->assertJsonPath('mensaje', 'La tarjeta ya se recibió el 19/10/2026.');
});

test('quien salió con la tarjeta impresa aparece por recibir hasta que la entrega', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();
    ImpresionCredencial::create([
        'credencial_id' => $credencial->id, 'impresa_por' => $this->comunicacion->id, 'formato' => 'cr80', 'datos_impresos' => [],
    ]);
    $porRecibir = fn () => $this->actingAs($this->comunicacion, 'sanctum')
        ->getJson('/api/v1/comunicacion/credenciales?situacion=por_recibir')->assertOk();

    expect($porRecibir()->json('datos'))->toBe([]);

    ContratoServidor::where('servidor_id', $this->ana->id)->update(['estado' => 'terminado', 'fecha_fin' => '2026-10-19']);
    app(ContratoServidorService::class)->sincronizarPuestoDesdeVinculo($this->ana->id);

    $porRecibir()->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.id', $this->ana->id)
        ->assertJsonPath('datos.0.situacion', 'por_recibir');
    // En el listado de siempre ya no está: no trabaja aquí.
    $this->getJson('/api/v1/comunicacion/credenciales')->assertJsonCount(0, 'datos');

    $this->servicio->registrarDevolucion($credencial->fresh(), '2026-10-19', null, $this->comunicacion);

    expect($porRecibir()->json('datos'))->toBe([]);
});

test('con los fondos de Identidad, la tarjeta los lleva detrás de cada cara', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();
    $imagenes = fn (string $pdf) => preg_match_all('/\/Subtype\s*\/Image/', $pdf);
    $imprimir = fn () => $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson('/api/v1/comunicacion/credenciales/imprimir', ['credenciales' => [$credencial->id], 'formato' => 'cr80'])
        ->assertOk()
        ->getContent();

    $sinFondo = $imagenes($imprimir());

    // La persona designada carga los dos fondos (D14).
    $responsable = usuarioDePruebaCredencial('responsable-identidad');
    foreach (['fondo_credencial_anverso', 'fondo_credencial_reverso'] as $variante) {
        $imagen = imagecreatetruecolor(638, 1011);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, $variante === 'fondo_credencial_anverso' ? 20 : 200, 90, 60));
        $ruta = tempnam(sys_get_temp_dir(), 'fondo').'.png';
        imagepng($imagen, $ruta);

        $this->actingAs($responsable, 'sanctum')->post("/api/v1/comunicacion/identidad/{$variante}", [
            'archivo' => new \Illuminate\Http\UploadedFile($ruta, 'fondo.png', 'image/png', null, true),
        ], ['Accept' => 'application/json'])->assertCreated();
    }

    expect($imagenes($imprimir()))->toBe($sinFondo + 2);
});

test('avisa qué datos del reverso faltan en el expediente', function () {
    $sinDatos = servidorDePruebaCredencial($this, '0806666666', [
        'tipo_sangre' => null, 'contacto_emergencia_nombre' => null, 'contacto_emergencia_parentesco' => null,
        'contacto_emergencia_telefono' => null,
    ]);
    fotoDePruebaCredencial($this, $sinDatos);
    fotoDePruebaCredencial($this, $this->ana);

    $filas = collect($this->actingAs($this->comunicacion, 'sanctum')->getJson('/api/v1/comunicacion/credenciales')->assertOk()->json('datos'))
        ->keyBy('id');
    expect($filas[$sinDatos->id]['credencial']['datos_faltantes'])->toBe(['Tipo de sangre', 'Contacto de emergencia'])
        ->and($filas[$this->ana->id]['credencial']['datos_faltantes'])->toBe([]);

    $this->actingAs(usuarioDePruebaCredencial('servidor', $sinDatos->id), 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-credencial')
        ->assertOk()
        ->assertJsonPath('datos.datos_faltantes', ['Tipo de sangre', 'Contacto de emergencia']);
});

test('en producción no imprime una tarjeta cuyo QR lleva una dirección local', function () {
    fotoDePruebaCredencial($this, $this->ana);
    $credencial = Credencial::where('servidor_id', $this->ana->id)->sole();
    $imprimir = fn () => $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson('/api/v1/comunicacion/credenciales/imprimir', ['credenciales' => [$credencial->id], 'formato' => 'cr80']);

    config(['app.frontend_url' => 'http://localhost:3000']);

    // En desarrollo se imprime, para probar; la pantalla avisa.
    $imprimir()->assertOk();
    $this->getJson('/api/v1/comunicacion/credenciales/verificacion')
        ->assertOk()
        ->assertJsonPath('datos.local', true)
        ->assertJsonPath('datos.impresion_bloqueada', false)
        ->assertJsonPath('datos.url_base', 'http://localhost:3000/verificar/credencial/');

    $this->app['env'] = 'production';
    try {
        $imprimir()->assertStatus(422)
            ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'FRONTEND_URL'));
        $this->getJson('/api/v1/comunicacion/credenciales/verificacion')->assertJsonPath('datos.impresion_bloqueada', true);

        config(['app.frontend_url' => 'https://sgth.gadpe.gob.ec']);
        $imprimir()->assertOk();
        $this->getJson('/api/v1/comunicacion/credenciales/verificacion')->assertJsonPath('datos.local', false);
    } finally {
        $this->app['env'] = 'testing';
    }

    expect(ImpresionCredencial::count())->toBe(2);
});
