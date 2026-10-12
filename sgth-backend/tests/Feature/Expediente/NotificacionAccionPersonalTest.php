<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\MedioNotificacion;
use App\Enums\ResultadoNotificacion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Mail\Expediente\AccionPersonalNotificadaMail;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\AccionPersonalPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 6.3a del diseño de Acciones de Personal (6.4 y 9.13; TH 26): notificar
| al servidor. Por el portal, el documento aparece en «Mis documentos
| laborales» y le llega al correo —el institucional, o el personal si no tiene
| cuenta—; en persona, queda si lo recibió o si se negó, con la razón y un
| testigo. El documento imprime lo que pasó.
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $unidad = UnidadAdministrativa::create(['codigo' => 'NT-ADM', 'nombre' => 'Dirección Administrativa', 'nivel' => 2]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'plazas' => 1, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Analista Administrativo'])->id,
    ]);
    $this->unidad = $unidad;

    $this->contador = 0;
    /** Sin decir nada, con un correo institucional propio; null, sin cuenta. */
    $this->registrada = function (?string $correoInstitucional = 'auto', ?string $personal = null): MovimientoPersonal {
        $this->contador++;
        if ($correoInstitucional === 'auto') {
            $correoInstitucional = "titular{$this->contador}@esmeraldas.gob.ec";
        }
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6700000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Notificado'.$this->contador, 'regimen_laboral' => 'losep',
            'correo_personal' => $personal,
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
            'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
        ]);
        if ($correoInstitucional) {
            User::factory()->create(['email' => $correoInstitucional, 'servidor_id' => $servidor->id]);
        }

        return MovimientoPersonal::create([
            'servidor_id' => $servidor->id, 'estado' => EstadoAccionPersonal::REGISTRADA,
            'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
            'codigo_registro' => 'AP-2026-0'.(800 + $this->contador), 'descripcion' => 'Renuncia voluntaria.',
            'fecha_efectiva' => '2026-11-01', 'fecha_suscripcion' => '2026-10-10', 'fecha_registro' => '2026-10-11',
            'unidad_origen_id' => $this->unidad->id, 'puesto_origen_id' => $this->puesto->id,
        ]);
    };

    $this->notificar = fn (MovimientoPersonal $m, array $datos = []) => $this->putJson(
        "/api/v1/expediente/movimientos/{$m->id}/transicionar",
        ['estado' => 'notificada', ...$datos],
    );

    $this->html = function (MovimientoPersonal $m): string {
        ['vista' => $vista, 'datos' => $datos] = app(AccionPersonalPdfService::class)->documento($m->id);

        return view($vista, $datos)->render();
    };
});

test('por el portal, el correo sale al institucional con el documento, y queda esperando el acuse', function () {
    $accion = ($this->registrada)('titular@esmeraldas.gob.ec');

    ($this->notificar)($accion)->assertOk();

    $accion->refresh();
    expect($accion->estado)->toBe(EstadoAccionPersonal::NOTIFICADA)
        ->and($accion->notificacion_medio)->toBe(MedioNotificacion::PORTAL)
        ->and($accion->correo_destino)->toBe('institucional')
        ->and($accion->correo_enviado_en)->not->toBeNull()
        ->and($accion->acuse_en)->toBeNull();

    Mail::assertSent(AccionPersonalNotificadaMail::class, fn ($mail) => $mail->hasTo('titular@esmeraldas.gob.ec')
        && $mail->movimiento->is($accion)
        && count($mail->attachments()) === 1);

    $html = ($this->html)($accion);
    expect($html)->toContain('Portal del servidor y correo')
        ->and($html)->toContain('Pendiente');

    // En la bandeja, entre las que esperan el acuse.
    $this->getJson('/api/v1/expediente/movimientos?sin_acuse=1')
        ->assertOk()
        ->assertJsonPath('datos.data.0.id', $accion->id)
        ->assertJsonPath('datos.data.0.notificacion_medio', 'portal');
});

test('sin cuenta, al correo personal; sin ninguno, queda dicho para entregarlo a mano', function () {
    $personal = ($this->registrada)(null, 'personal@example.com');
    ($this->notificar)($personal)->assertOk();
    expect($personal->fresh()->correo_destino)->toBe('personal');
    Mail::assertSent(AccionPersonalNotificadaMail::class, fn ($mail) => $mail->hasTo('personal@example.com'));

    $sinCorreo = ($this->registrada)(null);
    ($this->notificar)($sinCorreo)->assertOk();
    expect($sinCorreo->fresh()->correo_destino)->toBe('sin_direccion')
        ->and($sinCorreo->fresh()->correo_enviado_en)->toBeNull()
        // La notificación queda igual: el documento ya está en su portal.
        ->and($sinCorreo->fresh()->estado)->toBe(EstadoAccionPersonal::NOTIFICADA);
});

test('en persona: si la recibió, su «Recibí» es el acuse; si se negó, la razón y el testigo', function () {
    $recibida = ($this->registrada)();
    ($this->notificar)($recibida, ['notificacion_medio' => 'personal', 'notificacion_resultado' => 'recibida'])->assertOk();

    $recibida->refresh();
    expect($recibida->notificacion_resultado)->toBe(ResultadoNotificacion::RECIBIDA)
        ->and($recibida->acuse_en)->not->toBeNull()
        ->and($recibida->correo_destino)->toBeNull();
    Mail::assertNothingSent();

    $negada = ($this->registrada)();
    ($this->notificar)($negada, ['notificacion_medio' => 'personal', 'notificacion_resultado' => 'negada'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['notificacion_razon', 'notificacion_testigo_nombre', 'notificacion_testigo_cedula'], 'errores');

    ($this->notificar)($negada, [
        'notificacion_medio' => 'personal', 'notificacion_resultado' => 'negada',
        'notificacion_razon' => 'Dice que no está de acuerdo con la fecha.',
        'notificacion_testigo_nombre' => 'Pedro Cortez', 'notificacion_testigo_cedula' => '0801234567',
    ])->assertOk();

    $negada->refresh();
    expect($negada->acuse_en)->toBeNull();

    $html = ($this->html)($negada);
    expect($html)->toContain('Dice que no está de acuerdo con la fecha.')
        ->and($html)->toContain('Pedro Cortez')
        ->and($html)->toContain('0801234567');
});

test('lo que se dejó al notificar ya no se reescribe', function () {
    $accion = ($this->registrada)();
    ($this->notificar)($accion, ['notificacion_medio' => 'personal', 'notificacion_resultado' => 'recibida'])->assertOk();

    // Antes de la 6.3a, con la acción ya notificada se podía cambiar quién y
    // cuándo la notificó.
    expect(fn () => $accion->fresh()->forceFill(['notificacion_resultado' => 'negada'])->save())
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'no se modifica');
    expect(fn () => $accion->fresh()->forceFill(['fecha_notificacion' => now()->addDay()])->save())
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'no se modifica');
    // El acuse, ya puesto, tampoco.
    expect(fn () => $accion->fresh()->forceFill(['acuse_en' => now()->addDay()])->save())
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'no se modifica');
});
