<?php

/*
| Cada JsonResource declara con `@mixin` el modelo que envuelve.
|
| Scramble —el que genera el contrato OpenAPI y, de ahí, los tipos del
| frontend— busca ese modelo en el docblock del recurso. Si no lo encuentra,
| lo adivina como `App\Models\<Nombre>`, y los modelos de este proyecto viven en
| subcarpetas (`App\Models\Expediente\Servidor`): sin modelo, cada campo del
| recurso sale como `string`. Así, un `id` o un `estado` booleano llegaban al
| frontend como texto, y regenerar los tipos rompía 186 comprobaciones de tsc.
|
| Con `@mixin` apuntando al modelo, Scramble usa sus columnas y sus casts.
*/

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

function recursosDelProyecto(): array
{
    $recursos = [];

    foreach (File::allFiles(app_path('Http/Resources')) as $archivo) {
        $clase = 'App\\Http\\Resources\\'.str_replace(
            ['/', '.php'],
            ['\\', ''],
            $archivo->getRelativePathname()
        );

        if (class_exists($clase) && is_subclass_of($clase, JsonResource::class)) {
            $recursos[] = $clase;
        }
    }

    return $recursos;
}

test('cada JsonResource declara con @mixin un modelo que existe', function () {
    $sinModelo = [];

    foreach (recursosDelProyecto() as $recurso) {
        $doc = (new ReflectionClass($recurso))->getDocComment() ?: '';

        if (! preg_match('/@mixin\s+\\\\?([\w\\\\]+)/', $doc, $m) || ! class_exists($m[1])) {
            $sinModelo[] = $recurso;
        }
    }

    expect(recursosDelProyecto())->not->toBeEmpty()
        ->and($sinModelo)->toBe([]);
});
