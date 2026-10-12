<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\AccionPersonalPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 6.2 del diseño de Acciones de Personal (6.5; TH 27): la vista previa de
| lo que todavía no se registra —borrador o suscrita—, con la marca BORRADOR,
| sin número y, en el borrador, con los firmantes que firmarían hoy. La ve
| quien prepara acciones; el titular no ve sus borradores.
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'empleado', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'VP-ADM', 'nombre' => 'Dirección Administrativa', 'nivel' => 2]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Analista Administrativo'])->id,
    ]);

    $this->contador = 0;
    $this->servidor = function (string $apellido, ?Puesto $en = null, string $nombramiento = 'nombramiento_permanente'): Servidor {
        $this->contador++;
        $en ??= $this->puesto;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6500000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => $apellido,
            'regimen_laboral' => $nombramiento === 'codigo_trabajo' ? 'codigo_trabajo' : 'losep',
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => $nombramiento,
            'unidad_administrativa_id' => $en->unidad_administrativa_id, 'puesto_id' => $en->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
        ]);

        return $servidor->fresh();
    };

    $this->accion = fn (Servidor $servidor, array $datos = []) => MovimientoPersonal::create([
        'servidor_id' => $servidor->id, 'estado' => EstadoAccionPersonal::BORRADOR,
        'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion' => 'Renuncia voluntaria.', 'fecha_efectiva' => '2026-11-01',
        'unidad_origen_id' => $this->unidad->id, 'puesto_origen_id' => $this->puesto->id,
        ...$datos,
    ]);

    $this->html = function (MovimientoPersonal $m): string {
        ['vista' => $vista, 'datos' => $datos] = app(AccionPersonalPdfService::class)->documento($m->id, vistaPrevia: true);

        return view($vista, $datos)->render();
    };

    /** El organigrama: quién firmaría hoy por cada rol. */
    $this->organigrama = function (): void {
        foreach ([
            ['PREF-VP', 'Prefectura', 'es_maxima_autoridad', 'Prefecto/a Provincial', 'Zambrano'],
            ['TH-VP', 'Dirección de Talento Humano', 'es_unidad_talento_humano', 'Director/a de Talento Humano', 'Valencia'],
        ] as [$codigo, $nombre, $marca, $cargo, $apellido]) {
            $unidad = UnidadAdministrativa::create(['codigo' => $codigo, 'nombre' => $nombre, 'nivel' => 1, $marca => true]);
            $jefe = Puesto::create([
                'unidad_administrativa_id' => $unidad->id, 'plazas' => 1, 'activo' => true, 'es_jefe' => true,
                'cargo_id' => Cargo::firstOrCreate(['nombre' => $cargo])->id,
            ]);
            ($this->servidor)($apellido, $jefe);
        }
    };
});

