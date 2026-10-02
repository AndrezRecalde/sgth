<?php

use App\Enums\AptitudMedica;
use App\Enums\TipoFichaFemo;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\CertificadoAptitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El certificado de aptitud médica ocupacional.
|
| Es el documento que Talento Humano necesita de una evaluación y que el FEMO
| no puede ser: el formulario 028 lleva antecedentes, examen físico y
| diagnósticos CIE-10, y eso es historia clínica (acuerdo con la UATH del
| 2026-09-26). Aquí viaja la aptitud, sus restricciones, la vigencia y quién
| firma.
|
| Las pruebas de contenido renderizan la plantilla, no el PDF: dompdf comprime
| el flujo del documento (`/FlateDecode`), así que sobre los bytes no se puede
| comprobar qué salió ni —lo que más importa aquí— qué no salió.
*/

function usuarioDeRolCertificado(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole(
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum'])
    );

    return $usuario;
}

function escenarioCertificado(
    string $aptitud = 'apto_con_restricciones',
    ?string $restricciones = 'Evitar levantar peso mayor a 10 kg.',
    bool $conFicha = true,
    string $estado = 'completada',
): array {
    $unidad = UnidadAdministrativa::create([
        'codigo' => 'OBR-09', 'nombre' => 'Dirección de Obras Públicas', 'nivel' => 1,
    ]);

    $grupo = GrupoOcupacional::create([
        'grado_codigo' => 'SPA3', 'grado_numerico' => 3, 'grupo' => 'Servicios',
        'denominacion_generica' => 'Operador', 'rmu' => 900.00,
        'regimen' => 'codigo_trabajo', 'activo' => true,
    ]);

    $cargo = Cargo::create([
        'nombre' => 'Operador de maquinaria pesada', 'activo' => true,
    ]);

    $puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id,
        'grupo_ocupacional_id' => $grupo->id,
        'cargo_id' => $cargo->id,
        'plazas' => 3,
        'regimen_laboral' => 'codigo_trabajo',
        'activo' => true,
    ]);

    $medico = Servidor::create([
        'cedula' => '0801111222', 'nombre' => 'Rosa', 'apellido' => 'Valencia',
        'regimen_laboral' => 'losep', 'estado' => true,
        'codigo_medico' => 'ACESS-08-4471',
    ]);

    $usuarioMedico = User::factory()->create(['servidor_id' => $medico->id]);

    $servidor = Servidor::create([
        'cedula' => '0804455661',
        'nombre' => 'Luis', 'segundo_nombre' => 'Alberto',
        'apellido' => 'Quiñónez', 'segundo_apellido' => 'Bone',
        'regimen_laboral' => 'codigo_trabajo',
        'unidad_administrativa_id' => $unidad->id,
        'puesto_id' => $puesto->id,
        'estado' => true,
    ]);

    $solicitante = usuarioDeRolCertificado('admin-uath');

    $ficha = $conFicha
        ? FichaSaludOcupacional::create([
            'servidor_id' => $servidor->id,
            'puesto_id' => $puesto->id,
            'fecha_evaluacion' => '2026-03-12',
            'tipo_ficha' => TipoFichaFemo::PERIODICA,
            'aptitud' => $aptitud,
            'restricciones' => $restricciones,
            // Lo que NO debe salir del Dispensario.
            'observaciones' => 'Refiere lumbalgia mecánica de tres meses de evolución.',
            'evaluador_id' => $usuarioMedico->id,
            'estado' => true,
        ])
        : null;

    $solicitud = SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica',
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => 'Luis Quiñónez',
        'solicitado_por' => $solicitante->id,
        'estado' => $estado,
        'dictamen' => $estado === 'completada' ? 'apto_con_restricciones' : null,
        'ficha_femo_id' => $ficha?->id,
    ]);

    return compact('solicitud', 'ficha', 'servidor', 'solicitante');
}

function rutaCertificado(int $id): string
{
    return "/api/v1/dispensario/solicitudes-certificacion/{$id}/certificado-aptitud";
}

/** El certificado como HTML: la misma vista y los mismos datos que el PDF. */
function certificadoRenderizado(int $solicitudId): string
{
    $servicio = app(CertificadoAptitudService::class);

    return view(
        CertificadoAptitudService::VISTA,
        $servicio->componer($solicitudId)['datos'],
    )->render();
}

test('Talento Humano descarga el certificado de aptitud', function (string $rol) {
    ['solicitud' => $solicitud] = escenarioCertificado();

    $respuesta = $this->actingAs(usuarioDeRolCertificado($rol), 'sanctum')
        ->get(rutaCertificado($solicitud->id));

    $respuesta->assertStatus(200);

    expect($respuesta->headers->get('content-type'))->toContain('application/pdf')
        ->and($respuesta->headers->get('content-disposition'))
        ->toContain('certificado-aptitud-0804455661-'.$solicitud->id.'.pdf');
})->with(['admin-uath', 'asistente-uath', 'analista-uath', 'medico']);

