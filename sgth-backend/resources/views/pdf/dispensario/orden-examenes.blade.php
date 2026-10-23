<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de exámenes {{ $orden->folio }}</title>
    <style>
        @page { margin: 26px 34px 70px; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.45;
        }

        /* ── Membrete ───────────────────────────────────────────── */
        table.membrete { width: 100%; border-collapse: collapse; }
        table.membrete td { vertical-align: middle; }
        table.membrete .logo { width: 74px; }
        table.membrete .logo img { width: 66px; }
        table.membrete .centro { text-align: center; }
        table.membrete h1 { font-size: 12.5px; margin: 0; letter-spacing: 0.3px; }
        table.membrete h2 { font-size: 11px; font-weight: normal; margin: 2px 0 0; }
        table.membrete .ruc { font-size: 9px; color: #555; margin-top: 2px; }
        table.membrete .folio-cell { width: 150px; text-align: right; }

        .folio-caja {
            border: 1.2px solid #222; padding: 5px 8px; display: inline-block;
            text-align: center; min-width: 122px;
        }
        .folio-caja .etiqueta { font-size: 8px; letter-spacing: 1px; color: #555; }
        .folio-caja .numero {
            font-family: "Courier New", monospace; font-size: 13px;
            font-weight: bold; letter-spacing: 1px;
        }

        .barra { border-top: 2px solid #222; margin: 9px 0 0; }

        .titulo {
            text-align: center; margin: 12px 0 10px;
            font-size: 15px; font-weight: bold; letter-spacing: 2px;
        }
        .urgente {
            display: inline-block; border: 1.5px solid #b71c1c; color: #b71c1c;
            font-size: 10px; letter-spacing: 1.5px; padding: 1px 6px;
            margin-left: 8px; vertical-align: middle;
        }

        /* ── Bloques de datos ───────────────────────────────────── */
        .seccion {
            background: #ececec; padding: 3px 7px; font-size: 9px;
            font-weight: bold; letter-spacing: 0.8px; margin-top: 11px;
            border: 1px solid #bbb; border-bottom: none;
        }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos td {
            border: 1px solid #bbb; padding: 4px 7px; vertical-align: top;
        }
        table.datos .etiqueta {
            display: block; font-size: 8px; color: #666;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        table.datos .valor { font-size: 11px; }

        /* ── Exámenes ───────────────────────────────────────────── */
        table.examenes { width: 100%; border-collapse: collapse; }
        table.examenes td {
            border: 1px solid #bbb; padding: 4px 7px; vertical-align: top;
        }
        table.examenes .area {
            width: 30%; font-size: 9px; font-weight: bold;
            text-transform: uppercase; letter-spacing: 0.4px; color: #444;
        }
        table.examenes .examen { font-size: 11px; }
        table.examenes .nota { font-size: 10px; color: #444; }

        .cierre {
            text-align: center; font-size: 9px; color: #555;
            letter-spacing: 1px; margin-top: 5px;
        }

        .consentimiento { font-size: 9.5px; color: #444; margin-top: 6px; }

        .indicaciones {
            border: 1px solid #bbb; border-top: none;
            padding: 6px 8px; font-size: 10.5px;
        }

        /* ── Firma ──────────────────────────────────────────────── */
        table.firmas { width: 100%; margin-top: 34px; border-collapse: collapse; }
        table.firmas td { text-align: center; vertical-align: bottom; }
        .linea-firma {
            border-top: 1px solid #222; width: 205px; margin: 26px auto 3px;
        }
        .firma-nombre { font-weight: bold; font-size: 11px; }
        .firma-detalle { font-size: 9px; color: #555; }

        /* ── Anulada ────────────────────────────────────────────── */
        .marca-anulada {
            position: absolute; top: 330px; left: 84px;
            font-size: 72px; color: #d32f2f; opacity: 0.15;
            transform: rotate(-24deg); font-weight: bold; letter-spacing: 6px;
        }
        .aviso-anulada {
            border: 1.5px solid #d32f2f; color: #b71c1c;
            padding: 6px 9px; margin-top: 11px; font-size: 10px;
        }

        /* ── Pie ────────────────────────────────────────────────── */
        .pie {
            position: fixed; bottom: -46px; left: 0; right: 0;
            font-size: 8px; color: #666;
            border-top: 1px solid #ddd; padding-top: 5px;
        }
    </style>
</head>
<body>

@php
    $anulada = $orden->estado === \App\Enums\EstadoOrdenExamen::ANULADA;
    $total = $orden->items->count();
@endphp

@if ($anulada)
    <div class="marca-anulada">ANULADA</div>
@endif

<table class="membrete">
    <tr>
        <td class="logo">
            @if (file_exists($logo))
                <img src="{{ $logo }}" alt="GADPE">
            @endif
        </td>
        <td class="centro">
            <h1>{{ $institucion['nombre'] }}</h1>
            <h2>{{ $institucion['unidad'] }}</h2>
            <div class="ruc">RUC {{ $institucion['ruc'] }}</div>
        </td>
        <td class="folio-cell">
            <div class="folio-caja">
                <div class="etiqueta">ORDEN N°</div>
                <div class="numero">{{ $orden->folio }}</div>
            </div>
        </td>
    </tr>
</table>
<div class="barra"></div>

<div class="titulo">
    ORDEN DE EXÁMENES
    @if ($orden->prioridad === \App\Enums\PrioridadOrdenExamen::URGENTE)
        <span class="urgente">URGENTE</span>
    @endif
</div>

@if ($anulada)
    {{-- La frase se arma antes, como en la receta: repartida entre @if y
         saltos de línea, el punto quedaba separado del nombre. --}}
    @php
        $anulacion = collect([
            $orden->anulado_en
                ? 'el ' . $orden->anulado_en->format('d/m/Y \a \l\a\s H:i')
                : null,
            $orden->anulador
                ? 'por ' . $orden->anulador->nombre_completo
                : null,
        ])->filter()->implode(' ');

        $motivo = rtrim(trim((string) $orden->motivo_anulacion), '.');
    @endphp
    <div class="aviso-anulada">
        <strong>Esta orden fue anulada</strong>{{ $anulacion ? ' ' . $anulacion : '' }}.
        @if ($motivo !== '')
            Motivo: {{ $motivo }}.
        @endif
        No debe procesarse.
    </div>
@endif

{{-- ── Paciente ─────────────────────────────────────────────── --}}
<div class="seccion">DATOS DEL PACIENTE</div>
<table class="datos">
    <tr>
        <td colspan="2" style="width: 50%">
            <span class="etiqueta">Apellidos y nombres</span>
            <span class="valor">{{ $paciente['nombre'] }}</span>
        </td>
        <td style="width: 22%">
            <span class="etiqueta">Cédula</span>
            <span class="valor">{{ $paciente['cedula'] ?? '—' }}</span>
        </td>
        <td style="width: 14%">
            <span class="etiqueta">Edad</span>
            <span class="valor">
                {{ $paciente['edad'] !== null ? $paciente['edad'] . ' años' : '—' }}
            </span>
        </td>
        <td style="width: 14%">
            <span class="etiqueta">Sexo</span>
            <span class="valor">{{ $paciente['sexo'] ?? '—' }}</span>
        </td>
    </tr>
</table>

{{-- ── Solicitud ────────────────────────────────────────────── --}}
{{-- El diagnóstico presuntivo sí se imprime, al revés que en la receta: el
     laboratorio lo pide para orientar el análisis y es uno de los datos de
     las solicitudes del MSP (D1). La receta pasa por mostradores y farmacias
     que no lo necesitan; esta orden va a quien procesa la muestra. --}}
<div class="seccion">DATOS DE LA SOLICITUD</div>
<table class="datos">
    <tr>
        <td style="width: 25%">
            <span class="etiqueta">Fecha de solicitud</span>
            <span class="valor">{{ $orden->fecha_solicitud->format('d/m/Y') }}</span>
        </td>
        <td style="width: 25%">
            <span class="etiqueta">Válida hasta</span>
            <span class="valor">{{ $validaHasta->format('d/m/Y') }}</span>
        </td>
        <td style="width: 25%">
            <span class="etiqueta">Servicio</span>
            <span class="valor">{{ $servicio ?? '—' }}</span>
        </td>
        <td style="width: 25%">
            <span class="etiqueta">Prioridad</span>
            <span class="valor">{{ $orden->prioridad->etiqueta() }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="etiqueta">Diagnóstico presuntivo (CIE-10)</span>
            <span class="valor">
                @if ($orden->diagnosticoCie10)
                    {{ $orden->diagnosticoCie10->codigo }} · {{ $orden->diagnosticoCie10->descripcion }}
                @else
                    —
                @endif
            </span>
        </td>
        <td colspan="2">
            <span class="etiqueta">Laboratorio de destino</span>
            <span class="valor">{{ $orden->laboratorio_destino ?: '—' }}</span>
        </td>
    </tr>
</table>

{{-- ── Exámenes ─────────────────────────────────────────────── --}}
<div class="seccion">EXÁMENES SOLICITADOS</div>
<table class="examenes">
    @foreach ($grupos as $grupo)
        @foreach ($grupo['items'] as $item)
            <tr>
                @if ($loop->first)
                    <td class="area" rowspan="{{ $grupo['items']->count() }}">{{ $grupo['area'] }}</td>
                @endif
                <td>
                    <span class="examen">{{ $item->examen->nombre }}</span>
                    @if ($item->nota)
                        <span class="nota">· {{ $item->examen->etiqueta_nota ?? 'Nota' }}: {{ $item->nota }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    @endforeach
</table>

{{-- El total cierra la lista, como en la receta: sin él, cualquiera puede
     añadir un examen a mano debajo del último. --}}
<div class="cierre">
    {{-- Las dos formas enteras: «examen» + «es» pierde la tilde de «exámenes». --}}
    ——— {{ $total }} {{ $total === 1 ? 'examen solicitado' : 'exámenes solicitados' }}
    · fin de la orden ———
</div>

@if ($orden->consentimiento_firmado)
    {{-- Para quien procesa un examen sensible (VIH, embarazo, drogas): el
         consentimiento se firmó en papel en el Dispensario (D8). --}}
    <div class="consentimiento">
        El paciente firmó en el Dispensario Médico el consentimiento informado
        para los exámenes que lo requieren.
    </div>
@endif

@if ($orden->indicaciones)
    <div class="seccion">INDICACIONES</div>
    <div class="indicaciones">{{ $orden->indicaciones }}</div>
@endif

{{-- ── Firma ────────────────────────────────────────────────── --}}
{{-- A mano (D11), con el código ACESS que identifica a quien pide. --}}
<table class="firmas">
    <tr>
        <td>
            <div class="linea-firma"></div>
            <div class="firma-nombre">{{ $profesional['nombre'] }}</div>
            <div class="firma-detalle">
                Profesional solicitante
                @if ($profesional['cedula'])
                    · C.I. {{ $profesional['cedula'] }}
                @endif
            </div>
            <div class="firma-detalle">
                Código ACESS: {{ $profesional['codigo'] ?? '—' }}
            </div>
        </td>
    </tr>
</table>

<div class="pie">
    {{-- La regla de la receta (#535): si la fecha de solicitud y la del
         registro no coinciden, se dicen las dos. --}}
    Orden {{ $orden->folio }} ·
    @if ($orden->created_at->isSameDay($orden->fecha_solicitud))
        emitida el {{ $orden->created_at->format('d/m/Y \a \l\a\s H:i') }} ·
    @else
        emitida el {{ $orden->fecha_solicitud->format('d/m/Y') }}
        (registrada el {{ $orden->created_at->format('d/m/Y \a \l\a\s H:i') }}) ·
    @endif
    {{ $institucion['establecimiento'] }}.
    Documento generado por el Sistema de Gestión de Talento Humano del GAD
    Provincial de Esmeraldas; su validez puede verificarse en el Dispensario
    Médico citando el número de orden. Válida únicamente para el paciente
    identificado en este documento.
</div>

</body>
</html>
