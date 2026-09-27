<?php

namespace Tests\Feature\Expediente;

use App\Enums\TipoCertificadoLaboral;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EmisionCertificadoLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\CertificadoLaboralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Las reglas del certificado las fijó la UATH el 2026-09-25 y el 2026-09-26.
| Antes de eso el documento imprimía «N/A» en toda la columna Puesto, iba sin
| nombre en la firma y listaba los vínculos anulados como tiempo trabajado.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $this->unidad = unidadDePrueba(['codigo' => 'UATH-CERT', 'nombre' => 'Gestión Administrativa']);
    $this->puesto = puestoDePrueba($this->unidad);

    $this->servicio = app(CertificadoLaboralService::class);

    $this->servidorCon = function (array $atributos = []): Servidor {
        return Servidor::create(array_merge([
            'cedula'                    => '1710000001',
            'nombre'                    => 'Ana',
            'apellido'                  => 'Pérez',
            'regimen_laboral'           => 'losep',
            'estado'                    => true,
            'puesto_id'                 => $this->puesto->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'fecha_ingreso_institucion' => '2020-01-01',
        ], $atributos));
    };

    $this->contrato = function (Servidor $s, array $atributos = []): ContratoServidor {
        return ContratoServidor::create(array_merge([
            'servidor_id'              => $s->id,
            'tipo_nombramiento'        => 'nombramiento_permanente',
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2020-01-01',
            'estado'                   => 'vigente',
            'remuneracion'             => 1200,
        ], $atributos));
    };
});

test('el puesto de cada período sale con su nombre, no como N/A', function () {
    $servidor = ($this->servidorCon)();
    ($this->contrato)($servidor);

    $emision = $this->servicio->emitir($servidor, false, $this->uath->id);

    expect($emision->datos['periodos'][0]['puesto'])
        ->toBe($this->puesto->cargo->nombre)
        ->not->toBe('N/A');
});

test('un vínculo anulado no cuenta como tiempo trabajado', function () {
    $servidor = ($this->servidorCon)();
    ($this->contrato)($servidor, ['numero_contrato' => 'CT-VIGENTE']);
    ($this->contrato)($servidor, [
        'numero_contrato' => 'CT-ANULADO',
        'estado'          => 'cancelado',
        'fecha_inicio'    => '2019-01-01',
        'fecha_fin'       => '2019-12-31',
    ]);

    $emision = $this->servicio->emitir($servidor, false, $this->uath->id);

    expect(collect($emision->datos['periodos'])->pluck('numero_contrato')->all())
        ->toBe(['CT-VIGENTE']);
});

test('el tiempo de servicio se cuenta desde el ingreso a la institución', function () {
    // Las dos fechas se separan siete años a propósito: en régimen LOSEP la
    // antigüedad en el sector público es mayor, y es la que rige para las
    // vacaciones. Lo que se certifica es el tiempo AQUÍ.
    $servidor = ($this->servidorCon)([
        'fecha_ingreso_sector_publico' => '2010-01-01',
        'fecha_ingreso_institucion'    => '2017-01-01',
    ]);
    ($this->contrato)($servidor);

    $emision = $this->servicio->emitir($servidor, false, $this->uath->id);

    // En la misma dirección que el servicio: Carbon devuelve la diferencia
    // con signo, y al revés sale negativa.
    $enLaInstitucion = (int) floor(
        \Illuminate\Support\Carbon::parse('2017-01-01')->diffInYears(now())
    );

    expect($emision->datos['anios_servicio'])->toBe($enLaInstitucion)
        // Y coincide con lo que muestra la ficha. Llegaron a decir cifras
        // distintas del mismo servidor: ese era el problema.
        ->and($emision->datos['anios_servicio'])->toBe($servidor->fresh()->anios_servicio);
});

test('la remuneración solo viaja en la variante que la pide', function () {
    $servidor = ($this->servidorCon)();
    ($this->contrato)($servidor);

    $sin = $this->servicio->emitir($servidor, false, $this->uath->id);
    $con = $this->servicio->emitir($servidor, true, $this->uath->id);

    expect($sin->datos['periodos'][0]['remuneracion'])->toBeNull()
        ->and($con->datos['periodos'][0]['remuneracion'])->not->toBeNull();
});

