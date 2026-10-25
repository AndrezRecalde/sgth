<?php

/*
| La validación de lo que el servidor envía desde el Portal (fase 6 de
| docs/diseno-examenes-medicos.md, D2): un médico lo acepta y nace el resultado
| de la historia clínica, contra la orden y por revisar; o lo rechaza con un
| motivo que el servidor ve. Enfermería lo ve en la bandeja y no lo atiende.
*/

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\EnvioResultado;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();
    Storage::fake('local');
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Envios del Portal']);
    $puesto = puestoDePrueba($unidad, 'Analista de Envios del Portal');
    $cuenta = function (string $cedula, string $rol) use ($unidad, $puesto) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => 'Lucia', 'apellido' => "Envio {$cedula}",
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
        ]);
        $usuario = User::create([
            'email' => "{$cedula}@example.com", 'usuario_ti' => "u{$cedula}",
            'password' => bcrypt('123456'), 'primer_login' => false,
            'servidor_id' => $servidor->id,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->solicitante = $cuenta('0812480001', 'medico');
    $this->otroMedico = $cuenta('0812480002', 'medico');
    $this->enfermera = $cuenta('0812480003', 'enfermera');
    $this->servidor = $cuenta('0812480004', 'servidor');

    $this->historia = HistoriaClinica::create([
        'servidor_id' => $this->servidor->servidor_id, 'tipo_paciente' => 'servidor',
        'cedula_paciente' => '0812480004', 'numero_historia' => '0812480004',
    ]);
    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $this->solicitante->id, 'fecha_consulta' => now()->subDays(2),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
    $this->orden = app(OrdenExamenServiceInterface::class)->emitir(
        [
            'historia_clinica_id' => $this->historia->id,
            'consulta_medica_id'  => $this->consulta->id,
            'fecha_solicitud'     => now()->subDays(2)->toDateString(),
        ],
        array_map(fn ($n) => ['catalogo_examen_id' => CatalogoExamen::where('nombre', $n)->value('id')],
            ['Glucosa', 'Colesterol', 'Hemograma completo']),
        $this->solicitante->id,
    )->load('items.examen');

    $this->item = fn (string $nombre) => $this->orden->items->firstOrFail(fn ($i) => $i->examen->nombre === $nombre)->id;
});

