<?php

use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\FemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01',
        'nombre' => 'Unidad de Talento Humano',
        'nivel' => 1,
    ]);

    $this->cargo = Cargo::create([
        'nombre' => 'Analista de Seguridad Informática',
        'clasificacion_personal' => 'empleado',
        'codigo_ciuo' => '2529',
    ]);

    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'cargo_id' => $this->cargo->id,
        'plazas' => 5,
    ]);

    $this->servidor = Servidor::create([
        'cedula' => '0803114562',
        'nombre' => 'Marlon',
        'apellido' => 'Vera',
        'puesto_id' => $this->puesto->id,
    ]);

    $this->medico = User::create([
        'email' => 'medico@gadpe.gob.ec',
        'usuario_ti' => 'medico',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
    ]);

    $this->servicio = app(FemoService::class);

    $this->datosFicha = fn (array $extra = []) => [
        'ficha' => [
            'servidor_id' => $this->servidor->id,
            'fecha_evaluacion' => '2026-08-28',
            'tipo_ficha' => 'periodica',
            'aptitud' => 'apto',
            ...$extra,
        ],
    ];
});

test('el nombre del puesto y su CIUO se sellan desde puesto_id', function () {
    $ficha = $this->servicio->registrar(
        ($this->datosFicha)(['puesto_id' => $this->puesto->id]),
        $this->medico->id,
    );

    expect($ficha->puesto_id)->toBe($this->puesto->id)
        ->and($ficha->puesto_trabajo)->toBe('Analista de Seguridad Informática')
        ->and($ficha->puesto_trabajo_ciuo)->toBe('2529');
});

test('lo que el cliente escriba en el puesto se ignora si mandó puesto_id', function () {
    // El formulario ya no permite teclearlo, pero la API es pública para el
    // cliente: el nombre lo decide el servidor, no quien envía la petición.
    $ficha = $this->servicio->registrar(
        ($this->datosFicha)([
            'puesto_id' => $this->puesto->id,
            'puesto_trabajo' => 'Cargo inventado',
            'puesto_trabajo_ciuo' => '9999',
        ]),
        $this->medico->id,
    );

    expect($ficha->puesto_trabajo)->toBe('Analista de Seguridad Informática')
        ->and($ficha->puesto_trabajo_ciuo)->toBe('2529');
});

test('sin puesto_id se respeta el texto escrito a mano', function () {
    // Es el caso del candidato externo que todavía no tiene plaza asignada.
    $ficha = $this->servicio->registrar(
        ($this->datosFicha)([
            'puesto_trabajo' => 'Consultor externo',
            'puesto_trabajo_ciuo' => '2411',
        ]),
        $this->medico->id,
    );

    expect($ficha->puesto_id)->toBeNull()
        ->and($ficha->puesto_trabajo)->toBe('Consultor externo')
        ->and($ficha->puesto_trabajo_ciuo)->toBe('2411');
});

test('el sello sobrevive a que el cargo se renombre después', function () {
    $ficha = $this->servicio->registrar(
        ($this->datosFicha)(['puesto_id' => $this->puesto->id]),
        $this->medico->id,
    );

    $this->cargo->update([
        'nombre' => 'Especialista en Ciberseguridad',
        'codigo_ciuo' => '2529',
    ]);

    $guardada = FichaSaludOcupacional::findOrFail($ficha->id);

    // El vínculo sigue apuntando a la estructura viva; el texto es la
    // fotografía del día de la evaluación y no se mueve.
    expect($guardada->puesto_id)->toBe($this->puesto->id)
        ->and($guardada->puesto_trabajo)->toBe('Analista de Seguridad Informática')
        ->and($guardada->puesto->cargo->nombre)->toBe('Especialista en Ciberseguridad');
});

