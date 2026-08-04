<?php

namespace Tests\Feature\Expediente;

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-01', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1111111111', 'nombre' => 'Titular', 'apellido' => 'Test',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $this->service = app(ExpedienteService::class);
});

/**
 * ContratoServidor y MovimientoPersonal tienen secuencias de id
 * independientes: no basta con filtrar por referencia.id (podrían
 * coincidir entre un vínculo y un evento). Se filtra también por
 * referencia.modelo.
 */
function itemDeLinea($linea, string $modelo, int $id): ?array
{
    return $linea->first(
        fn (array $item) => $item['referencia']['modelo'] === $modelo && $item['referencia']['id'] === $id
    );
}

test('la línea de tiempo une vínculos y eventos ordenados, con el régimen jurídico correcto al cruzar de LOSEP a Código de Trabajo', function () {
    // Vínculo 1: servicios ocasionales (LOSEP), cerrado.
    $vinculo1 = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'servicios_ocasionales',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2019-01-01',
        'fecha_fin' => '2019-12-31',
        'motivo_fin' => 'Fin de contrato ocasional',
        'estado' => 'terminado',
    ]);

    // Vínculo 2: código de trabajo, vigente.
    $vinculo2 = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'codigo_trabajo',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2020-01-01',
        'estado' => 'vigente',
    ]);

    // Eventos en ambos períodos.
    $eventoLosep = MovimientoPersonal::create([
        'servidor_id' => $this->servidor->id,
        'tipo_movimiento' => 'ingreso',
        'descripcion' => 'Ingreso bajo servicios ocasionales',
        'fecha_efectiva' => '2019-01-01',
        'autorizado_por' => $this->user->id,
    ]);

    $eventoCodigoTrabajo = MovimientoPersonal::create([
        'servidor_id' => $this->servidor->id,
        'tipo_movimiento' => 'novedad_contrato',
        'descripcion' => 'Cambio a código de trabajo',
        'fecha_efectiva' => '2020-06-15',
        'autorizado_por' => $this->user->id,
    ]);

    $linea = $this->service->lineaDeTiempo($this->servidor->id);

    // 6 ítems: 2 vínculos iniciados + 1 vínculo cerrado + 2 eventos... y el
    // vínculo 2 (vigente) no genera 'vinculo_cerrado'.
    expect($linea)->toHaveCount(5);

    // Orden cronológico estricto.
    $fechas = $linea->pluck('fecha')->all();
    $ordenadas = $fechas;
    sort($ordenadas);
    expect($fechas)->toBe($ordenadas);

    $porTipo = $linea->groupBy('tipo');
    expect($porTipo->get('vinculo_iniciado'))->toHaveCount(2);
    expect($porTipo->get('vinculo_cerrado'))->toHaveCount(1);
    expect($porTipo->get('evento'))->toHaveCount(2);

    // El vínculo cerrado es el de servicios ocasionales, con su motivo_fin.
    $cierre = $porTipo->get('vinculo_cerrado')->first();
    expect($cierre['regimen_juridico'])->toBe('losep');
    expect($cierre['descripcion'])->toContain('Fin de contrato ocasional');

    // El evento de 2019 cae dentro del vínculo LOSEP.
    $eventoLosepItem = itemDeLinea($linea, 'MovimientoPersonal', $eventoLosep->id);
    expect($eventoLosepItem['regimen_juridico'])->toBe('losep');
    expect($eventoLosepItem['regimen_resuelto_por'])->toBe('vinculo_exacto');

    // El evento de 2020 cae dentro del vínculo de Código de Trabajo.
    $eventoCtItem = itemDeLinea($linea, 'MovimientoPersonal', $eventoCodigoTrabajo->id);
    expect($eventoCtItem['regimen_juridico'])->toBe('codigo_trabajo');
    expect($eventoCtItem['regimen_resuelto_por'])->toBe('vinculo_exacto');
});

