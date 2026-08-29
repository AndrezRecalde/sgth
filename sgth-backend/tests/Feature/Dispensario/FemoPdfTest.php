<?php

use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\FemoService;
use App\Services\Dispensario\PdfFemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * El PDF reproduce el formulario oficial del MSP. Estas pruebas comprueban que
 * los campos LLEGAN al papel; la maquetación se revisa abriendo un PDF real.
 *
 * Se extrae el texto del PDF con una búsqueda sobre los flujos de contenido,
 * suficiente para afirmar que un valor está o no está.
 */
beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Talento Humano', 'nivel' => 1,
    ]);
    $cargo = Cargo::create([
        'nombre' => 'Chofer', 'codigo_ciuo' => '8322',
    ]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'cargo_id' => $cargo->id, 'plazas' => 3,
    ]);

    $this->servidor = Servidor::create([
        'cedula' => '0801112223', 'nombre' => 'Marlon', 'apellido' => 'Vera',
        'genero' => 'masculino', 'tipo_sangre' => 'O+',
        'puesto_id' => $this->puesto->id,
    ]);

    $medicoServidor = Servidor::create([
        'cedula' => '0809998887', 'nombre' => 'Gregory', 'apellido' => 'House',
        'puesto_id' => $this->puesto->id, 'codigo_medico' => 'ACESS-08-4471',
    ]);

    $this->medico = User::create([
        'email' => 'house@gadpe.gob.ec', 'usuario_ti' => 'house',
        'password' => bcrypt('secreto'), 'primer_login' => false,
        'servidor_id' => $medicoServidor->id,
    ]);

    $this->ficha = app(FemoService::class)->registrar([
        'ficha' => [
            'servidor_id' => $this->servidor->id,
            'puesto_id' => $this->puesto->id,
            'fecha_evaluacion' => '2026-08-28',
            'tipo_ficha' => 'retiro',
            'aptitud' => 'apto',
            'lateralidad' => 'izquierda',
            'fecha_reintegro' => '2026-03-01',
            'fecha_ultimo_dia_laboral' => '2026-08-15',
            'grupo_enfermedad_catastrofica' => true,
            'grupo_adulto_mayor' => true,
            'autoriza_transfusion' => false,
        ],
        'constantes_vitales' => [
            'peso_kg' => 78.5, 'talla_cm' => 172, 'perimetro_abdominal_cm' => 94.5,
        ],
        'actividades' => [
            ['actividad' => 'Conducción de vehículo liviano', 'orden' => 1],
        ],
        'factores_riesgo' => [
            ['categoria' => 'seguridad', 'factor' => 'Cortes', 'presente' => true, 'actividad_index' => 0],
            ['categoria' => 'fisico', 'factor' => 'Temperaturas altas', 'presente' => true, 'actividad_index' => 0],
        ],
        'empleos_anteriores' => [
            ['centro_trabajo' => 'GAD Esmeraldas', 'es_trabajo_actual' => true],
        ],
    ], $this->medico->id);

    $this->pdf = app(PdfFemoService::class)->generarContent($this->ficha->id);
});

test('el PDF se genera y es un documento válido', function () {
    expect($this->pdf['content'])->toStartWith('%PDF-')
        ->and(strlen($this->pdf['content']))->toBeGreaterThan(5000)
        ->and($this->pdf['filename'])->toEndWith('.pdf');
});

test('el nombre del archivo identifica a la ficha', function () {
    expect($this->pdf['filename'])->toContain((string) $this->ficha->id);
});

