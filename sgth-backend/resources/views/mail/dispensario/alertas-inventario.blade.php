@php
    /**
     * Orden deliberado: primero lo que ya no se puede entregar, luego lo que
     * puede faltar, y al final lo que conviene vigilar. Es el orden en que hay
     * que actuar, no el de gravedad nominal.
     */
@endphp
<x-mail::message>
# Alertas de la farmacia

Resumen del inventario del Dispensario Médico al
{{ now()->translatedFormat('d \d\e F \d\e Y') }}.

@if ($caducadas->isNotEmpty())
## Caducadas ({{ $caducadas->count() }})

Estas existencias **no se pueden despachar**. Corresponde darlas de baja desde
Inventario para que dejen de figurar como disponibles.

<x-mail::table>
| Medicina | Existencias | Caducó |
| :------- | ----------: | :----- |
@foreach ($caducadas as $medicina)
| {{ $medicina->nombre }} {{ $medicina->concentracion }} | {{ $medicina->stock_actual }} | {{ $medicina->fecha_caducidad->format('d/m/Y') }} |
@endforeach
</x-mail::table>
@endif

@if ($bajoMinimo->isNotEmpty())
## Bajo mínimo ({{ $bajoMinimo->count() }})

Conviene reponerlas por adquisición antes de que se agoten.

<x-mail::table>
| Medicina | Existencias | Mínimo |
| :------- | ----------: | -----: |
@foreach ($bajoMinimo as $medicina)
| {{ $medicina->nombre }} {{ $medicina->concentracion }} | {{ $medicina->stock_actual }} | {{ $medicina->stock_minimo }} |
@endforeach
</x-mail::table>
@endif

@if ($porCaducar->isNotEmpty())
## Por caducar en los próximos {{ $diasAviso }} días ({{ $porCaducar->count() }})

Todavía se pueden entregar. Priorizar su despacho evita tener que darlas de
baja más adelante.

<x-mail::table>
| Medicina | Existencias | Caduca |
| :------- | ----------: | :----- |
@foreach ($porCaducar as $medicina)
| {{ $medicina->nombre }} {{ $medicina->concentracion }} | {{ $medicina->stock_actual }} | {{ $medicina->fecha_caducidad->format('d/m/Y') }} |
@endforeach
</x-mail::table>
@endif

@php
    // La misma variable que ya usa CORS para el origen del SPA: el enlace debe
    // llevar a la pantalla, no a la API.
    $inventarioUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/')
        . '/salud/farmacia';
@endphp

<x-mail::button :url="$inventarioUrl">
Abrir el inventario
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