test('un evento en un hueco entre contratos se resuelve como vinculo_mas_cercano, no como exacto', function () {
    $vinculoAnterior = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'servicios_ocasionales',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2018-01-01',
        'fecha_fin' => '2018-12-31',
        'motivo_fin' => 'Fin de contrato',
        'estado' => 'terminado',
    ]);

    // Hueco: 2019-01-01 a 2019-05-31, sin ningún contrato.
    ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'codigo_trabajo',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2019-06-01',
        'estado' => 'vigente',
    ]);

    $eventoEnHueco = MovimientoPersonal::create([
        'servidor_id' => $this->servidor->id,
        'tipo_movimiento' => 'traslado',
        'descripcion' => 'Evento durante el hueco entre contratos',
        'fecha_efectiva' => '2019-03-15',
        'autorizado_por' => $this->user->id,
    ]);

    $linea = $this->service->lineaDeTiempo($this->servidor->id);

    $item = itemDeLinea($linea, 'MovimientoPersonal', $eventoEnHueco->id);

    expect($item['regimen_resuelto_por'])->toBe('vinculo_mas_cercano');
    // Debe tomar el régimen del vínculo ANTERIOR (servicios ocasionales,
    // losep), no el que todavía no empezaba (código de trabajo).
    expect($item['regimen_juridico'])->toBe('losep');
    expect($item['referencia'])->toBe(['modelo' => 'MovimientoPersonal', 'id' => $eventoEnHueco->id]);
});

test('un evento antes de cualquier vínculo se marca sin_vinculo, sin adivinar régimen', function () {
    ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2021-01-01',
        'estado' => 'vigente',
    ]);

    $eventoPrevio = MovimientoPersonal::create([
        'servidor_id' => $this->servidor->id,
        'tipo_movimiento' => 'ingreso',
        'descripcion' => 'Evento anterior a cualquier vínculo registrado',
        'fecha_efectiva' => '2015-01-01',
        'autorizado_por' => $this->user->id,
    ]);

    $linea = $this->service->lineaDeTiempo($this->servidor->id);

    $item = itemDeLinea($linea, 'MovimientoPersonal', $eventoPrevio->id);

    expect($item['regimen_resuelto_por'])->toBe('sin_vinculo');
    expect($item['regimen_juridico'])->toBeNull();
});

test('un vínculo de servicios profesionales se marca codigo_civil_losncp, no codigo_trabajo', function () {
    // esLosep() es binario y excluye tanto CODIGO_TRABAJO como
    // SERVICIOS_PROFESIONALES por igual — este test existe para que un
    // futuro cambio a ese binario no vuelva a colapsar servicios
    // profesionales dentro de código de trabajo en silencio.
    $vinculoServiciosProfesionales = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'servicios_profesionales',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2022-01-01',
        // Servicios Profesionales dura el año calendario y la BD exige el
        // vencimiento; el servicio lo deriva, pero este fixture crea el
        // contrato directamente.
        'fecha_fin' => '2022-12-31',
        'estado' => 'vigente',
    ]);

    $evento = MovimientoPersonal::create([
        'servidor_id' => $this->servidor->id,
        'tipo_movimiento' => 'novedad_contrato',
        'descripcion' => 'Evento durante el contrato de servicios profesionales',
        'fecha_efectiva' => '2022-06-15',
        'autorizado_por' => $this->user->id,
    ]);

    $linea = $this->service->lineaDeTiempo($this->servidor->id);

    // El vínculo mismo.
    $vinculoItem = itemDeLinea($linea, 'ContratoServidor', $vinculoServiciosProfesionales->id);
    expect($vinculoItem['regimen_juridico'])->toBe('codigo_civil_losncp');
    expect($vinculoItem['regimen_juridico'])->not->toBe('codigo_trabajo');

    // El evento resuelto vía ese vínculo (vinculo_exacto).
    $eventoItem = itemDeLinea($linea, 'MovimientoPersonal', $evento->id);
    expect($eventoItem['regimen_resuelto_por'])->toBe('vinculo_exacto');
    expect($eventoItem['regimen_juridico'])->toBe('codigo_civil_losncp');
    expect($eventoItem['regimen_juridico'])->not->toBe('codigo_trabajo');
});
