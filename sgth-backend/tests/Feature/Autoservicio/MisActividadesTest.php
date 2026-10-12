<?php

namespace Tests\Feature\Autoservicio;

use App\Models\Actividades\ActividadLaboral;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Actividades\MisActividadesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| «Mis actividades» en el portal (decisiones del 2026-10-12): el servidor anota
| sus actividades laborales por día —sin horas—, con categoría y producto; las
| filtra y exporta el informe en PDF dirigido a su jefe inmediato. Son suyas:
| nadie más las ve ni las aprueba. Se anotan y corrigen solo en el mes en curso.
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->direccion = UnidadAdministrativa::create(['codigo' => 'MA-DIR', 'nombre' => 'Dirección de Planificación', 'nivel' => 2]);
    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'MA-PRO', 'nombre' => 'Unidad de Proyectos', 'nivel' => 3, 'unidad_padre_id' => $this->direccion->id,
    ]);
    $this->puesto = fn (UnidadAdministrativa $u, string $cargo, bool $jefe = false) => Puesto::create([
        'unidad_administrativa_id' => $u->id, 'plazas' => 1, 'activo' => true, 'es_jefe' => $jefe,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => $cargo])->id,
    ]);

    $this->contador = 0;
    $this->servidor = function (string $apellido, Puesto $puesto): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6900000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => $apellido, 'regimen_laboral' => 'losep',
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
            'unidad_administrativa_id' => $puesto->unidad_administrativa_id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
        ]);

        return $servidor;
    };

    $this->director = ($this->servidor)('Director', ($this->puesto)($this->direccion, 'Director de Planificación', true));
    $this->jefe = ($this->servidor)('Jefe', ($this->puesto)($this->unidad, 'Jefe de Proyectos', true));
    $this->yo = ($this->servidor)('Propio', ($this->puesto)($this->unidad, 'Analista de Proyectos'));
    $this->otro = ($this->servidor)('Ajeno', ($this->puesto)($this->unidad, 'Asistente de Proyectos'));

    $this->usuario = User::factory()->create(['servidor_id' => $this->yo->id]);
    $this->usuario->assignRole('servidor');
    $this->actingAs($this->usuario, 'sanctum');

    $this->anotada = fn (Servidor $de, string $fecha, array $mas = []) => ActividadLaboral::create([
        'servidor_id' => $de->id, 'fecha' => $fecha,
        'descripcion' => 'Revisión de expedientes.', 'categoria' => 'elaboracion_documentos', ...$mas,
    ]);
    $this->datos = fn (array $mas = []) => [
        'fecha' => '2026-10-12',
        'descripcion' => 'Reunión de planificación del POA.', 'categoria' => 'reunion',
        'producto_entregable' => 'Acta de la reunión', ...$mas,
    ];
});

test('anota actividades del día, sin horas; corregibles por ser del mes', function () {
    // Varias el mismo día: no hay horario que se cruce.
    $this->postJson('/api/v1/autoservicio/mis-actividades', ($this->datos)())->assertCreated();
    $this->postJson('/api/v1/autoservicio/mis-actividades', ($this->datos)(['descripcion' => 'Revisión del acta.']))
        ->assertCreated()
        ->assertJsonPath('datos.fecha', '2026-10-12')
        ->assertJsonMissingPath('datos.hora_inicio')
        ->assertJsonPath('datos.categoria_etiqueta', 'Reunión')
        ->assertJsonPath('datos.producto_entregable', 'Acta de la reunión')
        ->assertJsonPath('datos.puede_editar', true);

    expect(ActividadLaboral::where('servidor_id', $this->yo->id)->count())->toBe(2);
});

test('el servidor_id lo pone la cuenta, no la petición', function () {
    $this->postJson('/api/v1/autoservicio/mis-actividades', ($this->datos)(['servidor_id' => $this->otro->id]))
        ->assertCreated();

    expect(ActividadLaboral::sole()->servidor_id)->toBe($this->yo->id);
});

