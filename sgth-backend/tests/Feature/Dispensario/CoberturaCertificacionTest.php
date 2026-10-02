<?php

use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\CoberturaCertificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El tablero de cobertura: una fila por servidor activo, no por solicitud.
|
| Lo que ninguna pantalla del módulo podía responder es a quién le toca la
| evaluación y no la tiene, porque las tres listan solicitudes y una solicitud
| solo existe cuando alguien se acordó de pedirla.
*/

const RUTA_COBERTURA = '/api/v1/dispensario/certificaciones/cobertura';

function usuarioRrhhCobertura(): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole(
        Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum'])
    );

    return $usuario;
}

function unidadCobertura(string $codigo, string $nombre): UnidadAdministrativa
{
    return UnidadAdministrativa::create([
        'codigo' => $codigo, 'nombre' => $nombre, 'nivel' => 1,
    ]);
}

function servidorCobertura(
    string $cedula,
    ?UnidadAdministrativa $unidad = null,
    bool $activo = true,
): Servidor {
    return Servidor::create([
        'cedula' => $cedula,
        'nombre' => 'Servidor',
        'apellido' => "Numero{$cedula}",
        'regimen_laboral' => 'losep',
        'unidad_administrativa_id' => $unidad?->id,
        'estado' => $activo,
    ]);
}

/**
 * Una evaluación completada hace `$haceAnios` años, con su ficha firmada.
 *
 * La fecha de la ficha es la que manda para el vencimiento; se fija aparte de
 * `created_at` a propósito, porque es la distinción que la consulta resuelve
 * con un `coalesce`.
 */
function evaluacionCompletada(
    Servidor $servidor,
    User $solicitante,
    float $haceAnios,
    string $dictamen = 'apto',
    ?string $restricciones = null,
): SolicitudCertificacionMedica {
    $fecha = now()->subDays((int) round($haceAnios * 365));

    $ficha = FichaSaludOcupacional::create([
        'servidor_id' => $servidor->id,
        'fecha_evaluacion' => $fecha->toDateString(),
        'tipo_ficha' => 'periodica',
        'aptitud' => $dictamen,
        'restricciones' => $restricciones,
        'evaluador_id' => $solicitante->id,
        'estado' => true,
    ]);

    return SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica',
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => trim("{$servidor->nombre} {$servidor->apellido}"),
        'solicitado_por' => $solicitante->id,
        'estado' => 'completada',
        'dictamen' => $dictamen,
        'ficha_femo_id' => $ficha->id,
        'created_at' => $fecha,
    ]);
}

/** @return array<string, array<string, mixed>> indexado por cédula */
function filasPorCedula(array $datos): array
{
    return collect($datos)->keyBy('cedula')->map(fn ($f) => (array) $f)->all();
}

test('un servidor sin ninguna solicitud aparece como «sin evaluación»', function () {
    $usuario = usuarioRrhhCobertura();
    servidorCobertura('0900000001');

    $respuesta = $this->actingAs($usuario, 'sanctum')->getJson(RUTA_COBERTURA);

    $respuesta->assertStatus(200);

    $filas = filasPorCedula($respuesta->json('datos.data'));

    expect($filas['0900000001']['estado_cobertura'])->toBe('sin_evaluacion')
        ->and($filas['0900000001']['vence_el'])->toBeNull()
        ->and($filas['0900000001']['fecha_evaluacion'])->toBeNull();

    expect($respuesta->json('datos.resumen.sin_evaluacion'))->toBe(1);
});

test('los cuatro estados de cobertura salen del plazo de la UATH', function () {
    $usuario = usuarioRrhhCobertura();

    $alDia = servidorCobertura('0900000010');
    $porVencer = servidorCobertura('0900000011');
    $vencida = servidorCobertura('0900000012');
    servidorCobertura('0900000013');

    // Dentro del plazo y lejos del aviso de 90 días.
    evaluacionCompletada($alDia, $usuario, haceAnios: 0.5);
    // A un mes de cumplir los dos años: entra en el aviso.
    evaluacionCompletada($porVencer, $usuario, haceAnios: 1.95);
    // Pasados los dos años.
    evaluacionCompletada($vencida, $usuario, haceAnios: 2.5);

    $respuesta = $this->actingAs($usuario, 'sanctum')->getJson(RUTA_COBERTURA);
    $filas = filasPorCedula($respuesta->json('datos.data'));

    expect($filas['0900000010']['estado_cobertura'])->toBe('al_dia')
        ->and($filas['0900000011']['estado_cobertura'])->toBe('por_vencer')
        ->and($filas['0900000012']['estado_cobertura'])->toBe('vencida')
        ->and($filas['0900000013']['estado_cobertura'])->toBe('sin_evaluacion');

    expect($respuesta->json('datos.resumen'))
        ->toMatchArray([
            'total' => 4,
            'al_dia' => 1,
            'por_vencer' => 1,
            'vencida' => 1,
            'sin_evaluacion' => 1,
        ]);
});

