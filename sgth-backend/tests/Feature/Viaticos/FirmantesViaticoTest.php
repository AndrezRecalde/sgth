<?php

/*
| Quién firma cada documento del viático, sellado al emitirlo.
|
| Los tres PDF resolvían las firmas al imprimir: un informe reimpreso meses
| después salía con quien ocupara el cargo ese día. Gestión Financiera lo
| planteó el 2026-09-15 con el caso real: la autoridad que autoriza la salida
| puede irse de vacaciones durante la comisión, y al regreso firma quien la
| subroga.
|
| Cada documento se sella por separado, con la fecha en que se emite:
| la solicitud al aprobar, el informe al presentar la liquidación y el
| comprobante al contabilizar.
*/

use App\Enums\EstadoSubrogacion;
use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoFirmante;
use App\Services\Viatico\FirmanteViaticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $cedula = 800007000;
    $this->servidorEn = function (?\App\Models\Estructura\Puesto $puesto, string $apellido) use (&$cedula) {
        $servidor = Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Ana',
            'apellido'                 => $apellido,
            'puesto_id'                => $puesto?->id,
            'unidad_administrativa_id' => $puesto?->unidad_administrativa_id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);

        if ($puesto) {
            // El titular de un puesto es quien tiene contrato vigente sobre él.
            ContratoServidor::create([
                'servidor_id'              => $servidor->id,
                'tipo_nombramiento'        => 'nombramiento_permanente',
                'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
                'puesto_id'                => $puesto->id,
                'fecha_inicio'             => '2020-01-01',
                'estado'                   => 'vigente',
            ]);
        }

        return $servidor;
    };

    // Las tres unidades de las que salen las firmas.
    $prefectura = unidadDePrueba(['codigo' => 'FPRE', 'nombre' => 'Prefectura Provincial']);
    $prefectura->update(['es_maxima_autoridad' => true]);
    $financiera = unidadDePrueba(['codigo' => 'FFIN', 'nombre' => 'Gestión Financiera']);
    $financiera->update(['es_unidad_financiera' => true]);
    $this->unidadDelServidor = unidadDePrueba(['codigo' => 'FOBR', 'nombre' => 'Obras Públicas']);

    $this->puestoPrefecto = puestoJefeDePrueba($prefectura, 'Prefecto/a Provincial');
    $this->prefecto  = ($this->servidorEn)($this->puestoPrefecto, 'Prefecta');
    $this->directora = ($this->servidorEn)(puestoJefeDePrueba($financiera, 'Directora Financiera'), 'Financiera');
    $this->jefe      = ($this->servidorEn)(puestoJefeDePrueba($this->unidadDelServidor, 'Director de Obras'), 'Jefa');

    $this->titular = ($this->servidorEn)(puestoDePrueba($this->unidadDelServidor), 'Viajera');

    $this->financieroUser = User::factory()->create([
        'servidor_id' => ($this->servidorEn)(null, 'Contable')->id,
    ]);
    $this->financieroUser->assignRole('financiero');

    $this->titularUser = User::factory()->create(['servidor_id' => $this->titular->id]);
    $this->titularUser->assignRole('servidor');

    $this->viatico = fn (EstadoViatico $estado = EstadoViatico::SOLICITADO) => Viatico::create([
        'servidor_id'        => $this->titular->id,
        'zona'               => 'dentro_provincia',
        'datetime_salida'    => '2026-10-05 08:00',
        'datetime_llegada'   => '2026-10-07 18:00',
        'noches'             => 2,
        'justificacion'      => 'Supervisión de obras viales',
        'estado'             => $estado,
        'monto_calculado'    => 160,
        'monto_anticipo'     => 0,
        'modalidad_anticipo' => 'sin_anticipo',
    ]);

    $this->sellar = fn (Viatico $v, string $documento, ?string $fecha = null) =>
        app(FirmanteViaticoService::class)->sellar($v, $documento, $fecha);
});

afterEach(fn () => Carbon::setTestNow());

it('sella los tres firmantes con el cargo que ejercen', function () {
    $viatico = ($this->viatico)();

    ($this->sellar)($viatico, FirmanteViaticoService::SOLICITUD, '2026-10-01');

    $firmas = ViaticoFirmante::where('viatico_id', $viatico->id)->get()->keyBy('rol');

    expect($firmas)->toHaveCount(3)
        ->and($firmas['maxima_autoridad']->servidor_id)->toBe($this->prefecto->id)
        ->and($firmas['maxima_autoridad']->cargo)->toBe('Prefecto/a Provincial')
        ->and($firmas['maxima_autoridad']->nombre)->toBe('Prefecta Ana')
        // El jefe sale de la unidad del servidor que viaja.
        ->and($firmas['jefe_unidad']->servidor_id)->toBe($this->jefe->id)
        ->and($firmas['director_financiero']->servidor_id)->toBe($this->directora->id)
        ->and($firmas['director_financiero']->cargo)->toBe('Directora Financiera')
        ->and($firmas['maxima_autoridad']->aviso)->toBeNull();
});

