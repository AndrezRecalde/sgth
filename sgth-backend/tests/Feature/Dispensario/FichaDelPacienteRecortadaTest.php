<?php

/*
| El Dispensario con lo justo del paciente (docs/revision-ficha-paciente-
| dispensario.md, 2026-10-29).
|
| `Servidor` no oculta nada, y cargarlo sin columnas mandaba su ficha entera
| —cédula, tipo de sangre, celular, correo personal, dirección, contacto de
| emergencia— en el contexto de la consulta, las consultas, las atenciones de
| enfermería y la solicitud del FEMO. Las consultas mandaban además la del
| médico, que `nombre_completo` cargaba por su cuenta. Y tres rutas de
| historias que ninguna pantalla pedía mandaban la de todos los pacientes.
*/

use App\Enums\EstadoPostulante;
use App\Enums\TipoProcesoConvocatoria;
use App\Models\Dispensario\CatalogoServicioEnfermeria;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use App\Services\Dispensario\CertificadoMedicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class, RefreshDatabase::class);

/** Lo que la ficha de un servidor —paciente o médico— no debe soltar. */
function fichaDelServidorRecortada(): array
{
    return [
        'cedula', 'fecha_nacimiento', 'tipo_sangre', 'telefono_celular', 'correo_personal',
        'direccion_domicilio', 'pasaporte_numero', 'tiene_discapacidad', 'tiene_enfermedad_catastrofica',
        'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
    ];
}

/** Ni la de un familiar, ni su titular. */
function fichaDeLaCargaRecortada(): array
{
    return [
        'cedula', 'fecha_nacimiento', 'genero', 'persona_con_discapacidad',
        'posee_enfermedad_catastrofica', 'observaciones', 'servidor',
    ];
}

/** Que llegue el nombre y nada de lo demás, salvo lo que se permite. */
function expectSoloElNombreRecortado(?array $persona, string $nombre, array $ficha, array $permitidos = []): void
{
    expect($persona)->not->toBeNull()
        ->and($persona['nombre'] ?? $persona['nombres'])->toBe($nombre);

    foreach (array_diff($ficha, $permitidos) as $campo) {
        expect($persona)->not->toHaveKey($campo);
    }
}

function usuarioFichaRecortada(string|array $roles, ?Servidor $servidor = null): User
{
    $usuario = User::factory()->create(['servidor_id' => $servidor?->id]);
    $usuario->assignRole($roles);

    return $usuario;
}

beforeEach(function () {
    Servidor::unguard();
    $ficha = [
        'estado' => true, 'fecha_nacimiento' => '1985-04-02', 'tipo_sangre' => 'O+',
        'telefono_celular' => '0991112233', 'direccion_domicilio' => 'Calle 10 de Agosto',
        'pasaporte_numero' => 'A1234567', 'contacto_emergencia_telefono' => '0994445566',
    ];

    $this->titular = Servidor::create([
        ...$ficha, 'cedula' => '0812800001', 'nombre' => 'Marta', 'apellido' => 'Quiñónez',
        'genero' => 'femenino', 'tiene_discapacidad' => true, 'correo_personal' => 'marta@example.com',
        'contacto_emergencia_nombre' => 'Luis Quiñónez',
    ]);
    $this->hija = CargaFamiliar::create([
        'servidor_id' => $this->titular->id, 'cedula' => '0812800002', 'nombres' => 'Lía',
        'apellidos' => 'Quiñónez', 'parentesco' => 'hijo', 'fecha_nacimiento' => '2016-05-01',
        'genero' => 'femenino', 'persona_con_discapacidad' => false, 'posee_enfermedad_catastrofica' => false,
        'observaciones' => 'Asma en control',
    ]);

    // El médico también tiene ficha, y `nombre_completo` la traía entera.
    $delMedico = Servidor::create([
        ...$ficha, 'cedula' => '0812800003', 'nombre' => 'Pedro', 'apellido' => 'Cedeño',
        'correo_personal' => 'pedro@example.com', 'contacto_emergencia_nombre' => 'Ana Cedeño',
    ]);
    $this->medico = usuarioFichaRecortada(['medico', 'medico-ocupacional'], $delMedico);
    $this->enfermera = usuarioFichaRecortada('enfermera');

    $this->historiaTitular = HistoriaClinica::create([
        'cedula_paciente' => $this->titular->cedula, 'numero_historia' => $this->titular->cedula,
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->titular->id, 'estado' => true,
    ]);
    $this->historiaHija = HistoriaClinica::create([
        'cedula_paciente' => $this->hija->cedula, 'numero_historia' => $this->hija->cedula,
        'tipo_paciente' => 'familiar', 'carga_familiar_id' => $this->hija->id, 'estado' => true,
    ]);
});

