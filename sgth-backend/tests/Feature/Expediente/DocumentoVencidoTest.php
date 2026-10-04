<?php

use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Un documento ya vencido no se anexa al expediente (Talento Humano,
 * 2026-10-03). El que vence hoy todavía vale: con `after:today` se
 * rechazaba, aunque la tabla no lo pinta como vencido.
 */

beforeEach(function () {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Vencidos']);
    $servidor = Servidor::forceCreate([
        'cedula' => '0801111111', 'nombre' => 'Pía', 'apellido' => 'Vencida',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Vencidos')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->url = "/api/v1/expediente/servidores/{$servidor->id}/documentos";
});

function subirDocumentoVencidoTest($caso, ?string $vence)
{
    return $caso->post($caso->url, array_filter([
        'tipo_documento'    => 'cedula_identidad',
        'archivo'           => UploadedFile::fake()->create('cedula.pdf', 20, 'application/pdf'),
        'fecha_vencimiento' => $vence,
    ]), ['Accept' => 'application/json']);
}

test('un documento ya vencido no se anexa', function () {
    subirDocumentoVencidoTest($this, now()->subDay()->toDateString())
        ->assertUnprocessable()
        ->assertJsonPath(
            'errores.fecha_vencimiento.0',
            'El documento ya está vencido: no se anexa al expediente.',
        );
});

test('el que vence hoy, el que vence después y el que no vence se anexan', function () {
    subirDocumentoVencidoTest($this, now()->toDateString())->assertCreated();
    subirDocumentoVencidoTest($this, now()->addYear()->toDateString())->assertCreated();
    subirDocumentoVencidoTest($this, null)->assertCreated();
});
