<?php

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * La bandeja «Por revisar» (5.4 y D3 de docs/diseno-examenes-medicos.md): los
 * resultados que nadie ha leído, los más antiguos primero. Cada médico ve los
 * suyos —los de lo que pidió o, sin orden, los de sus consultas— y la
 * administración, los de todos, filtrables por quien responde (2026-10-29).
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $usuario = function (string $alias, string $rol) {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

        return $user;
    };

    $this->medicoA = $usuario('por-revisar-a', 'medico');
    $this->medicoB = $usuario('por-revisar-b', 'medico');

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Resultados por Revisar']);
    $puesto = puestoDePrueba($unidad, 'Analista de Resultados por Revisar');
    $servidor = Servidor::create([
        'cedula' => '0812490001', 'nombre' => 'Lucia', 'apellido' => 'Quinonez',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $this->historia = HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);

    $consulta = fn (User $medico) => ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $medico->id, 'fecha_consulta' => now()->subDays(3),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
    $this->consultaA = $consulta($this->medicoA);
    $this->consultaB = $consulta($this->medicoB);
});

function ordenPorRevisar(ConsultaMedica $consulta): OrdenExamen
{
    return app(OrdenExamenServiceInterface::class)->emitir(
        ['historia_clinica_id' => $consulta->historia_clinica_id, 'consulta_medica_id' => $consulta->id,
            'fecha_solicitud' => now()->toDateString()],
        [['catalogo_examen_id' => CatalogoExamen::where('nombre', 'Glucosa')->value('id')]],
        $consulta->medico_id,
    );
}

function resultadoPorRevisar(ConsultaMedica $consulta, ?OrdenExamen $orden, string $descripcion, int $horasAtras, string $estado = 'por_revisar'): ResultadoMedico
{
    return ResultadoMedico::create([
        'historia_clinica_id' => $consulta->historia_clinica_id,
        'consulta_medica_id'  => $consulta->id,
        'orden_examen_id'     => $orden?->id,
        'subido_por'          => $consulta->medico_id,
        'tipo'                => 'laboratorio',
        'descripcion'         => $descripcion,
        'archivo'             => "resultados-medicos/{$descripcion}.pdf",
        'fecha_resultado'     => now()->toDateString(),
        'estado_revision'     => $estado,
        'created_at'          => now()->subHours($horasAtras),
    ]);
}

function descripcionesPorRevisar(string $query = ''): array
{
    return collect(test()->getJson('/api/v1/dispensario/examenes/bandeja/por-revisar' . ($query !== '' ? "?{$query}" : ''))
        ->assertOk()->json('datos.data'))->pluck('descripcion')->all();
}

test('lista_lo_que_nadie_ha_leido_los_mas_antiguos_primero', function () {
    $ordenA = ordenPorRevisar($this->consultaA);
    resultadoPorRevisar($this->consultaA, $ordenA, 'reciente', 1);
    resultadoPorRevisar($this->consultaA, $ordenA, 'antiguo', 30);
    resultadoPorRevisar($this->consultaA, $ordenA, 'ya-leido', 50, 'revisado');

    $this->actingAs($this->medicoA, 'sanctum');

    expect(descripcionesPorRevisar())->toBe(['antiguo', 'reciente']);

    $fila = $this->getJson('/api/v1/dispensario/examenes/bandeja/por-revisar')->json('datos.data.0');
    expect($fila['historia_clinica']['servidor']['cedula'])->toBe('0812490001')
        ->and($fila['orden_examen']['solicitante']['id'])->toBe($this->medicoA->id);

    // La bandeja de órdenes cuenta los por revisar para su pestaña.
    expect($this->getJson('/api/v1/dispensario/examenes/bandeja')->json('meta.resumen.por_revisar'))->toBe(2);
});

test('por_medico_salen_los_de_sus_ordenes_y_sin_orden_los_de_sus_consultas', function () {
    resultadoPorRevisar($this->consultaA, ordenPorRevisar($this->consultaA), 'orden-de-a', 5);
    resultadoPorRevisar($this->consultaA, null, 'consulta-de-a', 4);
    resultadoPorRevisar($this->consultaB, ordenPorRevisar($this->consultaB), 'orden-de-b', 3);
    // Subido en la consulta de B pero de una orden de A: responde A.
    resultadoPorRevisar($this->consultaB, ordenPorRevisar($this->consultaA), 'orden-de-a-en-consulta-de-b', 2);

    // El filtro es de la administración, que ve los de todos (2026-10-29).
    $administracion = User::create([
        'email' => 'por-revisar-admin@example.com', 'usuario_ti' => 'por-revisar-admin',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $administracion->assignRole('admin-dispensario');
    $this->actingAs($administracion, 'sanctum');

    expect(descripcionesPorRevisar("solicitante={$this->medicoA->id}"))
        ->toBe(['orden-de-a', 'consulta-de-a', 'orden-de-a-en-consulta-de-b'])
        ->and(descripcionesPorRevisar("solicitante={$this->medicoB->id}"))->toBe(['orden-de-b']);

    // Un médico ve solo lo suyo, aunque pida lo de un colega.
    $this->actingAs($this->medicoB, 'sanctum');

    expect(descripcionesPorRevisar())->toBe(['orden-de-b'])
        ->and(descripcionesPorRevisar("solicitante={$this->medicoA->id}"))->toBe([]);
});

test('busca_por_folio_cedula_o_nombre', function () {
    $orden = ordenPorRevisar($this->consultaA);
    resultadoPorRevisar($this->consultaA, $orden, 'con-orden', 2);
    resultadoPorRevisar($this->consultaA, null, 'sin-orden', 1);

    $this->actingAs($this->medicoA, 'sanctum');

    expect(descripcionesPorRevisar('buscar=' . urlencode($orden->folio)))->toBe(['con-orden'])
        ->and(descripcionesPorRevisar('buscar=0812490001'))->toBe(['con-orden', 'sin-orden'])
        ->and(descripcionesPorRevisar('buscar=quinonez%20lucia'))->toBe(['con-orden', 'sin-orden'])
        ->and(descripcionesPorRevisar('buscar=nadie'))->toBe([]);
});
