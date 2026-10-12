<?php

/**
 * Da a los roles de Talento Humano los permisos del trámite de una acción de
 * personal, con la misma migración que los reparte en producción.
 *
 * RefreshDatabase corre las migraciones antes de que la prueba cree sus roles,
 * así que la migración encuentra los permisos pero ningún rol al que dárselos.
 * Las pruebas que crean `admin-uath` o `asistente-uath` y usan las rutas de
 * acciones de personal la vuelven a correr después de crearlos.
 */
function permisosDeAccionesPersonal(): void
{
    (require database_path('migrations/2026_10_09_120000_crear_permisos_acciones_personal.php'))->up();
}

/**
 * Una misma escala para los puestos de un traslado de prueba. El traslado
 * exige igual remuneración (LOSEP Art. 35; fase 3.1), y sin grupo ocupacional
 * no hay cómo comprobarla. Es para las pruebas que usan un traslado como
 * vehículo de otra cosa —anular, la bitácora, la actividad laboral—; el
 * traslado en sí lo prueba TrasladoTest.
 *
 * El vínculo que se traslada tiene que cobrar lo mismo, o no tener
 * remuneración guardada: entonces cuenta la de su puesto.
 */
function escalaComunParaTraslados(float $rmu, \App\Models\Estructura\Puesto ...$puestos): void
{
    $grupo = \App\Models\Estructura\GrupoOcupacional::firstOrCreate(
        ['grado_codigo' => 'SP-TRASLADO-'.$rmu],
        [
            'grado_numerico'        => 1,
            'grupo'                 => 'SP-TRASLADO',
            'denominacion_generica' => 'Servidor público',
            'rmu'                   => $rmu,
            'regimen'               => 'losep',
            'activo'                => true,
        ],
    );

    foreach ($puestos as $puesto) {
        $puesto->update(['grupo_ocupacional_id' => $grupo->id]);
    }
}

/**
 * Un grupo ocupacional del nivel jerárquico superior. Desde la fase 5.2 la
 * subrogación solo procede sobre esos puestos (LOSEP, Art. 126), y las pruebas
 * que la usan como vehículo de otra cosa le dan uno a su puesto.
 */
function grupoNjsDePrueba(float $rmu = 3000.00): \App\Models\Estructura\GrupoOcupacional
{
    return \App\Models\Estructura\GrupoOcupacional::firstOrCreate(
        ['grado_codigo' => 'NJS-PRUEBA'],
        [
            'grado_numerico'        => 5,
            'grupo'                 => 'Nivel Jerárquico Superior',
            'denominacion_generica' => 'Directivo',
            'rmu'                   => $rmu,
            'regimen'               => 'losep',
            'activo'                => true,
        ]
    );
}

/**
 * La ausencia del titular que motiva una subrogación de prueba: una que no
 * está en el SGTH, respaldada con su resolución (fase 5.2).
 *
 * @return array{ausencia_tipo: string, resolucion_numero: string}
 */
function ausenciaOtraDePrueba(): array
{
    return ['ausencia_tipo' => 'otra', 'resolucion_numero' => 'RES-PRUEBA-001'];
}
