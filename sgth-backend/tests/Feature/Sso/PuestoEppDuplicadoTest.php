<?php

use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\PuestoEpp;
use App\Services\Sso\EppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Asignar dos veces el mismo equipo al mismo puesto.
 *
 * Era un `updateOrCreate`: la segunda asignación pisaba la primera y dejaba la
 * frecuencia de reposición en NULL, porque el formulario la manda vacía cuando
 * no se rellena. De este requerimiento sale el kit que se entrega al servidor,
 * así que perder la frecuencia es perder cuándo toca reponer —y la pantalla
 * respondía «equipo asignado».
 */

beforeEach(function () {
    $this->unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($this->unidad, 'Operador de maquinaria');
    $this->servicio = app(EppService::class);

    $this->equipo = EquipoProteccion::create([
        'codigo' => 'EPP-014',
        'nombre' => 'Respirador de media cara con filtros P100',
        'tipo' => 'respiratoria',
        'estado' => true,
    ]);
});

test('un puesto no requiere dos veces el mismo equipo', function () {
    $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
        'cantidad_requerida' => 2,
        'frecuencia_reposicion_meses' => 6,
    ]);

    expect(fn() => $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
        'cantidad_requerida' => 1,
    ]))->toThrow(ValidationException::class);
});

test('el rechazo no toca la asignación que ya estaba', function () {
    // Es el daño concreto que hacía el `updateOrCreate`: la segunda llamada
    // llega sin frecuencia y la dejaba en NULL.
    $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
        'cantidad_requerida' => 2,
        'frecuencia_reposicion_meses' => 6,
    ]);

    try {
        $this->servicio->asignarEquipoAPuesto([
            'puesto_id' => $this->puesto->id,
            'equipo_proteccion_id' => $this->equipo->id,
            'cantidad_requerida' => 1,
        ]);
    } catch (ValidationException) {
        // Lo que importa es lo que quedó en la base.
    }

    $asignacion = PuestoEpp::where('puesto_id', $this->puesto->id)->sole();

    expect($asignacion->cantidad_requerida)->toBe(2);
    expect($asignacion->frecuencia_reposicion_meses)->toBe(6);
});

test('el mensaje del rechazo cae en el campo del equipo', function () {
    // El formulario reparte los errores por campo; si la clave no fuera
    // `equipo_proteccion_id`, el aviso saldría suelto y sin señalar dónde.
    $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
    ]);

    try {
        $this->servicio->asignarEquipoAPuesto([
            'puesto_id' => $this->puesto->id,
            'equipo_proteccion_id' => $this->equipo->id,
        ]);
        $this->fail('Se esperaba que el duplicado fuera rechazado.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('equipo_proteccion_id');
    }
});

test('otro equipo en el mismo puesto se asigna sin problema', function () {
    $otro = EquipoProteccion::create([
        'codigo' => 'EPP-002',
        'nombre' => 'Guantes de nitrilo',
        'tipo' => 'manos',
        'estado' => true,
    ]);

    $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
    ]);
    $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $otro->id,
    ]);

    expect(PuestoEpp::where('puesto_id', $this->puesto->id)->count())->toBe(2);
});

test('el mismo equipo en otro puesto se asigna sin problema', function () {
    $otroPuesto = puestoDePrueba($this->unidad, 'Auxiliar de bodega');

    $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
    ]);
    $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $otroPuesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
    ]);

    expect(PuestoEpp::where('equipo_proteccion_id', $this->equipo->id)->count())->toBe(2);
});

test('eliminada la asignación, el equipo se puede volver a asignar', function () {
    // Es la salida que se le deja a quien quiere cambiar la cantidad o la
    // frecuencia: borrar y volver a agregar.
    $primera = $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
        'cantidad_requerida' => 2,
    ]);
    $this->servicio->eliminarAsignacion($this->puesto->id, $primera->id);

    $segunda = $this->servicio->asignarEquipoAPuesto([
        'puesto_id' => $this->puesto->id,
        'equipo_proteccion_id' => $this->equipo->id,
        'cantidad_requerida' => 4,
        'frecuencia_reposicion_meses' => 3,
    ]);

    expect($segunda->cantidad_requerida)->toBe(4);
    expect($segunda->frecuencia_reposicion_meses)->toBe(3);
    expect(PuestoEpp::where('puesto_id', $this->puesto->id)->count())->toBe(1);
});
