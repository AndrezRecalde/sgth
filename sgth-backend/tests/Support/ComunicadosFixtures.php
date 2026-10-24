<?php

use App\Contracts\Comunicados\ComunicadoServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Comunicados\Comunicado;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;

/*
| Ayudas de las pruebas de noticias y comunicados
| (docs/diseno-noticias-comunicados.md). Viven aquí y no en cada archivo
| porque las funciones de un test son globales: dos archivos con la misma
| ayuda chocan al correr la suite junta.
*/

function unidadDeComunicados(string $nombre, ?UnidadAdministrativa $padre = null): UnidadAdministrativa
{
    static $n = 0;
    $n++;

    return UnidadAdministrativa::create([
        'codigo'          => "COM-{$n}",
        'nombre'          => $nombre,
        'unidad_padre_id' => $padre?->id,
        'nivel'           => $padre ? $padre->nivel + 1 : 1,
        'estado'          => true,
    ]);
}

/** Un servidor con su cuenta, que es quien recibe las publicaciones. */
function servidorDeComunicados(
    UnidadAdministrativa $unidad,
    RegimenLaboral $regimen = RegimenLaboral::LOSEP,
    bool $activo = true,
): User {
    static $n = 0;
    $n++;

    $servidor = Servidor::create([
        'cedula'                   => sprintf('17%08d', $n),
        'nombre'                   => 'Servidor',
        'apellido'                 => "Comunicados {$n}",
        'regimen_laboral'          => $regimen,
        'estado'                   => $activo,
        'unidad_administrativa_id' => $unidad->id,
    ]);

    $usuario = User::factory()->create(['servidor_id' => $servidor->id]);
    $usuario->assignRole('servidor');

    return $usuario;
}

/** @param  string|list<string>  $roles */
function usuarioDeComunicados(string|array $roles): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($roles);

    return $usuario;
}

/** Lo que manda el formulario, completo para poder publicarlo. */
function datosDeComunicado(array $cambios = []): array
{
    return array_merge([
        'tipo'                  => 'comunicado',
        'titulo'                => 'Descanso obligatorio del 2 y 3 de noviembre',
        'resumen'               => 'Las unidades con servicio continuo envían su cuadro de turnos.',
        'cuerpo'                => '<p>A todos los servidores:</p><ul><li>Descanso el lunes 2.</li></ul>',
        'referencia'            => 'Circular GADPE-DATH-2026-045',
        'audiencia'             => 'todos',
        'unidades'              => [],
        'regimenes'             => [],
        'publicado_desde'       => null,
        // Obligatoria para publicar (2026-10-24); un mes, contado desde el
        // «ahora» de cada prueba.
        'publicado_hasta'       => now()->addMonth()->toDateString(),
        'fijado'                => false,
        'requiere_confirmacion' => true,
    ], $cambios);
}

function borradorDeComunicado(User $autor, array $cambios = []): Comunicado
{
    return app(ComunicadoServiceInterface::class)->crear(datosDeComunicado($cambios), $autor);
}

function publicadoDeComunicado(User $autor, array $cambios = []): Comunicado
{
    return app(ComunicadoServiceInterface::class)->publicar(borradorDeComunicado($autor, $cambios), $autor);
}