test('el certificado lleva la aptitud, las restricciones y quién firma', function () {
    ['solicitud' => $solicitud] = escenarioCertificado();

    $html = certificadoRenderizado($solicitud->id);

    expect($html)
        ->toContain('CERTIFICADO DE APTITUD')
        ->toContain('Apto con Restricciones')
        ->toContain('Evitar levantar peso mayor a 10 kg.')
        ->toContain('Operador de maquinaria pesada')
        ->toContain('Obras')
        ->toContain('Valencia')
        ->toContain('Registro profesional ACESS-08-4471')
        // El prefijo no se repite: `codigo_medico` ya trae el suyo.
        ->not->toContain('Registro ACESS ACESS')
        ->toContain('12/03/2026');
});

test('la vigencia se cuenta con el mismo plazo que usa el tablero', function () {
    ['solicitud' => $solicitud] = escenarioCertificado();

    $html = certificadoRenderizado($solicitud->id);

    // Evaluada el 12/03/2026 + los dos años de la UATH.
    expect($html)->toContain('12/03/2028');
});

test('el certificado NO lleva texto clínico', function () {
    ['solicitud' => $solicitud] = escenarioCertificado();

    $html = certificadoRenderizado($solicitud->id);

    // Es la razón de existir de este documento: la ficha tiene observaciones
    // clínicas y no salen. Si alguien añade `$ficha->observaciones` a la
    // plantilla, esta prueba lo para.
    expect($html)
        ->not->toContain('lumbalgia')
        ->not->toContain('Refiere');
});

test('sin restricciones el bloque no se imprime vacío', function () {
    ['solicitud' => $solicitud] = escenarioCertificado(
        aptitud: 'apto', restricciones: null
    );

    $html = certificadoRenderizado($solicitud->id);

    expect($html)
        ->toContain('Apto')
        ->not->toContain('Restricciones y recomendaciones');
});

test('las cuatro aptitudes de la ficha salen con su etiqueta', function (string $valor, string $etiqueta) {
    ['solicitud' => $solicitud] = escenarioCertificado(
        aptitud: $valor, restricciones: null
    );

    $html = certificadoRenderizado($solicitud->id);

    expect($html)->toContain($etiqueta);
})->with([
    ['apto', 'Apto'],
    ['apto_con_restricciones', 'Apto con Restricciones'],
    ['en_observacion', 'Apto en Observación'],
    ['no_apto', 'No Apto'],
]);

test('una evaluación sin ficha no puede certificarse', function () {
    ['solicitud' => $solicitud] = escenarioCertificado(conFicha: false);

    $this->actingAs(usuarioDeRolCertificado('admin-uath'), 'sanctum')
        ->getJson(rutaCertificado($solicitud->id))
        ->assertStatus(422);
});

test('una evaluación en curso no tiene aptitud que certificar', function () {
    ['solicitud' => $solicitud] = escenarioCertificado(estado: 'en_proceso');

    $this->actingAs(usuarioDeRolCertificado('admin-uath'), 'sanctum')
        ->getJson(rutaCertificado($solicitud->id))
        ->assertStatus(422);
});

test('un rol ajeno al trámite no descarga el certificado', function () {
    ['solicitud' => $solicitud] = escenarioCertificado();

    $this->actingAs(usuarioDeRolCertificado('jefe-unidad'), 'sanctum')
        ->getJson(rutaCertificado($solicitud->id))
        ->assertStatus(403);
});

test('el tablero de cobertura dice si la evaluación tiene ficha', function () {
    ['solicitud' => $solicitud] = escenarioCertificado();

    $respuesta = $this->actingAs(usuarioDeRolCertificado('admin-uath'), 'sanctum')
        ->getJson('/api/v1/dispensario/certificaciones/cobertura');

    $fila = collect($respuesta->json('datos.data'))
        ->firstWhere('cedula', '0804455661');

    expect($fila['ultima_solicitud_id'])->toBe($solicitud->id)
        ->and($fila['ultima_ficha_id'])->not->toBeNull();
});

