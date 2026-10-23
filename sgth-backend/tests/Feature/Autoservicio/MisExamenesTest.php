<?php

/*
| «Mis exámenes» del Portal (fase 6 de docs/diseno-examenes-medicos.md, 5.7).
|
| El servidor ve sus órdenes de exámenes, baja el impreso por si lo perdió y
| envía lo que le dio el laboratorio. Lo enviado queda por validar: no toca la
| orden ni la historia clínica hasta que un médico lo acepte (D2, D9). Lo de
| otro responde 404, como si no existiera.
*/

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
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

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Mis Examenes']);
    $puesto = puestoDePrueba($unidad, 'Analista de Mis Examenes');
    $cuenta = function (string $cedula, string $rol) use ($unidad, $puesto) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => 'Rosa', 'apellido' => "Paciente {$cedula}",
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

    $this->medico = $cuenta('0812470001', 'medico');
    $this->medico->servidor->update(['nombre' => 'Gregorio', 'apellido' => 'Casas']);
    $this->titular = $cuenta('0812470002', 'servidor');
    $this->ajeno = $cuenta('0812470003', 'servidor');

    $historia = fn (User $u) => HistoriaClinica::create([
        'servidor_id' => $u->servidor_id, 'tipo_paciente' => 'servidor',
        'cedula_paciente' => $u->servidor->cedula, 'numero_historia' => $u->servidor->cedula,
    ]);
    $this->historia = $historia($this->titular);
    $this->historiaAjena = $historia($this->ajeno);

    $this->actingAs($this->titular, 'sanctum');
});

/** Una orden del médico de la prueba, con exámenes del catálogo por nombre. */
function ordenDelPortal(HistoriaClinica $historia, array $nombres, ?string $fecha = null): OrdenExamen
{
    return app(OrdenExamenServiceInterface::class)->emitir(
        [
            'historia_clinica_id'  => $historia->id,
            'fecha_solicitud'      => $fecha ?? now()->toDateString(),
            'laboratorio_destino'  => 'Laboratorio Clínico del Norte',
            'indicaciones'         => 'Ayuno de 8 horas',
        ],
        array_map(fn ($n) => ['catalogo_examen_id' => CatalogoExamen::where('nombre', $n)->value('id')], $nombres),
        test()->medico->id,
    )->load('items.examen');
}

function itemDelPortal(OrdenExamen $orden, string $nombre): int
{
    return $orden->items->firstOrFail(fn ($i) => $i->examen->nombre === $nombre)->id;
}

function enviarDesdeElPortal(OrdenExamen $orden, array $items, array $cambios = []): TestResponse
{
    return test()->post("/api/v1/autoservicio/mis-examenes/ordenes/{$orden->id}/envios", [
        'items'           => $items,
        'fecha_resultado' => now()->toDateString(),
        'comentario'      => 'Me lo hice en el laboratorio del barrio',
        'archivo'         => UploadedFile::fake()->create('resultado.pdf', 40, 'application/pdf'),
        ...$cambios,
    ], ['Accept' => 'application/json']);
}

test('lista_solo_sus_ordenes_sin_diagnostico_ni_lectura_del_medico', function () {
    $orden = ordenDelPortal($this->historia, ['Glucosa', 'Colesterol']);
    ordenDelPortal($this->historiaAjena, ['Hemograma completo']);

    $respuesta = $this->getJson('/api/v1/autoservicio/mis-examenes')->assertOk();
    $datos = $respuesta->json('datos');

    expect($datos)->toHaveCount(1)
        ->and($datos[0]['folio'])->toBe($orden->folio)
        ->and($datos[0]['solicitante'])->toBe('Gregorio Casas')
        ->and($datos[0]['laboratorio_destino'])->toBe('Laboratorio Clínico del Norte')
        ->and(collect($datos[0]['items'])->pluck('nombre')->all())->toBe(['Glucosa', 'Colesterol'])
        ->and(collect($datos[0]['items'])->pluck('estado')->unique()->all())->toBe(['pendiente'])
        ->and($datos[0])->not->toHaveKeys(['diagnostico_cie10_id', 'diagnostico_cie10', 'historia_clinica_id'])
        ->and($respuesta->json('meta.total'))->toBe(1);
});