function consultaFichaRecortada(HistoriaClinica $historia, User $medico): ConsultaMedica
{
    return ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
        'medico_id' => $medico->id, 'fecha_consulta' => now()->toDateString(),
        'hora_consulta' => '08:00:00', 'motivo_consulta' => 'Cefalea',
        'diagnostico_detallado' => 'Cefalea tensional',
    ]);
}

test('las rutas de historias que ninguna pantalla pedía ya no existen', function () {
    $this->actingAs($this->medico, 'sanctum');

    // Queda el alta por POST en la misma dirección.
    $this->getJson('/api/v1/dispensario/historias-clinicas')->assertStatus(405);
    $this->getJson("/api/v1/dispensario/historias-clinicas/{$this->historiaTitular->id}")->assertNotFound();
    $this->getJson('/api/v1/dispensario/historias-clinicas/buscar-por-cedula?cedula=0812800001')->assertNotFound();
});

test('el contexto de la consulta trae el nombre del paciente y nada de su ficha', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    $titular = $this->getJson("/api/v1/dispensario/historias-clinicas/{$this->historiaTitular->id}/contexto-consulta")
        ->assertOk()->json('datos.historia_clinica.servidor');
    expectSoloElNombreRecortado($titular, 'Marta', fichaDelServidorRecortada());

    $hija = $this->getJson("/api/v1/dispensario/historias-clinicas/{$this->historiaHija->id}/contexto-consulta")
        ->assertOk()->json('datos.historia_clinica.carga_familiar');
    expectSoloElNombreRecortado($hija, 'Lía', fichaDeLaCargaRecortada());
});

test('el historial y el detalle de la consulta traen los nombres, sin las fichas del paciente ni del médico', function () {
    $consulta = consultaFichaRecortada($this->historiaTitular, $this->medico);
    $this->actingAs($this->medico, 'sanctum');

    $deLaLista = $this->getJson("/api/v1/dispensario/consultas?historia_clinica_id={$this->historiaTitular->id}")
        ->assertOk()->json('datos.data.0');
    $delDetalle = $this->getJson("/api/v1/dispensario/consultas/{$consulta->id}")
        ->assertOk()->json('datos');

    foreach ([$deLaLista, $delDetalle] as $respuesta) {
        expectSoloElNombreRecortado($respuesta['historia_clinica']['servidor'], 'Marta', fichaDelServidorRecortada());
        expect($respuesta['medico']['nombre_completo'])->toBe('Pedro Cedeño');
        expectSoloElNombreRecortado($respuesta['medico']['servidor'], 'Pedro', fichaDelServidorRecortada());
    }
});

test('registrar y corregir una consulta responde sin las fichas', function () {
    $this->actingAs($this->medico, 'sanctum');
    $datos = [
        'historia_clinica_id' => $this->historiaHija->id,
        'fecha_consulta' => now()->toDateString(), 'hora_consulta' => '09:00',
        'tipo_atencion' => 'primera_vez', 'tipo_diagnostico' => 'presuntivo',
        'motivo_consulta' => 'Tos', 'diagnostico_detallado' => 'Bronquitis aguda',
    ];

    $registrada = $this->postJson('/api/v1/dispensario/consultas', $datos)->assertCreated()->json('datos');
    $corregida = $this->patchJson("/api/v1/dispensario/consultas/{$registrada['id']}", [
        ...$datos, 'diagnostico_detallado' => 'Bronquitis aguda, sin fiebre',
    ])->assertOk()->json('datos');

    foreach ([$registrada, $corregida] as $respuesta) {
        expectSoloElNombreRecortado($respuesta['historia_clinica']['carga_familiar'], 'Lía', fichaDeLaCargaRecortada());
        expect($respuesta['medico']['nombre_completo'])->toBe('Pedro Cedeño');
        expectSoloElNombreRecortado($respuesta['medico']['servidor'], 'Pedro', fichaDelServidorRecortada());
    }
});

test('las atenciones de enfermería traen el nombre del paciente y nada de su ficha', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $servicio = CatalogoServicioEnfermeria::create(['nombre' => 'Curación', 'activo' => true]);

    $deLaHija = $this->postJson('/api/v1/dispensario/atenciones-enfermeria', [
        'carga_familiar_id' => $this->hija->id, 'catalogo_servicio_id' => $servicio->id,
    ])->assertCreated()->json('datos.carga_familiar');
    expectSoloElNombreRecortado($deLaHija, 'Lía', fichaDeLaCargaRecortada());

    $this->postJson('/api/v1/dispensario/atenciones-enfermeria', [
        'servidor_id' => $this->titular->id, 'catalogo_servicio_id' => $servicio->id,
    ])->assertCreated();

    $delTitular = collect($this->getJson('/api/v1/dispensario/atenciones-enfermeria')->assertOk()->json('datos.data'))
        ->firstWhere('servidor_id', $this->titular->id);
    expectSoloElNombreRecortado($delTitular['servidor'], 'Marta', fichaDelServidorRecortada());
});

