<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-TAX', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->service      = app(MovimientoPersonalService::class);
    $this->stateService = app(MovimientoPersonalStateService::class);

    $this->contador = 0;

    /**
     * Servidor con contrato vigente del nombramiento indicado. La antigüedad
     * por defecto (5 años) supera el umbral de las comisiones de servicios,
     * para que los tests de elegibilidad no fallen por otra regla.
     */
    $this->servidorCon = function (
        TipoNombramiento $nombramiento,
        ?string $ingresoInstitucion = '2018-01-01'
    ): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'user_id'                  => User::factory()->create()->id,
            'cedula'                   => str_pad((string) (9000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                   => 'Servidor',
            'apellido'                 => 'Taxonomia'.$this->contador,
            'regimen_laboral'          => 'losep',
            'puesto_id'                => $this->puesto->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'fecha_ingreso_institucion' => $ingresoInstitucion,
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => $ingresoInstitucion ?? '2018-01-01',
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };
});

// ── Coherencia tipo ↔ subtipo ───────────────────────────────────

test('los tres tipos paraguas exigen subtipo y el resto no lo admite', function () {
    $conSubtipo = [
        TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO,
        TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO,
        TipoMovimientoPersonal::CESACION_FUNCIONES,
    ];

    foreach (TipoMovimientoPersonal::cases() as $tipo) {
        expect($tipo->requiereSubtipo())
            ->toBe(in_array($tipo, $conSubtipo, true), "Tipo '{$tipo->value}'");
    }
});

test('cada subtipo pertenece a exactamente un tipo', function () {
    $vistos = [];

    foreach (TipoMovimientoPersonal::cases() as $tipo) {
        foreach ($tipo->subtiposPermitidos() as $subtipo) {
            expect($vistos)->not->toHaveKey(
                $subtipo->value,
                "El subtipo '{$subtipo->value}' pertenece a más de un tipo."
            );
            $vistos[$subtipo->value] = $tipo->value;
        }
    }

    expect(array_keys($vistos))
        ->toHaveCount(count(SubtipoMovimientoPersonal::cases()));
});

test('un cambio administrativo sin subtipo es rechazado', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento' => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'descripcion'     => 'Sin subtipo',
        'fecha_efectiva'  => '2026-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'requiere que se especifique el subtipo');
});

test('un subtipo que no pertenece al tipo es rechazado', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::JUBILACION->value,
        'descripcion'        => 'Subtipo cruzado',
        'fecha_efectiva'     => '2026-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'no corresponde a');
});

test('un tipo sin subtipos rechaza que se le envíe uno', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_DENOMINACION->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Subtipo indebido',
        'fecha_efectiva'     => '2026-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'no admite subtipo');
});

// ── Matriz de elegibilidad por subtipo ──────────────────────────

