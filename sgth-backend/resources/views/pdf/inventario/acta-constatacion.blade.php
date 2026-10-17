<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de constatación física de bienes informáticos</title>
    @include('pdf.inventario._estilos')
    <style>
        table.listado { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.listado thead { display: table-header-group; }
        table.listado th {
            background: #f6faf8; border: 0.7px solid #cfdad4; padding: 3px 5px;
            font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; color: #5b6b63; text-align: left;
        }
        table.listado td { border: 0.7px solid #cfdad4; padding: 3px 5px; font-size: 9px; vertical-align: top; }
        table.listado tr { page-break-inside: avoid; }
        .resumen { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .resumen td { border: 0.7px solid #cfdad4; padding: 5px; text-align: center; }
        .resumen .cifra { font-size: 14px; font-weight: bold; color: #17593f; }
        .resumen .rotulo { font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; color: #5b6b63; }
    </style>
</head>
<body>

@php
    $c = $constatacion;
    $fecha = fn ($f) => $f ? $f->locale('es')->isoFormat('DD [de] MMMM [del] YYYY, HH:mm') : '—';
    $orden = ['no_encontrado', 'otra_ubicacion', 'nuevo', 'de_baja', 'en_su_lugar', 'pendiente'];
@endphp

@include('pdf.inventario._membrete')

<table class="cintillo">
    <tr>
        <td style="width: 50%;"><span class="codigo">CONSTATACIÓN N.º {{ str_pad((string) $c->id, 5, '0', STR_PAD_LEFT) }}</span></td>
        <td class="lugar-fecha">Esmeraldas, {{ ($c->cerrada_en ?? now())->locale('es')->isoFormat('DD [de] MMMM [del] YYYY') }}</td>
    </tr>
</table>

<div class="titulo">
    <div class="supra">Inventario de Bienes Informáticos</div>
    <h1>Acta de Constatación Física</h1>
</div>

<p class="parrafo">
    La Dirección de Tecnologías de la Información y Comunicación del Gobierno Autónomo Descentralizado
    Provincial de Esmeraldas{{ $activosFijos ? ', con la participación de la Unidad de Activos Fijos y Bodega,' : '' }}
    realizó la constatación física de los bienes informáticos registrados en
    {{ $c->ubicacion?->nombre }}{{ $c->unidad ? ', de ' . $c->unidad->nombre : '' }}, mediante la lectura de
    sus etiquetas, con el resultado que se detalla.
    @unless($c->enCurso())
        Los bienes registrados en la ubicación que no se hallaron constan como no encontrados.
    @else
        La constatación sigue en curso: los bienes no leídos todavía constan como pendientes.
    @endunless
</p>

<div class="seccion">
    <h2>Datos de la constatación</h2>
    <table class="datos">
        <tr><td class="rotulo">Nombre</td><td colspan="3">{{ $c->nombre }}</td></tr>
        <tr>
            <td class="rotulo">Ubicación</td><td>{{ $c->ubicacion?->nombre }}</td>
            <td class="rotulo">Unidad</td><td>{{ $c->unidad?->nombre ?? 'Todas' }}</td>
        </tr>
        <tr>
            <td class="rotulo">Iniciada</td><td>{{ $fecha($c->created_at) }}<br>{{ $c->iniciadaPor?->nombre_completo }}</td>
            <td class="rotulo">Cerrada</td><td>{{ $c->cerrada_en ? $fecha($c->cerrada_en) : 'En curso' }}@if($c->cerradaPor)<br>{{ $c->cerradaPor->nombre_completo }}@endif</td>
        </tr>
    </table>
</div>

<table class="resumen">
    <tr>
        <td><div class="cifra">{{ $resumen['esperados'] }}</div><div class="rotulo">Registrados aquí</div></td>
        <td><div class="cifra">{{ $resumen['en_su_lugar'] }}</div><div class="rotulo">En su lugar</div></td>
        <td><div class="cifra">{{ $resumen['otra_ubicacion'] }}</div><div class="rotulo">De otra ubicación</div></td>
        <td><div class="cifra">{{ $resumen['nuevos'] }}</div><div class="rotulo">Registrados en sitio</div></td>
        <td><div class="cifra">{{ $c->enCurso() ? $resumen['pendientes'] : $resumen['no_encontrados'] }}</div><div class="rotulo">{{ $c->enCurso() ? 'Pendientes' : 'No encontrados' }}</div></td>
    </tr>
</table>

@foreach($orden as $resultado)
    @continue(! isset($filas[$resultado]))
    <div class="seccion">
        <h2>{{ $resultados[$resultado] }} ({{ count($filas[$resultado]) }})</h2>
        <table class="listado">
            <thead>
                <tr>
                    <th style="width: 13%;">Código</th>
                    <th style="width: 34%;">Equipo</th>
                    <th style="width: 27%;">Custodio</th>
                    <th>{{ $resultado === 'otra_ubicacion' ? 'Estaba registrado en' : 'Ubicación registrada' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filas[$resultado] as $f)
                    <tr>
                        <td>{{ $f['codigo_institucional'] }}</td>
                        <td>{{ $f['equipo'] ?: '—' }}</td>
                        <td>{{ $f['custodio'] ?? 'Sin custodio' }}</td>
                        <td>{{ $resultado === 'otra_ubicacion' ? ($f['ubicacion_registrada'] ?? 'Sin ubicación') : ($f['ubicacion_registrada'] ?? $f['ubicacion_actual'] ?? '—') }}@if($f['movido'])<br><span style="color: #5b6b63;">Se movió a esta ubicación.</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

@if($c->observaciones)
    <div class="seccion">
        <h2>Observaciones</h2>
        <p class="parrafo">{{ $c->observaciones }}</p>
    </div>
@endif

@php $ancho = $activosFijos ? '33.33%' : '50%'; @endphp
<div class="firmas">
    <table>
        <tr>
            <td style="width: {{ $ancho }};">
                <div class="firma-rotulo">Constató</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $c->iniciadaPor?->nombre_completo }}</p>
                <p class="firma-cargo">Unidad de Tecnologías de la Información</p>
            </td>
            <td style="width: {{ $ancho }};">
                <div class="firma-rotulo">Revisó</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">&nbsp;</p>
                <p class="firma-cargo">Jefe de Tecnologías de la Información</p>
            </td>
            @if($activosFijos)
                {{-- Sellado al cerrar la constatación (5.2b): una reimpresión no
                     le atribuye el acta a quien sea jefe hoy. Un puesto vacante
                     sale con el cargo y se firma a mano. --}}
                <td style="width: {{ $ancho }};">
                    <div class="firma-rotulo">Por Activos Fijos y Bodega</div>
                    <div class="firma-linea"></div>
                    <p class="firma-nombre">@if($activosFijos['nombre']){{ $activosFijos['nombre'] }}@else&nbsp;@endif</p>
                    <p class="firma-cargo">{{ $activosFijos['cargo'] }}</p>
                </td>
            @endif
        </tr>
    </table>
</div>

</body>
</html>
