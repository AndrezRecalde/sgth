<?php

use App\Enums\VarianteIdentidad;
use App\Models\Comunicacion\RecursoIdentidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/*
| La cabecera de los correos con el logo de Identidad institucional
| (docs/diseno-comunicacion-social.md, 4.2; fase 2.3). El cliente de correo lo
| pide desde fuera y sin sesión, así que va con la URL pública y su versión.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    // La carga inicial, sobre el disco falso.
    $migracion = require database_path('migrations/2026_11_03_000000_crear_identidad_institucional.php');
    $migracion->down();
    $migracion->up();

    config(['app.frontend_url' => 'https://sgth.ejemplo.ec/']);
});

test('el correo lleva el imagotipo vigente con su url publica y su version', function () {
    $logo = RecursoIdentidad::where('variante', VarianteIdentidad::IMAGOTIPO_HORIZONTAL->value)->sole();

    $html = (string) (new MailMessage)->line('Hola')->render();

    expect($html)->toContain("https://sgth.ejemplo.ec/api/v1/identidad/imagotipo_horizontal?v={$logo->id}")
        ->and($html)->toContain('alt="GADPE"');
});

test('sin logo el correo lleva el nombre de la app, como antes', function () {
    RecursoIdentidad::query()->delete();

    $html = (string) (new MailMessage)->line('Hola')->render();

    expect($html)->not->toContain('/api/v1/identidad/')
        ->and($html)->toContain(e(config('app.name')));
});