dataset('matriz de elegibilidad', [
    'traslado administrativo → solo permanente' => [
        SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO,
        [TipoNombramiento::PERMANENTE],
    ],
    'traspaso → solo permanente' => [
        SubtipoMovimientoPersonal::TRASPASO,
        [TipoNombramiento::PERMANENTE],
    ],
    'comisión con remuneración → solo permanente' => [
        SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION,
        [TipoNombramiento::PERMANENTE],
    ],
    'comisión sin remuneración → solo permanente' => [
        SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION,
        [TipoNombramiento::PERMANENTE],
    ],
    'sanción disciplinaria → permanente, provisional, ocasional, libre nombramiento y obreros' => [
        SubtipoMovimientoPersonal::SANCION_DISCIPLINARIA,
        [
            TipoNombramiento::PERMANENTE,
            TipoNombramiento::PROVISIONAL,
            TipoNombramiento::SERVICIOS_OCASIONALES,
            // TH equiparó Libre Nombramiento al ocasional el 2026-09-29.
            TipoNombramiento::LIBRE_NOMBRAMIENTO,
            // Y los obreros el 2026-10-04, solo para la multa.
            TipoNombramiento::CODIGO_TRABAJO,
        ],
    ],
    'renuncia → permanente, provisional, ocasional y libre nombramiento' => [
        SubtipoMovimientoPersonal::RENUNCIA,
        [
            TipoNombramiento::PERMANENTE,
            TipoNombramiento::PROVISIONAL,
            TipoNombramiento::SERVICIOS_OCASIONALES,
            // TH equiparó Libre Nombramiento al ocasional el 2026-09-29.
            TipoNombramiento::LIBRE_NOMBRAMIENTO,
        ],
    ],
    'destitución → permanente, provisional, ocasional y libre nombramiento' => [
        SubtipoMovimientoPersonal::DESTITUCION,
        [
            TipoNombramiento::PERMANENTE,
            TipoNombramiento::PROVISIONAL,
            TipoNombramiento::SERVICIOS_OCASIONALES,
            // TH equiparó Libre Nombramiento al ocasional el 2026-09-29.
            TipoNombramiento::LIBRE_NOMBRAMIENTO,
        ],
    ],
    'jubilación → permanente, provisional, ocasional y libre nombramiento' => [
        SubtipoMovimientoPersonal::JUBILACION,
        [
            TipoNombramiento::PERMANENTE,
            TipoNombramiento::PROVISIONAL,
            TipoNombramiento::SERVICIOS_OCASIONALES,
            // TH equiparó Libre Nombramiento al ocasional el 2026-09-29.
            TipoNombramiento::LIBRE_NOMBRAMIENTO,
        ],
    ],
    'incapacidad → permanente, provisional, ocasional y libre nombramiento' => [
        SubtipoMovimientoPersonal::INCAPACIDAD,
        [
            TipoNombramiento::PERMANENTE,
            TipoNombramiento::PROVISIONAL,
            TipoNombramiento::SERVICIOS_OCASIONALES,
            // TH equiparó Libre Nombramiento al ocasional el 2026-09-29.
            TipoNombramiento::LIBRE_NOMBRAMIENTO,
        ],
    ],
    'contrato finalizado → solo servicios profesionales' => [
        SubtipoMovimientoPersonal::CONTRATO_FINALIZADO,
        [TipoNombramiento::SERVICIOS_PROFESIONALES],
    ],
]);

test('la elegibilidad del subtipo cubre exactamente los nombramientos previstos', function (
    SubtipoMovimientoPersonal $subtipo,
    array $esperados
) {
    foreach (TipoNombramiento::cases() as $nombramiento) {
        expect($subtipo->elegiblePara($nombramiento))->toBe(
            in_array($nombramiento, $esperados, true),
            "Subtipo '{$subtipo->value}' con nombramiento '{$nombramiento->value}'"
        );
    }
})->with('matriz de elegibilidad');

test('una cesación por renuncia sobre un obrero es rechazada', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Renuncia de obrero',
        'fecha_efectiva'     => '2026-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'no aplica para el tipo de nombramiento');
});

test('un contrato finalizado sobre un permanente es rechazado', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::CONTRATO_FINALIZADO->value,
        'descripcion'        => 'Fin de contrato de un permanente',
        'fecha_efectiva'     => '2026-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'no aplica para el tipo de nombramiento');
});

// ── El hueco que cerró esta fase ────────────────────────────────

test('el tipo plano traspaso ahora valida elegibilidad vía su subtipo equivalente', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::SERVICIOS_OCASIONALES);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento' => TipoMovimientoPersonal::TRASPASO->value,
        'descripcion'     => 'Traspaso de un ocasional',
        'fecha_efectiva'  => '2026-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'no aplica para el tipo de nombramiento');
});

test('el tipo plano traslado ahora valida elegibilidad vía su subtipo equivalente', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PROVISIONAL);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento' => TipoMovimientoPersonal::TRASLADO->value,
        'descripcion'     => 'Traslado de un provisional',
        'fecha_efectiva'  => '2026-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'no aplica para el tipo de nombramiento');
});

// ── Comisión de servicios: ambas variantes ──────────────────────

/**
 * Las reglas de la comisión se comprobaban solo al crearla, y el borrador es
 * editable: bastaba registrar una de 2 años y estirarla después.
 */
test('editar el borrador no permite estirar la comisión más allá del límite', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $comision = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'descripcion'        => 'Comisión válida de 2 años',
        'fecha_efectiva'     => '2026-08-01',
        'fecha_inicio'       => '2026-08-01',
        'fecha_fin'          => '2028-07-31',
    ]);

    expect(fn () => $this->service->actualizarBorrador($comision, [
        'fecha_fin' => '2036-07-31',
    ]))->toThrow(ReglaNegocioException::class, 'entre 1 y 6 años');

    // Y el borrador no quedó modificado a medias.
    expect($comision->fresh()->fecha_fin->toDateString())->toBe('2028-07-31');
});

