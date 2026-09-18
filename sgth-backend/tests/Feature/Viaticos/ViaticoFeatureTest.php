<?php

use App\Enums\EstadoViatico;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\TarifaViatico;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\ViaticoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Viatico::unguard();
    TarifaViatico::unguard();

    $this->servidorUser = User::create([
        'email' => 'normal@example.com',
        'usuario_ti' => 'normal',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = unidadDePrueba(['nombre' => 'Direccion Test']);
    $this->puesto = puestoDePrueba($this->unidad);

    $this->servidor = Servidor::create([
        'cedula' => '0801234562',
        'nombre' => 'Pedro',
        'apellido' => 'Gomez',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => \App\Enums\RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2),
        'estado' => true,
    ]);

    // La FK va de users a servidores: servidores.user_id ya no existe.
    $this->servidorUser->update(['servidor_id' => $this->servidor->id]);
});

test('servidor_con_liquidacion_pendiente_no_puede_solicitar', function () {
    // Viático con fecha_fin hace 10 días (fuera de los 5 hábiles)
    Viatico::create([
        'servidor_id' => $this->servidor->id,
        'zona' => 'dentro_provincia',
        'datetime_salida' => now()->subDays(12),
        'datetime_llegada' => now()->subDays(10), // Hace 10 dias calendario (mas de 5 habiles seguros)
        'justificacion' => 'Comisión anterior',
        'estado' => EstadoViatico::PENDIENTE_LIQUIDACION->value,
        'monto_calculado' => 80,
    ]);

    $service = app(ViaticoService::class);
    
    expect(function () use ($service) {
        $service->solicitar($this->servidor->id, [
            'zona' => 'dentro_provincia',
            'datetime_salida' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'datetime_llegada' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'justificacion' => 'Nueva comision',
        ], $this->servidorUser->id);
    })->toThrow(\App\Exceptions\ReglaNegocioException::class, 'El servidor tiene bloqueada la solicitud de nuevos viáticos');
});

test('una_comision_del_mismo_dia_no_genera_viatico', function () {
    TarifaViatico::create([
        'zona' => 'dentro_provincia',
        'nivel' => 'servidor',
        'tipo_tarifa' => 'con_pernocte',
        'valor_diario' => 80.00,
    ]);

    $service = app(ViaticoService::class);

    // Ida y vuelta el mismo día: no hay noche que pagar y no existe la
    // subsistencia. Antes se cobraba como si hubiera dormido fuera.
    expect(fn () => $service->solicitar($this->servidor->id, [
        'zona' => 'dentro_provincia',
        'datetime_salida' => now()->setTime(8, 0)->format('Y-m-d H:i:s'),
        'datetime_llegada' => now()->setTime(16, 0)->format('Y-m-d H:i:s'),
        'justificacion' => 'Reunión rápida',
    ], $this->servidorUser->id))
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'al menos una noche fuera');
});

test('liquidacion_vence_a_los_4_dias_habiles', function () {
    // Configuramos el tiempo para el lunes 1 de Junio de 2026
    Carbon::setTestNow('2026-06-01 10:00:00');
    
    $fechaFin = Carbon::parse('2026-06-01 10:00:00');

    Viatico::create([
        'servidor_id' => $this->servidor->id,
        'zona' => 'dentro_provincia',
        'datetime_salida' => $fechaFin->copy()->subDays(2),
        'datetime_llegada' => $fechaFin, // Lunes
        'justificacion' => 'Comisión en Quito',
        'estado' => EstadoViatico::PENDIENTE_LIQUIDACION->value,
        'monto_calculado' => 160.00,
    ]);

    $service = app(ViaticoService::class);

    // 4 días hábiles a partir del regreso, el lunes 1 de junio (Gestión
    // Financiera, 2026-09-15; antes eran 5):
    // Dia 1 habil = 2 junio (martes)
    // Dia 2 habil = 3 junio (miercoles)
    // Dia 3 habil = 4 junio (jueves)
    // Dia 4 habil = 5 junio (viernes) -> Este es el ULTIMO dia permitido
    // Fin de semana: sabado 6, domingo 7
    // Lunes 8 de junio -> Vencido

    Carbon::setTestNow('2026-06-05 10:00:00');
    expect($service->verificarBloqueo($this->servidor->id))->toBeFalse();

    Carbon::setTestNow('2026-06-08 10:00:00');
    expect($service->verificarBloqueo($this->servidor->id))->toBeTrue();
});
