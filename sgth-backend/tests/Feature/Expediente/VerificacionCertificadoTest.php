<?php

namespace Tests\Feature\Expediente;

use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EmisionCertificadoLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\CertificadoLaboralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| La página de verificación es pública y sin sesión: la usan el banco o el
| IESS que reciben el papel. Lo que enseña y lo que calla es la parte
| delicada, así que va fijado aquí.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $unidad = unidadDePrueba(['codigo' => 'UATH-VER', 'nombre' => 'Gestión Administrativa']);
    $puesto = puestoDePrueba($unidad);

    $this->servidor = Servidor::create([
        'cedula'                    => '1710009988',
        'nombre'                    => 'Ana',
        'apellido'                  => 'Pérez',
        'regimen_laboral'           => 'losep',
        'estado'                    => true,
        'puesto_id'                 => $puesto->id,
        'unidad_administrativa_id'  => $unidad->id,
        'fecha_ingreso_institucion' => '2018-01-01',
    ]);

    ContratoServidor::create([
        'servidor_id'              => $this->servidor->id,
        'tipo_nombramiento'        => 'nombramiento_permanente',
        'unidad_administrativa_id' => $unidad->id,
        'puesto_id'                => $puesto->id,
        'fecha_inicio'             => '2018-01-01',
        'estado'                   => 'vigente',
        'remuneracion'             => 1500,
    ]);

    $this->emitir = fn (bool $conRemuneracion = false) => app(CertificadoLaboralService::class)
        ->emitir($this->servidor, $conRemuneracion, $this->uath->id);
});

test('cualquiera puede comprobar un certificado, sin iniciar sesión', function () {
    $emision = ($this->emitir)();

    $this->getJson("/api/v1/certificados/verificar/{$emision->codigo}")
        ->assertOk()
        ->assertJsonPath('datos.codigo', $emision->codigo)
        ->assertJsonPath('datos.nombre_completo', $this->servidor->nombre_completo)
        ->assertJsonPath('datos.vigente', true)
        ->assertJsonPath('datos.documento', 'CERTIFICADO LABORAL');
});

test('la cédula va enmascarada: se cotejan los últimos cuatro dígitos', function () {
    $emision = ($this->emitir)();

    $cedula = $this->getJson("/api/v1/certificados/verificar/{$emision->codigo}")
        ->assertOk()->json('datos.cedula');

    expect($cedula)->toBe('••••••9988')
        ->and($cedula)->not->toContain('1710');
});

test('la remuneración no aparece nunca, ni con el certificado que la lleva', function () {
    $emision = ($this->emitir)(true);

    // La emisión sí la guardó: es lo que decía el papel.
    expect($emision->datos['periodos'][0]['remuneracion'])->not->toBeNull();

    $cuerpo = $this->getJson("/api/v1/certificados/verificar/{$emision->codigo}")
        ->assertOk()->getContent();

    expect($cuerpo)->not->toContain('remuneracion')
        ->and($cuerpo)->not->toContain('1500')
        // Y tampoco el detalle de los períodos, que no hace falta para cotejar.
        ->and($cuerpo)->not->toContain('periodos');
});

test('un código que no existe no dice nada de nadie', function () {
    $this->getJson('/api/v1/certificados/verificar/CL-XXXX-XXXX-XXXX')
        ->assertNotFound()
        ->assertJsonPath('mensaje', 'No existe ningún certificado con ese código de verificación.');
});

test('un certificado vencido se reconoce como auténtico pero caducado', function () {
    $emision = ($this->emitir)();
    $emision->update(['vence_en' => now()->subDay()->toDateString()]);

    $this->getJson("/api/v1/certificados/verificar/{$emision->codigo}")
        ->assertOk()
        ->assertJsonPath('datos.vigente', false)
        ->assertJsonPath('datos.codigo', $emision->codigo);
});

test('recorrer códigos a ciegas se corta por límite de intentos', function () {
    // El código es aleatorio, así que adivinar uno ya es improbable; el tope
    // por IP existe para que ni siquiera compense intentarlo en volumen.
    foreach (range(1, 10) as $i) {
        $this->getJson("/api/v1/certificados/verificar/CL-AAAA-AAAA-".str_pad((string) $i, 4, '0', STR_PAD_LEFT))
            ->assertNotFound();
    }

    $this->getJson('/api/v1/certificados/verificar/CL-AAAA-AAAA-0011')
        ->assertStatus(429);
});