test('editar un borrador sin tocar las fechas no revalida la comisión', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $comision = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'descripcion'        => 'Comisión válida',
        'fecha_efectiva'     => '2026-08-01',
        'fecha_inicio'       => '2026-08-01',
        'fecha_fin'          => '2028-07-31',
    ]);

    $this->service->actualizarBorrador($comision, ['descripcion' => 'Descripción corregida']);

    expect($comision->fresh()->descripcion)->toBe('Descripción corregida');
});

test('la comisión con remuneración hereda las reglas de duración', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'descripcion'        => 'Comisión de 7 años',
        'fecha_efectiva'     => '2026-08-01',
        'fecha_inicio'       => '2026-08-01',
        'fecha_fin'          => '2033-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'entre 1 y 6 años');
});

test('la comisión con remuneración exige antigüedad mínima', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE, now()->subMonths(8)->toDateString());

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'descripcion'        => 'Comisión sin antigüedad',
        'fecha_efectiva'     => '2026-08-01',
        'fecha_inicio'       => '2026-08-01',
        'fecha_fin'          => '2028-08-01',
    ]))->toThrow(ReglaNegocioException::class, 'al menos 2 años de antigüedad');
});

test('exactamente 2 años de antigüedad cumple el umbral de la comisión', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE, now()->subYears(2)->toDateString());

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
        'descripcion'        => 'Comisión con exactamente 2 años de antigüedad',
        'fecha_efectiva'     => '2026-08-01',
        'fecha_inicio'       => '2026-08-01',
        'fecha_fin'          => '2028-08-01',
    ]);

    expect($movimiento->subtipo_movimiento)
        ->toBe(SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION);
});

// ── Dictamen médico ─────────────────────────────────────────────

test('el default de requiere_dictamen_medico sale del subtipo o del tipo', function () {
    expect(SubtipoMovimientoPersonal::JUBILACION->requiereDictamenMedicoPorDefecto())->toBeTrue()
        ->and(SubtipoMovimientoPersonal::INCAPACIDAD->requiereDictamenMedicoPorDefecto())->toBeTrue()
        ->and(SubtipoMovimientoPersonal::RENUNCIA->requiereDictamenMedicoPorDefecto())->toBeFalse()
        ->and(TipoMovimientoPersonal::INGRESO->requiereDictamenMedicoPorDefecto())->toBeTrue()
        ->and(TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->requiereDictamenMedicoPorDefecto())->toBeFalse();
});

test('una cesación por jubilación nace exigiendo dictamen médico', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::JUBILACION->value,
        'descripcion'        => 'Jubilación',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    expect($movimiento->requiere_dictamen_medico)->toBeTrue()
        ->and($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

test('Talento Humano puede desmarcar el dictamen médico al registrar', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'          => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento'       => SubtipoMovimientoPersonal::JUBILACION->value,
        'requiere_dictamen_medico' => false,
        'descripcion'              => 'Jubilación con dictamen previo en físico',
        'fecha_efectiva'           => '2026-08-01',
    ]);

    expect($movimiento->requiere_dictamen_medico)->toBeFalse();
});

test('suscribir una acción con dictamen requerido abre la solicitud al dispensario', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::JUBILACION->value,
        'descripcion'        => 'Jubilación',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    $solicitud = SolicitudCertificacionMedica::where('movimiento_personal_id', $movimiento->id)->first();

    expect($solicitud)->not->toBeNull()
        ->and($solicitud->tipo_evento)->toBe('retiro')
        ->and($solicitud->estado)->toBe('pendiente')
        ->and($solicitud->servidor_id)->toBe($servidor->id);
});

test('no se puede registrar una acción con dictamen pendiente', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::JUBILACION->value,
        'descripcion'        => 'Jubilación',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class, 'aún no ha emitido el dictamen');
});

test('un dictamen de no apto tampoco habilita el registro', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::JUBILACION->value,
        'descripcion'        => 'Jubilación',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    SolicitudCertificacionMedica::where('movimiento_personal_id', $movimiento->id)
        ->update(['estado' => 'completada', 'dictamen' => 'no_apto']);

    expect(fn () => $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class, 'no es de aptitud');
});

test('con dictamen de aptitud la acción se registra', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::JUBILACION->value,
        'descripcion'        => 'Jubilación',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    SolicitudCertificacionMedica::where('movimiento_personal_id', $movimiento->id)
        ->update(['estado' => 'completada', 'dictamen' => 'apto']);

    $registrado = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA)
        ->and($registrado->codigo_registro)->toStartWith('AP-');
});