test('el vencimiento se cuenta desde la fecha del acto médico, no desde la solicitud', function () {
    $usuario = usuarioRrhhCobertura();
    $servidor = servidorCobertura('0900000020');

    $solicitud = evaluacionCompletada($servidor, $usuario, haceAnios: 0.25);

    $respuesta = $this->actingAs($usuario, 'sanctum')->getJson(RUTA_COBERTURA);
    $fila = filasPorCedula($respuesta->json('datos.data'))['0900000020'];

    $fechaFicha = $solicitud->fichaSaludOcupacional->fecha_evaluacion;
    $esperado = \Carbon\Carbon::parse($fechaFicha)
        ->addYears(CoberturaCertificacionService::ANIOS_ENTRE_EVALUACIONES)
        ->toDateString();

    expect($fila['vence_el'])->toStartWith($esperado);
});

test('manda la evaluación más reciente, no la primera que se encuentre', function () {
    $usuario = usuarioRrhhCobertura();
    $servidor = servidorCobertura('0900000030');

    // Vencida hace tiempo y, después, una al día: la fila debe estar al día.
    evaluacionCompletada($servidor, $usuario, haceAnios: 4, dictamen: 'no_apto');
    evaluacionCompletada(
        $servidor, $usuario, haceAnios: 0.2,
        dictamen: 'apto_con_restricciones', restricciones: 'Sin levantar peso.'
    );

    $respuesta = $this->actingAs($usuario, 'sanctum')->getJson(RUTA_COBERTURA);
    $filas = filasPorCedula($respuesta->json('datos.data'));

    expect($filas)->toHaveCount(1);
    expect($filas['0900000030']['estado_cobertura'])->toBe('al_dia')
        ->and($filas['0900000030']['ultimo_dictamen'])->toBe('apto_con_restricciones')
        ->and($filas['0900000030']['restricciones'])->toBe('Sin levantar peso.');
});

test('una solicitud en curso se ve en la fila, para no volver a pedirla', function () {
    $usuario = usuarioRrhhCobertura();
    $servidor = servidorCobertura('0900000040');

    SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica',
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => 'Servidor Numero0900000040',
        'solicitado_por' => $usuario->id,
        'estado' => 'pendiente',
        'fecha_limite' => now()->addDays(7),
    ]);

    $respuesta = $this->actingAs($usuario, 'sanctum')->getJson(RUTA_COBERTURA);
    $fila = filasPorCedula($respuesta->json('datos.data'))['0900000040'];

    // Sigue sin evaluación —la pendiente no es una evaluación hecha— pero la
    // fila avisa de que ya está pedida.
    expect($fila['estado_cobertura'])->toBe('sin_evaluacion')
        ->and($fila['solicitud_activa_estado'])->toBe('pendiente');
});

test('una solicitud cancelada no cuenta como solicitud en curso', function () {
    $usuario = usuarioRrhhCobertura();
    $servidor = servidorCobertura('0900000045');

    SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica',
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => 'Servidor Numero0900000045',
        'solicitado_por' => $usuario->id,
        'estado' => 'cancelada',
        'cancelada_en' => now(),
        'cancelada_por' => $usuario->id,
        'motivo_cancelacion' => 'Lote mal lanzado.',
    ]);

    $respuesta = $this->actingAs($usuario, 'sanctum')->getJson(RUTA_COBERTURA);
    $fila = filasPorCedula($respuesta->json('datos.data'))['0900000045'];

    expect($fila['solicitud_activa_id'])->toBeNull()
        ->and($fila['estado_cobertura'])->toBe('sin_evaluacion');
});

test('un servidor inactivo no entra en la cobertura', function () {
    $usuario = usuarioRrhhCobertura();
    servidorCobertura('0900000050');
    servidorCobertura('0900000051', activo: false);

    $respuesta = $this->actingAs($usuario, 'sanctum')->getJson(RUTA_COBERTURA);
    $filas = filasPorCedula($respuesta->json('datos.data'));

    expect($filas)->toHaveKey('0900000050')
        ->and($filas)->not->toHaveKey('0900000051');
    expect($respuesta->json('datos.resumen.total'))->toBe(1);
});

