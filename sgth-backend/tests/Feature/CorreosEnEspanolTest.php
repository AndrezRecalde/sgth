<?php

use Illuminate\Mail\Markdown;

uses(Tests\TestCase::class);

/*
 * Las plantillas de correo de Laravel traen frases en inglés que se traducen
 * con lang/es.json, y ese archivo no existía: el pie de cada correo decía
 * «All rights reserved.» (visto el 2026-10-04 en el de las sanciones para
 * nómina).
 */

test('el pie de los correos sale en español', function () {
    $html = app(Markdown::class)->render('mail.disciplinario.sancion-para-nomina', [
        'anulada'  => false,
        'numero'   => 'AP-2026-0001',
        'servidor' => 'Prueba Correo',
        'cedula'   => '0800000000',
        'sancion'  => 'Multa',
        'detalle'  => '5 % de la remuneración mensual unificada',
        'desde'    => '06/10/2026',
        'hasta'    => null,
        'base'     => '$1.000,00',
        'monto'    => '$50,00',
        'motivo'   => null,
    ])->toHtml();

    expect($html)->toContain('Todos los derechos reservados.')
        ->not->toContain('All rights reserved.');
});

test('las frases de las plantillas de Laravel están traducidas', function () {
    foreach (['All rights reserved.', 'Hello!', 'Regards,', 'Whoops!'] as $frase) {
        expect(__($frase))->not->toBe($frase, "Falta «{$frase}» en lang/es.json");
    }
});
