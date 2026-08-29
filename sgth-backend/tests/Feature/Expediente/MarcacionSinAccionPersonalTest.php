<?php

use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * La restricción de marcación no puede vivir solo en la acción de personal.
 *
 * `MovimientoPersonalService::resolverPuedeMarcar()` fuerza `puede_marcar` a
 * falso para las modalidades que no marcan, pero existe otra puerta: el
 * endpoint que registra un contrato directamente
 * (`POST servidores/{id}/contratos`), que llama a `ContratoServidorService::crear()`
 * sin pasar por ahí. Y `contratos_servidor.puede_marcar` tiene `DEFAULT true`,
 * así que omitir el campo activaba la marcación de un contrato civil.
 */
beforeEach(function () {
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($unidad, 'Consultor');

    $this->servidor = Servidor::create([
        'cedula' => '0800000301', 'nombre' => 'Prueba', 'apellido' => 'Marcación',
        'estado' => true,
    ]);

    $this->servicio = app(ContratoServidorService::class);

    $this->contratar = fn (TipoNombramiento $nombramiento, array $extra = []) =>
        $this->servicio->crear($this->servidor->id, [
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->puesto->unidad_administrativa_id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2026-01-01',
            'fecha_fin'                => '2026-12-31',
            'estado'                   => 'vigente',
            'origen'                   => 'accion_personal',
            ...$extra,
        ]);
});

test('un contrato de servicios profesionales nace sin marcación', function () {
    $contrato = ($this->contratar)(TipoNombramiento::SERVICIOS_PROFESIONALES);

    expect($contrato->puede_marcar)->toBeFalse()
        // El observer copia el valor al servidor, que es lo que mira el
        // controlador de marcaciones.
        ->and($this->servidor->fresh()->puede_marcar)->toBeFalse();
});

test('no vale mandarlo en true a mano', function () {
    // Es una restricción, no un valor sugerido: no puede depender de que el
    // cliente se porte bien.
    $contrato = ($this->contratar)(
        TipoNombramiento::SERVICIOS_PROFESIONALES,
        ['puede_marcar' => true]
    );

    expect($contrato->puede_marcar)->toBeFalse()
        ->and($this->servidor->fresh()->puede_marcar)->toBeFalse();
});

test('libre nombramiento y elección popular tampoco marcan', function () {
    $lnr = ($this->contratar)(
        TipoNombramiento::LIBRE_NOMBRAMIENTO,
        ['puede_marcar' => true, 'estado' => 'terminado']
    );

    expect($lnr->puede_marcar)->toBeFalse();
});

test('un obrero del Código del Trabajo sí puede marcar si así se registra', function () {
    // Control: entre los obreros unos marcan y otros no, así que aquí el valor
    // enviado se respeta. Si se forzara a todos, este test fallaría.
    $contrato = ($this->contratar)(
        TipoNombramiento::CODIGO_TRABAJO,
        ['puede_marcar' => true]
    );

    expect($contrato->puede_marcar)->toBeTrue();
});
