<?php

/*
| Una historia clínica viva por servidor y por familiar (hallazgo del
| 2026-10-23): la base rechaza la segunda. Una fusionada, retirada en blando,
| no cuenta. Y la migración no sigue si quedan duplicados sin juntar.
*/

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Una Historia']);
    $this->servidor = Servidor::create([
        'cedula' => '0812520001', 'nombre' => 'Rosa', 'apellido' => 'Indice',
        'puesto_id' => puestoDePrueba($unidad, 'Analista de Una Historia')->id,
        'unidad_administrativa_id' => $unidad->id, 'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $this->hijo = CargaFamiliar::create([
        'servidor_id' => $this->servidor->id, 'cedula' => '0812520002', 'nombres' => 'Tomás', 'apellidos' => 'Indice',
        'parentesco' => 'hijo', 'fecha_nacimiento' => '2015-03-01', 'genero' => 'M', 'estado' => true,
    ]);
});

test('un servidor no puede tener dos historias vivas, salvo una retirada', function () {
    HistoriaClinica::create(['tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'cedula_paciente' => '0812520001']);

    // En una transacción propia: un error de la base aborta la de la prueba.
    expect(fn () => DB::transaction(fn () => HistoriaClinica::create(['tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id])))
        ->toThrow(UniqueConstraintViolationException::class);

    // Una fusionada se retira en blando y se queda con su dueño, para la constancia.
    HistoriaClinica::create(['tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'deleted_at' => now()]);

    expect(HistoriaClinica::withTrashed()->where('servidor_id', $this->servidor->id)->count())->toBe(2);
});

test('un familiar tampoco', function () {
    HistoriaClinica::create(['tipo_paciente' => 'familiar', 'carga_familiar_id' => $this->hijo->id, 'cedula_paciente' => '0812520002']);

    expect(fn () => DB::transaction(fn () => HistoriaClinica::create(['tipo_paciente' => 'familiar', 'carga_familiar_id' => $this->hijo->id])))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('la migración no sigue si quedan duplicados y dice cómo juntarlos', function () {
    $migracion = require database_path('migrations/2026_11_22_000000_una_historia_clinica_por_paciente.php');
    $migracion->down();

    HistoriaClinica::create(['tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id]);
    HistoriaClinica::create(['tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'cedula_paciente' => '0812520001']);

    expect(fn () => $migracion->up())->toThrow(RuntimeException::class, 'sgth:historias:fusionar');

    // Juntas, sigue.
    $this->artisan('sgth:historias:fusionar', ['--responsable' => 'Prueba'])->assertSuccessful();
    $migracion->up();

    expect(DB::table('pg_indexes')->where('tablename', 'historias_clinicas')->pluck('indexname')->all())
        ->toContain('uq_hc_servidor_vivo', 'uq_hc_carga_familiar_viva');
});