test('el filtro por unidad acota las filas y el resumen', function () {
    $usuario = usuarioRrhhCobertura();
    $obras = unidadCobertura('OBR-01', 'Dirección de Obras Públicas');
    $uath = unidadCobertura('UATH-01', 'Unidad de Talento Humano');

    servidorCobertura('0900000060', $obras);
    servidorCobertura('0900000061', $obras);
    servidorCobertura('0900000062', $uath);

    $respuesta = $this->actingAs($usuario, 'sanctum')
        ->getJson(RUTA_COBERTURA.'?unidad_administrativa_id='.$obras->id);

    $filas = filasPorCedula($respuesta->json('datos.data'));

    expect($filas)->toHaveCount(2)
        ->and($filas)->not->toHaveKey('0900000062');
    expect($respuesta->json('datos.resumen.total'))->toBe(2);
    expect($filas['0900000060']['unidad'])->toBe('Dirección de Obras Públicas');
});

test('el filtro por estado acota las filas pero deja el semáforo completo', function () {
    $usuario = usuarioRrhhCobertura();

    $alDia = servidorCobertura('0900000070');
    $vencida = servidorCobertura('0900000071');
    servidorCobertura('0900000072');

    evaluacionCompletada($alDia, $usuario, haceAnios: 0.5);
    evaluacionCompletada($vencida, $usuario, haceAnios: 3);

    $respuesta = $this->actingAs($usuario, 'sanctum')
        ->getJson(RUTA_COBERTURA.'?estado_cobertura=vencida');

    $filas = filasPorCedula($respuesta->json('datos.data'));

    expect($filas)->toHaveCount(1)
        ->and($filas)->toHaveKey('0900000071');

    // El semáforo sigue contando los tres: si se recalculara sobre lo
    // filtrado, pulsar «Vencidas» pondría las otras cifras a cero.
    expect($respuesta->json('datos.resumen'))
        ->toMatchArray(['total' => 3, 'al_dia' => 1, 'vencida' => 1, 'sin_evaluacion' => 1]);
});

test('el buscador encuentra por cédula y por nombre', function () {
    $usuario = usuarioRrhhCobertura();
    servidorCobertura('0900000080');
    servidorCobertura('0900000081');

    $porCedula = $this->actingAs($usuario, 'sanctum')
        ->getJson(RUTA_COBERTURA.'?buscar=0900000081');
    expect(filasPorCedula($porCedula->json('datos.data')))->toHaveCount(1);

    $porNombre = $this->actingAs($usuario, 'sanctum')
        ->getJson(RUTA_COBERTURA.'?buscar=numero0900000080');
    expect(filasPorCedula($porNombre->json('datos.data')))->toHaveCount(1);
});

test('las filas que hay que perseguir salen primero', function () {
    $usuario = usuarioRrhhCobertura();

    // Creados en el orden contrario al que deben salir.
    $alDia = servidorCobertura('0900000090');
    evaluacionCompletada($alDia, $usuario, haceAnios: 0.1);

    $porVencer = servidorCobertura('0900000091');
    evaluacionCompletada($porVencer, $usuario, haceAnios: 1.95);

    servidorCobertura('0900000092');

    $vencida = servidorCobertura('0900000093');
    evaluacionCompletada($vencida, $usuario, haceAnios: 3);

    $respuesta = $this->actingAs($usuario, 'sanctum')->getJson(RUTA_COBERTURA);

    expect(collect($respuesta->json('datos.data'))->pluck('estado_cobertura')->all())
        ->toBe(['vencida', 'sin_evaluacion', 'por_vencer', 'al_dia']);
});

test('el Excel sale con una fila por servidor y su cabecera', function () {
    $usuario = usuarioRrhhCobertura();
    $servidor = servidorCobertura('0900000100', unidadCobertura('OBR-02', 'Obras'));
    evaluacionCompletada(
        $servidor, $usuario, haceAnios: 3,
        dictamen: 'apto_con_restricciones', restricciones: 'Evitar turnos nocturnos.'
    );

    $respuesta = $this->actingAs($usuario, 'sanctum')
        ->get(RUTA_COBERTURA.'/excel');

    $respuesta->assertStatus(200);
    expect($respuesta->headers->get('content-disposition'))
        ->toContain('cobertura_certificaciones_');
});

test('un rol ajeno al trámite no abre el tablero', function () {
    $usuario = User::factory()->create();
    $usuario->assignRole(
        Role::firstOrCreate(['name' => 'jefe-unidad', 'guard_name' => 'sanctum'])
    );

    $this->actingAs($usuario, 'sanctum')
        ->getJson(RUTA_COBERTURA)
        ->assertStatus(403);
});
