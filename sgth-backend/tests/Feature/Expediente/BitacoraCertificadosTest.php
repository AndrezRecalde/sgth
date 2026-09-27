<?php

namespace Tests\Feature\Expediente;

use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\CertificadoLaboralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| La bitácora de certificados emitidos: qué se le enseña a Talento Humano de
| lo que ya se entregó.
|
| Tiene dos filos. Por un lado evita emitir a ciegas —ver que hace tres días
| ya salió uno explica por qué la persona vuelve a pedirlo—. Por otro es un
| registro de acceso a datos personales, y un registro que enseñara de más
| sería el propio problema que pretende vigilar: cada emisión guarda en
| `datos` la foto completa del expediente, con la remuneración de cada
| período. Eso NO sale de aquí.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin-uath', 'servidor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }

    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $unidad = unidadDePrueba(['codigo' => 'UATH-BIT', 'nombre' => 'Gestión Administrativa']);
    $puesto = puestoDePrueba($unidad);

    $this->contador = 0;

    $this->servidorCon = function () use ($unidad, $puesto): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'                    => str_pad((string) (1740000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Ana',
            'apellido'                  => 'Pérez',
            'regimen_laboral'           => 'losep',
            'estado'                    => true,
            'puesto_id'                 => $puesto->id,
            'unidad_administrativa_id'  => $unidad->id,
            'fecha_ingreso_institucion' => '2020-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => 'nombramiento_permanente',
            'unidad_administrativa_id' => $unidad->id,
            'puesto_id'                => $puesto->id,
            'fecha_inicio'             => '2020-01-01',
            'estado'                   => 'vigente',
            'remuneracion'             => 1234.56,
        ]);

        return $servidor->fresh();
    };

    $this->servicio = app(CertificadoLaboralService::class);

    $this->bitacora = fn (Servidor $s) => $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$s->id}/certificados-emitidos");
});

test('lista lo emitido, del más reciente al más antiguo', function () {
    $servidor = ($this->servidorCon)();

    $viejo = $this->servicio->emitir($servidor, false, $this->uath->id);
    $viejo->update(['emitido_en' => now()->subDays(10)]);
    $nuevo = $this->servicio->emitir($servidor, true, $this->uath->id);

    ($this->bitacora)($servidor)
        ->assertOk()
        ->assertJsonCount(2, 'datos')
        ->assertJsonPath('datos.0.codigo', $nuevo->codigo)
        ->assertJsonPath('datos.0.con_remuneracion', true)
        ->assertJsonPath('datos.1.codigo', $viejo->codigo);
});

test('cada línea dice quién lo emitió: es un registro de acceso', function () {
    // Sin esto la bitácora responde «cuándo» pero no «quién», que es la mitad
    // que importa cuando alguien pregunta por qué salió un certificado.
    $servidor = ($this->servidorCon)();
    $this->uath->update(['servidor_id' => ($this->servidorCon)()->id]);

    $this->servicio->emitir($servidor, false, $this->uath->id);

    ($this->bitacora)($servidor)
        ->assertOk()
        ->assertJsonPath('datos.0.emitido_por', $this->uath->fresh()->nombre_completo);
});

test('la remuneración congelada no sale en la bitácora', function () {
    // `datos` lleva la foto del expediente, con el sueldo de cada período.
    // La bitácora dice qué se emitió, no lo que el documento decía.
    $servidor = ($this->servidorCon)();
    $this->servicio->emitir($servidor, true, $this->uath->id);

    $cuerpo = ($this->bitacora)($servidor)->assertOk()->getContent();

    expect($cuerpo)->not->toContain('1234.56')
        ->and($cuerpo)->not->toContain('periodos')
        ->and($cuerpo)->not->toContain('"datos":{');
});

test('la bitácora de un servidor no arrastra la de otro', function () {
    $ana = ($this->servidorCon)();
    $otro = ($this->servidorCon)();

    $this->servicio->emitir($ana, false, $this->uath->id);
    $this->servicio->emitir($otro, false, $this->uath->id);
    $this->servicio->emitir($otro, false, $this->uath->id);

    ($this->bitacora)($ana)->assertOk()->assertJsonCount(1, 'datos');
    ($this->bitacora)($otro)->assertOk()->assertJsonCount(2, 'datos');
});

test('un certificado vencido sigue en la bitácora, marcado como no vigente', function () {
    // Se emitió: eso ocurrió y no se borra. Lo que caducó es su validez.
    $servidor = ($this->servidorCon)();
    $emision = $this->servicio->emitir($servidor, false, $this->uath->id);
    $emision->update(['vence_en' => now()->subDay()->toDateString()]);

    ($this->bitacora)($servidor)
        ->assertOk()
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.vigente', false);
});

test('sin rol de Talento Humano no se ve la bitácora de nadie', function () {
    // Quien no puede entregar un certificado tampoco tiene por qué saber a
    // quién se le entregó uno ni cuándo.
    $servidor = ($this->servidorCon)();
    $this->servicio->emitir($servidor, false, $this->uath->id);

    $otro = User::factory()->create();
    $otro->assignRole('servidor');

    $this->actingAs($otro, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$servidor->id}/certificados-emitidos")
        ->assertForbidden();
});
