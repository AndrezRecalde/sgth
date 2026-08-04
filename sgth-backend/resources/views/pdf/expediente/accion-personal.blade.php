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
        .titulo { text-align: center; margin: 0 0 12px 0; }
        .titulo .supra {
            font-size: 9px;
            letter-spacing: 2px;
            color: #5b6b63;
            text-transform: uppercase;
        }
        .titulo h1 {
            font-size: 17px;
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
            margin: 7px auto 0 auto;
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
            margin-bottom: 14px;
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
            margin-bottom: 14px;
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
            padding: 8px 10px;
            border: 0.7px solid #c6d2cc;
        }
        .campo { margin-bottom: 5px; }
        .campo:last-child { margin-bottom: 0; }
        .campo .label {
            display: block;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #6b7c74;
        }
        .campo .valor { font-size: 10.5px; }
        .campo .valor.fuerte { font-weight: bold; }
        .vacio { color: #9aa8a1; }

        /* ── Firmas ───────────────────────────────────────────── */
        .firmas { width: 100%; margin-top: 34px; }
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
    </style>
</head>
<body>

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

    // Situación actual: se toman los valores congelados en la acción, no los
    // que el puesto tenga hoy. Un documento reimpreso años después debe seguir
    // mostrando las cifras que tuvo el original.
    $rmuOrigen = $movimiento->remuneracion_origen ?? $movimiento->puestoOrigen->rmu ?? null;
    $partidaOrigen = $movimiento->partidaOrigen->codigo
        ?? $movimiento->puestoOrigen->partidaPresupuestaria->codigo
        ?? null;

    // Propuesta: manda lo fijado en la acción; el puesto destino es el respaldo.
    $rmuPropuesta = $movimiento->remuneracion_propuesta ?? $movimiento->puestoDestino->rmu ?? null;
    $partidaPropuesta = $movimiento->partidaPresupuestaria->codigo
        ?? $movimiento->puestoDestino->partidaPresupuestaria->codigo
        ?? null;
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
            <span class="codigo">N.º {{ $codigoImpreso ?: 'S/N' }}</span>
        </td>
        <td class="lugar-fecha">
            Esmeraldas, {{ \Carbon\Carbon::parse($movimiento->fecha_efectiva)->locale('es')->isoFormat('DD [de] MMMM [del] YYYY') }}
        </td>
    </tr>
</table>

<div class="titulo">
    <div class="supra">Acción de Personal</div>
    <h1>{{ $movimiento->tipo_movimiento->etiqueta() }}</h1>
    @if($movimiento->subtipo_movimiento)
        <div class="subtipo">{{ $movimiento->subtipo_movimiento->etiqueta() }}</div>
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
        <td colspan="3"><strong>{!! $dato($fmtFecha($movimiento->fecha_efectiva)) !!}</strong></td>
    </tr>
</table>

<div class="bloque-titulo">Explicación</div>
<div class="explicacion">
    {{ $movimiento->descripcion }}
    @if($movimiento->resolucion_numero)
        <div class="respaldo">
            <strong>Documento de respaldo:</strong> {{ $movimiento->resolucion_numero }}
        </div>
    @endif
</div>

<table class="situacion">
    <tr>
        <th>Situación actual</th>
        <th class="propuesta">Situación propuesta</th>
    </tr>
    <tr>
        <td>
            @if($esIngreso)
                <div class="campo">
                    <span class="valor vacio">
                        Sin vínculo laboral previo — este es el primer ingreso del servidor.
                    </span>
                </div>
            @else
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
            <div class="campo"><span class="label">Dirección</span><span class="valor">{!! $dato($movimiento->unidadDestino->nombre ?? null) !!}</span></div>
            <div class="campo"><span class="label">Grupo ocupacional</span><span class="valor">{!! $dato($movimiento->puestoDestino->grupoOcupacional->denominacion_generica ?? null) !!}</span></div>
            <div class="campo"><span class="label">Puesto</span><span class="valor fuerte">{!! $dato($movimiento->puestoDestino->cargo->nombre ?? null) !!}</span></div>
            <div class="campo"><span class="label">Grado</span><span class="valor">{!! $dato($movimiento->puestoDestino->grupoOcupacional->grado_codigo ?? null) !!}</span></div>
            <div class="campo"><span class="label">Lugar de trabajo</span><span class="valor">{!! $dato($movimiento->puestoDestino ? ($movimiento->lugar_trabajo ?: 'Esmeraldas') : null) !!}</span></div>
            <div class="campo"><span class="label">R.M.U.</span><span class="valor fuerte">{!! $dato($fmtRmu($rmuPropuesta)) !!}</span></div>
            <div class="campo"><span class="label">Partida presupuestaria</span><span class="valor">{!! $dato($partidaPropuesta) !!}</span></div>
        </td>
    </tr>
</table>

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
            </td>
            <td style="width: 50%;">
                <div class="firma-rotulo">{{ $firmaTalentoHumano['rotulo'] }}</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">{{ $firmaTalentoHumano['nombre'] ?? $firmaTalentoHumano['cargo'] }}</p>
                <p class="firma-cargo">{{ $firmaTalentoHumano['cargo'] }}</p>
            </td>
        </tr>
    </table>
</div>

<div class="page-break"></div>

<table class="cintillo">
    <tr>
        <td style="width: 50%;">
            <span class="codigo">N.º {{ $codigoImpreso ?: 'S/N' }}</span>
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

<div class="bloque-titulo">Posesión del cargo</div>
<div class="juramento">
    Yo, <strong>{{ trim($apellidosServidor.' '.$nombresServidor) }}</strong>,
    con cédula de ciudadanía N.º <strong>{{ $servidor->cedula ?? '—' }}</strong>,
    juro lealtad al Estado ecuatoriano.
</div>

<table class="datos">
    <tr>
        <td class="label">Lugar</td>
        <td style="height: 22px;">&nbsp;</td>
        <td class="label">Fecha</td>
        <td>&nbsp;</td>
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

</body>
</html>
