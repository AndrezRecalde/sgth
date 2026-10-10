<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 2.3 del diseño de Acciones de Personal: la comisión de servicios con la
| regla legal [TH N5] —LOSEP 30 y 31, no la de la LOIP anulada— y la
| institución de destino, que también pide el intercambio voluntario.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $director = User::factory()->create();
    $director->assignRole('admin-uath');
    $this->actingAs($director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-COM', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $grupo = fn (string $codigo) => GrupoOcupacional::create([
        'grado_codigo' => $codigo, 'grado_numerico' => 1, 'grupo' => $codigo,
        'denominacion_generica' => $codigo, 'rmu' => 1500, 'regimen' => 'losep', 'activo' => true,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-COM', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
        'grupo_ocupacional_id' => $grupo('SP5')->id,
    ]);

    $this->puestoNjs = Puesto::create([
        'codigo' => 'P-NJS', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1,
        'grupo_ocupacional_id' => $grupo('NJS-5')->id,
    ]);

    $this->contador = 0;

    $this->permanente = function (string $ingreso = '2018-01-01', ?Puesto $puesto = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8700000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidor',
            'apellido'        => 'Comision'.$this->contador,
            'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => $ingreso,
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => 'nombramiento_permanente',
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => ($puesto ?? $this->puesto)->id,
            'fecha_inicio'             => $ingreso,
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->comision = fn (Servidor $s, string $clase, string $desde, string $hasta, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                    => $clase,
            'descripcion'              => 'Comisión de prueba',
            'fecha_efectiva'           => $desde,
            'fecha_inicio'             => $desde,
            'fecha_fin'                => $hasta,
            'institucion_destino'      => 'Ministerio del Trabajo',
            'requiere_dictamen_medico' => false,
            ...$extra,
        ]
    );
});

// ── Con remuneración (LOSEP 30) ─────────────────────────────────

test('con remuneración: 1 año de servicio a la fecha de inicio, no a hoy', function () {
    // Le falta un día a la fecha de inicio.
    ($this->comision)(($this->permanente)('2026-03-02'), 'comision_con_remuneracion', '2027-03-01', '2027-09-01')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'al menos 1 año de servicio'));

    ($this->comision)(($this->permanente)('2026-03-01'), 'comision_con_remuneracion', '2027-03-01', '2027-09-01')
        ->assertCreated();
});

test('con remuneración: hasta 2 años', function () {
    $servidor = ($this->permanente)();

    ($this->comision)($servidor, 'comision_con_remuneracion', '2027-03-01', '2029-02-28')->assertCreated();
    ($this->comision)($servidor, 'comision_con_remuneracion', '2027-03-01', '2029-03-01')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'dura hasta 2 años'));
});

test('para estudios o eventos, la obligación de volver queda en la acción', function () {
    ($this->comision)(($this->permanente)(), 'comision_con_remuneracion', '2027-03-01', '2028-02-28', [
        'para_estudios_o_eventos' => true,
    ])->assertCreated()->assertJsonPath('datos.para_estudios_o_eventos', true);
});

// ── Sin remuneración (LOSEP 31) ─────────────────────────────────

test('sin remuneración: hasta 6 años sumados en toda la carrera', function () {
    $servidor = ($this->permanente)('2010-01-01');

    // Cinco años de antes, registrados con el tipo plano de entonces.
    MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => TipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
        'estado'          => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro' => 'AP-2020-0001',
        'descripcion'     => 'Comisión anterior',
        'fecha_efectiva'  => '2015-01-01',
        'fecha_inicio'    => '2015-01-01',
        'fecha_fin'       => '2019-12-31',
    ]);

    // Una anulada no cuenta.
    MovimientoPersonal::create([
        'servidor_id'        => $servidor->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
        'estado'             => EstadoAccionPersonal::ANULADA,
        'descripcion'        => 'Comisión anulada',
        'fecha_efectiva'     => '2021-01-01',
        'fecha_inicio'       => '2021-01-01',
        'fecha_fin'          => '2025-12-31',
    ]);

    ($this->comision)($servidor, 'comision_sin_remuneracion', '2027-01-01', '2028-06-30')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, '6 años en toda la carrera'));

    ($this->comision)($servidor, 'comision_sin_remuneracion', '2027-01-01', '2027-12-31')->assertCreated();
});

