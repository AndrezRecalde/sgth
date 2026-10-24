<?php

/*
| `sgth:historias:fusionar` (hallazgo del 2026-10-23): junta las historias
| clínicas de un mismo paciente. Se queda la que tiene su cédula y todo lo
| demás se muda a ella; las otras se retiran en blando con la constancia de a
| cuál se fusionaron. Con dos odontogramas no se junta solo.
*/

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\FusionHistoriasService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'fusion@example.com', 'usuario_ti' => 'fusion',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $unidad = unidadDePrueba(['nombre' => 'Direccion de Fusion']);
    $this->servidor = Servidor::create([
        'cedula' => '0812510001', 'nombre' => 'Rosa', 'apellido' => 'Fusion',
        'puesto_id' => puestoDePrueba($unidad, 'Analista de Fusion')->id,
        'unidad_administrativa_id' => $unidad->id, 'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);

    // Como en desarrollo: la vieja sin cédula y con un triaje, una alergia y el
    // odontograma; la nueva con la cédula y una consulta.
    $this->vieja = HistoriaClinica::create([
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'estado' => true, 'grupo_sanguineo' => 'O+',
    ]);
    $this->nueva = HistoriaClinica::create([
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'estado' => true,
        'cedula_paciente' => '0812510001', 'numero_historia' => '0812510001',
    ]);

    $turno = AgendaMedica::create([
        'folio' => 'TUR-2026-77101', 'medico_id' => $this->medico->id, 'servidor_id' => $this->servidor->id,
        'fecha' => today()->toDateString(), 'estado' => 'atendido', 'tipo_atencion' => 'medicina_general',
        'registrado_en' => now(),
    ]);
    DB::table('triajes')->insert(['agenda_medica_id' => $turno->id, 'historia_clinica_id' => $this->vieja->id, 'enfermera_id' => $this->medico->id]);
    DB::table('alergias_paciente')->insert([
        'historia_clinica_id' => $this->vieja->id, 'tipo' => 'medicamento', 'descripcion' => 'Penicilina', 'severidad' => 'grave',
    ]);
    DB::table('odontogramas')->insert(['historia_clinica_id' => $this->vieja->id]);
    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->nueva->id, 'medico_id' => $this->medico->id,
        'fecha_consulta' => today(), 'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
});

test('simular dice qué se juntaría y no guarda nada', function () {
    $this->artisan('sgth:historias:fusionar', ['--simular' => true])
        ->expectsTable(
            ['Paciente', 'Cédula', 'Historias', 'Se queda', 'Se muda', 'Observación'],
            [['Rosa Fusion', '0812510001', "{$this->vieja->id}, {$this->nueva->id}", $this->nueva->id, '1 alergias, 1 odontograma, 1 triajes', '']],
        )
        ->expectsOutput('Simulación: no se guardó nada.')
        ->assertSuccessful();

    expect(HistoriaClinica::where('servidor_id', $this->servidor->id)->count())->toBe(2);
});

test('sin decir quién la autorizó no fusiona', function () {
    $this->artisan('sgth:historias:fusionar')->assertFailed();

    expect(HistoriaClinica::count())->toBe(2);
});

test('fusiona en la que tiene la cédula, muda todo y deja constancia', function () {
    $this->artisan('sgth:historias:fusionar', ['--responsable' => 'Jefatura del Dispensario'])
        ->expectsOutput('Fusionados: 1.')
        ->assertSuccessful();

    $vieja = HistoriaClinica::withTrashed()->findOrFail($this->vieja->id);
    $nueva = $this->nueva->fresh();

    expect($vieja->trashed())->toBeTrue()
        ->and($vieja->fusionada_con_id)->toBe($nueva->id)
        ->and($vieja->fusionada_en)->not->toBeNull()
        ->and(DB::table('triajes')->where('historia_clinica_id', $nueva->id)->count())->toBe(1)
        ->and(DB::table('alergias_paciente')->where('historia_clinica_id', $nueva->id)->value('descripcion'))->toBe('Penicilina')
        ->and(DB::table('odontogramas')->where('historia_clinica_id', $nueva->id)->count())->toBe(1)
        ->and($this->consulta->fresh()->historia_clinica_id)->toBe($nueva->id)
        // Lo que la vieja sabía y la nueva no.
        ->and($nueva->grupo_sanguineo)->toBe('O+')
        ->and(HistoriaClinica::delDueno($this->servidor->id, null)->id)->toBe($nueva->id);

    expect(DB::table('activity_log')->where('event', 'fusionada')->where('subject_id', $nueva->id)->value('properties'))
        ->toContain('Jefatura del Dispensario');

    // La segunda vez no queda nada que juntar.
    $this->artisan('sgth:historias:fusionar', ['--simular' => true])
        ->expectsOutput('Ningún paciente tiene más de una historia clínica.')
        ->assertSuccessful();
});

test('junta también la del preocupacional suelta a nombre de su cédula', function () {
    // Un servidor con su historia sin cédula y la del preocupacional sin dueño.
    $this->nueva->update(['servidor_id' => null, 'tipo_paciente' => 'candidato']);

    $grupo = app(FusionHistoriasService::class)->duplicados()->sole();
    expect($grupo['queda']->id)->toBe($this->nueva->id);

    app(FusionHistoriasService::class)->fusionar($grupo, 'Jefatura del Dispensario');

    expect($this->nueva->fresh()->servidor_id)->toBe($this->servidor->id)
        ->and($this->nueva->fresh()->tipo_paciente)->toBe('servidor')
        ->and(HistoriaClinica::where('servidor_id', $this->servidor->id)->count())->toBe(1);
});

test('con dos odontogramas no se junta solo', function () {
    DB::table('odontogramas')->insert(['historia_clinica_id' => $this->nueva->id]);

    $this->artisan('sgth:historias:fusionar', ['--responsable' => 'Jefatura del Dispensario'])
        ->expectsOutputToContain('Sin fusionar: 1.')
        ->assertSuccessful();

    expect(HistoriaClinica::count())->toBe(2);
});

test('no se queda atrás ninguna tabla que cuelgue de la historia', function () {
    $conHistoria = collect(DB::select(
        "select table_name from information_schema.columns
          where table_schema = 'public' and column_name = 'historia_clinica_id' order by table_name"
    ))->pluck('table_name')->all();

    expect($conHistoria)->toEqualCanonicalizing(FusionHistoriasService::DEPENDIENTES);
});
