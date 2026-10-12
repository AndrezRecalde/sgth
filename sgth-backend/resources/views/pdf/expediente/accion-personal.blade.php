<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acción de Personal</title>
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
            /* Sin letter-spacing: en mayúscula y a este cuerpo, el nombre
               completo solo entra en una línea si no se le añade holgura. */
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

        /* ── Título del acto ──────────────────────────────────── */
        .titulo { text-align: center; margin: 0 0 8px 0; }
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
        .titulo .subtipo {
            font-size: 11px;
            color: #3d4d46;
            margin-top: 1px;
        }
        .titulo .regla {
            border-bottom: 1.6px solid #17593f;
            width: 56px;
            margin: 5px auto 0 auto;
        }

        /* ── Cintillo de código y fecha ────────────────────────── */
        .cintillo { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .cintillo td { border: none; padding: 0; font-size: 9.5px; }
        .cintillo .codigo {
            border: 1px solid #17593f;
            border-radius: 2px;
            padding: 4px 9px;
            font-weight: bold;
            font-size: 11px;
            color: #17593f;
            letter-spacing: 0.5px;
        }
        .cintillo .lugar-fecha { text-align: right; color: #3d4d46; }

        /* ── Tablas de datos ──────────────────────────────────── */
        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.datos td {
            border: 0.7px solid #c6d2cc;
            padding: 5px 8px;
            font-size: 10px;
            vertical-align: middle;
        }
        table.datos td.label {
            font-weight: bold;
            font-size: 8.5px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #3d4d46;
            background: #f2f6f4;
            width: 21%;
        }
        /* Los nombres del servidor van en mayúscula, como en el formulario
           oficial impreso. Se hace por CSS para no alterar el dato guardado. */
        table.datos td.mayus { text-transform: uppercase; }

        /* ── Bloques con título ───────────────────────────────── */
        .bloque-titulo {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: #17593f;
            border-bottom: 0.7px solid #cfdad4;
            padding-bottom: 3px;
            margin: 0 0 6px 0;
        }
        .explicacion {
            text-align: justify;
            font-size: 10.5px;
            margin-bottom: 10px;
        }
        .respaldo {
            margin-top: 5px;
            font-size: 9.5px;
            color: #3d4d46;
        }

        /* ── Situación actual vs propuesta ────────────────────── */
        table.situacion {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        table.situacion th {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            padding: 5px 8px;
            background: #17593f;
            color: #ffffff;
            width: 50%;
        }
        table.situacion th.propuesta { background: #2f7d5c; }
        table.situacion td {
            vertical-align: top;
            width: 50%;
            padding: 6px 8px;
            border: 0.7px solid #c6d2cc;
        }
        /* Etiqueta y valor en la misma línea: con la rejilla de casillas y el
           acto de origen, apilados ya no cabía la primera página (fase 6.1). */
        .campo { margin-bottom: 3px; }
        .campo:last-child { margin-bottom: 0; }
        .campo .label {
            display: inline-block;
            width: 38%;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #6b7c74;
            vertical-align: top;
        }
        .campo .valor { display: inline-block; width: 60%; font-size: 10px; vertical-align: top; }
        .campo .valor.entero { width: 100%; }
        .campo .valor.fuerte { font-weight: bold; }
        .vacio { color: #9aa8a1; }

        /* ── Firmas ───────────────────────────────────────────── */
        .firmas { width: 100%; margin-top: 18px; page-break-inside: avoid; }
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
            margin-bottom: 36px;
        }
        .firma-linea {
            border-top: 0.9px solid #1c2321;
            width: 84%;
            margin: 0 auto 4px auto;
        }
        .firma-nombre { font-weight: bold; font-size: 10.5px; margin: 0; }
        .firma-cargo { font-size: 9.5px; color: #3d4d46; margin: 1px 0 0 0; }

        .juramento {
            text-align: justify;
            font-size: 11px;
            line-height: 1.7;
            padding: 10px 14px;
            border-left: 2.5px solid #17593f;
            background: #f6faf8;
            margin-bottom: 14px;
        }

        .page-break { page-break-before: always; }

        /* ── Sello de anulación ────────────────────────────────── */
        /* Un acto anulado sigue teniendo documento: lo que cambia es que el
           papel tiene que decir, sin que haya que buscarlo, que está sin
           efecto. El sello va en el cuerpo y no en un `position: fixed`
           rotado: dompdf no rota texto de forma fiable, y un fijo mal
           colocado se sale de la hoja. */
        .anulada {
            border: 2.5px solid #a3161b;
            background: #fdf2f2;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .anulada .sello {
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #a3161b;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }
        .anulada .detalle {
            font-size: 10px;
            color: #6b2226;
            margin: 0;
        }
        .anulada .motivo {
            font-size: 10.5px;
            color: #1c2321;
            margin: 6px 0 0 0;
            text-align: justify;
        }

        /* ── Formato del Ministerio (fase 6.1) ─────────────────── */
        /* Una casilla de marcar: un cuadro con borde y la X dentro. Los
           símbolos de casilla de la fuente no salen iguales en todos los
           visores; un borde, sí. */
        .caja {
            display: inline-block;
            width: 9px;
            height: 9px;
            line-height: 9px;
            border: 0.8px solid #1c2321;
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            margin-right: 4px;
            vertical-align: middle;
        }
        table.acto-origen { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.acto-origen td {
            border: 0.7px solid #c6d2cc;
            padding: 4px 8px;
            font-size: 9.5px;
        }
        table.acto-origen td.label {
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            color: #3d4d46;
            background: #f2f6f4;
        }
        table.rejilla { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.rejilla td {
            border: 0.7px solid #c6d2cc;
            padding: 3px 6px;
            font-size: 8px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            color: #3d4d46;
            width: 25%;
        }
        table.rejilla td.marcada { background: #e6f1ec; color: #17593f; font-weight: bold; }
        .base-legal { font-size: 9.5px; color: #3d4d46; margin-bottom: 5px; }

@include('pdf.expediente.partials.vista-previa-estilos')
    </style>
</head>
<body>
@if($vistaPrevia ?? false)
    @include('pdf.expediente.partials.vista-previa-marca')
@endif

@php
    $apellidosServidor = trim(collect([
        $servidor->apellido, $servidor->segundo_apellido,
    ])->filter()->join(' '));
    $nombresServidor = trim(collect([
        $servidor->nombre, $servidor->segundo_nombre,
    ])->filter()->join(' '));

    $fmtFecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->locale('es')->isoFormat('DD/MM/YYYY') : null;
    $fmtRmu = fn ($v) => $v !== null ? ('$ '.number_format((float) $v, 2)) : null;

    // Un valor ausente se imprime como raya tenue, no como texto normal: en un
    // documento oficial conviene que se vea que el dato no aplica.
    $dato = fn ($v) => filled($v)
        ? e($v)
        : '<span class="vacio">—</span>';

    // El código lo genera el sistema al registrar la acción. 'codigo' es un
    // campo libre que casi nunca se llena, y era el que se imprimía antes.
    $codigoImpreso = $movimiento->codigo_registro ?: $movimiento->codigo;

    $esIngreso = $movimiento->tipo_movimiento === \App\Enums\TipoMovimientoPersonal::INGRESO;

    // El reintegro devuelve al servidor al puesto que conservó durante la
    // ausencia (fase 2.4), y la amonestación escrita no lo mueve (fase 2.5): la
    // situación propuesta es la misma que la actual.
    $esReintegro = $movimiento->tipo_movimiento === \App\Enums\TipoMovimientoPersonal::REINTEGRO;
    // El del ascendido que no superó la prueba sí cambia de puesto: vuelve al
    // que tenía (fase 3.3).
    $reintegroDelAscenso = $esReintegro && $movimiento->esReintegroDelAscenso();
    $esReintegro = $esReintegro && ! $reintegroDelAscenso;
    $esAscenso = $movimiento->tipo_movimiento === \App\Enums\TipoMovimientoPersonal::ASCENSO;
    $mismoPuesto = $esReintegro
        || $movimiento->subtipo_movimiento === \App\Enums\SubtipoMovimientoPersonal::AMONESTACION_ESCRITA
        // El nombramiento definitivo: el mismo puesto, ahora permanente (fase 3.2).
        || $movimiento->tipo_movimiento === \App\Enums\TipoMovimientoPersonal::NOMBRAMIENTO_DEFINITIVO;
    $ausenciaQueCierra = $esReintegro ? $movimiento->movimientoRelacionado : null;

    // La restitución vuelve por sentencia (fase 2.6): dice qué cesación deja
    // sin efecto y con qué causa judicial.
    $esRestitucion = $movimiento->tipo_movimiento === \App\Enums\TipoMovimientoPersonal::RESTITUCION;
    $cesacionSinEfecto = $esRestitucion ? $movimiento->movimientoRelacionado : null;

    // El título es el nombre de la clase —traslado, encargo, cesación…—, el
    // mismo que se ve en pantalla. Subrogación y encargo comparten tipo, pero
    // no clase: un encargo impreso como «Subrogación» diría que se reemplazó a
    // un titular que no existe.
    $rotuloTipo = $movimiento->etiqueta();

    // Situación actual: se toman los valores congelados en la acción, no los
    // que el puesto tenga hoy. Un documento reimpreso años después debe seguir
    // mostrando las cifras que tuvo el original.
    $rmuOrigen = $movimiento->remuneracion_origen ?? $movimiento->puestoOrigen->rmu ?? null;
    $partidaOrigen = $movimiento->partidaOrigen->codigo
        ?? $movimiento->puestoOrigen->partidaPresupuestaria->codigo
        ?? null;

    // Propuesta: manda lo fijado en la acción; el puesto destino es el respaldo.
    $puestoPropuesto = $mismoPuesto ? $movimiento->puestoOrigen : $movimiento->puestoDestino;
    $unidadPropuesta = $mismoPuesto ? $movimiento->unidadOrigen : $movimiento->unidadDestino;
    $rmuPropuesta = $mismoPuesto
        ? $rmuOrigen
        : ($movimiento->remuneracion_propuesta ?? $movimiento->puestoDestino->rmu ?? null);
    $partidaPropuesta = $mismoPuesto
        ? $partidaOrigen
        : ($movimiento->partidaPresupuestaria->codigo
            ?? $movimiento->puestoDestino->partidaPresupuestaria->codigo
            ?? null);

    // ── El formato del Ministerio (diseño, 6.5; fase 6.1) ──────────
    // Los arma AccionPersonalPdfService; quien renderiza la vista sin él
    // —una prueba— los recibe vacíos.
    $modalidadActual = $modalidadActual ?? null;
    // La vista previa de lo que todavía no se registra (fase 6.2).
    $vistaPrevia = $vistaPrevia ?? false;
    $modalidadPropuesta = $modalidadPropuesta ?? null;
    $reemplazo = $reemplazo ?? null;

    // La fecha de emisión es la de suscripción, no la de vigencia.
    // En la vista previa de un borrador todavía no hay: se fija al suscribir.
    $emision = $movimiento->fechaDeEmision() ?? ($vistaPrevia ? null : $movimiento->fecha_efectiva);
    $casilla = $movimiento->casilla();
    $textoOtro = $movimiento->textoDeOtro();
    $casillas = \App\Enums\CasillaAccionPersonal::cases();

    // Las temporales dicen «hasta», y en vez de la situación propuesta, el
    // período: la comisión y la licencia no mueven al servidor de su puesto.
    $esTemporal = $movimiento->esAusenciaTemporal();
    $esSubrogacion = $movimiento->tipo_movimiento === \App\Enums\TipoMovimientoPersonal::SUBROGACION;
    $hasta = ($esTemporal || $esSubrogacion) ? $movimiento->fecha_fin : null;
    // El encargo sin fin dura hasta la designación del titular (fase 5.2).
    $encargoSinFin = $esSubrogacion && ! $movimiento->fecha_fin;
@endphp

<div class="membrete">
    <table>
        <tr>
            {{-- El logo cede ancho: el nombre institucional en mayúscula
                 ocupa bastante más y no debe partirse en tres líneas. --}}
            <td style="width: 26%;">
                @if(file_exists($logo))
                    <img src="{{ $logo }}" alt="Logo GADPE">
                @endif
            </td>
            <td class="institucion">
                <strong>Gobierno Autónomo Descentralizado Provincial de Esmeraldas</strong>
                Dirección de Gestión de Talento Humano
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
            <span class="codigo">{{ $vistaPrevia ? 'BORRADOR' : 'N.º '.($codigoImpreso ?: 'S/N') }}</span>
        </td>
        <td class="lugar-fecha">
            Esmeraldas, {{ $emision ? \Carbon\Carbon::parse($emision)->locale('es')->isoFormat('DD [de] MMMM [del] YYYY') : ($vistaPrevia ? 'la fecha de emisión se fija al suscribir' : '—') }}
        </td>
    </tr>
</table>

{{-- El decreto, acuerdo o resolución que la origina: el encabezado del
     formulario lo pide con su número y su fecha. --}}
<table class="acto-origen">
    <tr>
        @foreach(\App\Enums\TipoActoDeOrigen::cases() as $tipoActo)
            <td style="width: 16%;"><span class="caja">{!! $movimiento->acto_origen_tipo === $tipoActo ? 'X' : '&nbsp;' !!}</span>{{ $tipoActo->etiqueta() }}</td>
        @endforeach
        <td class="label" style="width: 8%;">N.º</td>
        <td style="width: 22%;">{!! $dato($movimiento->acto_origen_numero) !!}</td>
        <td class="label" style="width: 8%;">Fecha</td>
        <td>{!! $dato($fmtFecha($movimiento->acto_origen_fecha)) !!}</td>
    </tr>
</table>

@if($vistaPrevia)
    @include('pdf.expediente.partials.vista-previa-sello')
@endif

@if($anulada ?? false)
    <div class="anulada">
        <p class="sello">Acción de personal anulada</p>
        <p class="detalle">
            Este documento quedó sin efecto y no surte ninguna consecuencia
            jurídica ni administrativa.
        </p>
        @if($movimiento->motivo_anulacion)
            <p class="motivo"><strong>Motivo:</strong> {{ $movimiento->motivo_anulacion }}</p>
        @endif
    </div>
@endif

<div class="titulo">
    <div class="supra">Acción de Personal</div>
    <h1>{{ $rotuloTipo }}</h1>
    @if($movimiento->causal())
        <div class="subtipo">{{ $movimiento->causal()->etiqueta() }}</div>
    @endif
    <div class="regla"></div>
</div>

<table class="datos">
    <tr>
        <td class="label">Apellidos</td>
        <td class="mayus">{!! $dato($apellidosServidor) !!}</td>
        <td class="label">Cédula de ciudadanía</td>
        <td>{!! $dato($servidor->cedula) !!}</td>
    </tr>
    <tr>
        <td class="label">Nombres</td>
        <td class="mayus">{!! $dato($nombresServidor) !!}</td>
        <td class="label">Comp. de votación</td>
        <td>{!! $dato($servidor->numero_papeleta_votacion) !!}</td>
    </tr>
    <tr>
        <td class="label">Rige a partir de</td>
        @if($hasta || $encargoSinFin)
            <td><strong>{!! $dato($fmtFecha($movimiento->fecha_efectiva)) !!}</strong></td>
            <td class="label">Hasta</td>
            <td><strong>{{ $hasta ? $fmtFecha($hasta) : 'La designación del titular' }}</strong></td>
        @else
            <td colspan="3"><strong>{!! $dato($fmtFecha($movimiento->fecha_efectiva)) !!}</strong></td>
        @endif
    </tr>
</table>

<div class="bloque-titulo">Explicación</div>
<div class="explicacion">
    {{-- La base legal que pone la clase, congelada al suscribir (fase 6.1). --}}
    @if($movimiento->baseLegalImpresa())
        <div class="base-legal"><strong>Base legal:</strong> {{ $movimiento->baseLegalImpresa() }}</div>
    @endif
    {{ $movimiento->descripcion }}
    {{-- Fase 2.3: a dónde va y cuánto dura. Hasta aquí la institución iba,
         si iba, en la explicación, y el período solo salía en la suspensión. --}}
    @if($movimiento->institucion_destino && ! $esTemporal)
        <div class="respaldo">
            <strong>Institución de destino:</strong> {{ $movimiento->institucion_destino }}
        </div>
    @endif
    @if($ausenciaQueCierra)
        {{-- Fase 2.4: qué ausencia cierra y hasta cuándo duró. --}}
        @php
            $cierra = ($ausenciaQueCierra->etiquetaAusencia() ?? $ausenciaQueCierra->etiqueta())
                .($ausenciaQueCierra->codigo_registro ? " ({$ausenciaQueCierra->codigo_registro})" : '')
                .($ausenciaQueCierra->fecha_inicio
                    ? ', del '.$fmtFecha($ausenciaQueCierra->fecha_inicio)
                        .($ausenciaQueCierra->fecha_fin ? ' al '.$fmtFecha($ausenciaQueCierra->fecha_fin) : '')
                    : '');
        @endphp
        <div class="respaldo">
            <strong>Termina:</strong> {{ $cierra }}. El último día de la ausencia es el
            {{ $fmtFecha($movimiento->fecha_efectiva?->copy()->subDay()) }}.
        </div>
    @endif
    @if($esRestitucion)
        @if($cesacionSinEfecto)
            <div class="respaldo">
                <strong>Deja sin efecto:</strong>
                {{ $cesacionSinEfecto->causal()?->etiqueta() ?? $cesacionSinEfecto->etiqueta() }}{{ $cesacionSinEfecto->codigo_registro ? " ({$cesacionSinEfecto->codigo_registro})" : '' }},
                que rigió desde el {{ $fmtFecha($cesacionSinEfecto->fecha_efectiva) }}.
            </div>
        @endif
        <div class="respaldo">
            <strong>Sentencia:</strong> causa N.º {{ $movimiento->causa_judicial_numero }}, {{ $movimiento->unidad_judicial }};
            dictada el {{ $fmtFecha($movimiento->fecha_sentencia) }} y ejecutoriada el
            {{ $fmtFecha($movimiento->fecha_ejecutoria) }} (LOSEP Art. 46).
        </div>
        <div class="respaldo">
            <strong>Remuneraciones dejadas de percibir:</strong>
            {!! $dato($fmtRmu($movimiento->remuneraciones_dejadas_de_percibir)) !!}
        </div>
    @endif
    @if($movimiento->para_estudios_o_eventos)
        <div class="respaldo">
            Al terminar la comisión, el servidor debe servir en la institución un tiempo igual
            al de su duración (LOSEP Art. 30).
        </div>
    @endif
    @if($movimiento->supuesto_provisional_propuesto)
        {{-- Fase 3.2: para qué se da el provisional, y el acta del concurso del de prueba. --}}
        <div class="respaldo">
            <strong>Nombramiento provisional:</strong> {{ $movimiento->supuesto_provisional_propuesto->etiqueta() }}
            ({{ $movimiento->supuesto_provisional_propuesto->baseLegal() }}).
        </div>
    @endif
    @if($esAscenso)
        {{-- Fase 3.3: de prueba por seis meses, con el puesto de antes reservado. --}}
        <div class="respaldo">
            <strong>Ascenso:</strong> nombramiento provisional de prueba por seis meses (LOSEP Arts. 17 b.5 y 68).
            Se le reserva su puesto de {{ $movimiento->puestoOrigen?->cargo?->nombre ?? 'origen' }}: si no supera
            la prueba, vuelve a él con su remuneración anterior.
        </div>
    @endif
    @if($reintegroDelAscenso)
        @php $ascensoNoSuperado = $movimiento->movimientoRelacionado; @endphp
        <div class="respaldo">
            <strong>Vuelve:</strong> no superó el período de prueba del ascenso{{ $ascensoNoSuperado?->codigo_registro ? " ({$ascensoNoSuperado->codigo_registro})" : '' }},
            que rigió desde el {{ $fmtFecha($ascensoNoSuperado?->fecha_efectiva) }}: vuelve a su puesto anterior con
            su remuneración anterior (LOSEP Art. 17 b.5).
        </div>
    @endif
    @if($movimiento->informe_uath_numero)
        {{-- Fase 3.1: el traslado se hace previo informe de la UATH (LOSEP Art. 35).
             En el reintegro del ascendido es el de su evaluación (fase 3.3). --}}
        <div class="respaldo">
            <strong>{{ $reintegroDelAscenso ? 'Informe de la evaluación del período de prueba' : 'Informe técnico de la UATH' }}:</strong>
            {{ $movimiento->informe_uath_numero }}
        </div>
    @endif
    @if($movimiento->resolucion_numero)
        <div class="respaldo">
            <strong>Documento de respaldo:</strong> {{ $movimiento->resolucion_numero }}
        </div>
    @endif
</div>

{{-- Las 23 casillas del formulario, con la de la acción marcada. «Otro»
     lleva el nombre de la acción: una sanción, una cesación sin casilla
     propia. --}}
<table class="rejilla">
    @foreach(array_chunk($casillas, 4) as $fila)
        <tr>
            @foreach($fila as $i => $c)
                <td class="{{ $c === $casilla ? 'marcada' : '' }}"
                    @if($loop->last && count($fila) < 4) colspan="{{ 5 - count($fila) }}" @endif>
                    <span class="caja">{!! $c === $casilla ? 'X' : '&nbsp;' !!}</span>{{ $c->etiqueta() }}@if($c === \App\Enums\CasillaAccionPersonal::OTRO && $textoOtro): {{ $textoOtro }}@endif
                </td>
            @endforeach
        </tr>
    @endforeach
</table>

<table class="situacion">
    <tr>
        <th>Situación actual</th>
        <th class="propuesta">{{ !empty($descuento) ? 'Efecto en la remuneración' : ($esTemporal ? 'Período' : 'Situación propuesta') }}</th>
    </tr>
    <tr>
        <td>
            @if($esIngreso)
                <div class="campo">
                    <span class="valor vacio entero">
                        Sin vínculo laboral previo — este es el primer ingreso del servidor.
                    </span>
                </div>
            @elseif($esRestitucion)
                <div class="campo">
                    <span class="valor vacio entero">
                        Sin vínculo desde el {{ $fmtFecha($cesacionSinEfecto?->fecha_efectiva) }}, por la cesación
                        que esta acción deja sin efecto.
                    </span>
                </div>
            @else
                <div class="campo"><span class="label">Modalidad</span><span class="valor">{!! $dato($modalidadActual?->etiqueta()) !!}</span></div>
                <div class="campo"><span class="label">Dirección</span><span class="valor">{!! $dato($movimiento->unidadOrigen->nombre ?? null) !!}</span></div>
                <div class="campo"><span class="label">Grupo ocupacional</span><span class="valor">{!! $dato($movimiento->puestoOrigen->grupoOcupacional->denominacion_generica ?? null) !!}</span></div>
                <div class="campo"><span class="label">Puesto</span><span class="valor fuerte">{!! $dato($movimiento->puestoOrigen->cargo->nombre ?? null) !!}</span></div>
                <div class="campo"><span class="label">Grado</span><span class="valor">{!! $dato($movimiento->puestoOrigen->grupoOcupacional->grado_codigo ?? null) !!}</span></div>
                <div class="campo"><span class="label">Lugar de trabajo</span><span class="valor">{!! $dato($movimiento->lugar_trabajo ?: 'Esmeraldas') !!}</span></div>
                <div class="campo"><span class="label">R.M.U.</span><span class="valor fuerte">{!! $dato($fmtRmu($rmuOrigen)) !!}</span></div>
                <div class="campo"><span class="label">Partida presupuestaria</span><span class="valor">{!! $dato($partidaOrigen) !!}</span></div>
            @endif
        </td>
        <td>
        @if(!empty($descuento))
            {{-- Multa o suspensión (2026-10-04): el servidor sigue en su puesto,
                 así que la columna dice lo que Financiero necesita para el rol
                 de pagos. Valor de referencia: lo aplica Financiero. --}}
            <div class="campo"><span class="label">Sanción</span><span class="valor fuerte">{{ $descuento['sancion'] }}</span></div>
            <div class="campo"><span class="label">Detalle</span><span class="valor">{{ $descuento['detalle'] }}</span></div>
            @if($descuento['hasta'])
                <div class="campo"><span class="label">Período</span><span class="valor">Del {{ $fmtFecha($descuento['desde']) }} al {{ $fmtFecha($descuento['hasta']) }}</span></div>
            @endif
            <div class="campo"><span class="label">Base de cálculo (R.M.U.)</span><span class="valor">{!! $dato($fmtRmu($descuento['base'])) !!}</span></div>
            <div class="campo"><span class="label">Descuento referencial</span><span class="valor fuerte">{!! $dato($fmtRmu($descuento['monto'])) !!}</span></div>
            <div class="campo"><span class="valor vacio entero">
                {{ $descuento['hasta'] ? 'R.M.U. ÷ 30 × días de suspensión.' : 'Porcentaje de la multa sobre la R.M.U.' }}
                Valor de referencia: el descuento lo aplica Financiero en el rol de pagos.
            </span></div>
        @elseif($esTemporal)
            {{-- La comisión y la licencia no mueven al servidor: el documento
                 dice cuándo y, en la comisión, a dónde va (diseño, 6.5). --}}
            <div class="campo"><span class="label">Desde</span><span class="valor fuerte">{!! $dato($fmtFecha($movimiento->fecha_inicio ?? $movimiento->fecha_efectiva)) !!}</span></div>
            <div class="campo"><span class="label">Hasta</span><span class="valor fuerte">{!! $dato($fmtFecha($movimiento->fecha_fin)) !!}</span></div>
            @if($movimiento->institucion_destino)
                <div class="campo"><span class="label">Institución de destino</span><span class="valor">{{ $movimiento->institucion_destino }}</span></div>
            @endif
        @else
            <div class="campo"><span class="label">Modalidad</span><span class="valor">{!! $dato($modalidadPropuesta?->etiqueta()) !!}</span></div>
            <div class="campo"><span class="label">Dirección</span><span class="valor">{!! $dato($unidadPropuesta->nombre ?? null) !!}</span></div>
            <div class="campo"><span class="label">Grupo ocupacional</span><span class="valor">{!! $dato($puestoPropuesto->grupoOcupacional->denominacion_generica ?? null) !!}</span></div>
            <div class="campo"><span class="label">Puesto</span><span class="valor fuerte">{!! $dato($puestoPropuesto->cargo->nombre ?? null) !!}</span></div>
            <div class="campo"><span class="label">Grado</span><span class="valor">{!! $dato($puestoPropuesto->grupoOcupacional->grado_codigo ?? null) !!}</span></div>
            <div class="campo"><span class="label">Lugar de trabajo</span><span class="valor">{!! $dato($puestoPropuesto ? ($movimiento->lugar_trabajo ?: 'Esmeraldas') : null) !!}</span></div>
            <div class="campo"><span class="label">R.M.U.</span><span class="valor fuerte">{!! $dato($fmtRmu($rmuPropuesta)) !!}</span></div>
            <div class="campo"><span class="label">Partida presupuestaria</span><span class="valor">{!! $dato($partidaPropuesta) !!}</span></div>
        @endif
        </td>
    </tr>
</table>

@if($movimiento->acta_concurso_numero)
    <table class="datos">
        <tr>
            <td class="label">Acta final del concurso</td>
            <td>N.º {{ $movimiento->acta_concurso_numero }}</td>
            <td class="label">Fecha</td>
            <td>{!! $dato($fmtFecha($movimiento->acta_concurso_fecha)) !!}</td>
        </tr>
    </table>
@endif

<div class="firmas">
    <table>
        <tr>
            {{-- Firmantes sellados al suscribir la acción: no se resuelven al
                 imprimir, para que una reimpresión no atribuya la firma a quien
                 ocupe hoy el cargo. --}}
            <td style="width: 50%;">
                <div class="firma-rotulo">{{ $firmaAutoridad['rotulo'] }}</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $firmaAutoridad['nombre'] ?? $firmaAutoridad['cargo'] }}</p>
                <p class="firma-cargo">{{ $firmaAutoridad['cargo'] }}</p>
                @if(!empty($firmaAutoridad['provisional']))
                    <p class="firma-provisional">{{ $firmaAutoridad['nombre'] ? 'Provisional: se sella al suscribir' : 'Sin firmante: así no se podría suscribir' }}</p>
                @endif
            </td>
            <td style="width: 50%;">
                <div class="firma-rotulo">{{ $firmaTalentoHumano['rotulo'] }}</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $firmaTalentoHumano['nombre'] ?? $firmaTalentoHumano['cargo'] }}</p>
                <p class="firma-cargo">{{ $firmaTalentoHumano['cargo'] }}</p>
                @if(!empty($firmaTalentoHumano['provisional']))
                    <p class="firma-provisional">{{ $firmaTalentoHumano['nombre'] ? 'Provisional: se sella al suscribir' : 'Sin firmante: así no se podría suscribir' }}</p>
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- Registro y control: el número y la fecha con que se registró. --}}
<div class="bloque-titulo" style="margin-top: 14px;">Registro y control</div>
<table class="datos">
    <tr>
        <td class="label">Registrada con N.º</td>
        <td>{!! $dato($movimiento->codigo_registro) !!}</td>
        <td class="label">Fecha de registro</td>
        <td>{!! $dato($fmtFecha($movimiento->fecha_registro)) !!}</td>
    </tr>
</table>

<div class="page-break"></div>

<table class="cintillo">
    <tr>
        <td style="width: 50%;">
            <span class="codigo">{{ $vistaPrevia ? 'BORRADOR' : 'N.º '.($codigoImpreso ?: 'S/N') }}</span>
        </td>
        <td class="lugar-fecha">
            {{ trim($apellidosServidor.' '.$nombresServidor) }} · CI {{ $servidor->cedula ?? '—' }}
        </td>
    </tr>
</table>

<div class="bloque-titulo">Caución</div>
<table class="datos">
    <tr>
        <td class="label">Caucionado</td>
        <td>{!! $dato($movimiento->caucionado === null ? null : ($movimiento->caucionado ? 'SÍ' : 'NO')) !!}</td>
        <td class="label">Fecha</td>
        <td>{!! $dato($fmtFecha($movimiento->caucion_fecha)) !!}</td>
    </tr>
    <tr>
        <td class="label">Registrada con N.º</td>
        <td colspan="3">{!! $dato($movimiento->caucion_numero) !!}</td>
    </tr>
</table>

{{-- A quién reemplaza: la ausencia que cubre, el titular que subroga o
     quien ocupaba antes el puesto (fase 6.1). En las clases que ponen al
     servidor en un puesto, el bloque sale aunque no haya a quién. --}}
@if($reemplazo || $movimiento->llevaPosesion())
    <div class="bloque-titulo">Reemplazo</div>
    <table class="datos">
        <tr>
            <td class="label">Reemplaza a</td>
            <td>{!! $dato($reemplazo['a'] ?? null) !!}</td>
            <td class="label">En el puesto de</td>
            <td>{!! $dato($reemplazo['puesto'] ?? null) !!}</td>
        </tr>
        <tr>
            <td class="label">{{ $reemplazo['rotulo_por'] ?? 'Quien cesó por' }}</td>
            <td>{!! $dato($reemplazo['por'] ?? null) !!}</td>
            <td class="label">Acción N.º</td>
            <td>{!! $dato($reemplazo['accion'] ?? null) !!}</td>
        </tr>
    </table>
@endif

{{-- La posesión, solo en el ingreso, el ascenso, el nombramiento definitivo
     y el encargo (diseño, 6.4): el documento la imprimía también en las
     sanciones y las cesaciones. --}}
@if($movimiento->llevaPosesion())
<div class="bloque-titulo">Posesión del cargo</div>
<div class="juramento">
    Yo, <strong>{{ trim($apellidosServidor.' '.$nombresServidor) }}</strong>,
    con cédula de ciudadanía N.º <strong>{{ $servidor->cedula ?? '—' }}</strong>,
    juro lealtad al Estado ecuatoriano.
</div>

{{-- El lugar y la fecha que registró Talento Humano (fase 6.4); sin
     registrar, en blanco para llenarlos a mano. --}}
<table class="datos">
    <tr>
        <td class="label">Lugar</td>
        <td style="height: 22px;">{{ $movimiento->lugar_posesion }}</td>
        <td class="label">Fecha</td>
        <td>{{ $fmtFecha($movimiento->fecha_posesion) }}</td>
    </tr>
</table>

{{-- Una sola firma, centrada: quien toma posesión es el funcionario. La
     validación de Talento Humano ya consta en la primera página, con el
     firmante sellado — repetirla aquí pedía una rúbrica que nadie estampa. --}}
<div class="firmas">
    <table>
        <tr>
            <td style="width: 30%;">&nbsp;</td>
            <td style="width: 40%;">
                <div class="firma-rotulo">Toma de posesión</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ trim($apellidosServidor.' '.$nombresServidor) }}</p>
                <p class="firma-cargo">Funcionario</p>
            </td>
            <td style="width: 30%;">&nbsp;</td>
        </tr>
    </table>
</div>
@endif

{{-- La notificación (Reg. 22; diseño, 6.4): por el portal, con el acuse del
     servidor; en persona, el «Recibí» o, si se negó a recibirla, la razón con
     un testigo (fase 6.3a). Sin notificar todavía, en blanco para llenarlo a
     mano. --}}
@php
    $medioNotificacion = $movimiento->notificacion_medio;
    $negada = $movimiento->notificacion_resultado === \App\Enums\ResultadoNotificacion::NEGADA;
    $recibida = $movimiento->notificacion_resultado === \App\Enums\ResultadoNotificacion::RECIBIDA;
@endphp
<div class="bloque-titulo" style="margin-top: 14px;">Notificación</div>
@if($medioNotificacion === \App\Enums\MedioNotificacion::PORTAL)
<table class="datos">
    <tr>
        <td class="label">Medio</td>
        <td>Portal del servidor y correo</td>
        <td class="label">Notificada el</td>
        <td>{!! $dato($fmtFecha($movimiento->fecha_notificacion)) !!}</td>
    </tr>
    <tr>
        <td class="label">Acuse de recibo</td>
        <td colspan="3">{{ $movimiento->acuse_en ? $movimiento->acuse_en->format('d/m/Y H:i') : 'Pendiente' }}</td>
    </tr>
</table>
@else
<table class="datos">
    <tr>
        <td class="label">Recibí</td>
        <td style="height: 26px;">&nbsp;</td>
        <td class="label">Fecha</td>
        <td>{!! $recibida ? e($fmtFecha($movimiento->fecha_notificacion)) : '&nbsp;' !!}</td>
    </tr>
    <tr>
        <td class="label">Nombre</td>
        <td colspan="3" class="mayus">{{ trim($apellidosServidor.' '.$nombresServidor) }}</td>
    </tr>
</table>
<table class="datos">
    <tr>
        <td class="label">Razón de la negativa</td>
        <td colspan="3" style="height: 26px;">{!! $negada ? e($movimiento->notificacion_razon) : '&nbsp;' !!}</td>
    </tr>
    <tr>
        <td class="label">Testigo</td>
        <td>{!! $negada ? e($movimiento->notificacion_testigo_nombre) : '&nbsp;' !!}</td>
        <td class="label">Cédula</td>
        <td>{!! $negada ? e($movimiento->notificacion_testigo_cedula) : '&nbsp;' !!}</td>
    </tr>
    <tr>
        <td class="label">Firma del testigo</td>
        <td colspan="3" style="height: 26px;">&nbsp;</td>
    </tr>
</table>
@endif

</body>
</html>