test('el borrador sale con la marca, sin número y con los firmantes que firmarían hoy', function () {
    ($this->organigrama)();
    $borrador = ($this->accion)(($this->servidor)('Renunciante'));

    $html = ($this->html)($borrador);

    expect($html)->toContain('class="marca-borrador">BORRADOR')
        ->and($html)->toContain('Borrador — vista previa sin validez')
        ->and($html)->toContain('Todavía no se suscribe')
        ->and($html)->toContain('<span class="codigo">BORRADOR</span>')
        ->and($html)->not->toContain('N.º S/N')
        ->and($html)->toContain('la fecha de emisión se fija al suscribir')
        ->and($html)->toContain('Zambrano')
        ->and($html)->toContain('Valencia')
        ->and(substr_count($html, 'Provisional: se sella al suscribir'))->toBe(2)
        // La base legal de su causal, como en el documento.
        ->and($html)->toContain('LOSEP Art. 47 a; Reglamento Art. 102');

    $this->get("/api/v1/expediente/movimientos/{$borrador->id}/vista-previa")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', "inline; filename=\"vista_previa_accion_personal_{$borrador->id}.pdf\"");

    // Ni se sellaron los firmantes ni cambió nada.
    expect($borrador->fresh()->firmante_autoridad_nombre)->toBeNull()
        ->and($borrador->fresh()->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

test('la suscrita usa los firmantes sellados y su fecha de suscripción', function () {
    $suscrita = ($this->accion)(($this->servidor)('Suscrito'), [
        'estado' => EstadoAccionPersonal::SUSCRITA, 'fecha_suscripcion' => '2026-10-12',
        'firmante_autoridad_nombre' => 'Roberta Zambrano', 'firmante_autoridad_cargo' => 'Prefecta Provincial',
        'firmante_th_nombre' => 'Carlos Valencia', 'firmante_th_cargo' => 'Director de Talento Humano',
    ]);

    $html = ($this->html)($suscrita);

    expect($html)->toContain('Está suscrita y todavía no se registra')
        ->and($html)->toContain('Roberta Zambrano')
        ->and($html)->toContain('Esmeraldas, 12 de octubre del 2026')
        ->and($html)->not->toContain('Provisional: se sella al suscribir');
});

test('sin firmante en el organigrama, avisa que no se podría suscribir', function () {
    $html = ($this->html)(($this->accion)(($this->servidor)('SinFirmantes')));

    expect(substr_count($html, 'Sin firmante: así no se podría suscribir'))->toBe(2);
});

test('lo registrado no se previsualiza, ni lo que no tiene documento', function () {
    $registrada = ($this->accion)(($this->servidor)('Registrado'), [
        'estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'AP-2026-0700',
    ]);
    $this->getJson("/api/v1/expediente/movimientos/{$registrada->id}/vista-previa")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La vista previa es de lo que todavía no se registra: esta acción ya tiene su documento, descárguelo.');

    // El ingreso de un ocasional es el registro de su contrato: no tiene documento.
    $contrato = ($this->accion)(($this->servidor)('Ocasional'), [
        'tipo_movimiento' => TipoMovimientoPersonal::INGRESO->value, 'subtipo_movimiento' => null,
        'instrumento' => InstrumentoActo::REGISTRO_CONTRATO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
    ]);
    $this->getJson("/api/v1/expediente/movimientos/{$contrato->id}/vista-previa")->assertStatus(422);
});

test('el titular no ve la suya, ni la pide quien no prepara acciones', function () {
    $titular = ($this->servidor)('Titular');
    $borrador = ($this->accion)($titular);

    // Un funcionario de Talento Humano que es el titular de la acción.
    $propio = User::factory()->create(['servidor_id' => $titular->id]);
    $propio->assignRole('admin-uath');
    $this->actingAs($propio, 'sanctum')
        ->getJson("/api/v1/expediente/movimientos/{$borrador->id}/vista-previa")
        ->assertStatus(422);

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');
    $this->actingAs($empleado, 'sanctum')
        ->getJson("/api/v1/expediente/movimientos/{$borrador->id}/vista-previa")
        ->assertForbidden();
});

test('la hoja del obrero y la constancia de la autoridad también llevan la marca', function () {
    $obrero = ($this->servidor)('Obrero', null, 'codigo_trabajo');
    $hoja = ($this->accion)($obrero, [
        'tipo_movimiento' => TipoMovimientoPersonal::INCREMENTO_REMUNERACION->value, 'subtipo_movimiento' => null,
        'instrumento' => InstrumentoActo::DOCUMENTO_CT->value, 'remuneracion_propuesta' => 600,
    ]);
    $html = ($this->html)($hoja);
    expect($html)->toContain('Hoja de registro interno')
        ->and($html)->toContain('<span class="codigo">BORRADOR</span>')
        ->and($html)->toContain('<div class="footer">BORRADOR</div>')
        ->and($html)->toContain('la fecha se fija al registrar');

    $autoridad = ($this->servidor)('Autoridad', null, 'eleccion_popular');
    $constancia = ($this->accion)($autoridad, [
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_CONSEJO->value, 'subtipo_movimiento' => null,
        'instrumento' => InstrumentoActo::ACTO_AUTORIDAD->value,
    ]);
    expect(($this->html)($constancia))->toContain('Acto de autoridad electa')
        ->and(($this->html)($constancia))->toContain('Borrador — vista previa sin validez');
});

test('el recurso dice cuándo se puede pedir', function () {
    $borrador = ($this->accion)(($this->servidor)('Recurso'));
    $registrada = ($this->accion)(($this->servidor)('Recurso2'), [
        'estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'AP-2026-0701',
    ]);

    $this->getJson("/api/v1/expediente/movimientos/{$borrador->id}")->assertOk()->assertJsonPath('datos.puede_previsualizar', true);
    $this->getJson("/api/v1/expediente/movimientos/{$registrada->id}")->assertOk()->assertJsonPath('datos.puede_previsualizar', false);
});