test('el_portal_pide_su_permiso', function () {
    $this->titular->syncRoles([]);

    $this->getJson('/api/v1/autoservicio/mis-examenes')->assertForbidden();
});

test('baja_el_impreso_de_la_suya_y_no_el_de_otra_ni_el_de_una_anulada', function () {
    $propia = ordenDelPortal($this->historia, ['Glucosa']);
    $ajena = ordenDelPortal($this->historiaAjena, ['Glucosa']);

    $this->get("/api/v1/autoservicio/mis-examenes/ordenes/{$propia->id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->getJson("/api/v1/autoservicio/mis-examenes/ordenes/{$ajena->id}/pdf")->assertNotFound();

    app(OrdenExamenServiceInterface::class)->anular($propia->id, 'Error en la orden', $this->medico->id);

    $this->getJson("/api/v1/autoservicio/mis-examenes/ordenes/{$propia->id}/pdf")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', "La orden {$propia->folio} fue anulada: no la lleve al laboratorio.");
});

test('lo_enviado_queda_por_validar_y_no_toca_la_orden_ni_la_historia', function () {
    $orden = ordenDelPortal($this->historia, ['Glucosa', 'Colesterol']);

    $envio = enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Glucosa')])
        ->assertCreated()
        ->json('datos');

    expect($envio['estado'])->toBe('por_validar')
        ->and($envio['items'])->toBe([itemDelPortal($orden, 'Glucosa')])
        ->and($orden->fresh()->estado->value)->toBe('emitida')
        ->and($orden->items()->pluck('estado')->map->value->unique()->all())->toBe(['pendiente'])
        ->and(ResultadoMedico::count())->toBe(0);

    Storage::disk('local')->assertExists(EnvioResultado::findOrFail($envio['id'])->archivo);

    $listado = $this->getJson('/api/v1/autoservicio/mis-examenes')->json('datos.0.envios');
    expect(collect($listado)->pluck('id')->all())->toBe([$envio['id']]);
});

test('no_envia_a_una_orden_ajena_ni_cerrada', function () {
    $ajena = ordenDelPortal($this->historiaAjena, ['Glucosa']);
    enviarDesdeElPortal($ajena, [itemDelPortal($ajena, 'Glucosa')])->assertNotFound();

    $anulada = ordenDelPortal($this->historia, ['Glucosa']);
    app(OrdenExamenServiceInterface::class)->anular($anulada->id, 'Error en la orden', $this->medico->id);

    enviarDesdeElPortal($anulada, [itemDelPortal($anulada, 'Glucosa')])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', "La orden {$anulada->folio} está anulada: ya no recibe resultados.");

    expect(EnvioResultado::count())->toBe(0);
    // Lo rechazado no deja el archivo suelto en el disco.
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('no_envia_examenes_de_otra_orden_ni_los_que_ya_tienen_resultado', function () {
    $orden = ordenDelPortal($this->historia, ['Glucosa', 'Colesterol']);
    $otra = ordenDelPortal($this->historia, ['Hemograma completo']);

    enviarDesdeElPortal($orden, [itemDelPortal($otra, 'Hemograma completo')])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', "Hay exámenes que no son de la orden {$orden->folio}.");

    $orden->items()->whereKey(itemDelPortal($orden, 'Glucosa'))->update(['estado' => 'recibido']);

    enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Glucosa'), itemDelPortal($orden, 'Colesterol')])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'El Dispensario ya tiene el resultado de Glucosa.');
});

test('no_repite_un_examen_que_ya_espera_validacion', function () {
    $orden = ordenDelPortal($this->historia, ['Glucosa', 'Colesterol']);
    enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Glucosa')])->assertCreated();

    enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Glucosa'), itemDelPortal($orden, 'Colesterol')])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Ya envió el resultado de Glucosa y espera validación. Si se equivocó de archivo, retire ese envío antes.');

    // El otro examen sí se envía aparte.
    enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Colesterol')])->assertCreated();
});

test('la_fecha_del_resultado_no_es_futura_ni_anterior_a_la_orden', function () {
    $orden = ordenDelPortal($this->historia, ['Glucosa'], now()->subDays(5)->toDateString());
    $item = itemDelPortal($orden, 'Glucosa');

    enviarDesdeElPortal($orden, [$item], ['fecha_resultado' => now()->addDay()->toDateString()])
        ->assertStatus(422)
        ->assertJsonPath('errores.fecha_resultado.0', 'El resultado no puede tener fecha futura.');

    enviarDesdeElPortal($orden, [$item], ['fecha_resultado' => now()->subDays(6)->toDateString()])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'El resultado no puede ser anterior a la orden, del ' . now()->subDays(5)->format('d/m/Y') . '.');

    enviarDesdeElPortal($orden, [$item], ['fecha_resultado' => now()->subDays(5)->toDateString()])->assertCreated();
});

test('pide_el_archivo_y_al_menos_un_examen', function () {
    $orden = ordenDelPortal($this->historia, ['Glucosa']);

    $this->postJson("/api/v1/autoservicio/mis-examenes/ordenes/{$orden->id}/envios", [
        'items' => [], 'fecha_resultado' => now()->toDateString(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('errores.items.0', 'Marque qué exámenes de la orden trae el archivo.')
        ->assertJsonValidationErrors(['archivo'], 'errores');

    enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Glucosa')], [
        'archivo' => UploadedFile::fake()->create('resultado.docx', 40, 'application/msword'),
    ])
        ->assertStatus(422)
        ->assertJsonPath('errores.archivo.0', 'El archivo debe ser un PDF o una foto (JPG o PNG).');
});

test('retira_el_suyo_mientras_espera_y_deja_de_verlo', function () {
    $orden = ordenDelPortal($this->historia, ['Glucosa']);
    $envio = enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Glucosa')])->json('datos');

    $this->postJson("/api/v1/autoservicio/mis-examenes/envios/{$envio['id']}/retirar")
        ->assertOk()
        ->assertJsonPath('datos.estado', 'retirado');

    expect($this->getJson('/api/v1/autoservicio/mis-examenes')->json('datos.0.envios'))->toBe([]);

    $this->postJson("/api/v1/autoservicio/mis-examenes/envios/{$envio['id']}/retirar")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Este envío ya está retirado: ya no se retira.');

    // Retirado, el examen se puede volver a enviar.
    enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Glucosa')])->assertCreated();
});

test('lo_de_otro_no_se_retira_ni_se_descarga', function () {
    $ajena = ordenDelPortal($this->historiaAjena, ['Glucosa']);
    $envio = EnvioResultado::create([
        'orden_examen_id' => $ajena->id, 'enviado_por' => $this->ajeno->id,
        'items' => [itemDelPortal($ajena, 'Glucosa')], 'fecha_resultado' => now()->toDateString(),
        'archivo' => 'resultados-medicos/portal/ajeno.pdf', 'estado' => 'por_validar',
    ]);
    Storage::disk('local')->put($envio->archivo, '%PDF-1.4 ajeno');

    $this->postJson("/api/v1/autoservicio/mis-examenes/envios/{$envio->id}/retirar")->assertNotFound();
    $this->getJson("/api/v1/autoservicio/mis-examenes/envios/{$envio->id}/archivo")->assertNotFound();

    expect($envio->fresh()->estado->value)->toBe('por_validar');
});

test('baja_el_archivo_que_envio', function () {
    $orden = ordenDelPortal($this->historia, ['Glucosa']);
    $envio = enviarDesdeElPortal($orden, [itemDelPortal($orden, 'Glucosa')])->json('datos');

    $this->get("/api/v1/autoservicio/mis-examenes/envios/{$envio['id']}/archivo")->assertOk();
});
