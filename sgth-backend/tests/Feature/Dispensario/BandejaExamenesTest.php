<?php

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * La bandeja de exámenes (sección 5.4 de docs/diseno-examenes-medicos.md): las
 * órdenes emitidas o parciales, las más antiguas primero; las vencidas (90
 * días, D5) aparte; búsqueda por folio, cédula o nombre.
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

    $this->medico = $usuario('bandeja-medico', 'medico');
    $this->enfermera = $usuario('bandeja-enfermera', 'enfermera');
    $this->ajeno = $usuario('bandeja-uath', 'asistente-uath');

    $unidad = unidadDePrueba(['nombre' => 'Direccion de la Bandeja de Examenes']);
    $puesto = puestoDePrueba($unidad, 'Analista de la Bandeja de Examenes');
    $this->consultaDe = function (string $cedula, string $nombre, string $apellido) use ($unidad, $puesto) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => $nombre, 'apellido' => $apellido,
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
        ]);
        $historia = HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);

        return ConsultaMedica::create([
            'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
            'medico_id' => $this->medico->id, 'fecha_consulta' => now()->subDays(120),
            'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
        ]);
    };
});

/** Una orden de Glucosa pedida hace `$dias` días. */
function ordenDeBandeja(ConsultaMedica $consulta, int $dias): OrdenExamen
{
    return app(OrdenExamenServiceInterface::class)->emitir(
        [
            'historia_clinica_id' => $consulta->historia_clinica_id,
            'consulta_medica_id'  => $consulta->id,
            'fecha_solicitud'     => now()->subDays($dias)->toDateString(),
        ],
        [['catalogo_examen_id' => CatalogoExamen::where('nombre', 'Glucosa')->value('id')]],
        $consulta->medico_id,
    );
}

function bandeja(string $query = ''): Illuminate\Testing\TestResponse
{
    return test()->getJson('/api/v1/dispensario/examenes/bandeja' . ($query !== '' ? "?{$query}" : ''));
}

test('pendientes_las_abiertas_mas_antiguas_primero_y_las_vencidas_aparte', function () {
    $consulta = ($this->consultaDe)('0812470001', 'Rosa', 'Mena');
    $reciente = ordenDeBandeja($consulta, 2);
    $antigua = ordenDeBandeja($consulta, 60);
    $limite = ordenDeBandeja($consulta, 90);
    $vencida = ordenDeBandeja($consulta, 91);
    $completa = ordenDeBandeja($consulta, 10);
    $completa->update(['estado' => 'completa']);
    $anulada = ordenDeBandeja($consulta, 10);
    $anulada->update(['estado' => 'anulada']);
    $parcial = ordenDeBandeja($consulta, 30);
    $parcial->update(['estado' => 'parcial']);

    $this->actingAs($this->medico, 'sanctum');

    $pendientes = bandeja()->assertOk();
    expect(collect($pendientes->json('datos.data'))->pluck('folio')->all())
        ->toBe([$limite->folio, $antigua->folio, $parcial->folio, $reciente->folio])
        ->and($pendientes->json('meta.resumen'))->toBe(['pendientes' => 4, 'vencidas' => 1, 'por_revisar' => 0])
        // Con lo que la fila necesita: el paciente, quién la pidió y los exámenes.
        ->and($pendientes->json('datos.data.0.historia_clinica.servidor.cedula'))->toBe('0812470001')
        ->and($pendientes->json('datos.data.0.items.0.examen.nombre'))->toBe('Glucosa')
        ->and($pendientes->json('datos.data.0.solicitante.id'))->toBe($this->medico->id);

    $vencidas = bandeja('vista=vencidas')->assertOk();
    expect(collect($vencidas->json('datos.data'))->pluck('folio')->all())->toBe([$vencida->folio])
        ->and($vencidas->json('datos.data.0.vencida'))->toBeTrue();
});

test('busca_por_folio_cedula_o_nombre_del_paciente', function () {
    $rosa = ordenDeBandeja(($this->consultaDe)('0812470011', 'Rosa', 'Mena'), 5);
    $hugo = ordenDeBandeja(($this->consultaDe)('0812470012', 'Hugo', 'Arteaga'), 5);

    $this->actingAs($this->medico, 'sanctum');
    $folios = fn (string $q) => collect(bandeja('buscar=' . urlencode($q))->assertOk()->json('datos.data'))
        ->pluck('folio')->all();

    expect($folios($hugo->folio))->toBe([$hugo->folio])
        ->and($folios('0812470011'))->toBe([$rosa->folio])
        ->and($folios('arteaga'))->toBe([$hugo->folio])
        ->and($folios('Mena Rosa'))->toBe([$rosa->folio])
        // Un comodín escrito se busca tal cual, no como «cualquier cosa».
        ->and($folios('%'))->toBe([]);

    expect(bandeja('buscar=arteaga')->json('meta.resumen'))->toBe(['pendientes' => 1, 'vencidas' => 0, 'por_revisar' => 0]);
});

test('la_ve_quien_pide_examenes_y_nadie_mas', function () {
    ordenDeBandeja(($this->consultaDe)('0812470021', 'Ana', 'Lopez'), 1);

    // Enfermería dejó de verla el 2026-10-24.
    $this->actingAs($this->enfermera, 'sanctum');
    bandeja()->assertForbidden();

    $this->actingAs($this->ajeno, 'sanctum');
    bandeja()->assertForbidden();

    $this->actingAs($this->medico, 'sanctum');
    bandeja('vista=todas')->assertStatus(422);
    expect(bandeja('per_page=500')->assertOk()->json('datos.per_page'))->toBe(100);
});
