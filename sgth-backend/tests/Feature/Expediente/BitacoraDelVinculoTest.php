<?php

namespace Tests\Feature\Expediente;

use App\Enums\TipoEventoVinculo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| La bitácora del vínculo en el expediente (fase 1.2): lo que le pasó al
| contrato sin ser un acto va con su contrato, en `novedades`, y no entre las
| acciones de personal.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-01', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1111111111', 'nombre' => 'Servidor', 'apellido' => 'Bitacora',
        'regimen_laboral' => 'losep',
    ]);

    $this->anterior = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'servicios_ocasionales',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2023-01-01',
        'fecha_fin' => '2023-12-31',
        'estado' => 'terminado',
    ]);

    $this->vigente = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2024-01-01',
        'estado' => 'vigente',
    ]);

    $this->anotar = fn (array $datos): EventoVinculo => EventoVinculo::create([
        'servidor_id' => $this->servidor->id,
        'descripcion' => 'Entrada de prueba',
        'registrado_por' => $this->user->id,
        ...$datos,
    ]);

    $this->vinculoDe = fn (array $actividad, ContratoServidor $contrato): array => collect($actividad)
        ->first(fn (array $v) => $v['contrato']->id === $contrato->id);
});

test('la novedad que nombra su contrato va con ese, aunque su fecha caiga en otro', function () {
    // Un contrato cargado tarde: la entrada se anotó en 2024, pero es del de 2023.
    ($this->anotar)([
        'contrato_servidor_id' => $this->anterior->id,
        'tipo' => TipoEventoVinculo::CONTRATO_REGISTRADO,
        'fecha' => '2024-03-01',
    ]);

    $actividad = app(ContratoServidorService::class)->actividadLaboral($this->servidor->id);

    expect(($this->vinculoDe)($actividad, $this->anterior)['novedades'])->toHaveCount(1)
        ->and(($this->vinculoDe)($actividad, $this->vigente)['novedades'])->toBeEmpty();
});

test('la que no nombra contrato va con el vigente ese día', function () {
    // La constancia de una subrogación es del subrogante, no de un contrato.
    ($this->anotar)([
        'tipo' => TipoEventoVinculo::SUBROGACION_FINALIZADA,
        'fecha' => '2023-06-15',
        'descripcion' => 'Finalización anticipada de Subrogación.',
    ]);

    $actividad = app(ContratoServidorService::class)->actividadLaboral($this->servidor->id);
    $novedades = ($this->vinculoDe)($actividad, $this->anterior)['novedades'];

    expect($novedades)->toHaveCount(1)
        ->and($novedades[0]['tipo'])->toBe('subrogacion_finalizada')
        ->and($novedades[0]['etiqueta'])->toBe('Fin anticipado de subrogación o encargo')
        ->and($novedades[0]['fecha'])->toBe('2023-06-15')
        ->and($novedades[0]['descripcion'])->toBe('Finalización anticipada de Subrogación.')
        ->and(($this->vinculoDe)($actividad, $this->vigente)['novedades'])->toBeEmpty();
});

test('las novedades no se cuentan entre las acciones de personal', function () {
    ($this->anotar)([
        'contrato_servidor_id' => $this->vigente->id,
        'tipo' => TipoEventoVinculo::CONTRATO_REGISTRADO,
        'fecha' => '2024-01-01',
    ]);

    $vinculo = ($this->vinculoDe)(
        app(ContratoServidorService::class)->actividadLaboral($this->servidor->id),
        $this->vigente,
    );

    expect($vinculo['acciones'])->toBeEmpty()
        ->and($vinculo['novedades'])->toHaveCount(1);
});

test('van en orden y dicen quién las anotó', function () {
    ($this->anotar)([
        'contrato_servidor_id' => $this->vigente->id,
        'tipo' => TipoEventoVinculo::SUBROGACION_CANCELADA,
        'fecha' => '2025-05-01',
    ]);
    ($this->anotar)([
        'contrato_servidor_id' => $this->vigente->id,
        'tipo' => TipoEventoVinculo::CONTRATO_REGISTRADO,
        'fecha' => '2024-01-01',
    ]);

    $respuesta = $this->getJson("/api/v1/expediente/servidores/{$this->servidor->id}/actividad-laboral")
        ->assertOk();

    $vigente = collect($respuesta->json('datos'))->firstWhere('contrato.id', $this->vigente->id);

    expect(array_column($vigente['novedades'], 'fecha'))->toBe(['2024-01-01', '2025-05-01'])
        // Sin servidor detrás, el correo del usuario.
        ->and($vigente['novedades'][0]['registrado_por'])->toBe($this->user->email);
});