test('lista solo las suyas y filtra por rango, categoría y texto', function () {
    ($this->anotada)($this->yo, '2026-10-01', ['categoria' => 'reunion', 'descripcion' => 'Comité de obras']);
    ($this->anotada)($this->yo, '2026-10-05');
    ($this->anotada)($this->yo, '2026-10-09', ['producto_entregable' => 'Informe de obras']);
    ($this->anotada)($this->yo, '2026-09-30');
    ($this->anotada)($this->otro, '2026-10-05');

    $todas = $this->getJson('/api/v1/autoservicio/mis-actividades')->assertOk();
    expect($todas->json('meta.total'))->toBe(4)
        ->and($todas->json('datos.0.fecha'))->toBe('2026-10-09');

    $octubre = $this->getJson('/api/v1/autoservicio/mis-actividades?desde=2026-10-01&hasta=2026-10-31&per_page=2')->assertOk();
    expect($octubre->json('meta.total'))->toBe(3)
        ->and($octubre->json('datos'))->toHaveCount(2);

    expect($this->getJson('/api/v1/autoservicio/mis-actividades?categoria=reunion')->json('meta.total'))->toBe(1)
        // Busca en la descripción y en el producto, sin distinguir mayúsculas.
        ->and($this->getJson('/api/v1/autoservicio/mis-actividades?buscar=OBRAS')->json('meta.total'))->toBe(2);

    $this->getJson('/api/v1/autoservicio/mis-actividades?desde=2026-10-10&hasta=2026-10-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hasta'], 'errores');
});

test('solo se anotan días del mes en curso que ya llegaron', function () {
    $this->postJson('/api/v1/autoservicio/mis-actividades', ($this->datos)(['fecha' => '2026-10-13']))
        ->assertStatus(422)
        ->assertJsonPath('errores.fecha.0', 'No se anotan actividades de días que aún no llegan.');

    $this->postJson('/api/v1/autoservicio/mis-actividades', ($this->datos)(['fecha' => '2026-09-30']))
        ->assertStatus(422)
        ->assertJsonPath('errores.fecha.0', 'Solo se anotan actividades de octubre de 2026.');

    expect(ActividadLaboral::count())->toBe(0);
});

test('corrige y elimina las suyas del mes', function () {
    $actividad = ($this->anotada)($this->yo, '2026-10-12');

    $this->putJson("/api/v1/autoservicio/mis-actividades/{$actividad->id}", ($this->datos)(['fecha' => '2026-10-09']))
        ->assertOk()
        ->assertJsonPath('datos.fecha', '2026-10-09')
        ->assertJsonPath('datos.descripcion', 'Reunión de planificación del POA.');

    $this->deleteJson("/api/v1/autoservicio/mis-actividades/{$actividad->id}")->assertOk();
    expect(ActividadLaboral::count())->toBe(0)
        ->and(ActividadLaboral::withTrashed()->count())->toBe(1);
});