test('una acción sin dictamen requerido no abre solicitud ni se bloquea', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Renuncia voluntaria',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    expect(SolicitudCertificacionMedica::where('movimiento_personal_id', $movimiento->id)->count())->toBe(0);

    $registrado = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA);
});

// ── Inmutabilidad ───────────────────────────────────────────────

test('el subtipo no se puede modificar una vez registrada la acción', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'        => $servidor->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'estado'             => EstadoAccionPersonal::REGISTRADA,
        'descripcion'        => 'Renuncia registrada',
        'fecha_efectiva'     => '2026-08-01',
        'autorizado_por'     => $this->user->id,
    ]);

    expect(fn () => $movimiento->update([
        'subtipo_movimiento' => SubtipoMovimientoPersonal::JUBILACION->value,
    ]))->toThrow(ReglaNegocioException::class, "No se puede modificar 'subtipo_movimiento'");
});

// ── Qué produce documento imprimible ────────────────────────────

/*
| Mapa completo, para que la deriva se vea. El frontend lo espeja en
| `utils/estadoAccionPersonal.ts` (SIN_DOCUMENTO) y decide con él si ofrecer el
| botón «PDF»: las dos listas tienen que decir lo mismo, o la pantalla ofrece
| una descarga que responde 422.
*/
test('tieneDocumentoImprimible() cubre todo acto formal, que es todo lo que queda', function () {
    $conDocumento = [
        // Los ocho de esAccionDePersonal()…
        TipoMovimientoPersonal::CAMBIO_DENOMINACION,
        TipoMovimientoPersonal::PRESTACION_SERVICIOS,
        TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO,
        TipoMovimientoPersonal::COMISION_SIN_REMUNERACION,
        TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION,
        TipoMovimientoPersonal::INCREMENTO_REMUNERACION,
        TipoMovimientoPersonal::CESACION_FUNCIONES,
        TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO,
        // …más los tipos planos legados que tienen subtipo equivalente…
        TipoMovimientoPersonal::TRASLADO,
        TipoMovimientoPersonal::TRASPASO,
        TipoMovimientoPersonal::COMISION_SERVICIOS,
        TipoMovimientoPersonal::DESTITUCION,
        // …y los dos que se suman a mano en tieneDocumentoImprimible().
        TipoMovimientoPersonal::SUBROGACION,
        TipoMovimientoPersonal::INGRESO,
    ];

    // La bitácora del expediente —novedad de contrato, cambio de puesto,
    // cambio de régimen, egreso— no tenía documento, y desde la fase 1.2 ni
    // siquiera es un tipo de esta tabla: vive en `eventos_vinculo`.
    //
    // Sin tipos huérfanos: si mañana nace uno, esta prueba lo señala en vez de
    // dejarlo sin decidir por descuido.
    expect(count($conDocumento))->toBe(count(TipoMovimientoPersonal::cases()));

    foreach ($conDocumento as $tipo) {
        expect($tipo->tieneDocumentoImprimible())->toBeTrue("Tipo '{$tipo->value}'");
    }
});

test('el ingreso sigue fuera de esAccionDePersonal(), donde vive la elegibilidad', function () {
    // Si entrara ahí, validarElegibilidad() exigiría un nombramiento vigente y
    // todo ingreso fallaría con «el servidor no tiene un contrato vigente»: es
    // justo lo que no tiene quien ingresa. De ahí que el documento se sume
    // aparte, como la subrogación.
    expect(TipoMovimientoPersonal::INGRESO->esAccionDePersonal())->toBeFalse()
        ->and(TipoMovimientoPersonal::INGRESO->tieneDocumentoImprimible())->toBeTrue();
});

// ── Reglas revisadas con TH el 2026-09-28 ───────────────────────

/*
| Tres reglas que el código contradecía. Van juntas porque salen de la misma
| consulta y porque las tres se comprobaban solo en la pantalla o en ninguna
| parte. La cuarta que TH confirmó —cambio de denominación solo para obreros— ya
| estaba bien y la cubre la matriz de arriba.
*/
test('el incremento de remuneración es solo para obreros', function () {
    foreach (TipoNombramiento::cases() as $nombramiento) {
        expect(TipoMovimientoPersonal::INCREMENTO_REMUNERACION->elegiblePara($nombramiento))
            ->toBe($nombramiento === TipoNombramiento::CODIGO_TRABAJO, $nombramiento->value);
    }
});