test('sin remuneración: nunca para un puesto del nivel jerárquico superior', function () {
    ($this->comision)(($this->permanente)('2018-01-01', $this->puestoNjs), 'comision_sin_remuneracion', '2027-01-01', '2027-12-31')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'nivel jerárquico superior'));

    // La con remuneración no tiene esa restricción.
    ($this->comision)(($this->permanente)('2018-01-01', $this->puestoNjs), 'comision_con_remuneracion', '2027-01-01', '2027-12-31')
        ->assertCreated();
});

test('la marca de estudios no significa nada fuera de la comisión con remuneración', function () {
    ($this->comision)(($this->permanente)(), 'comision_sin_remuneracion', '2027-01-01', '2027-12-31', [
        'para_estudios_o_eventos' => true,
    ])->assertCreated()->assertJsonPath('datos.para_estudios_o_eventos', false);
});

// ── La institución de destino ───────────────────────────────────

test('la comisión y el intercambio piden la institución de destino', function () {
    $servidor = ($this->permanente)();

    foreach (['comision_con_remuneracion', 'comision_sin_remuneracion', 'intercambio_voluntario'] as $clase) {
        ($this->comision)($servidor, $clase, '2027-01-01', '2027-06-30', ['institucion_destino' => null])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'institución de destino'));
    }

    ($this->comision)($servidor, 'intercambio_voluntario', '2027-01-01', '2027-06-30', [
        'institucion_destino' => 'GAD Municipal de Esmeraldas',
    ])->assertCreated()->assertJsonPath('datos.institucion_destino', 'GAD Municipal de Esmeraldas');
});

test('corregir el borrador no la deja vacía', function () {
    $id = ($this->comision)(($this->permanente)(), 'comision_con_remuneracion', '2027-01-01', '2027-06-30')->json('datos.id');

    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['institucion_destino' => ''])
        ->assertStatus(422);
});

test('el catálogo dice qué clases la piden', function () {
    $clases = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->json('datos.clases'))
        ->keyBy('codigo');

    expect($clases['comision_con_remuneracion']['pide_institucion_destino'])->toBeTrue()
        ->and($clases['comision_sin_remuneracion']['pide_institucion_destino'])->toBeTrue()
        ->and($clases['intercambio_voluntario']['pide_institucion_destino'])->toBeTrue()
        ->and($clases['traslado']['pide_institucion_destino'])->toBeFalse()
        ->and($clases['comision_con_remuneracion']['admite_para_estudios_o_eventos'])->toBeTrue()
        ->and($clases['comision_sin_remuneracion']['admite_para_estudios_o_eventos'])->toBeFalse();
});

// ── El documento ────────────────────────────────────────────────

test('el documento imprime la institución, el período y la obligación de volver', function () {
    $comision = MovimientoPersonal::create([
        'servidor_id'        => ($this->permanente)()->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'estado'             => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro'    => 'AP-2027-0001',
        'descripcion'        => 'Comisión para la maestría',
        'institucion_destino' => 'Universidad Central del Ecuador',
        'para_estudios_o_eventos' => true,
        'fecha_efectiva'     => '2027-03-01',
        'fecha_inicio'       => '2027-03-01',
        'fecha_fin'          => '2028-02-28',
    ]);

    $firma = fn (RolFirmaAccionPersonal $rol) => ['rotulo' => $rol->rotuloDocumento(), 'nombre' => null, 'cargo' => null];

    $html = view('pdf.expediente.accion-personal', [
        'movimiento'         => $comision->fresh(),
        'servidor'           => $comision->servidor,
        'firmaAutoridad'     => $firma(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA),
        'firmaTalentoHumano' => $firma(RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO),
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Universidad Central del Ecuador')
        ->and($html)->toContain('del 01/03/2027')
        ->and($html)->toContain('al 28/02/2028')
        ->and($html)->toContain('un tiempo igual');
});
