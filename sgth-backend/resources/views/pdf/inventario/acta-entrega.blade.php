<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Entrega-Recepción de Bienes Informáticos</title>
    <style>
        /* dompdf no soporta flexbox ni grid: toda la maquetación va con
           tablas y bordes, que es lo que renderiza de forma predecible. */
        @page { margin: 26mm 14mm 20mm 14mm; }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.45;
            color: #1c2321;
            margin: 0;
            padding: 0;
        }

        /* ── Encabezado y pie fijos en todas las páginas ───────── */
        .membrete {
            position: fixed;
            top: -21mm; left: 0; right: 0;
            height: 17mm;
        }
        .membrete table { width: 100%; border-collapse: collapse; }
        .membrete td { vertical-align: middle; border: none; padding: 0; }
        .membrete img { max-height: 15mm; }
        .membrete .institucion {
            text-align: right;
            font-size: 9.5px;
            color: #5b6b63;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .membrete .institucion strong {
            display: block;
            font-size: 11px;
            color: #17593f;
            letter-spacing: 0;
            margin-bottom: 1px;
        }

        .footer {
            position: fixed;
            bottom: -14mm; left: 0; right: 0;
            text-align: center;
            font-size: 11px;
            color: #5b6b63;
            border-top: 0.7px solid #cfdad4;
            padding-top: 4px;
        }

        /* ── Cintillo de número y fecha ────────────────────────── */
        .cintillo { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .cintillo td { border: none; padding: 0; font-size: 10px; }
        .cintillo .codigo {
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #17593f;
        }
        .cintillo .lugar-fecha { text-align: right; color: #3d4d46; }

        /* ── Título del acto ──────────────────────────────────── */
        .titulo { text-align: center; margin: 0 0 14px 0; }
        .titulo .supra {
            font-size: 9px;
            letter-spacing: 2px;
            color: #5b6b63;
            text-transform: uppercase;
        }
        .titulo h1 {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin: 2px 0 0 0;
            color: #17593f;
        }

        /* ── Secciones de datos ───────────────────────────────── */
        .seccion { margin-bottom: 12px; }
        .seccion h2 {
            font-size: 9px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: #6b7c74;
            margin: 0 0 4px 0;
            padding-bottom: 2px;
            border-bottom: 0.7px solid #cfdad4;
            font-weight: bold;
        }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos td {
            border: 0.7px solid #cfdad4;
            padding: 4px 7px;
            vertical-align: top;
        }
        table.datos td.rotulo {
            width: 22%;
            background: #f6faf8;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #5b6b63;
        }

        .parrafo {
            text-align: justify;
            font-size: 10.5px;
            line-height: 1.6;
            margin: 0 0 10px 0;
        }

        .clausula {
            text-align: justify;
            font-size: 10px;
            line-height: 1.6;
            padding: 8px 12px;
            border-left: 2.5px solid #17593f;
            background: #f6faf8;
            margin-bottom: 14px;
        }

        /* Un bien ya devuelto no puede imprimirse como si siguiera bajo
           custodia: el aviso va en rojo y antes de las firmas. */
        .devuelto {
            text-align: justify;
            font-size: 10px;
            line-height: 1.6;
            padding: 8px 12px;
            border-left: 2.5px solid #8c2f26;
            background: #fdf5f4;
            color: #6d251e;
            margin-bottom: 14px;
        }

        /* ── Firmas ───────────────────────────────────────────── */
        .firmas { width: 100%; margin-top: 30px; }
        .firmas table { width: 100%; border-collapse: collapse; }
        .firmas td {
            text-align: center;
            vertical-align: bottom;
            border: none;
            padding: 0 12px;
        }
        .firma-rotulo {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #6b7c74;
            margin-bottom: 46px;
        }
        .firma-linea {
            border-top: 0.9px solid #1c2321;
            width: 84%;
            margin: 0 auto 4px auto;
        }
        .firma-nombre { font-weight: bold; font-size: 10.5px; margin: 0; }
        .firma-cargo { font-size: 9.5px; color: #3d4d46; margin: 1px 0 0 0; }
    </style>
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

<div class="membrete">
    <table>
        <tr>
            <td style="width: 26%;">
                @if(file_exists($logo))
                    <img src="{{ $logo }}" alt="Logo GADPE">
                @endif
            </td>
            <td class="institucion">
                <strong>Gobierno Autónomo Descentralizado Provincial de Esmeraldas</strong>
                Dirección de Tecnologías de la Información y Comunicación
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    Dirección: 10 de Agosto entre Bolívar y Pedro Vicente Maldonado.<br>
    Telefono: 062721433
</div>

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
            <td>{{ ucfirst($bien?->condicion_fisica ?? '—') }}</td>
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
