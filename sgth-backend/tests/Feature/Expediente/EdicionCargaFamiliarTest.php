<?php

use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Editar y volver a registrar un familiar.
 *
 * Hasta el 2026-10-03 la regla `unique` de la cédula no excluía la propia
 * fila: ningún familiar se podía editar, y los antiguos no podían completar
 * el sexo como prometía #311. Tampoco se podía volver a registrar uno borrado.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Edición Familias']);
    $this->titular = Servidor::forceCreate([
        'cedula' => '0806666661', 'nombre' => 'Marta', 'apellido' => 'Edición',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Edición Familias')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'fecha_ingreso_institucion' => '2020-01-01', 'estado' => true,
    ]);

    $this->datosHija = [
        'cedula' => '0806666662', 'nombres' => 'Ana', 'apellidos' => 'Edición',
        'parentesco' => 'hijo', 'fecha_nacimiento' => '2015-03-01', 'genero' => 'femenino',
        'persona_con_discapacidad' => false, 'posee_enfermedad_catastrofica' => false,
    ];
    $this->url = "/api/v1/expediente/servidores/{$this->titular->id}/cargas-familiares";
    $this->actingAs($this->uath, 'sanctum');
});

test('un familiar se edita reenviando su propia cédula', function () {
    $hija = CargaFamiliar::create([...$this->datosHija, 'servidor_id' => $this->titular->id]);

    $this->putJson("{$this->url}/{$hija->id}", [...$this->datosHija, 'nombres' => 'Ana Lucía'])
        ->assertOk();

    expect($hija->fresh()->nombres)->toBe('Ana Lucía');
});

test('la cédula de un familiar no se cambia al editarlo', function () {
    $hija = CargaFamiliar::create([...$this->datosHija, 'servidor_id' => $this->titular->id]);

    $this->putJson("{$this->url}/{$hija->id}", [...$this->datosHija, 'cedula' => '0806666669'])
        ->assertUnprocessable()
        ->assertJsonPath('errores.cedula.0', 'La cédula de un familiar no se modifica una vez registrada.');

    // Sin enviarla, se edita lo demás.
    $sinCedula = $this->datosHija;
    unset($sinCedula['cedula']);
    $this->putJson("{$this->url}/{$hija->id}", [...$sinCedula, 'genero' => 'masculino'])->assertOk();

    expect($hija->fresh())->cedula->toBe('0806666662')->genero->toBe('masculino');
});

test('un familiar antiguo sin cédula ni sexo los completa al editarlo', function () {
    $antiguo = CargaFamiliar::create([
        ...$this->datosHija, 'cedula' => null, 'genero' => null, 'servidor_id' => $this->titular->id,
    ]);

    $sinCedula = $this->datosHija;
    unset($sinCedula['cedula']);
    $this->putJson("{$this->url}/{$antiguo->id}", $sinCedula)
        ->assertUnprocessable()
        ->assertJsonPath('errores.cedula.0', 'La cédula del familiar es obligatoria.');

    $this->putJson("{$this->url}/{$antiguo->id}", $this->datosHija)->assertOk();

    expect($antiguo->fresh())->cedula->toBe('0806666662')->genero->toBe('femenino');
});

test('no se registra un familiar con la cédula de otro vigente', function () {
    CargaFamiliar::create([...$this->datosHija, 'servidor_id' => $this->titular->id]);

    $this->postJson($this->url, $this->datosHija)
        ->assertUnprocessable()
        ->assertJsonPath('errores.cedula.0', 'Esta cédula ya está registrada como carga familiar.');
});

test('volver a registrar un familiar borrado lo recupera con su historia clínica', function () {
    $hija = CargaFamiliar::create([...$this->datosHija, 'servidor_id' => $this->titular->id]);
    $historia = DB::table('historias_clinicas')->insertGetId([
        'numero_historia' => $hija->cedula, 'cedula_paciente' => $hija->cedula,
        'tipo_paciente' => 'familiar', 'carga_familiar_id' => $hija->id, 'estado' => true,
    ]);

    $this->deleteJson("{$this->url}/{$hija->id}")->assertOk();
    expect(CargaFamiliar::find($hija->id))->toBeNull();

    $id = $this->postJson($this->url, [...$this->datosHija, 'nombres' => 'Ana María'])
        ->assertCreated()
        ->json('datos.id');

    expect($id)->toBe($hija->id)
        ->and(CargaFamiliar::find($id)->nombres)->toBe('Ana María')
        ->and(CargaFamiliar::withTrashed()->where('cedula', '0806666662')->count())->toBe(1)
        ->and(DB::table('historias_clinicas')->where('id', $historia)->value('carga_familiar_id'))
            ->toBe($hija->id);
});
