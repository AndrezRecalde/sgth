<?php

/*
| Dirigir un permiso al jefe de Talento Humano en vez del jefe inmediato.
|
| Quien firma en ese caso no se elige a mano: es el jefe vigente de la unidad
| marcada como Talento Humano —o quien lo subrogue—, resuelto con la misma
| regla que usan las Acciones de Personal. Lo que llegue en `jefe_id` se ignora.
*/

use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    // La unidad de Talento Humano, con su director como titular vigente.
    $this->unidadTh = unidadDePrueba([
        'nombre' => 'Gestión de Talento Humano',
        'es_unidad_talento_humano' => true,
    ]);
    $puestoDirector = puestoJefeDePrueba($this->unidadTh, 'Director de Talento Humano');
    $this->directorTh = servidorParaDirigirATh(
        $this->unidadTh, $puestoDirector, '0801000001', 'Rodrigo', 'Valencia'
    );
    contratoVigenteDeTh($this->directorTh, $puestoDirector);

    // Una dirección cualquiera, con su propio jefe inmediato.
    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras Públicas']);
    $puestoJefe = puestoJefeDePrueba($this->unidad, 'Director de Obras Públicas');
    $this->jefeInmediato = servidorParaDirigirATh(
        $this->unidad, $puestoJefe, '0801000002', 'Carla', 'Inmediata'
    );
    $this->servidor = servidorParaDirigirATh(
        $this->unidad, puestoDePrueba($this->unidad), '0801000003', 'Ana', 'Solicitante'
    );

    $this->uath = User::create([
        'email' => 'uath-dirigido@example.com', 'usuario_ti' => 'uath_dir',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');
});

function servidorParaDirigirATh(
    UnidadAdministrativa $unidad,
    Puesto $puesto,
    string $cedula,
    string $nombre,
    string $apellido,
): Servidor {
    return Servidor::create([
        'cedula'                   => $cedula,
        'nombre'                   => $nombre,
        'apellido'                 => $apellido,
        'puesto_id'                => $puesto->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);
}

/** Sin contrato vigente, el resolvedor no lo reconoce como titular del puesto. */
function contratoVigenteDeTh(Servidor $servidor, Puesto $puesto): void
{
    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
        'puesto_id'                => $puesto->id,
        'fecha_inicio'             => '2020-01-01',
        'estado'                   => 'vigente',
    ]);
}

function pedirPermisoDirigido(Servidor $servidor, array $extra = []): TestResponse
{
    // Un día laborable: la fecha no es lo que se prueba aquí.
    $fecha = now()->addDay();
    while ($fecha->isWeekend()) {
        $fecha->addDay();
    }

    return test()->actingAs(test()->uath, 'sanctum')
        ->postJson('/api/v1/asistencia/permisos', array_merge([
            'servidor_id'              => $servidor->id,
            'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
            'tipo'                     => 'oficial',
            'fecha'                    => $fecha->toDateString(),
            'hora_inicio'              => '08:00',
            'hora_fin'                 => '10:00',
            'observacion'              => 'Comisión de servicios',
        ], $extra));
}

/** El HTML de la vista del PDF, armado con lo mismo que carga `exportar()`. */
function pdfDelUltimoPermiso(): string
{
    $permiso = PermisoServidor::with([
        'servidor.puesto.cargo', 'jefe', 'unidadAdministrativa', 'creadoPor',
    ])->latest('id')->firstOrFail();

    return view('permisos.permiso-pdf', [
        'permiso'            => $permiso,
        'mostrarObservacion' => true,
    ])->render();
}

test('dirigido a Talento Humano, firma su director y no el jefe inmediato', function () {
    pedirPermisoDirigido($this->servidor, [
        // Llega un jefe inmediato, pero con la opción activa se ignora.
        'jefe_id'                   => $this->jefeInmediato->id,
        'dirigido_a_talento_humano' => true,
    ])->assertCreated();

    $permiso = PermisoServidor::latest('id')->firstOrFail();

    expect($permiso->jefe_id)->toBe($this->directorTh->id)
        ->and($permiso->dirigido_a_talento_humano)->toBeTrue();
});

test('sin la opción, firma el jefe inmediato que se eligió', function () {
    pedirPermisoDirigido($this->servidor, ['jefe_id' => $this->jefeInmediato->id])
        ->assertCreated();

    $permiso = PermisoServidor::latest('id')->firstOrFail();

    expect($permiso->jefe_id)->toBe($this->jefeInmediato->id)
        ->and($permiso->dirigido_a_talento_humano)->toBeFalse();
});

test('el director de Talento Humano no puede dirigirse su propio permiso', function () {
    pedirPermisoDirigido($this->directorTh, ['dirigido_a_talento_humano' => true])
        ->assertStatus(422);

    expect(PermisoServidor::count())->toBe(0);
});

test('con la dirección de Talento Humano vacante, sale dirigido pero sin nombre', function () {
    // Otra unidad pasa a ser la de Talento Humano, con la jefatura sin titular.
    $this->unidadTh->update(['es_unidad_talento_humano' => false]);
    $vacante = unidadDePrueba([
        'nombre' => 'Talento Humano sin director',
        'es_unidad_talento_humano' => true,
    ]);
    puestoJefeDePrueba($vacante, 'Director de Talento Humano');

    pedirPermisoDirigido($this->servidor, ['dirigido_a_talento_humano' => true])
        ->assertCreated();

    $permiso = PermisoServidor::latest('id')->firstOrFail();

    // Mejor el cargo sin nombre que atribuirle la firma a quien no la va a dar.
    expect($permiso->jefe_id)->toBeNull()
        ->and($permiso->dirigido_a_talento_humano)->toBeTrue();
});

test('el PDF rotula la firma como del jefe de Talento Humano', function () {
    pedirPermisoDirigido($this->servidor, ['dirigido_a_talento_humano' => true])
        ->assertCreated();

    expect(pdfDelUltimoPermiso())
        ->toContain('Firma: Jefe de Talento Humano')
        ->toContain('DIRECTOR/A DE TALENTO HUMANO')
        ->toContain('VALENCIA RODRIGO')
        ->not->toContain('JEFE INMEDIATO');
});

test('sin la opción, el PDF sigue rotulando al jefe inmediato', function () {
    pedirPermisoDirigido($this->servidor, ['jefe_id' => $this->jefeInmediato->id])
        ->assertCreated();

    expect(pdfDelUltimoPermiso())
        ->toContain('Firma: Jefe Inmediato')
        ->toContain('JEFE INMEDIATO')
        ->toContain('INMEDIATA CARLA')
        ->not->toContain('Firma: Jefe de Talento Humano');
});
