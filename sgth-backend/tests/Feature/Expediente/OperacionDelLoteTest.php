<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\MedioNotificacion;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.2 del diseño de Acciones de Personal: operar el lote como conjunto.
| Sus documentos en un ZIP —con la marca BORRADOR lo que sigue en trámite—, la
| notificación por el portal de lo registrado, y el listado por páginas y con
| filtros. Se prueba con el traspaso de un puesto (fase 7.1a).
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->origen = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->destino = unidadDePrueba(['nombre' => 'Dirección de Planificación']);

    $this->contador = 0;
    $this->ocupante = function (Puesto $puesto): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6600700000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Lote'.$this->contador, 'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2018-01-02',
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
            'unidad_administrativa_id' => $puesto->unidad_administrativa_id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => 1000,
        ]);
        $servidor->update(['puesto_id' => $puesto->id, 'unidad_administrativa_id' => $puesto->unidad_administrativa_id]);

        return $servidor->fresh();
    };

    /** Un puesto con dos ocupantes y su traspaso preparado. */
    $this->lote = function (): LoteAccion {
        $puesto = puestoDePrueba($this->origen, 'Analista '.($this->contador + 1), ['plazas' => 3]);
        $this->ocupantes = [($this->ocupante)($puesto), ($this->ocupante)($puesto)];

        $id = $this->postJson('/api/v1/expediente/lotes-accion/traspaso-puesto', [
            'puesto_id' => $puesto->id, 'unidad_destino_id' => $this->destino->id, 'criterio' => 'reorganizacion_interna',
            'informe_uath_numero' => 'INF-UATH-2026-031', 'fecha_efectiva' => '2026-10-12',
            'descripcion' => 'Traspaso del puesto por la reorganización de la Dirección de Obras.',
        ])->assertCreated()->json('datos.id');

        return LoteAccion::findOrFail($id);
    };

    $this->registrar = fn (LoteAccion $l) => $this->postJson("/api/v1/expediente/lotes-accion/{$l->id}/registrar")->assertOk();

    /** Los nombres de los archivos del ZIP que devuelve la descarga. */
    $this->archivosDelZip = function (LoteAccion $l): array {
        $respuesta = $this->get("/api/v1/expediente/lotes-accion/{$l->id}/documentos")->assertOk();
        $zip = new ZipArchive;
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $nombres = array_map(fn (int $i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        $zip->close();

        return $nombres;
    };
});

// ── Los documentos ──────────────────────────────────────────────

test('los documentos del lote van en un ZIP, con la marca BORRADOR lo que sigue en trámite', function () {
    $lote = ($this->lote)();

    $nombres = ($this->archivosDelZip)($lote);

    expect($nombres)->toHaveCount(2)
        ->and($nombres[0])->toStartWith('01_borrador-')
        ->and($nombres[0])->toEndWith("_{$this->ocupantes[0]->cedula}.pdf")
        ->and($nombres[1])->toStartWith('02_borrador-');
});

test('registrado, va el documento definitivo, con su número', function () {
    $lote = ($this->lote)();
    ($this->registrar)($lote);

    $nombres = ($this->archivosDelZip)($lote);
    $codigo = $lote->movimientos()->orderBy('id')->value('codigo_registro');

    expect($nombres)->toHaveCount(2)
        ->and($nombres[0])->toBe("01_{$codigo}_{$this->ocupantes[0]->cedula}.pdf");
});

test('la vista previa de lo propio no va en el ZIP, y se dice por qué', function () {
    $lote = ($this->lote)();

    $otro = User::factory()->create(['servidor_id' => $this->ocupantes[0]->id]);
    $otro->assignRole('admin-uath');
    $this->actingAs($otro, 'sanctum');

    expect(($this->archivosDelZip)($lote))->toHaveCount(2)
        ->and(($this->archivosDelZip)($lote))->toContain('omitidos.txt');
});

// ── La notificación ─────────────────────────────────────────────

test('notificar el lote notifica por el portal lo registrado, y omite lo propio y lo ya notificado', function () {
    $lote = ($this->lote)();
    ($this->registrar)($lote);

    $otro = User::factory()->create(['servidor_id' => $this->ocupantes[0]->id]);
    $otro->assignRole('admin-uath');
    $this->actingAs($otro, 'sanctum');

    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/notificar")
        ->assertOk()
        ->assertJsonPath('datos.notificados', 1)
        ->assertJsonPath('datos.omitidos.0.motivo', fn (string $m) => str_contains($m, 'su propia acción'));

    $actos = $lote->movimientos()->orderBy('id')->get();
    expect($actos[0]->estado)->toBe(EstadoAccionPersonal::REGISTRADA)
        ->and($actos[1]->estado)->toBe(EstadoAccionPersonal::NOTIFICADA)
        ->and($actos[1]->notificacion_medio)->toBe(MedioNotificacion::PORTAL);

    // Otra persona notifica lo que falta; lo ya notificado no se repite.
    $this->actingAs($this->user, 'sanctum');
    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/notificar")
        ->assertOk()
        ->assertJsonPath('datos.notificados', 1)
        ->assertJsonPath('datos.omitidos', []);
});

// ── El listado ──────────────────────────────────────────────────

test('el listado va por páginas, y filtra por tipo y por situación', function () {
    $enTramite = ($this->lote)();
    $registrado = ($this->lote)();
    ($this->registrar)($registrado);
    $descartado = ($this->lote)();
    $this->postJson("/api/v1/expediente/lotes-accion/{$descartado->id}/descartar", ['motivo' => 'Se preparó por error.'])->assertOk();

    $ids = fn (string $consulta) => collect($this->getJson("/api/v1/expediente/lotes-accion?{$consulta}")->assertOk()->json('datos.data'))->pluck('id')->all();

    expect($ids('situacion=en_tramite'))->toBe([$enTramite->id])
        ->and($ids('situacion=registrado'))->toBe([$registrado->id])
        ->and($ids('situacion=anulado'))->toBe([$descartado->id])
        ->and($ids('tipo=revision_clasificacion'))->toBe([])
        ->and($ids('per_page=2'))->toHaveCount(2);

    $this->getJson('/api/v1/expediente/lotes-accion?situacion=otra')->assertStatus(422);
});
