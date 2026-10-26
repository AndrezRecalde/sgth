<?php

/*
| El reporte «Exámenes pedidos» (6.5 de docs/diseno-examenes-medicos.md):
| órdenes y exámenes del período, los que volvieron, los que esperan, los que
| se vencieron y la mediana de días de retorno. Por mes, por área o por quien
| pidió la orden. La administración lo ve entero; cada médico, lo suyo.
*/

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

function usuarioExamenesPedidos(string $alias, string $rol, ?string $nombre = null): User
{
    $servidor = null;
    if ($nombre) {
        $unidad = unidadDePrueba(['nombre' => "Unidad {$alias}"]);
        $servidor = Servidor::create([
            'cedula' => '08' . str_pad((string) crc32($alias) % 100000000, 8, '0', STR_PAD_LEFT),
            'nombre' => $nombre, 'apellido' => 'Pedidos',
            'puesto_id' => puestoDePrueba($unidad, "Puesto {$alias}")->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
        ]);
    }
    $user = User::create([
        'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $servidor?->id,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $user;
}

/** Una orden de hace `$dias` días, con exámenes del catálogo por nombre. */
function ordenExamenesPedidos(HistoriaClinica $historia, User $medico, int $dias, array $nombres): OrdenExamen
{
    return app(OrdenExamenServiceInterface::class)->emitir(
        ['historia_clinica_id' => $historia->id, 'fecha_solicitud' => today()->subDays($dias)->toDateString()],
        array_map(fn ($n) => ['catalogo_examen_id' => CatalogoExamen::where('nombre', $n)->value('id')], $nombres),
        $medico->id,
    )->load('items.examen');
}

/** El resultado de esos exámenes, llegado al Dispensario `$dias` días después de la solicitud. */
function resultadoExamenesPedidos(OrdenExamen $orden, array $nombres, int $dias): void
{
    $resultado = ResultadoMedico::create([
        'historia_clinica_id' => $orden->historia_clinica_id, 'orden_examen_id' => $orden->id,
        'subido_por' => $orden->solicitado_por, 'tipo' => 'laboratorio', 'descripcion' => implode(', ', $nombres),
        'archivo' => 'resultados-medicos/prueba.pdf', 'fecha_resultado' => $orden->fecha_solicitud,
        'estado_revision' => 'por_revisar',
    ]);
    app(OrdenExamenServiceInterface::class)->adjuntarResultado(
        $resultado,
        $orden->items->filter(fn ($i) => in_array($i->examen->nombre, $nombres, true))->pluck('id')->all(),
    );
    $resultado->forceFill(['created_at' => $orden->fecha_solicitud->copy()->addDays($dias)->setTime(10, 0)])->saveQuietly();
}

beforeEach(function () {
    Model::unguard();

    $this->admin = usuarioExamenesPedidos('admin-pedidos', 'admin-dispensario');
    $this->enfermera = usuarioExamenesPedidos('enf-pedidos', 'enfermera');
    $this->ana = usuarioExamenesPedidos('ana-pedidos', 'medico', 'Ana');
    $this->bruno = usuarioExamenesPedidos('bruno-pedidos', 'medico', 'Bruno');

    $paciente = usuarioExamenesPedidos('paciente-pedidos', 'servidor', 'Paciente');
    $historia = HistoriaClinica::create([
        'servidor_id' => $paciente->servidor_id, 'tipo_paciente' => 'servidor',
        'cedula_paciente' => $paciente->servidor->cedula, 'numero_historia' => $paciente->servidor->cedula,
    ]);
    $ordenes = app(OrdenExamenServiceInterface::class);

    // Ana: una reciente con dos que volvieron a los 4 días y uno pendiente…
    $reciente = ordenExamenesPedidos($historia, $this->ana, 10, ['Glucosa', 'Colesterol', 'Hemograma completo']);
    resultadoExamenesPedidos($reciente, ['Glucosa', 'Colesterol'], 4);
    // …y una de hace 100 días: la urea nunca volvió (vencida) y el hemograma no se hizo.
    $vieja = ordenExamenesPedidos($historia, $this->ana, 100, ['Urea', 'Hemograma completo']);
    $ordenes->marcarNoRealizado($vieja->id, $vieja->items->last()->id, 'El laboratorio no lo hace');

    // Bruno: una anulada y una que volvió a los 2 días.
    $anulada = ordenExamenesPedidos($historia, $this->bruno, 10, ['Glucosa']);
    $ordenes->anular($anulada->id, 'Error en la orden', $this->bruno->id);
    $completa = ordenExamenesPedidos($historia, $this->bruno, 10, ['Hemograma completo']);
    resultadoExamenesPedidos($completa, ['Hemograma completo'], 2);

    // Fuera del período: no cuenta.
    ordenExamenesPedidos($historia, $this->ana, 200, ['Glucosa']);

    $this->filtros = '?desde=' . today()->subDays(120)->toDateString() . '&hasta=' . today()->toDateString();
});

/** El mes como lo escribe el reporte: «Octubre 2026». */
function mesExamenesPedidos(Carbon\CarbonInterface $fecha): string
{
    return ucfirst($fecha->locale('es')->isoFormat('MMMM YYYY'));
}

function filasExamenesPedidos(User $quien, string $query): Illuminate\Support\Collection
{
    return collect(test()->actingAs($quien, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/examenes_pedidos' . test()->filtros . $query)
        ->assertOk()->json('datos.filas'));
}

test('por profesional cuenta órdenes, exámenes, retorno, vencidos y anuladas', function () {
    $filas = filasExamenesPedidos($this->admin, '&agrupacion=profesional')->keyBy('profesional');

    expect($filas->keys()->all())->toBe(['Ana Pedidos', 'Bruno Pedidos'])
        ->and($filas['Ana Pedidos'])->toBe([
            'profesional' => 'Ana Pedidos', 'ordenes' => 2, 'examenes' => 5, 'recibidos' => 2,
            'no_realizados' => 1, 'pendientes' => 1, 'vencidos' => 1, 'retorno_dias' => 4, 'anuladas' => 0,
        ])
        ->and($filas['Bruno Pedidos'])->toBe([
            'profesional' => 'Bruno Pedidos', 'ordenes' => 1, 'examenes' => 1, 'recibidos' => 1,
            'no_realizados' => 0, 'pendientes' => 0, 'vencidos' => 0, 'retorno_dias' => 2, 'anuladas' => 1,
        ]);
});

test('por área sigue el orden de la hoja y una orden cuenta en cada área que toca', function () {
    $filas = filasExamenesPedidos($this->admin, '&agrupacion=area');

    expect($filas->pluck('area')->all())->toBe(['Bioquímica', 'Hematología'])
        ->and($filas[0])->toMatchArray(['ordenes' => 2, 'examenes' => 3, 'recibidos' => 2, 'vencidos' => 1, 'retorno_dias' => 4, 'anuladas' => 1])
        ->and($filas[1])->toMatchArray(['ordenes' => 3, 'examenes' => 3, 'recibidos' => 1, 'no_realizados' => 1, 'pendientes' => 1, 'retorno_dias' => 2]);
});

test('por mes es la agrupación de por defecto y la mediana es la del mes', function () {
    $filas = filasExamenesPedidos($this->admin, '')->keyBy('mes');

    $viejo = mesExamenesPedidos(today()->subDays(100));
    $reciente = mesExamenesPedidos(today()->subDays(10));

    expect($filas->keys()->all())->toBe([$viejo, $reciente])
        ->and($filas[$viejo])->toMatchArray(['ordenes' => 1, 'examenes' => 2, 'vencidos' => 1, 'retorno_dias' => null])
        // 4, 4 y 2 días: la mediana es 4.
        ->and($filas[$reciente])->toMatchArray(['ordenes' => 2, 'examenes' => 4, 'recibidos' => 3, 'retorno_dias' => 4, 'anuladas' => 1]);
});

test('el médico ve solo sus órdenes aunque pida las de otro, y enfermería no lo saca', function () {
    $filas = filasExamenesPedidos($this->ana, "&agrupacion=profesional&profesional_id={$this->bruno->id}");

    expect($filas->pluck('profesional')->all())->toBe(['Ana Pedidos']);

    $this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/examenes_pedidos' . $this->filtros)
        ->assertForbidden();
});

test('sale en excel', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/dispensario/reportes/examenes_pedidos/excel' . $this->filtros)
        ->assertOk();
});
