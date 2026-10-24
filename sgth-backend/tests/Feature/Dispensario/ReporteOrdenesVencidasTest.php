<?php

/*
| El reporte «Órdenes vencidas» (6.5 de docs/diseno-examenes-medicos.md): las
| órdenes de más de 90 días que siguen esperando resultados, con el contacto
| del paciente para buscarlo. Foto de hoy y nominal: la autoridad no lo ve.
*/

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

function servidorOrdenesVencidas(string $cedula, string $nombre, string $unidad, ?string $celular = null): Servidor
{
    $u = unidadDePrueba(['nombre' => $unidad]);

    return Servidor::create([
        'cedula' => $cedula, 'nombre' => $nombre, 'apellido' => 'Vencida',
        'puesto_id' => puestoDePrueba($u, "Puesto {$cedula}")->id, 'unidad_administrativa_id' => $u->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'telefono_celular' => $celular,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
}

function usuarioOrdenesVencidas(string $alias, string $rol, ?Servidor $servidor = null): User
{
    $user = User::create([
        'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $servidor?->id,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $user;
}

/** Una orden de hace `$dias` días, con exámenes del catálogo por nombre. */
function ordenOrdenesVencidas(HistoriaClinica $historia, User $medico, int $dias, array $nombres): OrdenExamen
{
    return app(OrdenExamenServiceInterface::class)->emitir(
        ['historia_clinica_id' => $historia->id, 'fecha_solicitud' => today()->subDays($dias)->toDateString()],
        array_map(fn ($n) => ['catalogo_examen_id' => CatalogoExamen::where('nombre', $n)->value('id')], $nombres),
        $medico->id,
    )->load('items.examen');
}

beforeEach(function () {
    Model::unguard();

    $this->admin = usuarioOrdenesVencidas('admin-vencidas', 'admin-dispensario');
    $this->autoridad = usuarioOrdenesVencidas('aut-vencidas', 'maxima-autoridad');
    $this->ana = usuarioOrdenesVencidas('ana-vencidas', 'medico', servidorOrdenesVencidas('0812490001', 'Ana', 'Unidad Ana'));
    $this->bruno = usuarioOrdenesVencidas('bruno-vencidas', 'medico', servidorOrdenesVencidas('0812490002', 'Bruno', 'Unidad Bruno'));

    $titular = servidorOrdenesVencidas('0812490003', 'Rosa', 'Direccion de Obras', '0991234567');
    $deRosa = HistoriaClinica::create([
        'servidor_id' => $titular->id, 'tipo_paciente' => 'servidor',
        'cedula_paciente' => $titular->cedula, 'numero_historia' => $titular->cedula,
    ]);
    $hijo = CargaFamiliar::create([
        'servidor_id' => $titular->id, 'cedula' => '0812490004', 'nombres' => 'Tomás', 'apellidos' => 'Vencida',
        'parentesco' => 'hijo', 'fecha_nacimiento' => '2015-03-01', 'genero' => 'M', 'estado' => true,
    ]);
    $deTomas = HistoriaClinica::create([
        'carga_familiar_id' => $hijo->id, 'tipo_paciente' => 'familiar',
        'cedula_paciente' => '0812490004', 'numero_historia' => '0812490004',
    ]);
    $ordenes = app(OrdenExamenServiceInterface::class);

    // Vencida de Ana: la glucosa espera, el colesterol ya volvió.
    $this->deAna = ordenOrdenesVencidas($deRosa, $this->ana, 100, ['Glucosa', 'Colesterol']);
    $this->deAna->items->last()->update(['estado' => 'recibido']);
    $this->deAna->update(['estado' => 'parcial']);
    // Vencida de Bruno, del hijo: el teléfono es el de la titular.
    $this->deBruno = ordenOrdenesVencidas($deTomas, $this->bruno, 95, ['Hemograma completo']);

    // No están vencidas: una anulada, una reciente y una completa.
    $anulada = ordenOrdenesVencidas($deRosa, $this->ana, 110, ['Urea']);
    $ordenes->anular($anulada->id, 'Error en la orden', $this->ana->id);
    ordenOrdenesVencidas($deRosa, $this->ana, 10, ['Urea']);
    $completa = ordenOrdenesVencidas($deRosa, $this->ana, 120, ['Urea']);
    $completa->items->first()->update(['estado' => 'recibido']);
    $completa->update(['estado' => 'completa']);
});

function filasOrdenesVencidas(User $quien, string $query = ''): Collection
{
    return collect(test()->actingAs($quien, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/examenes_vencidas?desde=2026-01-01&hasta=2026-01-31' . $query)
        ->assertOk()->json('datos.filas'));
}

test('lista las vencidas de hoy, las más viejas primero, con el contacto para buscarlas', function () {
    $filas = filasOrdenesVencidas($this->admin);

    expect($filas->pluck('folio')->all())->toBe([$this->deAna->folio, $this->deBruno->folio])
        ->and($filas[0])->toBe([
            'folio' => $this->deAna->folio, 'fecha' => today()->subDays(100)->toDateString(), 'dias' => 100,
            'cedula' => '0812490003', 'paciente' => 'Rosa Vencida', 'tipo_paciente' => 'Servidor',
            'unidad' => 'Direccion de Obras', 'telefono' => '0991234567', 'pendientes' => 'Glucosa',
            'profesional' => 'Ana Vencida',
        ])
        ->and($filas[1])->toMatchArray([
            'paciente' => 'Tomás Vencida', 'tipo_paciente' => 'Familiar', 'dias' => 95,
            // El familiar se busca por la titular: su unidad y su teléfono.
            'unidad' => 'Direccion de Obras', 'telefono' => '0991234567',
            'pendientes' => 'Hemograma completo', 'profesional' => 'Bruno Vencida',
        ]);
});

test('cada médico ve las que pidió, y la autoridad no la saca', function () {
    expect(filasOrdenesVencidas($this->bruno, "&profesional_id={$this->ana->id}")->pluck('folio')->all())
        ->toBe([$this->deBruno->folio]);

    $this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/examenes_vencidas?desde=2026-01-01&hasta=2026-01-31')
        ->assertForbidden();
});

test('es una foto de hoy: el catálogo lo dice y el excel también', function () {
    $reporte = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->json('datos.reportes'))
        ->firstWhere('clave', 'examenes_vencidas');

    expect($reporte)->toMatchArray(['periodo' => false, 'corte' => 'Órdenes vencidas', 'nominal' => true]);

    $respuesta = $this->get('/api/v1/dispensario/reportes/examenes_vencidas/excel?desde=2026-01-01&hasta=2026-01-31')
        ->assertOk();
    $hoja = PhpOffice\PhpSpreadsheet\IOFactory::load($respuesta->getFile()->getPathname())->getActiveSheet();

    expect($hoja->getCell('A3')->getValue())->toStartWith('Órdenes vencidas al ' . now()->format('d/m/Y'));
});