test('las de meses anteriores quedan fijas, y las de otro no existen para él', function () {
    $septiembre = ($this->anotada)($this->yo, '2026-09-30');
    $ajena = ($this->anotada)($this->otro, '2026-10-12');

    expect($this->getJson('/api/v1/autoservicio/mis-actividades')->json('datos.0.puede_editar'))->toBeFalse();

    $this->putJson("/api/v1/autoservicio/mis-actividades/{$septiembre->id}", ($this->datos)())
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Es de septiembre de 2026: las actividades de meses anteriores ya no se corrigen ni se eliminan.');
    $this->deleteJson("/api/v1/autoservicio/mis-actividades/{$septiembre->id}")->assertStatus(422);

    // Ni moviendo una de este mes al anterior.
    $octubre = ($this->anotada)($this->yo, '2026-10-12');
    $this->putJson("/api/v1/autoservicio/mis-actividades/{$octubre->id}", ($this->datos)(['fecha' => '2026-09-29']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fecha'], 'errores');

    $this->putJson("/api/v1/autoservicio/mis-actividades/{$ajena->id}", ($this->datos)())->assertNotFound();
    $this->deleteJson("/api/v1/autoservicio/mis-actividades/{$ajena->id}")->assertNotFound();
    expect($ajena->fresh()->trashed())->toBeFalse();
});

test('exporta el PDF del rango, que es obligatorio y de hasta un año', function () {
    ($this->anotada)($this->yo, '2026-10-05');

    $this->get('/api/v1/autoservicio/mis-actividades/pdf')
        ->assertStatus(422);
    $this->getJson('/api/v1/autoservicio/mis-actividades/pdf?desde=2025-10-01&hasta=2026-10-31')
        ->assertStatus(422)
        ->assertJsonPath('errores.hasta.0', 'El informe cubre hasta un año: acorte el rango.');

    $respuesta = $this->get('/api/v1/autoservicio/mis-actividades/pdf?desde=2026-10-01&hasta=2026-10-31')->assertOk();
    expect($respuesta->headers->get('content-type'))->toContain('application/pdf')
        ->and($respuesta->headers->get('content-disposition'))->toContain("actividades_{$this->yo->cedula}_20261001_20261031.pdf");
});

test('el informe lleva solo lo filtrado, en orden, y va al jefe de su unidad; el del jefe, al de la unidad de arriba', function () {
    $despues = ($this->anotada)($this->yo, '2026-10-06', ['categoria' => 'reunion']);
    $antes = ($this->anotada)($this->yo, '2026-10-05');
    ($this->anotada)($this->yo, '2026-11-02');
    ($this->anotada)($this->otro, '2026-10-05');

    $servicio = app(MisActividadesService::class);
    $informe = $servicio->paraInforme($this->usuario, ['desde' => '2026-10-01', 'hasta' => '2026-10-31']);

    expect($informe['actividades']->pluck('id')->all())->toBe([$antes->id, $despues->id])
        ->and($informe['periodo'])->toBe('DEL 01 DE OCTUBRE DE 2026 AL 31 DE OCTUBRE DE 2026')
        ->and($informe['cargo'])->toBe('Analista de Proyectos')
        ->and($informe['unidad'])->toBe('Unidad de Proyectos')
        ->and($informe['jefe'])->toBe(['nombre' => 'Jefe Servidor', 'cargo' => 'Jefe de Proyectos']);

    $soloReuniones = $servicio->paraInforme($this->usuario, ['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'categoria' => 'reunion']);
    expect($soloReuniones['actividades'])->toHaveCount(1);

    $delJefe = User::factory()->create(['servidor_id' => $this->jefe->id]);
    expect($servicio->paraInforme($delJefe, ['desde' => '2026-10-01', 'hasta' => '2026-10-31'])['jefe'])
        ->toBe(['nombre' => 'Director Servidor', 'cargo' => 'Director de Planificación']);
});

test('sin titular, va a quien tenga el encargo (E) o la subrogación (S); sin nadie, el cargo sin nombre', function () {
    // El jefe deja el puesto: queda vacante.
    ContratoServidor::where('servidor_id', $this->jefe->id)->update(['estado' => 'terminado']);
    $puestoJefe = Puesto::where('unidad_administrativa_id', $this->unidad->id)->where('es_jefe', true)->first();
    $servicio = app(MisActividadesService::class);
    $filtros = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];

    expect($servicio->paraInforme($this->usuario, $filtros)['jefe'])
        ->toBe(['nombre' => null, 'cargo' => 'Jefe de Proyectos']);

    $encargo = \App\Models\Expediente\Subrogacion::create([
        'tipo' => 'encargo', 'servidor_subrogante_id' => $this->otro->id,
        'unidad_administrativa_id' => $this->unidad->id, 'puesto_subrogado_id' => $puestoJefe->id,
        'fecha_inicio' => '2026-10-01', 'fecha_fin' => null, 'motivo' => 'encargo_vacante',
        'estado' => 'activa', 'registrado_por' => $this->usuario->id,
    ]);
    expect($servicio->paraInforme($this->usuario, $filtros)['jefe'])
        ->toBe(['nombre' => 'Ajeno Servidor', 'cargo' => 'Jefe de Proyectos (E)']);

    $encargo->update(['tipo' => 'subrogacion', 'motivo' => 'vacaciones', 'servidor_subrogado_id' => $this->jefe->id, 'fecha_fin' => '2026-10-31']);
    expect($servicio->paraInforme($this->usuario, $filtros)['jefe'])
        ->toBe(['nombre' => 'Ajeno Servidor', 'cargo' => 'Jefe de Proyectos (S)']);
});

test('el período lleva las dos fechas del filtro, completas', function () {
    $p = fn (string $d, string $h) => MisActividadesService::periodo(Carbon::parse($d), Carbon::parse($h));

    expect($p('2026-10-12', '2026-10-12'))->toBe('EL 12 DE OCTUBRE DE 2026')
        ->and($p('2026-10-05', '2026-10-09'))->toBe('DEL 05 DE OCTUBRE DE 2026 AL 09 DE OCTUBRE DE 2026')
        ->and($p('2025-12-15', '2026-01-10'))->toBe('DEL 15 DE DICIEMBRE DE 2025 AL 10 DE ENERO DE 2026');
});

test('sin el permiso no entra', function () {
    $sinRol = User::factory()->create(['servidor_id' => $this->otro->id]);
    $this->actingAs($sinRol, 'sanctum');

    $this->getJson('/api/v1/autoservicio/mis-actividades')->assertForbidden();
    $this->postJson('/api/v1/autoservicio/mis-actividades', ($this->datos)())->assertForbidden();
    $this->getJson('/api/v1/autoservicio/mis-actividades/pdf?desde=2026-10-01&hasta=2026-10-31')->assertForbidden();
});
