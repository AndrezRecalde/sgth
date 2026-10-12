<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
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
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 6.1 del diseño de Acciones de Personal (6.5): el documento con el
| formato del Ministerio. La fecha de emisión es la de suscripción; el acto que
| la origina, en su casilla; la rejilla de las 23 casillas con la de la acción
| marcada; la base legal en la explicación; la modalidad del vínculo; el período
| en las temporales; el bloque de registro; y en la segunda página, a quién
| reemplaza, la posesión solo donde va y la notificación.
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'DOC-MDT', 'nombre' => 'Dirección Administrativa', 'nivel' => 2]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Analista Administrativo'])->id,
    ]);

    $this->contador = 0;
    $this->servidor = function (string $apellido, bool $conVinculo = true): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6300000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => $apellido, 'regimen_laboral' => 'losep',
        ]);
        if ($conVinculo) {
            ContratoServidor::create([
                'servidor_id' => $servidor->id, 'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
                'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->puesto->id,
                'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
            ]);
        }

        return $servidor->fresh();
    };

    /** Una acción ya registrada, con su número. */
    $this->accion = fn (Servidor $servidor, array $datos) => MovimientoPersonal::create([
        'servidor_id' => $servidor->id, 'estado' => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro' => 'AP-2026-'.str_pad((string) (++$this->contador), 4, '0', STR_PAD_LEFT),
        'descripcion' => 'Explicación de Talento Humano.', 'fecha_efectiva' => '2026-11-01',
        'fecha_suscripcion' => '2026-10-12', 'fecha_registro' => '2026-10-13',
        'unidad_origen_id' => $this->unidad->id, 'puesto_origen_id' => $this->puesto->id,
        ...$datos,
    ]);

    $this->html = function (MovimientoPersonal $m): string {
        ['vista' => $vista, 'datos' => $datos] = app(AccionPersonalPdfService::class)->documento($m->id);

        return view($vista, $datos)->render();
    };

    /** El texto de la casilla marcada en la rejilla, sin el marcado. */
    $this->marcada = function (string $html): array {
        preg_match_all('/<td class="marcada"[^>]*>(.*?)<\/td>/s', $html, $m);

        return array_map(fn ($c) => trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('</span>', '</span> ', $c)))), $m[1]);
    };
});

test('la emisión es la de suscripción, el acto de origen sale en su casilla y la cesación marca su causal', function () {
    $renuncia = ($this->accion)(($this->servidor)('Renunciante'), [
        'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'acto_origen_tipo' => 'resolucion', 'acto_origen_numero' => 'RES-GADPE-2026-015', 'acto_origen_fecha' => '2026-10-10',
    ]);
    $html = ($this->html)($renuncia);

    expect($html)->toContain('Esmeraldas, 12 de octubre del 2026')
        ->and($html)->not->toContain('01 de noviembre del 2026')
        ->and($html)->toContain('RES-GADPE-2026-015')
        ->and($html)->toContain('10/10/2026')
        // La acción de antes de la 6.1, sin base guardada, imprime la de su causal.
        ->and($html)->toContain('LOSEP Art. 47 a; Reglamento Art. 102')
        ->and(($this->marcada)($html))->toBe(['X Renuncia'])
        // Registro y control.
        ->and($html)->toContain('Registro y control')
        ->and($html)->toContain('13/10/2026')
        // La cesación no lleva posesión, y sí notificación.
        ->and($html)->not->toContain('Posesión del cargo')
        ->and($html)->toContain('Razón de la negativa');

    // La casilla del acto de origen marcada es la de la resolución.
    expect($html)->toMatch('/<span class="caja">X<\/span>Resolución/');
});

test('la sanción y la cesación sin casilla propia van en «Otro» con su nombre', function () {
    $multa = ($this->accion)(($this->servidor)('Sancionado'), [
        'tipo_movimiento' => TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::SANCION_PECUNIARIA->value,
    ]);
    expect(($this->marcada)(($this->html)($multa)))->toBe(['X Otro: Sanción Disciplinaria: sanción pecuniaria administrativa']);

    $fallecimiento = ($this->accion)(($this->servidor)('Fallecido'), [
        'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::FALLECIMIENTO->value,
    ]);
    expect(($this->marcada)(($this->html)($fallecimiento)))->toBe(['X Otro: Cesación de Funciones: fallecimiento']);
});