test('un cargo sin CIUO deja la ficha sin código, no con uno inventado', function () {
    $this->cargo->update(['codigo_ciuo' => null]);

    $ficha = $this->servicio->registrar(
        ($this->datosFicha)([
            'puesto_id' => $this->puesto->id,
            'puesto_trabajo_ciuo' => '1234',
        ]),
        $this->medico->id,
    );

    expect($ficha->puesto_trabajo_ciuo)->toBeNull();
});

// ── Sección G: factores de riesgo ───────────────────────────────

test('el endpoint sirve el catálogo oficial de riesgos', function () {
    $rol = \Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    );
    $this->medico->assignRole($rol);
    $this->actingAs($this->medico, 'sanctum');

    $catalogo = $this->getJson('/api/v1/dispensario/fichas-sso/catalogo-riesgos')
        ->assertOk()
        ->json('datos');

    expect(array_keys($catalogo))->toHaveCount(6)
        ->and($catalogo['seguridad']['grupos'])->toHaveCount(4)
        ->and($catalogo['fisico']['grupos'][0]['factores'])->toContain('Temperaturas altas');
});

test('la subcategoría del factor se sella desde el catálogo', function () {
    $ficha = $this->servicio->registrar([
        'ficha' => [
            'servidor_id' => $this->servidor->id,
            'fecha_evaluacion' => '2026-08-28',
            'tipo_ficha' => 'periodica',
            'aptitud' => 'apto',
        ],
        'actividades' => [
            ['actividad' => 'Mantenimiento de vehículos', 'orden' => 1],
        ],
        'factores_riesgo' => [
            ['categoria' => 'seguridad', 'factor' => 'Cortes', 'presente' => true, 'actividad_index' => 0],
            ['categoria' => 'fisico', 'factor' => 'Ruido', 'presente' => true, 'actividad_index' => 0],
        ],
    ], $this->medico->id);

    $porFactor = $ficha->factoresRiesgo->keyBy('factor');

    // «De seguridad» es la única categoría que el MSP subdivide.
    expect($porFactor['Cortes']->subcategoria)->toBe('mecanicos')
        ->and($porFactor['Ruido']->subcategoria)->toBeNull();
});

test('se rechaza un factor de riesgo que no está en el catálogo del MSP', function () {
    $rol = \Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    );
    $this->medico->assignRole($rol);
    $this->actingAs($this->medico, 'sanctum');

    $respuesta = $this->postJson('/api/v1/dispensario/fichas-sso', [
        'ficha' => [
            'servidor_id' => $this->servidor->id,
            'fecha_evaluacion' => '2026-08-28',
            'tipo_ficha' => 'periodica',
            'aptitud' => 'apto',
        ],
        'actividades' => [['actividad' => 'Conducción', 'orden' => 1]],
        'factores_riesgo' => [[
            // Existía en el catálogo anterior del frontend, pero no en el MSP.
            'categoria' => 'fisico',
            'factor' => 'Trabajo en alturas',
            'presente' => true,
            'actividad_index' => 0,
        ]],
    ])->assertStatus(422);

    // La API envuelve los errores en 'errores', no en el 'errors' de Laravel,
    // y la clave lleva puntos, así que assertJsonPath los tomaría como
    // separadores de ruta.
    $errores = $respuesta->json('errores');

    expect($errores)->toHaveKey('factores_riesgo.0.factor')
        ->and($errores['factores_riesgo.0.factor'][0])
        ->toBe('Ese factor de riesgo no existe en el formulario del MSP.');
});

// ── Campos del MSP que faltaban ─────────────────────────────────

test('se guardan los cuatro grupos de atención prioritaria del MSP', function () {
    $ficha = $this->servicio->registrar(
        ($this->datosFicha)([
            'grupo_embarazada' => false,
            'grupo_discapacidad' => true,
            'porcentaje_discapacidad' => '40',
            'grupo_enfermedad_catastrofica' => true,
            'grupo_adulto_mayor' => true,
        ]),
        $this->medico->id,
    );

    expect($ficha->grupo_discapacidad)->toBeTrue()
        ->and($ficha->grupo_enfermedad_catastrofica)->toBeTrue()
        ->and($ficha->grupo_adulto_mayor)->toBeTrue()
        ->and($ficha->porcentaje_discapacidad)->toBe('40');
});

