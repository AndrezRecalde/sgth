@props(['url'])
{{--
    La cabecera de los correos (Comunicación Social, fase 2.3): el imagotipo
    horizontal vigente, con su URL absoluta y su versión, porque el cliente de
    correo lo pide desde fuera y sin sesión (lo sirve IdentidadPublicaController).
    Va por FRONTEND_URL: el API se publica en el mismo dominio que la app.

    Si no hay logo, el nombre de la app, como la plantilla de Laravel. Muchos
    clientes no cargan imágenes de entrada: el texto alternativo dice de quién
    es el correo. Sin la clase `logo` del tema, que lo forzaría a un cuadrado.
--}}
@php
    $logo = app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)
        ->resolver(\App\Enums\VarianteIdentidad::IMAGOTIPO_HORIZONTAL);
    $siglas = app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)->datos()->siglas;
    $base = rtrim((string) config('app.frontend_url'), '/');
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logo && $base !== '')
<img src="{{ $base }}/api/v1/identidad/imagotipo_horizontal?v={{ $logo->id }}" alt="{{ $siglas ?: trim($slot) }}" style="height: 48px; width: auto; max-width: 280px; border: none;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
