<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe técnico de baja de bien informático</title>
    @include('pdf.inventario._estilos')
</head>
<body>

@php
    $bien = $baja->bien;
    $ultima = $bien?->asignaciones->first();
    $persona = fn ($u) => $u?->servidor ? trim($u->servidor->nombre . ' ' . $u->servidor->apellido) : null;
    $cargo = fn ($u) => $u?->servidor?->puesto?->cargo?->nombre ?? 'Dirección de Tecnologías de la Información y Comunicación';
    $fecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->locale('es')->isoFormat('DD [de] MMMM [del] YYYY') : '—';
@endphp

@include('pdf.inventario._membrete')

<table class="cintillo">
    <tr>
        <td style="width: 50%;"><span class="codigo">INFORME N.º {{ $baja->numero() }}</span></td>
        <td class="lugar-fecha">Esmeraldas, {{ $fecha($baja->fecha_informe) }}</td>
    </tr>
</table>

<div class="titulo">
    <div class="supra">Inventario de Bienes Informáticos</div>
    <h1>Informe técnico de baja</h1>
</div>

<div class="seccion">
    <h2>Bien informático</h2>
    <table class="datos">
        <tr>
            <td class="rotulo">Código institucional</td>
            <td>{{ $bien?->codigo_institucional ?? '—' }}</td>
            <td class="rotulo">Código anterior</td>
            <td>{{ $bien?->codigo_anterior ?? '—' }}</td>
        </tr>
        <tr>
            <td class="rotulo">Tipo</td>
            <td>{{ $bien?->tipo?->nombre ?? '—' }}</td>
            <td class="rotulo">Marca y modelo</td>
            <td>{{ trim(($bien?->marca?->nombre ?? '') . ' ' . ($bien?->modelo ?? '')) ?: '—' }}</td>
        </tr>
        <tr>
            <td class="rotulo">N.º de serie</td>
            <td>{{ $bien?->numero_serie ?? '—' }}</td>
            <td class="rotulo">Fecha de compra</td>
            <td>{{ $fecha($bien?->fecha_compra) }}</td>
        </tr>
        <tr>
            <td class="rotulo">Último custodio</td>
            <td>{{ $ultima?->servidor ? trim($ultima->servidor->nombre . ' ' . $ultima->servidor->apellido) : '—' }}</td>
            <td class="rotulo">Ubicación</td>
            <td>{{ $bien?->ubicacion?->nombre ?? '—' }}</td>
        </tr>
    </table>
</div>

<div class="seccion">
    <h2>Motivo de la baja</h2>
    <table class="datos">
        <tr><td>{{ $baja->motivo->etiqueta() }}</td></tr>
    </table>
</div>

<div class="seccion">
    <h2>Diagnóstico técnico</h2>
    <table class="datos">
        <tr><td style="white-space: pre-line;">{{ $baja->diagnostico }}</td></tr>
    </table>
</div>

@if($baja->recomendacion)
    <div class="seccion">
        <h2>Recomendación</h2>
        <table class="datos">
            <tr><td style="white-space: pre-line;">{{ $baja->recomendacion }}</td></tr>
        </table>
    </div>
@endif

@if($baja->componentes->isNotEmpty())
    <div class="seccion">
        <h2>Componentes que salen con el equipo</h2>
        <table class="datos">
            @foreach($baja->componentes as $componente)
                <tr>
                    <td class="rotulo">{{ $componente->bien?->tipo?->nombre ?? 'Componente' }}</td>
                    <td>{{ $componente->bien?->codigo_institucional }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

@if($baja->estado->value === 'aprobada')
    <div class="clausula">
        Baja aprobada el {{ $fecha($baja->fecha_baja) }}@if($baja->memorando_numero) mediante {{ $baja->memorando_numero }}@endif.
        El bien sale del servicio y se entrega a la Unidad de Activos Fijos y Bodega para el trámite que corresponde
        conforme al Reglamento General para la Administración, Utilización, Manejo y Control de los Bienes e
        Inventarios del Sector Público.
        @if($baja->entregado_activos_en) Entregado a Activos Fijos el {{ $fecha($baja->entregado_activos_en) }}. @endif
    </div>
@elseif($baja->estado->value === 'rechazada')
    <div class="devuelto">
        Esta propuesta de baja fue rechazada: {{ $baja->motivo_rechazo }}. El bien sigue en el inventario.
    </div>
@else
    <div class="devuelto">
        Propuesta de baja pendiente de la aprobación del jefe de TIC. Este informe no acredita que el bien esté dado de baja.
    </div>
@endif

<div class="firmas">
    <table>
        <tr>
            <td style="width: 50%;">
                <div class="firma-rotulo">Elaborado por</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $persona($baja->informadoPor) ?? '—' }}</p>
                <p class="firma-cargo">{{ $cargo($baja->informadoPor) }}</p>
            </td>
            <td style="width: 50%;">
                <div class="firma-rotulo">Aprobado por</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $persona($baja->aprobadoPor) ?? 'Jefe de TIC' }}</p>
                <p class="firma-cargo">{{ $cargo($baja->aprobadoPor) }}</p>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