test('las respuestas de urgencia distinguen «no» de «sin respuesta»', function () {
    $ficha = $this->servicio->registrar(
        ($this->datosFicha)([
            'autoriza_transfusion' => false,
            // `tratamiento_hormonal` no se envía: nadie preguntó.
        ]),
        $this->medico->id,
    );

    expect($ficha->autoriza_transfusion)->toBeFalse()
        ->and($ficha->tratamiento_hormonal)->toBeNull();
});

test('se guardan lateralidad y las fechas de reintegro y salida', function () {
    $ficha = $this->servicio->registrar(
        ($this->datosFicha)([
            'tipo_ficha' => 'retiro',
            'lateralidad' => 'izquierda',
            'fecha_reintegro' => '2026-03-01',
            'fecha_ultimo_dia_laboral' => '2026-08-15',
        ]),
        $this->medico->id,
    );

    expect($ficha->lateralidad)->toBe('izquierda')
        ->and($ficha->fecha_reintegro->toDateString())->toBe('2026-03-01')
        ->and($ficha->fecha_ultimo_dia_laboral->toDateString())->toBe('2026-08-15');
});

test('se guardan el perímetro abdominal y la marca de trabajo actual', function () {
    $ficha = $this->servicio->registrar([
        ...($this->datosFicha)(),
        'constantes_vitales' => [
            'peso_kg' => 78.5,
            'talla_cm' => 172,
            'perimetro_abdominal_cm' => 94.5,
        ],
        'empleos_anteriores' => [
            ['centro_trabajo' => 'GAD Esmeraldas', 'es_trabajo_actual' => true],
            ['centro_trabajo' => 'Municipio de Atacames', 'es_trabajo_actual' => false],
        ],
    ], $this->medico->id);

    expect((float) $ficha->constantesVitales->perimetro_abdominal_cm)->toBe(94.5);

    $porCentro = $ficha->empleosAnteriores->keyBy('centro_trabajo');
    expect($porCentro['GAD Esmeraldas']->es_trabajo_actual)->toBeTrue()
        ->and($porCentro['Municipio de Atacames']->es_trabajo_actual)->toBeFalse();
});

test('la lateralidad solo admite derecha o izquierda', function () {
    $rol = \Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    );
    $this->medico->assignRole($rol);
    $this->actingAs($this->medico, 'sanctum');

    $this->postJson('/api/v1/dispensario/fichas-sso', [
        'ficha' => [
            'servidor_id' => $this->servidor->id,
            'fecha_evaluacion' => '2026-08-28',
            'tipo_ficha' => 'periodica',
            'aptitud' => 'apto',
            'lateralidad' => 'ambidiestro',
        ],
    ])->assertStatus(422);
});

// ── Sección O: datos del profesional ────────────────────────────

test('la ficha expone el código médico de quien la evalúa', function () {
    // El médico es un servidor con registro profesional ante el ACESS.
    $evaluador = Servidor::create([
        'cedula' => '0801234567',
        'nombre' => 'Gregory',
        'apellido' => 'House',
        'puesto_id' => $this->puesto->id,
        'codigo_medico' => 'ACESS-08-4471',
    ]);
    $this->medico->update(['servidor_id' => $evaluador->id]);

    $ficha = $this->servicio->registrar(
        ($this->datosFicha)(['puesto_id' => $this->puesto->id]),
        $this->medico->id,
    );

    expect($ficha->evaluador->servidor->codigo_medico)->toBe('ACESS-08-4471');
});

test('el código médico es opcional: no todo servidor es personal de salud', function () {
    $ficha = $this->servicio->registrar(
        ($this->datosFicha)(),
        $this->medico->id,
    );

    expect($this->servidor->codigo_medico)->toBeNull()
        ->and($ficha->id)->toBeGreaterThan(0);
});
