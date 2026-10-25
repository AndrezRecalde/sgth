<?php

/*
| El reporte «Lectura de resultados» (6.5 de docs/diseno-examenes-medicos.md):
| los resultados que llegaron en el período, cuántos esperan su lectura,
| cuántos salieron normales, alterados o críticos, y en cuántos días se leen.
| Por mes o por el médico que responde (D3). Agregado.
*/

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

function usuarioLecturaResultados(string $alias, string $rol, ?string $nombre = null): User
{
    $servidor = null;
    if ($nombre) {
        $unidad = unidadDePrueba(['nombre' => "Unidad {$alias}"]);
        $servidor = Servidor::create([
            'cedula' => '09' . str_pad((string) crc32($alias) % 100000000, 8, '0', STR_PAD_LEFT),
            'nombre' => $nombre, 'apellido' => 'Lectura',
            'puesto_id' => puestoDePrueba($unidad, "Puesto {$alias}")->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
        ]);
    }
    $user = User::create([
        'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $servidor?->id,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $user;
}

/**
 * Un resultado llegado hace `$diasAtras` días y, si se revisó, leído
 * `$diasLectura` días después.
 */
function resultadoLecturaResultados(array $datos, int $diasAtras, ?string $lectura = null, ?int $diasLectura = null): ResultadoMedico
{
    $llego = now()->subDays($diasAtras)->setTime(9, 0);

    $resultado = ResultadoMedico::create([
        'historia_clinica_id' => test()->historia->id, 'subido_por' => test()->ana->id,
        'tipo' => 'laboratorio', 'descripcion' => 'Resultado', 'archivo' => 'resultados-medicos/prueba.pdf',
        'fecha_resultado' => $llego->toDateString(),
        'estado_revision' => $diasLectura === null && $lectura === null ? 'por_revisar' : 'revisado',
        'interpretacion' => $lectura,
        'revisado_en' => $diasLectura === null ? null : $llego->copy()->addDays($diasLectura),
        ...$datos,
    ]);
    $resultado->forceFill(['created_at' => $llego])->saveQuietly();

    return $resultado;
}

beforeEach(function () {
    Model::unguard();

    $this->admin = usuarioLecturaResultados('admin-lectura', 'admin-dispensario');
    $this->autoridad = usuarioLecturaResultados('aut-lectura', 'maxima-autoridad');
    $this->enfermera = usuarioLecturaResultados('enf-lectura', 'enfermera');
    $this->ana = usuarioLecturaResultados('ana-lectura', 'medico', 'Ana');
    $this->bruno = usuarioLecturaResultados('bruno-lectura', 'medico', 'Bruno');

    $paciente = usuarioLecturaResultados('paciente-lectura', 'servidor', 'Paciente');
    $this->historia = HistoriaClinica::create([
        'servidor_id' => $paciente->servidor_id, 'tipo_paciente' => 'servidor',
        'cedula_paciente' => $paciente->servidor->cedula, 'numero_historia' => $paciente->servidor->cedula,
    ]);

    // De Ana: lo que vuelve de su orden, aunque lo lea otro médico.
    $orden = app(OrdenExamenServiceInterface::class)->emitir(
        ['historia_clinica_id' => $this->historia->id, 'fecha_solicitud' => today()->subDays(110)->toDateString()],
        [['catalogo_examen_id' => CatalogoExamen::where('nombre', 'Glucosa')->value('id')]],
        $this->ana->id,
    );
    $deAna = ['orden_examen_id' => $orden->id];
    resultadoLecturaResultados($deAna, 5, 'normal', 2);
    resultadoLecturaResultados($deAna, 5, 'alterado', 0);
    resultadoLecturaResultados([...$deAna, 'revisado_por' => $this->bruno->id], 5, 'critico', 1);
    resultadoLecturaResultados($deAna, 5);
    resultadoLecturaResultados($deAna, 100, 'normal', 3);

    // De Bruno: sin orden, por su consulta. Uno por revisar y uno anterior a la revisión.
    $consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $this->bruno->id, 'fecha_consulta' => today()->subDays(10),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
    resultadoLecturaResultados(['consulta_medica_id' => $consulta->id], 5);
    resultadoLecturaResultados(['consulta_medica_id' => $consulta->id, 'estado_revision' => 'revisado'], 5);

    // Retirado y fuera del período: no cuentan.
    resultadoLecturaResultados($deAna, 5, 'critico', 1)->delete();
    resultadoLecturaResultados($deAna, 200, 'critico', 1);

    $this->filtros = '?desde=' . today()->subDays(120)->toDateString() . '&hasta=' . today()->toDateString();
});

function filasLecturaResultados(User $quien, string $query): Collection
{
    return collect(test()->actingAs($quien, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/examenes_lectura' . test()->filtros . $query)
        ->assertOk()->json('datos.filas'));
}

test('por médico que responde cuenta lecturas, el porcentaje y la mediana de días', function () {
    $filas = filasLecturaResultados($this->admin, '&agrupacion=profesional')->keyBy('profesional');

    expect($filas->keys()->all())->toBe(['Ana Lectura', 'Bruno Lectura'])
        // Leídos: 0, 1, 2 y 3 días; la mediana es 1,5. Dos de cuatro, alterados o críticos.
        ->and($filas['Ana Lectura'])->toBe([
            'profesional' => 'Ana Lectura', 'resultados' => 5, 'por_revisar' => 1,
            'normales' => 2, 'alterados' => 1, 'criticos' => 1, 'alterados_pct' => 50,
            'sin_lectura' => 0, 'lectura_dias' => 1.5,
        ])
        ->and($filas['Bruno Lectura'])->toBe([
            'profesional' => 'Bruno Lectura', 'resultados' => 2, 'por_revisar' => 1,
            'normales' => 0, 'alterados' => 0, 'criticos' => 0, 'alterados_pct' => null,
            'sin_lectura' => 1, 'lectura_dias' => null,
        ]);
});

test('por mes es la agrupación de por defecto', function () {
    $filas = filasLecturaResultados($this->autoridad, '')->keyBy('mes');

    // El mes como lo escribe el reporte: «Octubre 2026».
    $mes = fn ($fecha) => ucfirst($fecha->locale('es')->isoFormat('MMMM YYYY'));
    $viejo = $mes(now()->subDays(100));
    $reciente = $mes(now()->subDays(5));

    expect($filas->keys()->all())->toBe([$viejo, $reciente])
        ->and($filas[$viejo])->toMatchArray(['resultados' => 1, 'normales' => 1])
        ->and($filas[$reciente])->toMatchArray(['resultados' => 6, 'por_revisar' => 2, 'criticos' => 1, 'sin_lectura' => 1]);
});

test('el médico ve solo los suyos aunque pida los de otro, y enfermería no lo saca', function () {
    $filas = filasLecturaResultados($this->bruno, "&agrupacion=profesional&profesional_id={$this->ana->id}");

    expect($filas->pluck('profesional')->all())->toBe(['Bruno Lectura']);

    $this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/examenes_lectura' . $this->filtros)
        ->assertForbidden();
});