test('la prestación de servicios es para provisionales, ocasionales, servicios profesionales y libre nombramiento', function () {
    $elegibles = [
        TipoNombramiento::PROVISIONAL,
        TipoNombramiento::SERVICIOS_OCASIONALES,
        TipoNombramiento::SERVICIOS_PROFESIONALES,
        // Añadido el 2026-09-29 junto con el resto de lo que TH equipara al
        // ocasional; ver el test de abajo.
        TipoNombramiento::LIBRE_NOMBRAMIENTO,
    ];

    foreach (TipoNombramiento::cases() as $nombramiento) {
        expect(TipoMovimientoPersonal::PRESTACION_SERVICIOS->elegiblePara($nombramiento))
            ->toBe(in_array($nombramiento, $elegibles, true), $nombramiento->value);
    }
});

// ── Libre Nombramiento y Remoción (TH, 2026-09-29) ──────────────

/*
| Las reglas del 2026-09-28 dejaban a Libre Nombramiento y Remoción sin NINGUNA
| acción de personal disponible: no aparecía en ninguna de las listas. Podía ser
| correcto por la naturaleza de la figura, así que se preguntó, y TH respondió
| que «es similar a un nombramiento ocasional, es decir tener las acciones que
| tiene este último».
|
| El test no enumera: compara contra el ocasional. Así, el día que cambie una
| regla del ocasional, esto falla si alguien no movió la de al lado — que es
| justo la forma en que las dos listas se separarían sin que nadie lo note.
*/
test('libre nombramiento tiene exactamente las mismas acciones que un ocasional', function () {
    foreach (TipoMovimientoPersonal::cases() as $tipo) {
        expect($tipo->elegiblePara(TipoNombramiento::LIBRE_NOMBRAMIENTO))
            ->toBe($tipo->elegiblePara(TipoNombramiento::SERVICIOS_OCASIONALES), $tipo->value);
    }

    foreach (SubtipoMovimientoPersonal::cases() as $subtipo) {
        expect($subtipo->elegiblePara(TipoNombramiento::LIBRE_NOMBRAMIENTO))
            ->toBe($subtipo->elegiblePara(TipoNombramiento::SERVICIOS_OCASIONALES), $subtipo->value);
    }
});

/*
| Y la comprobación de fondo: que ningún nombramiento se quede sin nada que
| registrar. Es la pregunta que destapó lo de Libre Nombramiento, y sin un test
| la siguiente regla que TH ajuste puede volver a dejar a alguien fuera en
| silencio — las listas son seis y se editan de una en una.
|
| Elección Popular queda fuera del barrido a propósito: solo tiene licencia sin
| remuneración, y eso es deliberado.
*/
test('ningún tipo de nombramiento se queda sin acciones de personal', function () {
    foreach (TipoNombramiento::cases() as $nombramiento) {
        $disponibles = array_filter(
            TipoMovimientoPersonal::cases(),
            fn (TipoMovimientoPersonal $tipo) => $tipo->elegiblePara($nombramiento),
        );

        expect($disponibles)->not->toBeEmpty(
            "El nombramiento '{$nombramiento->value}' no tiene ninguna acción de personal disponible."
        );
    }
});

/*
| El traslado es el intercambio de personal ENTRE INSTITUCIONES y no cierra ni
| crea vínculo, así que no reubica a nadie dentro del GAD. Se había implementado
| como movimiento interno, idéntico al traspaso: los dos subtipos hacían lo mismo
| y solo cambiaba la palabra impresa en el documento.
*/
test('el traslado no reubica y el traspaso sí', function () {
    expect(SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO->modificaPuesto())->toBeFalse()
        ->and(SubtipoMovimientoPersonal::TRASPASO->modificaPuesto())->toBeTrue();

    // Y ninguno de los otros dos del tipo paraguas: son ausencias temporales.
    expect(SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->modificaPuesto())->toBeFalse()
        ->and(SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->modificaPuesto())->toBeFalse();
});

test('un traslado se registra sin puesto de destino', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO->value,
        'descripcion'        => 'Traslado al Ministerio de Salud Pública.',
        'fecha_efectiva'     => '2026-10-01',
        // Sin puesto_destino_id: no hay puesto del GAD que ocupar.
    ]);

    $registrado = $this->stateService->transicionar(
        $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA),
        EstadoAccionPersonal::REGISTRADA,
    );

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA);

    // El vínculo no se movió: ni de puesto ni de unidad, y sigue vigente.
    $vigente = $servidor->fresh()->contratoVigente;

    expect($vigente)->not->toBeNull()
        ->and($vigente->puesto_id)->toBe($this->puesto->id);
});
