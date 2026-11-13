<?php

namespace Tests\Feature\Expediente;

use App\Enums\Permiso;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\BrechasDePerfilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Las brechas de perfil (docs/diseno-manual-de-puestos.md, P7; PR 17): cada
| servidor frente al perfil de su puesto, para el plan de capacitación.
*/

const RUTA_BRECHAS = '/api/v1/expediente/brechas-de-perfil';

/** Un servidor con contrato vigente en el puesto. */
function servidorEnPuesto(Puesto $puesto, string $apellido, array $contrato = []): Servidor
{
    static $n = 0;
    $n++;

    Servidor::unguard();
    $servidor = Servidor::create([
        'cedula' => sprintf('08%08d', $n), 'nombre' => 'Prueba', 'apellido' => $apellido, 'estado' => true,
    ]);
    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
        'puesto_id'                => $puesto->id,
        'fecha_inicio'             => '2026-01-01',
        'estado'                   => 'vigente',
        'origen'                   => 'accion_personal',
        ...$contrato,
    ]);

    return $servidor;
}

function formacionDe(Servidor $servidor, string $titulo, ?string $nivel, string $estado = 'validado', string $tipo = 'estudio', string $fin = '2020-12-31'): void
{
    HistorialAcademicoServidor::create([
        'servidor_id' => $servidor->id, 'tipo_estudio' => $tipo, 'nivel_estudio' => $nivel,
        'nacionalidad_estudio' => 'nacional', 'institucion' => 'Universidad de Prueba',
        'fecha_inicio' => '2016-01-01', 'fecha_fin' => $fin, 'titulo_capacitacion' => $titulo, 'estado' => $estado,
    ]);
}

/** El perfil con sus alternativas de instrucción y sus temáticas de capacitación. */
function requisitosDelPuesto(Puesto $puesto, array $niveles, ?string $capacitacion = null): void
{
    $perfil = $puesto->perfil()->create(['capacitacion' => $capacitacion]);
    foreach ($niveles as $i => $nivel) {
        $perfil->requisitos()->create(['nivel_instruccion' => $nivel, 'meses_experiencia' => 12, 'orden' => $i + 1]);
    }
}

function filaDe(array $informe, Servidor $servidor): array
{
    return collect($informe['filas'])->firstWhere('servidor.id', $servidor->id);
}

beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'lector-brechas-prueba', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo(Permission::firstOrCreate(['name' => Permiso::VER_EXPEDIENTE_TODOS->value, 'guard_name' => 'sanctum']));
    $this->talentoHumano = User::factory()->create();
    $this->talentoHumano->assignRole($rol);

    $this->administrativa = unidadDePrueba(['nombre' => 'Gestión Administrativa', 'nivel' => 2]);
    $this->bodega = unidadDePrueba(['nombre' => 'Activos Fijos y Bodega', 'nivel' => 3, 'unidad_padre_id' => $this->administrativa->id]);
    $this->financiera = unidadDePrueba(['nombre' => 'Gestión Financiera', 'nivel' => 2]);

    $this->analista = puestoDePrueba($this->administrativa, 'Analista Administrativo');
    requisitosDelPuesto($this->analista, ['tercer_nivel', 'tecnico_superior'], 'Contratación pública y control de bienes.');
});

// ── Permisos ─────────────────────────────────────────────────────────────

test('el informe y su Excel son de quien ve todos los expedientes', function () {
    servidorEnPuesto($this->analista, 'Mendoza');

    $this->actingAs(User::factory()->create(), 'sanctum');
    $this->getJson(RUTA_BRECHAS)->assertForbidden();
    $this->get(RUTA_BRECHAS.'/excel')->assertForbidden();

    $this->actingAs($this->talentoHumano, 'sanctum');
    $this->getJson(RUTA_BRECHAS)->assertOk()->assertJsonPath('datos.resumen.total', 1);
    $excel = $this->get(RUTA_BRECHAS.'/excel')->assertOk();
    expect($excel->headers->get('Content-Disposition'))->toContain('brechas_de_perfil_');
});

// ── Instrucción ──────────────────────────────────────────────────────────

test('cumple con el título validado más alto que alcance alguna alternativa', function () {
    $conTercerNivel = servidorEnPuesto($this->analista, 'Andrade');
    formacionDe($conTercerNivel, 'Bachiller en Ciencias', 'secundaria');
    formacionDe($conTercerNivel, 'Ingeniero Comercial', 'tercer_nivel');

    // Tecnológico superior es más que el técnico superior que también acepta.
    $conTecnologo = servidorEnPuesto($this->analista, 'Bravo');
    formacionDe($conTecnologo, 'Tecnólogo en Contabilidad', 'tecnologico_superior');

    $informe = app(BrechasDePerfilService::class)->informe();

    expect(filaDe($informe, $conTercerNivel)['instruccion'])->toBe([
        'estado'      => 'cumple',
        'tiene'       => 'Tercer nivel de grado',
        'pide'        => ['Tercer nivel de grado', 'Técnico superior'],
        'por_validar' => 0,
    ])
        ->and(filaDe($informe, $conTecnologo)['instruccion']['estado'])->toBe('cumple');
});