test('a quien presta servicios profesionales no se le certifica trabajo en dependencia', function () {
    $profesional = ($this->servidorCon)([
        'cedula'          => '1710000002',
        'regimen_laboral' => 'servicios_profesionales',
    ]);
    ($this->contrato)($profesional, [
        'tipo_nombramiento' => 'servicios_profesionales',
        'fecha_fin'         => '2020-12-31',
    ]);

    $emision = $this->servicio->emitir($profesional, false, $this->uath->id);

    expect($emision->tipo)->toBe(TipoCertificadoLaboral::PRESTACION_SERVICIOS)
        ->and($emision->tipo->titulo())->toBe('CERTIFICADO DE PRESTACIÓN DE SERVICIOS');

    // Y a un servidor de carrera se le sigue certificando lo suyo.
    $deCarrera = ($this->servidorCon)(['cedula' => '1710000003']);
    ($this->contrato)($deCarrera);

    expect($this->servicio->emitir($deCarrera, false, $this->uath->id)->tipo)
        ->toBe(TipoCertificadoLaboral::LABORAL);
});

test('cada emisión deja constancia, con código propio y 30 días de vigencia', function () {
    $servidor = ($this->servidorCon)();
    ($this->contrato)($servidor);

    $primera = $this->servicio->emitir($servidor, false, $this->uath->id);
    $segunda = $this->servicio->emitir($servidor, false, $this->uath->id);

    expect(EmisionCertificadoLaboral::count())->toBe(2)
        ->and($primera->codigo)->not->toBe($segunda->codigo)
        ->and($primera->codigo)->toStartWith('CL-')
        ->and($primera->vence_en->toDateString())
        ->toBe(now()->addDays(30)->toDateString())
        ->and($primera->emitido_por)->toBe($this->uath->id)
        ->and($primera->estaVigente())->toBeTrue();
});

test('el documento se compone y lleva impreso su código de verificación', function () {
    $servidor = ($this->servidorCon)();
    ($this->contrato)($servidor);

    $emision = $this->servicio->emitir($servidor, false, $this->uath->id);
    $pdf = $this->servicio->pdf($emision);

    expect($pdf)->toStartWith('%PDF')
        ->and(strlen($pdf))->toBeGreaterThan(1000)
        ->and($this->servicio->nombreArchivo($emision))
        ->toContain($emision->codigo);
});

test('el certificado ya no deja archivos en el disco del servidor', function () {
    $servidor = ($this->servidorCon)();
    ($this->contrato)($servidor);

    $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/expediente/servidores/{$servidor->id}/certificado-laboral")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(is_dir(storage_path('app/certificados-laborales')))->toBeFalse();
});

test('el certificado entra en una hoja, también con nombres largos', function () {
    // Se ha escapado dos veces. La primera comprobación se hizo con una unidad
    // de nombre corto y «cabe en una hoja» resultó ser «cabe con estos datos»:
    // con «Gestión de Tecnologías de la Información y Comunicación», que ocupa
    // cuatro líneas en la celda, la firma se iba a una segunda página casi
    // vacía. Aquí queda fijado con el caso difícil.
    $unidadLarga = unidadDePrueba([
        'codigo' => 'UATH-TIC',
        'nombre' => 'Gestión de Tecnologías de la Información y Comunicación',
    ]);
    $puesto = puestoDePrueba(
        $unidadLarga,
        'Analista de Tecnologías de la Información y Comunicación',
    );

    $servidor = ($this->servidorCon)([
        'cedula'                   => '1710000009',
        'nombre'                   => 'Cristhian Andrés',
        'apellido'                 => 'Recalde Solano',
        'unidad_administrativa_id' => $unidadLarga->id,
        'puesto_id'                => $puesto->id,
    ]);
    ($this->contrato)($servidor, [
        'unidad_administrativa_id' => $unidadLarga->id,
        'puesto_id'                => $puesto->id,
    ]);

    $pdf = $this->servicio->pdf(
        $this->servicio->emitir($servidor, true, $this->uath->id),
    );

    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBe(1);
});
