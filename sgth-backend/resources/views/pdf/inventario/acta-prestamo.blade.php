<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de préstamo de bienes informáticos</title>
    @include('pdf.inventario._estilos')
</head>
<body>

@php
    $s = $prestamo->servidor;
    $nombre = $s ? trim($s->nombre . ' ' . $s->apellido) : '—';
    $momento = fn ($f) => $f ? $f->locale('es')->isoFormat('DD [de] MMMM [del] YYYY, HH:mm') : '—';
    $persona = fn ($u) => $u?->servidor ? trim($u->servidor->nombre . ' ' . $u->servidor->apellido) : null;
@endphp

@include('pdf.inventario._membrete')

<table class="cintillo">
    <tr>
        <td style="width: 50%;"><span class="codigo">PRÉSTAMO N.º {{ $prestamo->folio }}</span></td>
        <td class="lugar-fecha">Esmeraldas, {{ $prestamo->entregado_en?->locale('es')->isoFormat('DD [de] MMMM [del] YYYY') }}</td>
    </tr>
</table>

<div class="titulo">
    <div class="supra">Inventario de Bienes Informáticos</div>
    <h1>Acta de Préstamo</h1>
</div>

<p class="parrafo">
    La Dirección de Tecnologías de la Información y Comunicación del Gobierno Autónomo Descentralizado
    Provincial de Esmeraldas entrega en préstamo los bienes informáticos que se detallan, para el uso y en el
    lugar indicados, y el servidor los recibe en la condición señalada, comprometiéndose a devolverlos en la
    fecha y hora previstas.
</p>

<div class="seccion">
    <h2>Servidor que recibe</h2>
    <table class="datos">
        <tr>
            <td class="rotulo">Nombres y apellidos</td>
            <td>{{ $nombre }}</td>
            <td class="rotulo">Cédula</td>
            <td>{{ $s?->cedula ?? '—' }}</td>
        </tr>
        <tr>
            <td class="rotulo">Unidad</td>
            <td colspan="3">{{ $prestamo->unidadAdministrativa?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="rotulo">Para qué</td>
            <td colspan="3">{{ $prestamo->motivo }}</td>
        </tr>
        <tr>
            <td class="rotulo">Dónde</td>
            <td colspan="3">{{ $prestamo->lugar_uso }}{{ $prestamo->fuera_del_edificio ? ' (fuera del edificio)' : '' }}</td>
        </tr>
        <tr>
            <td class="rotulo">Entregado</td>
            <td>{{ $momento($prestamo->entregado_en) }}</td>
            <td class="rotulo">Devolución prevista</td>
            <td>{{ $momento($prestamo->fin_previsto) }}</td>
        </tr>
    </table>
</div>

<div class="seccion">
    <h2>Bienes prestados</h2>
    <table class="datos">
        <tr>
            <td class="rotulo">Código</td>
            <td class="rotulo">Bien</td>
            <td class="rotulo">Accesorios</td>
            <td class="rotulo">Sale</td>
            <td class="rotulo">Vuelve</td>
        </tr>
        @foreach($prestamo->items as $item)
            <tr>
                <td>{{ $item->bien?->codigo_institucional }}</td>
                <td>{{ collect([$item->bien?->tipo?->nombre, $item->bien?->marca?->nombre, $item->bien?->modelo, $item->bien?->numero_serie ? 'S/N ' . $item->bien->numero_serie : null])->filter()->implode(' · ') }}</td>
                <td>{{ $item->accesorios ?? '—' }}</td>
                <td>{{ $item->condicion_entrega?->etiqueta() ?? '—' }}</td>
                <td>{{ $item->condicion_devolucion?->etiqueta() ?? '—' }}{{ $item->novedad ? ': ' . $item->novedad : '' }}</td>
            </tr>
        @endforeach
    </table>
</div>

<div class="clausula">
    El servidor responde por la conservación y el buen uso de los bienes mientras los tenga, y por su devolución
    completa, con sus accesorios, en la fecha y hora previstas. Cualquier daño, pérdida o robo se comunica de
    inmediato a la Dirección de Tecnologías de la Información y Comunicación.
</div>

@if($prestamo->devuelto_en)
    <div class="devuelto">
        Devuelto el {{ $momento($prestamo->devuelto_en) }}@if($persona($prestamo->recibidoPor)), recibido por {{ $persona($prestamo->recibidoPor) }}@endif.
        El acta se imprime como respaldo de un préstamo ya concluido.
    </div>
@endif

<div class="firmas">
    <table>
        <tr>
            <td style="width: 50%;">
                <div class="firma-rotulo">Entrega</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $persona($prestamo->entregadoPor) ?? '—' }}</p>
                <p class="firma-cargo">Dirección de Tecnologías de la Información y Comunicación</p>
            </td>
            <td style="width: 50%;">
                <div class="firma-rotulo">Recibe</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $nombre }}</p>
                <p class="firma-cargo">C.C. {{ $s?->cedula ?? '—' }}</p>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