test('solo cuentan los títulos validados', function () {
    $porValidar = servidorEnPuesto($this->analista, 'Cedeño');
    formacionDe($porValidar, 'Bachiller en Ciencias', 'secundaria');
    formacionDe($porValidar, 'Ingeniero Comercial', 'tercer_nivel', 'por_validar');
    formacionDe($porValidar, 'Licenciado en Administración', 'tercer_nivel', 'rechazado');

    $sinTitulos = servidorEnPuesto($this->analista, 'Delgado');

    $informe = app(BrechasDePerfilService::class)->informe();

    expect(filaDe($informe, $porValidar)['instruccion'])->toMatchArray([
        'estado' => 'no_cumple', 'tiene' => 'Secundaria', 'por_validar' => 1,
    ])
        ->and(filaDe($informe, $sinTitulos)['instruccion'])->toMatchArray(['estado' => 'no_cumple', 'tiene' => null]);
});

test('los estudios en curso los cubre un tercer nivel; si no, quedan por revisar', function () {
    $puesto = puestoDePrueba($this->administrativa, 'Asistente Administrativo');
    requisitosDelPuesto($puesto, ['estudios_superiores_en_curso']);
    $bachiller = servidorEnPuesto($puesto, 'Espinoza');
    formacionDe($bachiller, 'Bachiller en Ciencias', 'secundaria');
    $graduado = servidorEnPuesto($puesto, 'Franco');
    formacionDe($graduado, 'Licenciada en Secretariado', 'tercer_nivel');

    $informe = app(BrechasDePerfilService::class)->informe();

    expect(filaDe($informe, $bachiller)['instruccion']['estado'])->toBe('por_revisar')
        ->and(filaDe($informe, $graduado)['instruccion']['estado'])->toBe('cumple');
});

test('sin requisitos en el perfil no hay con qué comparar', function () {
    $sinPerfil = servidorEnPuesto(puestoDePrueba($this->administrativa, 'Chofer'), 'García');

    expect(filaDe(app(BrechasDePerfilService::class)->informe(), $sinPerfil)['instruccion'])
        ->toMatchArray(['estado' => 'sin_requisitos', 'pide' => []]);
});

// ── Capacitación ─────────────────────────────────────────────────────────

test('marca los cursos validados que tratan de las temáticas del perfil', function () {
    $servidor = servidorEnPuesto($this->analista, 'Hurtado');
    formacionDe($servidor, 'Taller de contratos del sector público', null, tipo: 'capacitacion', fin: '2024-05-10');
    formacionDe($servidor, 'Control de bienes y existencias', null, tipo: 'capacitacion', fin: '2025-03-01');
    formacionDe($servidor, 'Primeros auxilios', null, tipo: 'capacitacion');
    // Uno por validar no cuenta.
    formacionDe($servidor, 'Contratación pública avanzada', null, 'por_validar', 'capacitacion');

    $capacitacion = filaDe(app(BrechasDePerfilService::class)->informe(), $servidor)['capacitacion'];

    expect($capacitacion)->toBe([
        'tematicas'    => 'Contratación pública y control de bienes.',
        'registradas'  => 3,
        // La más reciente primero; «sector público» solo no relaciona nada.
        'relacionadas' => [
            ['titulo' => 'Control de bienes y existencias', 'fecha' => '2025-03-01'],
            ['titulo' => 'Taller de contratos del sector público', 'fecha' => '2024-05-10'],
        ],
    ]);
});

// ── El informe ───────────────────────────────────────────────────────────

test('filtra por gestión con sus subprocesos, y solo contratos vigentes', function () {
    $enLaGestion = servidorEnPuesto($this->analista, 'Ibarra');
    $enElSubproceso = servidorEnPuesto(puestoDePrueba($this->bodega, 'Guardalmacén'), 'Jaramillo');
    servidorEnPuesto(puestoDePrueba($this->financiera, 'Contador'), 'Klinger');
    servidorEnPuesto($this->analista, 'López', ['estado' => 'terminado', 'fecha_fin' => '2026-06-30']);

    $this->actingAs($this->talentoHumano, 'sanctum');
    $datos = $this->getJson(RUTA_BRECHAS.'?unidad_administrativa_id='.$this->administrativa->id)->assertOk()->json('datos');

    expect(collect($datos['filas'])->pluck('servidor.id')->sort()->values()->all())
        ->toBe(collect([$enLaGestion->id, $enElSubproceso->id])->sort()->values()->all())
        ->and($datos['resumen'])->toBe([
            'total' => 2, 'cumple' => 0, 'no_cumple' => 1, 'por_revisar' => 0, 'sin_requisitos' => 1,
            'sin_capacitacion_relacionada' => 1,
        ]);

    $this->getJson(RUTA_BRECHAS.'?unidad_administrativa_id=999999')->assertUnprocessable();
});
