<?php

/*
| El firmante de un permiso puede estar en otra unidad (decidido con TH el
| 2026-10-07).
|
| El jefe inmediato de un jefe de unidad está fuera de ella, y el jefe de
| Talento Humano no tenía a nadie que le firmara: en su unidad no hay otro jefe
| y dirigirlo a Talento Humano lo tenía a él mismo de firmante. Talento Humano
| elige ahora entre los jefes de toda la institución, que el listado de
| servidores entrega con `es_jefe`.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->prefectura = unidadDePrueba(['nombre' => 'Prefectura']);
    $this->th = unidadDePrueba(['nombre' => 'Talento Humano', 'es_unidad_talento_humano' => true]);

    $this->prefecta = servidorFirmanteOtra($this->prefectura, puestoJefeDePrueba($this->prefectura, 'Prefecta'), '0804000001');
    $this->jefeTh = servidorFirmanteOtra($this->th, puestoJefeDePrueba($this->th, 'Director de TH'), '0804000002');
    $this->analista = servidorFirmanteOtra($this->th, puestoDePrueba($this->th), '0804000003');

    $this->uath = User::create([
        'email' => 'uath-firmante@example.com', 'usuario_ti' => 'uath_firm',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');
});

function servidorFirmanteOtra(UnidadAdministrativa $unidad, Puesto $puesto, string $cedula): Servidor
{
    return Servidor::create([
        'cedula'                   => $cedula,
        'nombre'                   => 'Nombre',
        'apellido'                 => 'Firmante',
        'puesto_id'                => $puesto->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);
}

test('el listado de servidores filtra a los jefes de toda la institución', function () {
    $ids = $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/expediente/servidores?es_jefe=true&per_page=50')
        ->assertOk()
        ->json('datos.*.id');

    expect($ids)->toEqualCanonicalizing([$this->prefecta->id, $this->jefeTh->id]);
});

test('es_jefe=false devuelve a quienes no ocupan una jefatura', function () {
    $ids = $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/expediente/servidores?es_jefe=false&per_page=50')
        ->assertOk()
        ->json('datos.*.id');

    expect($ids)->toBe([$this->analista->id]);
});

test('Talento Humano registra el permiso de su jefe con un firmante de otra unidad', function () {
    $this->actingAs($this->uath, 'sanctum')
        ->postJson('/api/v1/asistencia/permisos', [
            'servidor_id' => $this->jefeTh->id,
            'jefe_id'     => $this->prefecta->id,
            'tipo'        => 'oficial',
            'observacion' => 'Reunión en el Ministerio del Trabajo',
            'fecha'       => now()->next(Carbon::MONDAY)->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin'    => '10:00',
        ])->assertCreated();

    $permiso = PermisoServidor::latest('id')->firstOrFail();

    // Firma la Prefecta, pero el permiso sigue siendo de la unidad del servidor.
    expect($permiso->jefe_id)->toBe($this->prefecta->id)
        ->and($permiso->unidad_administrativa_id)->toBe($this->th->id);
});
