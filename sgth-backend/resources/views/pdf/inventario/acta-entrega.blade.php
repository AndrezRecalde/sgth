<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Entrega-Recepción de Bienes Informáticos</title>
    @include('pdf.inventario._estilos')
</head>
<body>

@php
    $bien     = $asignacion->bien;
    $servidor = $asignacion->servidor;

    $nombreServidor = trim(($servidor?->nombre ?? '') . ' ' . ($servidor?->apellido ?? ''));

    // La unidad del servidor puede venir por su columna o por la de su puesto;
    // se prefiere la propia, que es la que el expediente mantiene al día.
    $unidad = $servidor?->unidadAdministrativa?->nombre
        ?? $servidor?->puesto?->unidadAdministrativa?->nombre;

    $caracteristicas = collect($bien?->caracteristicas_tecnicas ?? [])
        ->filter(fn ($valor) => $valor !== null && $valor !== '');
@endphp

@include('pdf.inventario._membrete')

<table class="cintillo">
    <tr>
        <td style="width: 50%;">
            <span class="codigo">ACTA N.º {{ $numero }}</span>
        </td>
        <td class="lugar-fecha">
            Esmeraldas, {{ \Carbon\Carbon::parse($asignacion->fecha_asignacion)->locale('es')->isoFormat('DD [de] MMMM [del] YYYY') }}
        </td>
    </tr>
</table>

<div class="titulo">
    <div class="supra">Inventario de Bienes Informáticos</div>
    <h1>Acta de Entrega-Recepción</h1>
</div>

<p class="parrafo">
    En la ciudad de Esmeraldas, en la fecha señalada, la Dirección de Tecnologías
    de la Información y Comunicación del Gobierno Autónomo Descentralizado
    Provincial de Esmeraldas hace la entrega del bien informático que se detalla
    a continuación, y el servidor receptor lo recibe a entera satisfacción,
    asumiendo desde este acto su custodia y buen uso.
</p>

<div class="seccion">
    <h2>Servidor receptor</h2>
    <table class="datos">
        <tr>
            <td class="rotulo">Nombres y apellidos</td>
            <td>{{ $nombreServidor !== '' ? $nombreServidor : '—' }}</td>
            <td class="rotulo">Cédula</td>
            <td>{{ $servidor?->cedula ?? '—' }}</td>
        </tr>
        <tr>
            <td class="rotulo">Puesto</td>
            <td>{{ $servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
            <td class="rotulo">Unidad</td>
            <td>{{ $unidad ?? '—' }}</td>
        </tr>
        {{-- Quien usa el equipo cuando no es el custodio (D3): el acta la
             firma el custodio, que responde por el bien. --}}
        @if($asignacion->usuario)
            <tr>
                <td class="rotulo">Usuario del equipo</td>
                <td colspan="3">{{ trim($asignacion->usuario->apellido . ' ' . $asignacion->usuario->nombre) }} (C.C. {{ $asignacion->usuario->cedula }})</td>
            </tr>
        @endif
        @if($asignacion->motivo === 'traspaso' && $asignacion->anterior?->servidor)
            <tr>
                <td class="rotulo">Traspaso desde</td>
                <td colspan="3">{{ trim($asignacion->anterior->servidor->apellido . ' ' . $asignacion->anterior->servidor->nombre) }}</td>
            </tr>
        @endif
        @if($asignacion->memorando_numero)
            <tr>
                <td class="rotulo">Memorando</td>
                <td colspan="3">{{ $asignacion->memorando_numero }}</td>
            </tr>
        @endif
    </table>
</div>

<div class="seccion">
    <h2>Bien entregado</h2>
    <table class="datos">
        <tr>
            <td class="rotulo">Código institucional</td>
            <td>{{ $bien?->codigo_institucional ?? '—' }}</td>
            <td class="rotulo">Tipo</td>
            <td>{{ $bien?->tipo?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="rotulo">Marca</td>
            <td>{{ $bien?->marca?->nombre ?? '—' }}</td>
            <td class="rotulo">Modelo</td>
            <td>{{ $bien?->modelo ?? '—' }}</td>
        </tr>
        <tr>
            <td class="rotulo">N.º de serie</td>
            <td>{{ $bien?->numero_serie ?? '—' }}</td>
            <td class="rotulo">Condición física</td>
            <td>{{ $bien?->condicion_fisica?->etiqueta() ?? '—' }}</td>
        </tr>
    </table>
</div>

@if($caracteristicas->isNotEmpty())
    <div class="seccion">
        <h2>Características técnicas</h2>
        <table class="datos">
            @foreach($caracteristicas as $clave => $valor)
                <tr>
                    <td class="rotulo">{{ ucfirst(str_replace('_', ' ', (string) $clave)) }}</td>
                    <td>{{ is_array($valor) ? implode(', ', $valor) : $valor }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

@if($asignacion->observaciones)
    <div class="seccion">
        <h2>Observaciones</h2>
        <table class="datos">
            <tr><td>{{ $asignacion->observaciones }}</td></tr>
        </table>
    </div>
@endif

<div class="clausula">
    El servidor receptor queda constituido en custodio del bien descrito y
    responde por su conservación, buen uso y devolución, conforme al Reglamento
    General para la Administración, Utilización, Manejo y Control de los Bienes
    e Inventarios del Sector Público. Cualquier traslado, préstamo o novedad que
    afecte al bien —incluidos daño, pérdida o robo— debe comunicarse de
    inmediato a la Dirección de Tecnologías de la Información y Comunicación.
</div>

@if($asignacion->fecha_devolucion)
    <div class="devuelto">
        Esta asignación registra devolución con fecha
        {{ \Carbon\Carbon::parse($asignacion->fecha_devolucion)->locale('es')->isoFormat('DD [de] MMMM [del] YYYY') }}.
        El acta se imprime como respaldo del período de custodia ya concluido y
        no acredita tenencia actual del bien.
    </div>
@endif

<div class="firmas">
    <table>
        <tr>
            <td style="width: 50%;">
                {{-- Quien entrega sale de la columna sellada al registrar la
                     asignación, no de quien imprima hoy: una reimpresión no
                     puede atribuirle el acto a otra persona. --}}
                <div class="firma-rotulo">Entrega</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $entrega['nombre'] ?? $entrega['cargo'] }}</p>
                <p class="firma-cargo">{{ $entrega['cargo'] }}</p>
            </td>
            <td style="width: 50%;">
                <div class="firma-rotulo">Recibe</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $nombreServidor !== '' ? $nombreServidor : '—' }}</p>
                <p class="firma-cargo">C.C. {{ $servidor?->cedula ?? '—' }}</p>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