test('el ingreso lleva la posesión y dice a quién reemplaza en el puesto', function () {
    $anterior = ($this->servidor)('Saliente');
    ($this->accion)($anterior, [
        'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'codigo_registro' => 'AP-2026-0100', 'fecha_efectiva' => '2026-09-30',
    ]);

    $ingreso = ($this->accion)(($this->servidor)('Entrante', false), [
        'tipo_movimiento' => TipoMovimientoPersonal::INGRESO->value,
        'unidad_origen_id' => null, 'puesto_origen_id' => null,
        'unidad_destino_id' => $this->unidad->id, 'puesto_destino_id' => $this->puesto->id,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
    ]);
    $html = ($this->html)($ingreso);

    expect(($this->marcada)($html))->toBe(['X Ingreso'])
        ->and($html)->toContain('Posesión del cargo')
        ->and($html)->toContain('Reemplaza a')
        ->and($html)->toContain('Saliente Servidor')
        ->and($html)->toContain('Quien cesó por')
        ->and($html)->toContain('AP-2026-0100')
        // La modalidad propuesta.
        ->and($html)->toContain('Nombramiento Permanente')
        ->and($html)->toContain('LOSEP Arts. 16 a 18; Reglamento a la LOSEP Art. 19');
});

test('la comisión dice el período y la institución en vez de la situación propuesta', function () {
    $comision = ($this->accion)(($this->servidor)('Comisionado'), [
        'tipo_movimiento' => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2027-01-31', 'institucion_destino' => 'Ministerio de Salud Pública',
    ]);
    $html = ($this->html)($comision);

    expect(($this->marcada)($html))->toBe(['X Comisión de servicios'])
        ->and($html)->toContain('>Período<')
        ->and($html)->not->toContain('Situación propuesta')
        ->and($html)->toContain('Institución de destino')
        ->and($html)->toContain('Ministerio de Salud Pública')
        ->and($html)->toContain('31/01/2027')
        // La modalidad del vínculo que tenía.
        ->and($html)->toContain('Nombramiento Permanente')
        ->and($html)->toContain('LOSEP Art. 30')
        ->and($html)->not->toContain('Posesión del cargo');
});

test('la base legal se propone, se corrige en el borrador y se congela al suscribir', function () {
    $estados = app(MovimientoPersonalStateService::class);
    $borrador = fn (array $extra = []) => MovimientoPersonal::create([
        'servidor_id' => ($this->servidor)('Borrador')->id, 'estado' => EstadoAccionPersonal::BORRADOR,
        'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion' => 'Renuncia voluntaria.', 'fecha_efectiva' => '2026-11-01',
        'unidad_origen_id' => $this->unidad->id, 'puesto_origen_id' => $this->puesto->id,
        ...$extra,
    ]);

    $vacia = $borrador();
    expect($vacia->base_legal)->toBeNull()
        ->and($vacia->baseLegalDeLaClase())->toBe('LOSEP Art. 47 a; Reglamento Art. 102');
    $estados->transicionar($vacia, EstadoAccionPersonal::SUSCRITA, ['dictamen_presupuestario_ref' => 'DP-2026-010']);
    expect($vacia->fresh()->base_legal)->toBe('LOSEP Art. 47 a; Reglamento Art. 102');

    $corregida = $borrador(['base_legal' => 'LOSEP Art. 47 a; Reglamento Art. 102; Memorando GADPE-TH-2026-300']);
    $estados->transicionar($corregida, EstadoAccionPersonal::SUSCRITA, ['dictamen_presupuestario_ref' => 'DP-2026-011']);
    expect($corregida->fresh()->base_legal)->toBe('LOSEP Art. 47 a; Reglamento Art. 102; Memorando GADPE-TH-2026-300');
});

test('el formulario recibe la base de cada clase, y el acto de origen va con su número', function () {
    $clases = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk()->json('datos.clases'))
        ->keyBy('codigo');

    expect($clases['traslado']['base_legal'])->toBe('LOSEP Arts. 35 y 36')
        ->and($clases['encargo']['base_legal'])->toBe('LOSEP Art. 127; Reglamento a la LOSEP Art. 271');

    $servidor = ($this->servidor)('Formulario');
    $this->postJson("/api/v1/expediente/servidores/{$servidor->id}/movimientos", [
        'clase' => 'cesacion', 'causal' => 'renuncia', 'descripcion' => 'Renuncia.', 'fecha_efectiva' => '2026-11-01',
        'acto_origen_tipo' => 'resolucion',
    ])->assertStatus(422)->assertJsonValidationErrors(['acto_origen_numero'], 'errores');
});
