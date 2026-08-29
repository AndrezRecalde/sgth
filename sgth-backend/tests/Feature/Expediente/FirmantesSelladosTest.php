<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\FirmanteAccionPersonalService;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->firmanteService = app(FirmanteAccionPersonalService::class);
    $this->service = app(MovimientoPersonalService::class);
    $this->stateService = app(MovimientoPersonalStateService::class);

    $this->contador = 0;

    /** Unidad con su puesto de jefatura y, opcionalmente, su titular. */
    $this->unidadConJefe = function (
        string $nombreUnidad,
        string $nombreCargo,
        bool $conTitular = true
    ): array {
        $this->contador++;

        $unidad = UnidadAdministrativa::create([
            'codigo' => 'U-'.$this->contador, 'nombre' => $nombreUnidad, 'nivel' => 2,
        ]);

        $cargo = Cargo::create([
            'nombre' => $nombreCargo,
        ]);

        $puesto = Puesto::create([
            'unidad_administrativa_id' => $unidad->id,
            'cargo_id' => $cargo->id,
            'plazas' => 1,
            'es_jefe' => true,
        ]);

        $titular = null;

        if ($conTitular) {
            $this->contador++;

            $titular = Servidor::create([
                'user_id' => User::factory()->create()->id,
                'cedula' => str_pad((string) (4000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
                'nombre' => 'Titular', 'apellido' => 'Numero'.$this->contador,
                'regimen_laboral' => 'losep',
                'puesto_id' => $puesto->id,
                'unidad_administrativa_id' => $unidad->id,
            ]);

            ContratoServidor::create([
                'servidor_id' => $titular->id,
                'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
                'unidad_administrativa_id' => $unidad->id,
                'puesto_id' => $puesto->id,
                'fecha_inicio' => '2020-01-01',
                'estado' => 'vigente',
            ]);
        }

        return [$unidad, $puesto, $titular];
    };

    /** Servidor cesable, para generar acciones suscribibles. */
    $this->servidorVinculado = function (): Servidor {
        $this->contador++;

        $unidad = UnidadAdministrativa::create([
            'codigo' => 'UOP-'.$this->contador, 'nombre' => 'Unidad Operativa', 'nivel' => 2,
        ]);

        $puesto = Puesto::create([
            'unidad_administrativa_id' => $unidad->id, 'plazas' => 5,
        ]);

        $servidor = Servidor::create([
            'user_id' => User::factory()->create()->id,
            'cedula' => str_pad((string) (3000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Vinculado'.$this->contador,
            'regimen_laboral' => 'losep',
            'puesto_id' => $puesto->id,
            'unidad_administrativa_id' => $unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id' => $servidor->id,
            'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
            'unidad_administrativa_id' => $unidad->id,
            'puesto_id' => $puesto->id,
            'fecha_inicio' => '2018-01-01',
            'estado' => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->cesacionSuscrita = function (Servidor $servidor) {
        $m = $this->service->registrar($servidor->id, [
            'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
            'descripcion' => 'Renuncia voluntaria',
            'fecha_efectiva' => '2026-08-01',
        ]);

        return $this->stateService->transicionar($m, EstadoAccionPersonal::SUSCRITA);
    };
});

// ── Restricciones del organigrama ───────────────────────────────

test('una unidad no puede tener dos puestos de jefatura', function () {
    [$unidad] = ($this->unidadConJefe)('Dirección X', 'Director X', false);

    expect(fn () => Puesto::create([
        'unidad_administrativa_id' => $unidad->id,
        'plazas' => 1,
        'es_jefe' => true,
    ]))->toThrow(QueryException::class);
});

test('un puesto de jefatura no puede tener más de una plaza', function () {
    $unidad = UnidadAdministrativa::create([
        'codigo' => 'U-PLZ', 'nombre' => 'Dirección Y', 'nivel' => 2,
    ]);

    expect(fn () => Puesto::create([
        'unidad_administrativa_id' => $unidad->id,
        'plazas' => 3,
        'es_jefe' => true,
    ]))->toThrow(QueryException::class);
});

test('mover el anclaje desmarca la unidad anterior', function () {
    [$a] = ($this->unidadConJefe)('Talento Humano Vieja', 'Director TH', false);
    [$b] = ($this->unidadConJefe)('Talento Humano Nueva', 'Director TH 2', false);

    $servicio = app(\App\Services\Estructura\EstructuraService::class);

    $servicio->actualizarUnidad($a->id, ['es_unidad_talento_humano' => true]);
    $servicio->actualizarUnidad($b->id, ['es_unidad_talento_humano' => true]);

    expect($a->fresh()->es_unidad_talento_humano)->toBeFalse()
        ->and($b->fresh()->es_unidad_talento_humano)->toBeTrue()
        ->and(UnidadAdministrativa::where('es_unidad_talento_humano', true)->count())->toBe(1);
});

test('solo una unidad puede ser la máxima autoridad', function () {
    [$a] = ($this->unidadConJefe)('Prefectura', 'Prefecto', false);
    [$b] = ($this->unidadConJefe)('Viceprefectura', 'Viceprefecto', false);

    $a->update(['es_maxima_autoridad' => true]);

    expect(fn () => $b->update(['es_maxima_autoridad' => true]))
        ->toThrow(QueryException::class);
});

// ── Derivación desde el organigrama ─────────────────────────────

test('el firmante es el jefe de la unidad anclada', function () {
    [$unidad, , $prefecto] = ($this->unidadConJefe)('Prefectura', 'Prefecto Provincial');
    $unidad->update(['es_maxima_autoridad' => true]);

    $firma = $this->firmanteService->resolver(
        RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, '2026-08-01'
    );

    expect($firma['servidor']->id)->toBe($prefecto->id)
        ->and($firma['cargo'])->toBe('Prefecto Provincial')
        ->and($firma['subrogado'])->toBeFalse();
});

test('sin unidad anclada se cae al cargo genérico', function () {
    $firma = $this->firmanteService->resolver(
        RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, '2026-08-01'
    );

    expect($firma['servidor'])->toBeNull()
        ->and($firma['cargo'])->toBe('PREFECTO/A PROVINCIAL');
});

test('con el puesto de jefatura vacante se imprime el cargo pero sin nombre', function () {
    [$unidad] = ($this->unidadConJefe)('Prefectura', 'Prefecto Provincial', false);
    $unidad->update(['es_maxima_autoridad' => true]);

    $firma = $this->firmanteService->resolver(
        RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, '2026-08-01'
    );

    expect($firma['servidor'])->toBeNull()
        ->and($firma['cargo'])->toBe('Prefecto Provincial');
});

test('una subrogación vigente hace que firme el subrogante', function () {
    [$unidad, $puesto, $titular] = ($this->unidadConJefe)('Talento Humano', 'Director de TH');
    $unidad->update(['es_unidad_talento_humano' => true]);

    $encargado = ($this->servidorVinculado)();

    Subrogacion::create([
        'tipo' => 'subrogacion',
        'servidor_subrogante_id' => $encargado->id,
        'servidor_subrogado_id' => $titular->id,
        'unidad_administrativa_id' => $unidad->id,
        'puesto_subrogado_id' => $puesto->id,
        'fecha_inicio' => '2026-07-01',
        'fecha_fin' => '2026-09-30',
        'motivo' => 'vacaciones',
        'estado' => 'activa',
        'registrado_por' => $this->user->id,
    ]);

    $durante = $this->firmanteService->resolver(
        RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO, '2026-08-01'
    );

    expect($durante['servidor']->id)->toBe($encargado->id)
        ->and($durante['subrogado'])->toBeTrue()
        // El cargo impreso es el del puesto, marcado como subrogado.
        ->and($durante['cargo'])->toBe('Director de TH');

    // Fuera del período de la subrogación vuelve a firmar el titular.
    $despues = $this->firmanteService->resolver(
        RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO, '2026-11-01'
    );

    expect($despues['servidor']->id)->toBe($titular->id)
        ->and($despues['subrogado'])->toBeFalse();
});

// ── Sellado ─────────────────────────────────────────────────────

test('suscribir sella nombre, cargo y cédula de ambos firmantes', function () {
    [$uPrefectura, , $prefecto] = ($this->unidadConJefe)('Prefectura', 'Prefecto Provincial');
    $uPrefectura->update(['es_maxima_autoridad' => true]);

    [$uTh, , $director] = ($this->unidadConJefe)('Talento Humano', 'Director de TH');
    $uTh->update(['es_unidad_talento_humano' => true]);

    $movimiento = ($this->cesacionSuscrita)(($this->servidorVinculado)());

    expect($movimiento->firmante_autoridad_id)->toBe($prefecto->id)
        ->and($movimiento->firmante_autoridad_cargo)->toBe('Prefecto Provincial')
        ->and($movimiento->firmante_autoridad_cedula)->toBe($prefecto->cedula)
        ->and($movimiento->firmante_th_id)->toBe($director->id)
        ->and($movimiento->firmante_th_cargo)->toBe('Director de TH')
        ->and($movimiento->fecha_suscripcion)->not->toBeNull();
});

test('el sello marca al firmante que actuó por subrogación', function () {
    [$uTh, $puestoTh, $titular] = ($this->unidadConJefe)('Talento Humano', 'Director de TH');
    $uTh->update(['es_unidad_talento_humano' => true]);

    $encargado = ($this->servidorVinculado)();

    Subrogacion::create([
        'tipo' => 'encargo',
        'servidor_subrogante_id' => $encargado->id,
        'servidor_subrogado_id' => $titular->id,
        'unidad_administrativa_id' => $uTh->id,
        'puesto_subrogado_id' => $puestoTh->id,
        'fecha_inicio' => now()->subMonth()->toDateString(),
        'fecha_fin' => now()->addMonth()->toDateString(),
        'motivo' => 'vacaciones',
        'estado' => 'activa',
        'registrado_por' => $this->user->id,
    ]);

    $movimiento = ($this->cesacionSuscrita)(($this->servidorVinculado)());

    expect($movimiento->firmante_th_id)->toBe($encargado->id)
        ->and($movimiento->firmante_th_cargo)->toBe('Director de TH (S)');
});

test('cambiar de autoridad después no reescribe lo ya firmado', function () {
    [$uPrefectura, $puesto, $prefectoViejo] = ($this->unidadConJefe)('Prefectura', 'Prefecto Provincial');
    $uPrefectura->update(['es_maxima_autoridad' => true]);

    $movimiento = ($this->cesacionSuscrita)(($this->servidorVinculado)());
    $selladoOriginal = $movimiento->firmante_autoridad_nombre;

    // Rota la autoridad: el titular anterior cesa y entra otro.
    ContratoServidor::where('servidor_id', $prefectoViejo->id)
        ->update(['estado' => 'terminado', 'fecha_fin' => now()->toDateString(), 'motivo_fin' => 'Fin de período']);

    $prefectoNuevo = ($this->servidorVinculado)();
    ContratoServidor::create([
        'servidor_id' => $prefectoNuevo->id,
        'tipo_nombramiento' => TipoNombramiento::ELECCION_POPULAR->value,
        'unidad_administrativa_id' => $uPrefectura->id,
        'puesto_id' => $puesto->id,
        'fecha_inicio' => now()->toDateString(),
        'estado' => 'vigente',
    ]);

    $registrado = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect($registrado->firmante_autoridad_id)->toBe($prefectoViejo->id)
        ->and($registrado->firmante_autoridad_nombre)->toBe($selladoOriginal);
});

test('el sello no se puede modificar una vez registrada la acción', function () {
    $movimiento = ($this->cesacionSuscrita)(($this->servidorVinculado)());
    $registrado = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect(fn () => $registrado->update(['firmante_autoridad_nombre' => 'OTRA PERSONA']))
        ->toThrow(ReglaNegocioException::class, "No se puede modificar 'firmante_autoridad_nombre'");
});

// ── El documento impreso ────────────────────────────────────────

test('el PDF imprime al firmante sellado y no al titular de hoy', function () {
    [$uPrefectura, $puesto, $prefectoQueFirma] = ($this->unidadConJefe)('Prefectura', 'Prefecto Provincial');
    $uPrefectura->update(['es_maxima_autoridad' => true]);

    $movimiento = ($this->cesacionSuscrita)(($this->servidorVinculado)());
    $registrado = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    // Cambia el titular DESPUÉS de que la acción quedó registrada.
    ContratoServidor::where('servidor_id', $prefectoQueFirma->id)
        ->update(['estado' => 'terminado', 'fecha_fin' => now()->toDateString(), 'motivo_fin' => 'Fin de período']);

    $posterior = ($this->servidorVinculado)();
    ContratoServidor::create([
        'servidor_id' => $posterior->id,
        'tipo_nombramiento' => TipoNombramiento::ELECCION_POPULAR->value,
        'unidad_administrativa_id' => $uPrefectura->id,
        'puesto_id' => $puesto->id,
        'fecha_inicio' => now()->toDateString(),
        'estado' => 'vigente',
    ]);

    $resultado = app(\App\Services\Expediente\AccionPersonalPdfService::class)
        ->generarContent($registrado->id);

    expect($resultado['content'])->toStartWith('%PDF');

    $html = view('pdf.expediente.accion-personal', [
        'movimiento' => $registrado->fresh(),
        'servidor'   => $registrado->servidor,
        'firmaAutoridad' => [
            'rotulo' => RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA->rotuloDocumento(),
            'nombre' => $registrado->firmante_autoridad_nombre,
            'cargo'  => $registrado->firmante_autoridad_cargo,
        ],
        'firmaTalentoHumano' => [
            'rotulo' => RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO->rotuloDocumento(),
            'nombre' => $registrado->firmante_th_nombre,
            'cargo'  => $registrado->firmante_th_cargo,
        ],
        'logo' => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain($registrado->firmante_autoridad_nombre)
        ->and($html)->not->toContain($posterior->apellido);
});

// ── Endpoint ────────────────────────────────────────────────────

test('el endpoint de vigentes explica por qué un rol no se resuelve', function () {
    [$uTh] = ($this->unidadConJefe)('Talento Humano', 'Director de TH', false);
    $uTh->update(['es_unidad_talento_humano' => true]);

    $datos = collect(
        $this->getJson('/api/v1/expediente/firmantes-accion-personal/vigentes')
            ->assertOk()
            ->json('datos')
    )->keyBy('rol_firma');

    expect($datos)->toHaveCount(count(RolFirmaAccionPersonal::cases()))
        // Sin unidad anclada.
        ->and($datos['autoridad_nominadora']['resuelto'])->toBeFalse()
        ->and($datos['autoridad_nominadora']['motivo_sin_resolver'])->toContain('ninguna unidad marcada')
        // Anclada pero con la jefatura vacante.
        ->and($datos['responsable_talento_humano']['resuelto'])->toBeFalse()
        ->and($datos['responsable_talento_humano']['motivo_sin_resolver'])->toContain('jefatura ocupado')
        ->and($datos['responsable_talento_humano']['unidad']['nombre'])->toBe('Talento Humano');
});

test('el endpoint devuelve al firmante cuando el organigrama está completo', function () {
    [$uTh, , $director] = ($this->unidadConJefe)('Talento Humano', 'Director de TH');
    $uTh->update(['es_unidad_talento_humano' => true]);

    $datos = collect(
        $this->getJson('/api/v1/expediente/firmantes-accion-personal/vigentes')->json('datos')
    )->keyBy('rol_firma');

    expect($datos['responsable_talento_humano']['resuelto'])->toBeTrue()
        ->and($datos['responsable_talento_humano']['servidor']['id'])->toBe($director->id)
        ->and($datos['responsable_talento_humano']['cargo'])->toBe('Director de TH');
});