test('la solicitud del FEMO trae lo clínico del paciente y nada más de su ficha', function () {
    $this->titular->discapacidades()->create([
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 35, 'numero_carnet_conadis' => '1',
    ]);
    $solicitud = SolicitudCertificacionMedica::create([
        'servidor_id' => $this->titular->id, 'tipo_evento' => 'periodica', 'origen' => 'expediente',
        'cedula_paciente' => $this->titular->cedula, 'nombres_paciente' => 'Marta Quiñónez',
        'estado' => 'pendiente', 'solicitado_por' => $this->medico->id,
    ]);
    $this->actingAs($this->medico, 'sanctum');

    $servidor = $this->getJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}")
        ->assertOk()->json('datos.servidor');

    // El género, el tipo de sangre y la discapacidad los usa el FEMO.
    expectSoloElNombreRecortado($servidor, 'Marta', fichaDelServidorRecortada(), ['cedula', 'tipo_sangre', 'tiene_discapacidad']);
    expect($servidor['genero'])->toBe('femenino')
        ->and($servidor['tipo_sangre'])->toBe('O+')
        ->and($servidor['tiene_discapacidad'])->toBeTrue()
        ->and($servidor['discapacidades'][0]['porcentaje'])->toBe('35.00');
});

test('la solicitud del FEMO de un candidato trae lo que usa la ficha y nada más', function () {
    $unidad = unidadDePrueba(['nombre' => 'Unidad Ficha Recortada']);
    $convocatoria = Convocatoria::create([
        'puesto_id' => puestoDePrueba($unidad, 'Analista Ficha Recortada')->id,
        'codigo' => 'CNV-2026-910', 'titulo' => 'Analista', 'descripcion' => 'Concurso',
        'fecha_inicio' => now()->subDays(20), 'fecha_fin' => now()->addDays(10),
        'tipo_proceso' => TipoProcesoConvocatoria::FORMAL,
    ]);
    $postulante = Postulante::create([
        'convocatoria_id' => $convocatoria->id, 'cedula' => '1756600001',
        'nombres' => 'Mariela', 'apellidos' => 'Chica', 'correo' => 'mariela@example.test',
        'telefono' => '0998887766', 'genero' => 'femenino', 'estado_civil' => 'soltero',
        'fecha_nacimiento' => '1996-02-20', 'tipo_sangre' => 'A+',
        'estado' => EstadoPostulante::GANADOR_POTENCIAL,
    ]);
    $solicitud = SolicitudCertificacionMedica::create([
        'postulante_id' => $postulante->id, 'convocatoria_id' => $convocatoria->id,
        'tipo_evento' => 'ingreso', 'origen' => 'reclutamiento',
        'cedula_paciente' => $postulante->cedula, 'nombres_paciente' => 'Mariela Chica',
        'estado' => 'pendiente', 'solicitado_por' => $this->medico->id,
    ]);
    $this->actingAs($this->medico, 'sanctum');

    $candidato = $this->getJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}")
        ->assertOk()->json('datos.postulante');

    expect($candidato['nombres'])->toBe('Mariela')
        ->and($candidato['genero'])->toBe('femenino')
        ->and($candidato['tipo_sangre'])->toBe('A+');

    foreach (['telefono', 'estado_civil', 'fecha_nacimiento', 'provincia_nacimiento_id', 'canton_nacimiento_id'] as $campo) {
        expect($candidato)->not->toHaveKey($campo);
    }
});

test('el PDF del certificado de un familiar sigue nombrando a su titular', function () {
    $consulta = consultaFichaRecortada($this->historiaHija, $this->medico);
    $certificado = app(CertificadoMedicoService::class)->emitir([
        'consulta_medica_id' => $consulta->id, 'dias_reposo' => 1,
    ], $this->medico->id);

    $paciente = null;
    View::composer('pdf.dispensario.certificado-medico', function ($vista) use (&$paciente) {
        $paciente = $vista->getData()['paciente'];
    });

    $this->actingAs($this->medico, 'sanctum')
        ->get("/api/v1/dispensario/certificados-medicos/{$certificado->id}/pdf")
        ->assertOk();

    expect($paciente)->toBe([
        'nombre' => 'Lía Quiñónez', 'cedula' => '0812800002',
        'condicion' => 'Carga familiar de Marta Quiñónez',
    ]);
});
