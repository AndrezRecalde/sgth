<?php

namespace Tests\Feature\Expediente;

use App\Mail\Expediente\CertificadoLaboralMail;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\CertificadoLaboralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| A quién se le manda el certificado. Es lo único del envío que puede fallar
| en silencio y con consecuencias: mandar el documento de alguien —con su
| cédula y, según la variante, su remuneración— a la dirección equivocada.
|
| La regla la fijó la UATH el 2026-09-25: el correo institucional, que vive en
| la cuenta de usuario; si no hay, el personal; y si tampoco, que Talento
| Humano lo descargue y lo envíe a mano.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $unidad = unidadDePrueba(['codigo' => 'UATH-MAIL', 'nombre' => 'Gestión Administrativa']);
    $puesto = puestoDePrueba($unidad);

    $this->contador = 0;

    $this->servidorCon = function (array $atributos = []) use ($unidad, $puesto): Servidor {
        $this->contador++;

        $servidor = Servidor::create(array_merge([
            'cedula'                    => str_pad((string) (1720000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Ana',
            'apellido'                  => 'Pérez',
            'regimen_laboral'           => 'losep',
            'estado'                    => true,
            'puesto_id'                 => $puesto->id,
            'unidad_administrativa_id'  => $unidad->id,
            'fecha_ingreso_institucion' => '2020-01-01',
        ], $atributos));

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => 'nombramiento_permanente',
            'unidad_administrativa_id' => $unidad->id,
            'puesto_id'                => $puesto->id,
            'fecha_inicio'             => '2020-01-01',
            'estado'                   => 'vigente',
            'remuneracion'             => 1200,
        ]);

        return $servidor->fresh();
    };

    $this->servicio = app(CertificadoLaboralService::class);
});

test('va al correo institucional, que vive en la cuenta de usuario', function () {
    $servidor = ($this->servidorCon)(['correo_personal' => 'particular@gmail.com']);
    User::factory()->create([
        'servidor_id' => $servidor->id,
        'email'       => 'a.perez@gadpe.gob.ec',
    ]);

    $emision = $this->servicio->emitir($servidor->fresh(), false, $this->uath->id);
    $origen = $this->servicio->enviar($emision, $servidor->fresh());

    expect($origen)->toBe('institucional');

    Mail::assertSent(
        CertificadoLaboralMail::class,
        fn ($mail) => $mail->hasTo('a.perez@gadpe.gob.ec')
            && ! $mail->hasTo('particular@gmail.com'),
    );
});

test('sin cuenta de usuario cae al correo personal', function () {
    // El caso del ex servidor: ya no tiene acceso al sistema, pero sigue
    // necesitando su certificado.
    $servidor = ($this->servidorCon)(['correo_personal' => 'exservidor@gmail.com']);

    $emision = $this->servicio->emitir($servidor, false, $this->uath->id);

    expect($this->servicio->enviar($emision, $servidor))->toBe('personal');

    Mail::assertSent(
        CertificadoLaboralMail::class,
        fn ($mail) => $mail->hasTo('exservidor@gmail.com'),
    );
});

test('sin ninguna dirección no se inventa una: no se manda nada', function () {
    $servidor = ($this->servidorCon)(['correo_personal' => null]);

    $emision = $this->servicio->emitir($servidor, false, $this->uath->id);

    expect($this->servicio->enviar($emision, $servidor))->toBe('sin_direccion');

    Mail::assertNothingSent();
});

test('el correo lleva el PDF adjunto y el código en el cuerpo', function () {
    $servidor = ($this->servidorCon)(['correo_personal' => 'ana@gmail.com']);
    $emision = $this->servicio->emitir($servidor, false, $this->uath->id);

    $this->servicio->enviar($emision, $servidor);

    Mail::assertSent(CertificadoLaboralMail::class, function ($mail) use ($emision) {
        $adjuntos = $mail->attachments();
        $cuerpo = $mail->render();

        return count($adjuntos) === 1
            && str_contains($mail->envelope()->subject, 'CERTIFICADO LABORAL')
            && str_contains($cuerpo, $emision->codigo)
            && str_contains($cuerpo, '/verificar/'.$emision->codigo);
    });
});

test('emitir sin pedir el envío no manda ningún correo', function () {
    $servidor = ($this->servidorCon)(['correo_personal' => 'ana@gmail.com']);

    $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/expediente/servidores/{$servidor->id}/certificado-laboral")
        ->assertOk()
        ->assertHeader('X-Envio-Certificado', 'no_solicitado');

    Mail::assertNothingSent();
});

test('pidiendo el envío, la respuesta dice de dónde salió la dirección', function () {
    $servidor = ($this->servidorCon)(['correo_personal' => 'ana@gmail.com']);

    $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/expediente/servidores/{$servidor->id}/certificado-laboral?enviar=1")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Envio-Certificado', 'personal');

    Mail::assertSent(CertificadoLaboralMail::class);
});

test('si el correo no sale, el certificado se emite igual y se avisa', function () {
    // Pasó de verdad contra el servidor real: el SMTP no respondía, la
    // excepción subía hasta el controlador y quien emitía perdía el PDF de un
    // certificado que sí había quedado registrado.
    $servidor = ($this->servidorCon)(['correo_personal' => 'ana@gmail.com']);

    Mail::shouldReceive('to')->andThrow(
        new \Symfony\Component\Mailer\Exception\TransportException('sin conexión'),
    );

    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/expediente/servidores/{$servidor->id}/certificado-laboral?enviar=1");

    $respuesta->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Envio-Certificado', 'fallo_envio');

    // La emisión quedó registrada: el papel existe y su código verifica.
    expect(\App\Models\Expediente\EmisionCertificadoLaboral::count())->toBe(1);
});
