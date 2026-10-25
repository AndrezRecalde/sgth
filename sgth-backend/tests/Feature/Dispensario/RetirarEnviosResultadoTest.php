<?php

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/*
 * El servidor dejó de enviar resultados desde el Portal el 2026-10-25 y la
 * tabla de los envíos se va (migración 2026_11_25_000000). Lo validado sigue
 * en la historia clínica; lo que ningún resultado usa se borra del disco.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

test('la_migracion_borra_la_tabla_y_solo_los_archivos_que_ningun_resultado_usa', function () {
    Model::unguard();
    Storage::fake('local');
    $migracion = require database_path('migrations/2026_11_25_000000_retirar_envios_resultado.php');

    // La tabla, como la dejó la fase 6.
    $migracion->down();

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Envios Retirados']);
    $servidor = Servidor::create([
        'cedula' => '0812490001', 'nombre' => 'Paciente', 'apellido' => 'Envios',
        'puesto_id' => puestoDePrueba($unidad, 'Analista de Envios Retirados')->id,
        'unidad_administrativa_id' => $unidad->id, 'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $paciente = User::create([
        'email' => 'envios-paciente@example.com', 'usuario_ti' => 'enviospaciente',
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $servidor->id,
    ]);
    $medico = User::create([
        'email' => 'envios-medico@example.com', 'usuario_ti' => 'enviosmedico',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));
    $historia = HistoriaClinica::create([
        'servidor_id' => $servidor->id, 'tipo_paciente' => 'servidor',
        'cedula_paciente' => '0812490001', 'numero_historia' => '0812490001',
    ]);
    $orden = app(OrdenExamenServiceInterface::class)->emitir(
        ['historia_clinica_id' => $historia->id, 'fecha_solicitud' => today()->toDateString()],
        [['catalogo_examen_id' => CatalogoExamen::where('nombre', 'Glucosa')->value('id')]],
        $medico->id,
    );

    $validado = 'resultados-medicos/portal/1/validado.pdf';
    $rechazado = 'resultados-medicos/portal/1/rechazado.pdf';
    $sinAtender = 'resultados-medicos/portal/1/sin-atender.jpg';
    foreach ([$validado, $rechazado, $sinAtender] as $archivo) {
        Storage::disk('local')->put($archivo, 'contenido');
    }

    // El validado nació como resultado de la historia con el mismo archivo.
    $resultado = ResultadoMedico::create([
        'historia_clinica_id' => $historia->id, 'orden_examen_id' => $orden->id, 'subido_por' => $medico->id,
        'tipo' => 'laboratorio', 'descripcion' => 'Glucosa', 'archivo' => $validado,
        'fecha_resultado' => today()->toDateString(),
    ]);
    $envio = fn (string $archivo, string $estado, ?int $resultadoId = null) => DB::table('envios_resultado')->insert([
        'orden_examen_id' => $orden->id, 'enviado_por' => $paciente->id, 'items' => '[]',
        'fecha_resultado' => today()->toDateString(), 'archivo' => $archivo, 'estado' => $estado,
        'resultado_medico_id' => $resultadoId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $envio($validado, 'validado', $resultado->id);
    $envio($rechazado, 'rechazado');
    $envio($sinAtender, 'por_validar');

    $migracion->up();

    expect(Schema::hasTable('envios_resultado'))->toBeFalse()
        ->and(Storage::disk('local')->exists($validado))->toBeTrue()
        ->and(Storage::disk('local')->exists($rechazado))->toBeFalse()
        ->and(Storage::disk('local')->exists($sinAtender))->toBeFalse()
        ->and($resultado->fresh()->archivo)->toBe($validado);

    // Sin la tabla, no hace nada.
    $migracion->up();
});

test('las_rutas_del_envio_y_de_mis_examenes_ya_no_existen', function () {
    $medico = User::create([
        'email' => 'envios-rutas@example.com', 'usuario_ti' => 'enviosrutas',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));
    $this->actingAs($medico, 'sanctum');

    $this->getJson('/api/v1/autoservicio/mis-examenes')->assertNotFound();
    $this->getJson('/api/v1/dispensario/examenes/bandeja/por-validar')->assertNotFound();
    $this->patchJson('/api/v1/dispensario/envios-resultado/1/validar')->assertNotFound();

    expect($this->getJson('/api/v1/dispensario/examenes/bandeja')->assertOk()->json('meta.resumen'))
        ->not->toHaveKey('por_validar');
});
