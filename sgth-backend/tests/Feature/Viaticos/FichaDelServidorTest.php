<?php

/*
| Las pantallas de viáticos solo reciben el nombre de quien viaja (2026-11-18).
|
| `Servidor` no declara `$hidden`: cargar la relación sin lista de columnas
| serializa la ficha entera. El listado y la ficha de viáticos lo hacían, así
| que Financiero y Talento Humano —que ven los viáticos de todos— recibían el
| teléfono, el correo personal, la dirección, el tipo de sangre y el contacto
| de emergencia de cada viajero, en pantallas que solo pintan un nombre.
|
| De paso, las tablas mostraban solo el primer nombre y el primer apellido,
| porque esos eran los únicos que se mandaban.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\Viatico;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    // En `$this` y no en una constante del archivo: las constantes de un test
    // son globales, y toda la suite corre en un proceso. Ya había una
    // `DATOS_DE_LA_FICHA` en el Dispensario, y la primera en cargarse ganaba.
    $this->sensibles = [
        'telefono_celular', 'correo_personal', 'direccion_domicilio', 'tipo_sangre',
        'fecha_nacimiento', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
        'tiene_discapacidad', 'tiene_enfermedad_catastrofica', 'tipo_nombramiento',
    ];

    $unidad = unidadDePrueba(['codigo' => 'GFIC']);
    $this->viajero = Servidor::create([
        'cedula' => '0800009201', 'nombre' => 'Ana', 'segundo_nombre' => 'Lucía',
        'apellido' => 'Pérez', 'segundo_apellido' => 'Mora',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
        // Lo que no debe salir.
        'telefono_celular' => '0999888777', 'correo_personal' => 'ana.personal@gmail.com',
        'direccion_domicilio' => 'Calle Sucre y Bolívar', 'tipo_sangre' => 'O+',
        'fecha_nacimiento' => '1990-05-20', 'tiene_discapacidad' => true,
        'contacto_emergencia_nombre' => 'Luis Mora', 'contacto_emergencia_telefono' => '0988777666',
    ]);

    $this->titular = User::factory()->create(['servidor_id' => $this->viajero->id]);
    $this->titular->assignRole('servidor');

    $financiero = Servidor::create([
        'cedula' => '0800009202', 'nombre' => 'Fausto', 'apellido' => 'Cedeño',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $this->financiero = User::factory()->create(['servidor_id' => $financiero->id]);
    $this->financiero->assignRole('financiero');

    $this->viatico = Viatico::create([
        'servidor_id' => $this->viajero->id, 'zona' => 'fuera_provincia',
        'datetime_salida' => '2026-10-06 08:00:00', 'datetime_llegada' => '2026-10-08 18:00:00',
        'noches' => 2, 'justificacion' => 'Comisión de servicio en Quito',
        'estado' => EstadoViatico::SOLICITADO, 'monto_calculado' => 160,
        'monto_anticipo' => 0, 'modalidad_anticipo' => 'total',
    ]);
});

it('el listado de todos no manda la ficha del viajero', function () {
    $respuesta = $this->actingAs($this->financiero, 'sanctum')
        ->getJson('/api/v1/viaticos')
        ->assertOk()
        ->assertJsonPath('datos.data.0.servidor.nombre', 'Ana');

    foreach ($this->sensibles as $campo) {
        expect($respuesta->json('datos.data.0.servidor'))->not->toHaveKey($campo);
    }
});

it('la ficha del viático tampoco, y trae lo que la pantalla muestra', function () {
    $respuesta = $this->actingAs($this->financiero, 'sanctum')
        ->getJson("/api/v1/viaticos/{$this->viatico->id}")
        ->assertOk()
        // El cargo y la unidad se siguen mostrando en «Datos del viaje».
        ->assertJsonPath('datos.servidor.segundo_apellido', 'Mora')
        ->assertJsonStructure(['datos' => ['servidor' => ['puesto' => ['cargo', 'unidad_administrativa']]]]);

    foreach ($this->sensibles as $campo) {
        expect($respuesta->json('datos.servidor'))->not->toHaveKey($campo);
    }
});

it('la bandeja manda los dos nombres y los dos apellidos, y nada más', function () {
    $fila = $this->actingAs($this->financiero, 'sanctum')
        ->getJson('/api/v1/viaticos/bandeja?etapa=por_aprobar')
        ->assertOk()
        ->json('datos.0.servidor');

    expect($fila)->toBe([
        'id'               => $this->viajero->id,
        'nombre'           => 'Ana',
        'segundo_nombre'   => 'Lucía',
        'apellido'         => 'Pérez',
        'segundo_apellido' => 'Mora',
        'cedula'           => '0800009201',
        'unidad'           => $this->viajero->unidadAdministrativa->nombre,
    ]);
});

it('el titular ve su propio viático sin que le devuelvan su ficha', function () {
    $respuesta = $this->actingAs($this->titular, 'sanctum')
        ->getJson('/api/v1/viaticos?propios=1')
        ->assertOk()
        ->assertJsonCount(1, 'datos.data');

    foreach ($this->sensibles as $campo) {
        expect($respuesta->json('datos.data.0.servidor'))->not->toHaveKey($campo);
    }
});
