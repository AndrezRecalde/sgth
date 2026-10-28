<?php

use App\Contracts\Comunicacion\CredencialServiceInterface;
use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Models\Comunicacion\Credencial;
use App\Models\Comunicacion\FotografiaServidor;
use App\Models\Comunicacion\ImpresionCredencial;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Comunicacion\DatosCredencial;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
| El Inicio de Comunicación Social (docs/diseno-comunicacion-social.md, 8;
| fase 3.3): las fotos por unidad, las credenciales por imprimir, por
| reimprimir, por vencer y por recibir, y los logos. Cada bloque con su permiso.
|
| Hoy es el lunes 19 de octubre de 2026.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

function servidorDePruebaTablero(object $prueba, string $cedula, $unidad, array $atributos = []): Servidor
{
    return Servidor::create(array_merge([
        'cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => "Tablero{$cedula}",
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
        'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $prueba->puesto->id,
    ], $atributos));
}

/** Una foto vigente sin archivo: al tablero solo le importa que exista. */
function fotoDePruebaTablero(Servidor $servidor): ?Credencial
{
    FotografiaServidor::create([
        'servidor_id' => $servidor->id, 'archivo' => 'f.jpg', 'miniatura' => 'f-m.jpg', 'origen' => 'individual', 'vigente' => true,
    ]);

    return app(CredencialServiceInterface::class)->sincronizar($servidor->id);
}

function impresionDePruebaTablero(Credencial $credencial): void
{
    $servidor = Servidor::with(DatosCredencial::RELACIONES_SERVIDOR)->find($credencial->servidor_id);

    ImpresionCredencial::create([
        'credencial_id' => $credencial->id, 'formato' => 'cr80',
        'datos_impresos' => DatosCredencial::de($servidor, $credencial->fresh()),
    ]);
}

function usuarioDePruebaTablero(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-19 10:00'));

    $this->obras = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->tic = unidadDePrueba(['nombre' => 'Dirección de TIC']);
    $this->puesto = puestoDePrueba($this->obras, 'Analista', ['plazas' => 20]);
});

test('cuenta lo que falta: fotos por unidad y credenciales por imprimir, reimprimir, vencer y recibir', function () {
    // Obras: tres activos, uno sin foto.
    $impresa = servidorDePruebaTablero($this, '0801000001', $this->obras);
    $credencialImpresa = fotoDePruebaTablero($impresa);
    impresionDePruebaTablero($credencialImpresa);

    $cambiada = servidorDePruebaTablero($this, '0801000002', $this->obras);
    impresionDePruebaTablero(fotoDePruebaTablero($cambiada));
    $cambiada->update(['contacto_emergencia_nombre' => 'Otro contacto']);

    servidorDePruebaTablero($this, '0801000003', $this->obras);

    // TIC: dos activos sin foto y uno con contrato que termina en diez días.
    servidorDePruebaTablero($this, '0801000004', $this->tic);
    servidorDePruebaTablero($this, '0801000005', $this->tic);
    $ocasional = servidorDePruebaTablero($this, '0801000006', $this->tic);
    ContratoServidor::create([
        'servidor_id' => $ocasional->id, 'tipo_nombramiento' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'unidad_administrativa_id' => $this->tic->id, 'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2026-01-02', 'fecha_fin' => '2026-10-29', 'estado' => 'vigente', 'remuneracion' => 1000,
    ]);
    fotoDePruebaTablero($ocasional);

    // Se fue con la tarjeta impresa.
    $salio = servidorDePruebaTablero($this, '0801000007', $this->obras);
    impresionDePruebaTablero(fotoDePruebaTablero($salio));
    $salio->update(['estado' => false, 'unidad_administrativa_id' => null]);
    app(CredencialServiceInterface::class)->sincronizar($salio->id);

    $datos = $this->actingAs(usuarioDePruebaTablero('comunicacion-social'), 'sanctum')
        ->getJson('/api/v1/comunicacion/tablero')
        ->assertOk()
        ->json('datos');

    expect($datos['fotografias'])->toMatchArray(['activos' => 6, 'con_foto' => 3, 'sin_foto' => 3])
        // Primero la unidad que más fotos debe.
        ->and($datos['fotografias']['por_unidad'][0])->toMatchArray(['unidad' => 'Dirección de TIC', 'activos' => 3, 'con_foto' => 1])
        ->and($datos['fotografias']['por_unidad'][1])->toMatchArray(['unidad' => 'Dirección de Obras', 'activos' => 3, 'con_foto' => 2])
        ->and($datos['credenciales'])->toMatchArray([
            'vigentes' => 3, 'sin_imprimir' => 1, 'desactualizadas' => 1, 'vencidas' => 0,
            'por_vencer' => 1, 'por_recibir' => 1,
        ])
        ->and($datos['credenciales']['proximas'][0])->toMatchArray(['servidor_id' => $ocasional->id, 'valida_hasta' => '2026-10-29'])
        // A Comunicación no le toca la identidad institucional.
        ->and($datos['identidad'])->toBeNull();

    // La misma regla que el listado: los dos cuentan igual.
    $this->getJson('/api/v1/comunicacion/credenciales?situacion=desactualizada')->assertJsonCount(1, 'datos');
    $this->getJson('/api/v1/comunicacion/credenciales?situacion=por_recibir')->assertJsonCount(1, 'datos');
});

test('una vigente cuyo contrato ya terminó cuenta como vencida aunque la tarea no haya corrido', function () {
    $ocasional = servidorDePruebaTablero($this, '0802000001', $this->obras);
    ContratoServidor::create([
        'servidor_id' => $ocasional->id, 'tipo_nombramiento' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'unidad_administrativa_id' => $this->obras->id, 'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2026-01-02', 'fecha_fin' => '2026-10-20', 'estado' => 'vigente', 'remuneracion' => 1000,
    ]);
    fotoDePruebaTablero($ocasional);
    $this->travelTo(Carbon::parse('2026-10-22 08:00'));

    $this->actingAs(usuarioDePruebaTablero('comunicacion-social'), 'sanctum')
        ->getJson('/api/v1/comunicacion/tablero')
        ->assertJsonPath('datos.credenciales.vigentes', 0)
        ->assertJsonPath('datos.credenciales.vencidas', 1)
        ->assertJsonPath('datos.credenciales.por_vencer', 0);
});

test('cada bloque sale con su permiso', function () {
    $tablero = fn (string $rol) => $this->actingAs(usuarioDePruebaTablero($rol), 'sanctum')->getJson('/api/v1/comunicacion/tablero');

    // Talento Humano y Auditoría consultan las credenciales, pero no las
    // fotos: el bloque los llevaba a una pantalla que no pueden abrir (PR 14).
    foreach (['admin-uath', 'auditor'] as $rol) {
        $datos = $tablero($rol)->assertOk()->json('datos');
        expect($datos['fotografias'])->toBeNull()
            ->and($datos['credenciales'])->not->toBeNull()
            ->and($datos['identidad'])->toBeNull();
    }

    // Quien toma las fotos, sí.
    expect($tablero('comunicacion-social')->assertOk()->json('datos.fotografias'))->not->toBeNull();

    // La persona designada para los logos ve solo los logos.
    $datos = $tablero('responsable-identidad')->assertOk()->json('datos');
    expect($datos['fotografias'])->toBeNull()
        ->and($datos['credenciales'])->toBeNull()
        ->and($datos['identidad'])->toHaveKeys(['faltan_obligatorias', 'con_respaldo', 'programadas']);

    $tablero('servidor')->assertForbidden();
});