/** Lo que el servidor manda desde el Portal, con su sesión. */
function envioDelServidor(array $items): EnvioResultado
{
    $id = test()->actingAs(test()->servidor, 'sanctum')
        ->post('/api/v1/autoservicio/mis-examenes/ordenes/' . test()->orden->id . '/envios', [
            'items'           => $items,
            'fecha_resultado' => now()->subDay()->toDateString(),
            'comentario'      => 'Del laboratorio del barrio',
            'archivo'         => UploadedFile::fake()->create('resultado.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('datos.id');

    return EnvioResultado::findOrFail($id);
}

function validarEnvio(User $quien, EnvioResultado $envio, array $items): TestResponse
{
    return test()->actingAs($quien, 'sanctum')
        ->patchJson("/api/v1/dispensario/envios-resultado/{$envio->id}/validar", [
            'items'           => $items,
            'tipo'            => 'laboratorio',
            'descripcion'     => 'Glucosa y colesterol',
            'fecha_resultado' => now()->subDay()->toDateString(),
        ]);
}

test('la_bandeja_por_validar_lo_lista_y_el_resumen_lo_cuenta', function () {
    $envio = envioDelServidor([($this->item)('Glucosa')]);

    $this->actingAs($this->otroMedico, 'sanctum');

    $pagina = $this->getJson('/api/v1/dispensario/examenes/bandeja/por-validar')->assertOk()->json('datos');
    expect(collect($pagina['data'])->pluck('id')->all())->toBe([$envio->id])
        ->and($pagina['data'][0]['orden_examen']['folio'])->toBe($this->orden->folio)
        ->and($pagina['data'][0]['orden_examen']['historia_clinica']['servidor']['cedula'])->toBe('0812480004');

    // La búsqueda por cédula, por folio y una que no coincide.
    expect($this->getJson('/api/v1/dispensario/examenes/bandeja/por-validar?buscar=0812480004')->json('datos.total'))->toBe(1)
        ->and($this->getJson('/api/v1/dispensario/examenes/bandeja/por-validar?buscar=' . $this->orden->folio)->json('datos.total'))->toBe(1)
        ->and($this->getJson('/api/v1/dispensario/examenes/bandeja/por-validar?buscar=Otro')->json('datos.total'))->toBe(0)
        ->and($this->getJson('/api/v1/dispensario/examenes/bandeja')->json('meta.resumen.por_validar'))->toBe(1);
});

test('al_validarlo_nace_el_resultado_por_revisar_contra_la_orden', function () {
    $envio = envioDelServidor([($this->item)('Glucosa')]);

    // El médico confirma los exámenes: el archivo traía también el colesterol.
    validarEnvio($this->otroMedico, $envio, [($this->item)('Glucosa'), ($this->item)('Colesterol')])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'validado');

    $envio->refresh();
    $resultado = ResultadoMedico::with('itemsOrden.examen')->findOrFail($envio->resultado_medico_id);

    expect($resultado->historia_clinica_id)->toBe($this->historia->id)
        ->and($resultado->consulta_medica_id)->toBe($this->consulta->id)
        ->and($resultado->orden_examen_id)->toBe($this->orden->id)
        ->and($resultado->subido_por)->toBe($this->otroMedico->id)
        ->and($resultado->archivo)->toBe($envio->archivo)
        ->and($resultado->estado_revision->value)->toBe('por_revisar')
        // Responde quien pidió la orden, no quien validó (D3).
        ->and($resultado->load('ordenExamen')->responsableId())->toBe($this->solicitante->id)
        ->and($resultado->itemsOrden->pluck('examen.nombre')->all())->toBe(['Glucosa', 'Colesterol'])
        ->and($this->orden->fresh()->estado->value)->toBe('parcial')
        ->and($envio->atendido_por)->toBe($this->otroMedico->id);

    // El listado del paciente dice que vino del Portal.
    $this->getJson("/api/v1/dispensario/resultados-medicos?historia_clinica_id={$this->historia->id}")
        ->assertOk()
        ->assertJsonPath('datos.0.envio_portal.id', $envio->id);

    // Y el servidor lo ve validado, con sus exámenes recibidos.
    $orden = $this->actingAs($this->servidor, 'sanctum')->getJson('/api/v1/autoservicio/mis-examenes')->json('datos.0');
    expect($orden['envios'][0]['estado'])->toBe('validado')
        ->and(collect($orden['items'])->pluck('estado', 'nombre')->all())->toBe([
            'Glucosa' => 'recibido', 'Colesterol' => 'recibido', 'Hemograma completo' => 'pendiente',
        ]);
});

test('al_rechazarlo_el_servidor_ve_el_motivo_y_nada_mas_cambia', function () {
    $envio = envioDelServidor([($this->item)('Glucosa')]);

    $this->actingAs($this->solicitante, 'sanctum')
        ->patchJson("/api/v1/dispensario/envios-resultado/{$envio->id}/rechazar", ['motivo_rechazo' => ''])
        ->assertStatus(422)
        ->assertJsonPath('errores.motivo_rechazo.0', 'Indique por qué no se acepta: el servidor lo verá en el Portal.');

    $this->patchJson("/api/v1/dispensario/envios-resultado/{$envio->id}/rechazar", [
        'motivo_rechazo' => 'La foto no se lee: envíe el PDF del laboratorio',
    ])->assertOk()->assertJsonPath('datos.estado', 'rechazado');

    expect(ResultadoMedico::count())->toBe(0)
        ->and($this->orden->fresh()->estado->value)->toBe('emitida');

    $envioEnPortal = $this->actingAs($this->servidor, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-examenes')->json('datos.0.envios.0');
    expect($envioEnPortal['estado'])->toBe('rechazado')
        ->and($envioEnPortal['motivo_rechazo'])->toBe('La foto no se lee: envíe el PDF del laboratorio');

    // Rechazado, el servidor puede volver a enviar el mismo examen.
    envioDelServidor([($this->item)('Glucosa')]);
});

test('solo_se_atiende_lo_que_espera', function () {
    $envio = envioDelServidor([($this->item)('Glucosa')]);
    validarEnvio($this->solicitante, $envio, [($this->item)('Glucosa')])->assertOk();

    validarEnvio($this->solicitante, $envio, [($this->item)('Glucosa')])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Este envío ya está validado: solo se valida o se rechaza uno por validar.');

    $this->patchJson("/api/v1/dispensario/envios-resultado/{$envio->id}/rechazar", ['motivo_rechazo' => 'Tarde'])
        ->assertStatus(422);

    expect(ResultadoMedico::count())->toBe(1);
});

test('si_la_orden_se_cerro_mientras_esperaba_no_se_valida', function () {
    $envio = envioDelServidor([($this->item)('Glucosa')]);
    app(OrdenExamenServiceInterface::class)->anular($this->orden->id, 'Error en la orden', $this->solicitante->id);

    validarEnvio($this->solicitante, $envio, [($this->item)('Glucosa')])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', "La orden {$this->orden->folio} está anulada: el resultado se sube sin orden o contra una orden nueva.");

    // Nada a medias: ni resultado ni envío atendido.
    expect(ResultadoMedico::withTrashed()->count())->toBe(0)
        ->and($envio->fresh()->estado->value)->toBe('por_validar');
});

test('no_se_validan_examenes_de_otra_orden', function () {
    $envio = envioDelServidor([($this->item)('Glucosa')]);
    $otra = app(OrdenExamenServiceInterface::class)->emitir(
        ['historia_clinica_id' => $this->historia->id, 'fecha_solicitud' => now()->toDateString()],
        [['catalogo_examen_id' => CatalogoExamen::where('nombre', 'Urea')->value('id')]],
        $this->solicitante->id,
    );

    validarEnvio($this->solicitante, $envio, [$otra->items()->value('id')])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', "Hay exámenes que no son de la orden {$this->orden->folio}.");
});

test('ni_enfermeria_ni_el_servidor_lo_ven_ni_lo_atienden', function () {
    $envio = envioDelServidor([($this->item)('Glucosa')]);

    // Enfermería lo veía hasta el 2026-10-24.
    $this->actingAs($this->enfermera, 'sanctum');
    $this->getJson('/api/v1/dispensario/examenes/bandeja/por-validar')->assertForbidden();
    $this->getJson("/api/v1/dispensario/envios-resultado/{$envio->id}/archivo")->assertForbidden();
    validarEnvio($this->enfermera, $envio, [($this->item)('Glucosa')])->assertForbidden();
    $this->patchJson("/api/v1/dispensario/envios-resultado/{$envio->id}/rechazar", ['motivo_rechazo' => 'No'])
        ->assertForbidden();

    $this->actingAs($this->servidor, 'sanctum');
    $this->getJson('/api/v1/dispensario/examenes/bandeja/por-validar')->assertForbidden();
    validarEnvio($this->servidor, $envio, [($this->item)('Glucosa')])->assertForbidden();

    expect($envio->fresh()->estado->value)->toBe('por_validar');
});

test('el_motivo_del_rechazo_admite_lo_que_deja_escribir_la_pantalla', function () {
    $envio = envioDelServidor([($this->item)('Glucosa')]);
    // El modal de motivo de la casa deja escribir hasta 500 caracteres.
    $motivo = str_repeat('La hoja no trae el nombre del paciente. ', 12);

    $this->actingAs($this->solicitante, 'sanctum')
        ->patchJson("/api/v1/dispensario/envios-resultado/{$envio->id}/rechazar", ['motivo_rechazo' => $motivo])
        ->assertOk();

    expect($envio->fresh()->motivo_rechazo)->toBe(trim($motivo));

    $this->patchJson("/api/v1/dispensario/envios-resultado/{$envio->id}/rechazar", [
        'motivo_rechazo' => str_repeat('x', 501),
    ])->assertStatus(422)->assertJsonValidationErrors(['motivo_rechazo'], 'errores');
});
