<?php

use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\PdfFemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Los campos del impreso SNS-MSP/HCU-form.123/2025 que la ficha no tenía: las
| observaciones de C, F y J, y el resultado de los exámenes reproductivos
| (solo si interfiere con la actividad laboral y con autorización del
| titular). Se guardan por HTTP y llegan al PDF.
*/

test('las observaciones de C, F y J y el resultado reproductivo se guardan y se imprimen', function () {
    Servidor::unguard();
    $servidor = Servidor::create([
        'cedula' => '0804258986', 'nombre' => 'Ana', 'apellido' => 'Cortez', 'genero' => 'femenino',
    ]);
    $medico = User::factory()->create();
    $medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));
    $solicitud = SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica', 'origen' => 'expediente',
        'servidor_id' => $servidor->id, 'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => 'Ana Cortez', 'solicitado_por' => $medico->id,
        'estado' => 'en_proceso', 'fecha_limite' => now()->addDays(7),
    ]);

    $id = $this->actingAs($medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', [
            'solicitud_id' => $solicitud->id,
            'ficha' => [
                'fecha_evaluacion' => '2026-10-01',
                'observacion_antecedentes' => 'Antecedentes referidos por la paciente.',
                'observacion_examen_fisico' => 'Examen sin alteraciones relevantes.',
                'observacion_examenes' => 'EKG pendiente por falta de equipo.',
            ],
            'antecedente_reproductivo' => [
                'examenes_realizados' => 'Ecografía mamaria',
                'examenes_tiempo_anios' => 1,
                'examenes_resultado' => 'BI-RADS 2',
            ],
            'empleos_anteriores' => [[
                'centro_trabajo' => 'GADPE', 'tipo_evento_laboral' => 'ninguno',
                'fecha_inicio' => '2010-07-01', 'fecha_fin' => '2026-07-01',
            ]],
        ])
        ->assertCreated()
        ->json('datos.id');

    $ficha = FichaSaludOcupacional::findOrFail($id);
    expect($ficha->observacion_examenes)->toBe('EKG pendiente por falta de equipo.')
        ->and($ficha->antecedenteReproductivo->examenes_resultado)->toBe('BI-RADS 2');

    $html = view(
        'pdf.dispensario.femo.formulario-028',
        app(PdfFemoService::class)->datosDeLaVista($id),
    )->render();

    expect($html)
        ->toContain('Antecedentes referidos por la paciente.')
        ->toContain('Examen sin alteraciones relevantes.')
        ->toContain('EKG pendiente por falta de equipo.')
        ->toContain('Resultado: BI-RADS 2')
        // «Tiempo de trabajo» en meses, como lo escribe el Dispensario.
        ->toContain('192 M')
        ->toContain('Tipo de Actividad');
});