it('firma quien subroga, y el cargo lo dice', function () {
    $subrogante = ($this->servidorEn)(null, 'Subrogante');

    Subrogacion::create([
        'tipo'                     => 'subrogacion',
        'servidor_subrogante_id'   => $subrogante->id,
        'servidor_subrogado_id'    => $this->prefecto->id,
        'unidad_administrativa_id' => $this->puestoPrefecto->unidad_administrativa_id,
        'puesto_subrogado_id'      => $this->puestoPrefecto->id,
        'fecha_inicio'             => '2026-10-01',
        'fecha_fin'                => '2026-10-20',
        'motivo'                   => 'vacaciones',
        'registrado_por'           => $this->financieroUser->id,
        'estado'                   => EstadoSubrogacion::ACTIVA->value,
    ]);

    ($this->sellar)(($this->viatico)(), FirmanteViaticoService::SOLICITUD, '2026-10-05');

    $firma = ViaticoFirmante::where('rol', 'maxima_autoridad')->sole();

    expect($firma->servidor_id)->toBe($subrogante->id)
        ->and($firma->cargo)->toBe('Prefecto/a Provincial (S)')
        ->and($firma->subrogado)->toBeTrue()
        // Con subrogación registrada no hay nada que avisar.
        ->and($firma->aviso)->toBeNull();
});

it('avisa cuando el titular estaba de vacaciones y nadie registró la subrogación', function () {
    Vacacion::create([
        'servidor_id'      => $this->prefecto->id,
        'fecha_inicio'     => '2026-10-01',
        'fecha_fin'        => '2026-10-10',
        'dias_solicitados' => 8,
        'tipo_dias'        => 'habiles',
        'estado'           => 'aprobada',
    ]);

    ($this->sellar)(($this->viatico)(), FirmanteViaticoService::SOLICITUD, '2026-10-05');

    $firma = ViaticoFirmante::where('rol', 'maxima_autoridad')->sole();

    expect($firma->servidor_id)->toBe($this->prefecto->id)
        ->and($firma->aviso)->toContain('vacaciones aprobadas el 05/10/2026');
});

it('cada documento se sella en su fecha, y lo sellado no se reescribe', function () {
    $viatico = ($this->viatico)();

    ($this->sellar)($viatico, FirmanteViaticoService::SOLICITUD, '2026-10-01');

    // La prefecta deja el puesto y entra otra persona.
    ContratoServidor::where('servidor_id', $this->prefecto->id)->update(['estado' => 'terminado']);
    $nueva = ($this->servidorEn)($this->puestoPrefecto, 'Nueva');

    ($this->sellar)($viatico, FirmanteViaticoService::SOLICITUD, '2026-10-20'); // no debe tocar nada
    ($this->sellar)($viatico, FirmanteViaticoService::INFORME, '2026-10-20');

    $porDocumento = ViaticoFirmante::where('rol', 'maxima_autoridad')->get()->keyBy('documento');

    expect($porDocumento['solicitud']->servidor_id)->toBe($this->prefecto->id)
        ->and($porDocumento['informe']->servidor_id)->toBe($nueva->id);
});

it('las transiciones sellan el documento que emiten', function () {
    $viatico = ($this->viatico)(EstadoViatico::PENDIENTE_LIQUIDACION);
    $liquidacion = LiquidacionViatico::create([
        'viatico_id' => $viatico->id, 'total_facturas' => 0,
        'fecha_liquidacion' => now()->toDateString(),
    ]);
    \App\Models\Viatico\ActividadLiquidacion::create([
        'liquidacion_viatico_id' => $liquidacion->id,
        'fecha' => '2026-10-06', 'descripcion' => 'Inspección de la vía', 'lugar' => 'Quinindé', 'orden' => 1,
    ]);
    comprobanteAceptado($liquidacion);

    $this->actingAs($this->titularUser, 'sanctum')
        ->postJson("/api/v1/viaticos/{$viatico->id}/liquidacion/confirmar")
        ->assertOk();

    $this->actingAs($this->financieroUser, 'sanctum')
        ->postJson("/api/v1/viaticos/{$viatico->id}/contabilizar", [
            'numero_resolucion' => 'RES-2026-001', 'partida_presupuestaria' => '530301',
        ])
        ->assertOk();

    expect(ViaticoFirmante::where('viatico_id', $viatico->id)->pluck('documento')->unique()->sort()->values()->all())
        ->toBe(['comprobante', 'informe']);
});