test('el certificado cabe en una hoja, aun con restricciones largas', function () {
    // El fallo histórico de estas plantillas en dompdf: el bloque de firma se
    // empuja entero a una segunda hoja casi vacía (ver los comentarios de
    // `certificado-laboral.blade.php`). Se comprueba sobre el PDF, no sobre el
    // HTML, porque es el paginador de dompdf lo que se está probando.
    ['solicitud' => $solicitud] = escenarioCertificado(
        restricciones: 'Evitar la manipulación manual de cargas superiores a 10 kg, '
            .'la exposición prolongada a vibración de cuerpo entero y la permanencia '
            .'en posturas forzadas de flexión del tronco por más de treinta minutos '
            .'continuos. Requiere rotación de tarea cada dos horas y pausas activas '
            .'de cinco minutos. Reevaluación en seis meses o antes si aparece '
            .'sintomatología. No se recomienda la conducción de maquinaria pesada '
            .'en turnos nocturnos mientras persista el cuadro.'
    );

    $pdf = app(App\Services\Dispensario\CertificadoAptitudService::class)
        ->generarContent($solicitud->id)['content'];

    // El objeto /Pages declara cuántas hojas tiene el documento. Es legible sin
    // descomprimir: lo que dompdf comprime es el contenido, no la estructura.
    // Se extrae el número en vez de afirmar sobre los bytes, porque al fallar
    // una aserción sobre el PDF entero vuelca megabytes de binario.
    preg_match('#/Type /Pages.*?/Count (\d+)#s', $pdf, $coincidencias);

    expect((int) ($coincidencias[1] ?? 0))->toBe(1);
});

test('a un candidato de selección no se le llama servidor', function () {
    $solicitante = usuarioDeRolCertificado('analista-uath');

    // No hay factory de Postulante, y `convocatoria_id` no admite nulos: el
    // andamio mínimo es unidad -> grupo -> puesto -> convocatoria.
    $unidad = UnidadAdministrativa::create([
        'codigo' => 'SEL-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);
    $grupo = GrupoOcupacional::create([
        'grado_codigo' => 'SP5', 'grado_numerico' => 5, 'grupo' => 'Profesional',
        'denominacion_generica' => 'Analista', 'rmu' => 1300.00,
        'regimen' => 'losep', 'activo' => true,
    ]);
    $puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id,
        'grupo_ocupacional_id' => $grupo->id,
        'plazas' => 1, 'regimen_laboral' => 'losep', 'activo' => true,
    ]);
    $convocatoria = App\Models\Seleccion\Convocatoria::create([
        'puesto_id' => $puesto->id,
        'codigo' => 'CNV-2026-900',
        'titulo' => 'Analista de Talento Humano',
        'descripcion' => 'Concurso de méritos y oposición',
        'fecha_inicio' => now()->subDays(20),
        'fecha_fin' => now()->addDays(10),
        'tipo_proceso' => App\Enums\TipoProcesoConvocatoria::FORMAL,
    ]);

    $postulante = App\Models\Seleccion\Postulante::create([
        'convocatoria_id' => $convocatoria->id,
        'cedula' => '1756677889',
        'nombres' => 'Mariela', 'apellidos' => 'Chica',
        'correo' => 'mariela.chica@example.test',
        'genero' => 'femenino', 'estado_civil' => 'soltero',
        'fecha_nacimiento' => '1996-02-20',
        // El `estado` por defecto de la columna no pasa su propio check: se
        // fija el que corresponde a quien ya va a evaluación médica.
        'estado' => App\Enums\EstadoPostulante::GANADOR_POTENCIAL,
    ]);

    $medico = Servidor::create([
        'cedula' => '0802222333', 'nombre' => 'Rosa', 'apellido' => 'Valencia',
        'regimen_laboral' => 'losep', 'estado' => true, 'codigo_medico' => 'ACESS-08-4471',
    ]);
    $usuarioMedico = User::factory()->create(['servidor_id' => $medico->id]);

    $ficha = FichaSaludOcupacional::create([
        'postulante_id' => $postulante->id,
        'fecha_evaluacion' => '2026-03-12',
        'tipo_ficha' => TipoFichaFemo::INGRESO,
        'aptitud' => AptitudMedica::APTO,
        'evaluador_id' => $usuarioMedico->id,
        'estado' => true,
    ]);

    $solicitud = SolicitudCertificacionMedica::create([
        'tipo_evento' => 'ingreso',
        'origen' => 'reclutamiento',
        'postulante_id' => $postulante->id,
        'cedula_paciente' => $postulante->cedula,
        'nombres_paciente' => trim("{$postulante->nombres} {$postulante->apellidos}"),
        'solicitado_por' => $solicitante->id,
        'estado' => 'completada',
        'dictamen' => 'apto',
        'ficha_femo_id' => $ficha->id,
    ]);

    $html = certificadoRenderizado($solicitud->id);

    expect($html)
        ->toContain('el/la aspirante')
        ->not->toContain('el/la servidor/a')
        ->toContain($postulante->cedula);
});