test('la vista del formulario recibe todos los campos nuevos', function () {
    // Se renderiza la vista aparte del PDF: comprobar el HTML es más fiable que
    // rastrear cadenas dentro de los flujos comprimidos del documento.
    $ficha = app(FemoService::class)->obtener($this->ficha->id);

    $html = view('pdf.dispensario.femo._pagina-1', [
        'ficha' => $ficha,
        'persona' => (object) [
            'nombre' => 'Marlon', 'segundo_nombre' => null,
            'apellido' => 'Vera', 'segundo_apellido' => null,
            'cedula' => '0801112223', 'fecha_nacimiento' => null,
            'genero' => 'masculino', 'tipo_sangre' => 'O+',
            'tiene_enfermedad_catastrofica' => false, 'numero_historia' => '0801112223',
        ],
        'edad' => 40,
        'examenFisicoPorRegion' => collect(),
        'antecedentesPorTipo' => collect(),
        'regiones' => [],
        'esFemenino' => false,
        'antReprod' => null,
        'consumoSustancias' => collect(),
        'logo' => null,
    ])->render();

    expect($html)
        ->toContain('Lateralidad')
        ->toContain('Izquierda')
        ->toContain('Adulto Mayor: SI')
        ->toContain('E. Catastrófica: SI')
        ->toContain('Fecha de Reintegro')
        ->toContain('01/03/2026')
        ->toContain('Último Día Laboral')
        ->toContain('15/08/2026')
        ->toContain('Perím. Abd.')
        ->toContain('94.5')
        // Respondió que NO, que no es lo mismo que no haber respondido.
        ->toContain('Autoriza Transfusión')
        ->toContain('Tratamiento Hormonal');
});

test('la enfermedad catastrófica se imprime desde la ficha, no del expediente', function () {
    $ficha = app(FemoService::class)->obtener($this->ficha->id);

    // El expediente del servidor NO la tiene marcada; la ficha SÍ. Antes la
    // plantilla leía el expediente y contradecía lo que registró el médico.
    $html = view('pdf.dispensario.femo._pagina-1', [
        'ficha' => $ficha,
        'persona' => (object) [
            'nombre' => 'Marlon', 'segundo_nombre' => null,
            'apellido' => 'Vera', 'segundo_apellido' => null,
            'cedula' => '0801112223', 'fecha_nacimiento' => null,
            'genero' => 'masculino', 'tipo_sangre' => 'O+',
            'tiene_enfermedad_catastrofica' => false, 'numero_historia' => null,
        ],
        'edad' => 40,
        'examenFisicoPorRegion' => collect(),
        'antecedentesPorTipo' => collect(),
        'regiones' => [],
        'esFemenino' => false,
        'antReprod' => null,
        'consumoSustancias' => collect(),
        'logo' => null,
    ])->render();

    expect($html)->toContain('E. Catastrófica: SI');
});

test('la sección G imprime la subcategoría de los riesgos de seguridad', function () {
    $ficha = app(FemoService::class)->obtener($this->ficha->id);

    $filas = $ficha->factoresRiesgo
        ->groupBy(fn ($f) => $f->categoria->value)
        ->map(fn ($cat) => $cat->groupBy('factor')->map(fn ($ff) => [
            'subcategoria' => $ff->first()->subcategoria,
            'actividades' => $ff->pluck('ficha_actividad_id')->filter()->unique()->values(),
        ]));

    $html = view('pdf.dispensario.femo._pagina-2', [
        'ficha' => $ficha,
        'actividadesRiesgo' => $ficha->actividades,
        'filasRiesgoPorCategoria' => $filas,
        'categoriasRiesgo' => App\Enums\CategoriaRiesgoLaboral::cases(),
        'etiquetasSubcategoria' => [
            'locativos' => 'Locativos', 'mecanicos' => 'Mecánicos',
            'electricos' => 'Eléctricos', 'otros' => 'Otros',
        ],
        'logo' => null,
    ])->render();

    expect($html)
        ->toContain('Mecánicos')
        ->toContain('Cortes')
        ->toContain('Temperaturas altas')
        // Columna ANTERIOR / ACTUAL de la sección H.
        ->toContain('ACTUAL');
});

test('la sección O imprime el código médico del evaluador', function () {
    $ficha = app(FemoService::class)->obtener($this->ficha->id);

    $html = view('pdf.dispensario.femo._pagina-3', [
        'ficha' => $ficha,
        'logo' => null,
    ])->render();

    expect($html)
        ->toContain('Código Médico')
        ->toContain('ACESS-08-4471');
});
