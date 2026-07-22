<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acción de Personal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #222;
            margin: 0;
            padding: 30px 40px;
        }
        .header {
            width: 100%;
            margin-bottom: 15px;
        }
        .header img {
            max-width: 160px;
        }
        .titulo {
            text-align: center;
            margin: 10px 0 15px 0;
        }
        .titulo h1 {
            font-size: 13px;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .titulo h2 {
            font-size: 11px;
            font-weight: normal;
            margin: 2px 0;
        }
        .titulo h3 {
            font-size: 15px;
            font-weight: bold;
            margin: 4px 0 0 0;
            text-transform: uppercase;
        }
        .codigo-box {
            position: absolute;
            top: 30px;
            right: 40px;
            border: 1px solid #333;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: bold;
        }
        .fecha {
            text-align: right;
            font-size: 10px;
            margin-bottom: 10px;
        }
        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.datos td {
            border: 1px solid #999;
            padding: 5px 8px;
            font-size: 10px;
            vertical-align: top;
        }
        table.datos td.label {
            font-weight: bold;
            width: 22%;
            background: #f5f5f5;
        }
        .explicacion-titulo {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            margin: 15px 0 6px 0;
        }
        .explicacion-texto {
            text-align: justify;
            margin-bottom: 15px;
        }
        table.situacion {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.situacion th {
            text-align: center;
            font-size: 11px;
            padding: 6px 0;
        }
        table.situacion td {
            vertical-align: top;
            width: 50%;
            padding: 0 10px;
        }
        .campo {
            margin-bottom: 5px;
        }
        .campo .label {
            font-weight: bold;
            font-size: 9.5px;
        }
        .campo .valor {
            font-size: 10px;
        }
        .firmas {
            width: 100%;
            margin-top: 40px;
        }
        .firmas table {
            width: 100%;
        }
        .firmas td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .firma-titulo {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 40px;
        }
        .firma-linea {
            border-top: 1px solid #000;
            width: 80%;
            margin: 0 auto 4px auto;
        }
        .firma-nombre {
            font-weight: bold;
            font-size: 10px;
            margin: 0;
        }
        .firma-cargo {
            font-size: 9.5px;
            margin: 0;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0; right: 0;
            text-align: center;
            font-size: 8.5px;
            color: #4a7a4a;
            border-top: 1px solid #4a7a4a;
            padding-top: 4px;
        }
        .page-break { page-break-before: always; }
        .seccion-titulo {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            margin: 15px 0 8px 0;
        }
    </style>
</head>
<body>

@php
    $nombreCompletoServidor = trim(collect([
        $servidor->apellido, $servidor->segundo_apellido,
    ])->filter()->join(' '));
    $nombresServidor = trim(collect([
        $servidor->nombre, $servidor->segundo_nombre,
    ])->filter()->join(' '));

    $fmtFecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->locale('es')->isoFormat('DD/MM/YYYY') : null;
    $fmtRmu = fn ($v) => $v !== null ? ('$ '.number_format((float) $v, 2)) : null;
@endphp

<div class="codigo-box">Cod.: {{ $movimiento->codigo ?? 'S/N' }}</div>

<div class="header">
    @if(file_exists($logo))
        <img src="{{ $logo }}" alt="Logo GADPE">
    @endif
</div>

<div class="titulo">
    <h1>GESTIÓN DE TALENTO HUMANO</h1>
    <h2>ACCIÓN DE PERSONAL</h2>
    <h3>{{ $movimiento->tipo_movimiento->etiqueta() }}</h3>
</div>

<div class="fecha">
    Fecha:<br>
    Esmeraldas, {{ \Carbon\Carbon::parse($movimiento->fecha_efectiva)->locale('es')->isoFormat('DD [de] MMMM [del] YYYY') }}
</div>

<table class="datos">
    <tr>
        <td class="label">APELLIDOS:</td>
        <td>{{ $nombreCompletoServidor ?: '—' }}</td>
        <td class="label">CED. CIUDADANÍA:</td>
        <td>{{ $servidor->cedula ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">NOMBRES:</td>
        <td>{{ $nombresServidor ?: '—' }}</td>
        <td class="label">COMP. / VOTACIÓN:</td>
        <td>{{ $servidor->numero_papeleta_votacion ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">RIGE A PARTIR DE:</td>
        <td colspan="3">{{ $fmtFecha($movimiento->fecha_efectiva) ?? '—' }}</td>
    </tr>
</table>

<div class="explicacion-titulo">EXPLICACIÓN</div>
<div class="explicacion-texto">
    {{ $movimiento->descripcion }}
    @if($movimiento->resolucion_numero)
        <br><br>Documento de respaldo: {{ $movimiento->resolucion_numero }}
    @endif
</div>

<table class="situacion">
    <tr>
        <th style="border-bottom: 1px solid #333;">SITUACIÓN ACTUAL</th>
        <th style="border-bottom: 1px solid #333;">SITUACIÓN PROPUESTA</th>
    </tr>
    <tr>
        <td>
            <div class="campo"><span class="label">DIRECCIÓN: </span><span class="valor">{{ $movimiento->unidadOrigen->nombre ?? '—' }}</span></div>
            <div class="campo"><span class="label">GRUPO OCUPACIONAL: </span><span class="valor">{{ $movimiento->puestoOrigen->grupoOcupacional->denominacion_generica ?? '—' }}</span></div>
            <div class="campo"><span class="label">PUESTO: </span><span class="valor">{{ $movimiento->puestoOrigen->cargo->nombre ?? '—' }}</span></div>
            <div class="campo"><span class="label">GRADO: </span><span class="valor">{{ $movimiento->puestoOrigen->grupoOcupacional->grado_codigo ?? '—' }}</span></div>
            <div class="campo"><span class="label">LUGAR DE TRABAJO: </span><span class="valor">{{ $movimiento->lugar_trabajo ?? 'Esmeraldas' }}</span></div>
            <div class="campo"><span class="label">R.M.U.: </span><span class="valor">{{ $fmtRmu($movimiento->puestoOrigen->rmu ?? null) ?? '—' }}</span></div>
            <div class="campo"><span class="label">PARTIDA PRESUPUESTARIA: </span><span class="valor">{{ $movimiento->puestoOrigen->partidaPresupuestaria->codigo ?? '—' }}</span></div>
        </td>
        <td>
            <div class="campo"><span class="label">DIRECCIÓN: </span><span class="valor">{{ $movimiento->unidadDestino->nombre ?? '—' }}</span></div>
            <div class="campo"><span class="label">GRUPO OCUPACIONAL: </span><span class="valor">{{ $movimiento->puestoDestino->grupoOcupacional->denominacion_generica ?? '—' }}</span></div>
            <div class="campo"><span class="label">PUESTO: </span><span class="valor">{{ $movimiento->puestoDestino->cargo->nombre ?? '—' }}</span></div>
            <div class="campo"><span class="label">GRADO: </span><span class="valor">{{ $movimiento->puestoDestino->grupoOcupacional->grado_codigo ?? '—' }}</span></div>
            <div class="campo"><span class="label">LUGAR DE TRABAJO: </span><span class="valor">{{ $movimiento->puestoDestino ? ($movimiento->lugar_trabajo ?? 'Esmeraldas') : '—' }}</span></div>
            <div class="campo"><span class="label">R.M.U.: </span><span class="valor">{{ $fmtRmu($movimiento->puestoDestino->rmu ?? null) ?? '—' }}</span></div>
            <div class="campo"><span class="label">PARTIDA PRESUPUESTARIA: </span><span class="valor">{{ $movimiento->puestoDestino->partidaPresupuestaria->codigo ?? '—' }}</span></div>
        </td>
    </tr>
</table>

<div class="firmas">
    <table>
        <tr>
            <td>
                <div class="firma-titulo">AUTORIDAD NOMINADORA</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">
                    {{ $autoridadNominadora ? trim($autoridadNominadora->apellido.' '.$autoridadNominadora->nombre) : 'PREFECTO/A PROVINCIAL' }}
                </p>
                <p class="firma-cargo">{{ $autoridadNominadora->puesto->cargo->nombre ?? 'PREFECTO/A PROVINCIAL' }}</p>
            </td>
            <td>
                <div class="firma-titulo">RECURSOS HUMANOS</div>
                <div class="firma-linea"></div>
                <p class="firma-nombre">
                    {{ $responsableTalentoHumano ? trim($responsableTalentoHumano->apellido.' '.$responsableTalentoHumano->nombre) : 'DIRECTOR/A DE TALENTO HUMANO' }}
                </p>
                <p class="firma-cargo">{{ $responsableTalentoHumano->puesto->cargo->nombre ?? 'DIRECTOR/A DE TALENTO HUMANO' }}</p>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    GAD Provincial de Esmeraldas — Dirección: 10 de Agosto entre Bolívar y Pedro Vicente Maldonado. Teléfono: 06-2721433
</div>

<div class="page-break"></div>

<div class="header">
    @if(file_exists($logo))
        <img src="{{ $logo }}" alt="Logo GADPE">
    @endif
</div>

<table class="datos" style="margin-top: 20px;">
    <tr>
        <td class="label">CAUCIONADO:</td>
        <td>{{ $movimiento->caucionado === null ? '—' : ($movimiento->caucionado ? 'SI' : 'NO') }}</td>
        <td class="label">FECHA:</td>
        <td>{{ $fmtFecha($movimiento->caucion_fecha) ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">CAUCIÓN REGISTRADA CON No.:</td>
        <td colspan="3">{{ $movimiento->caucion_numero ?? '—' }}</td>
    </tr>
</table>

<div class="seccion-titulo" style="text-align: center;">POSESIÓN DEL CARGO</div>
<div class="explicacion-texto">
    YO <strong>{{ trim($nombreCompletoServidor.' '.$nombresServidor) }}</strong>
    CON CÉDULA DE CIUDADANÍA No. <strong>{{ $servidor->cedula ?? '—' }}</strong>
    JURO LEALTAD AL ESTADO ECUATORIANO.
</div>

<table class="datos" style="margin-top: 20px;">
    <tr>
        <td class="label">LUGAR:</td>
        <td colspan="3">&nbsp;</td>
    </tr>
    <tr>
        <td class="label">FECHA:</td>
        <td colspan="3">&nbsp;</td>
    </tr>
</table>

<div class="firmas">
    <table>
        <tr>
            <td>
                <div class="firma-linea" style="margin-top: 40px;"></div>
                <p class="firma-cargo">Funcionario</p>
            </td>
            <td>
                <div class="firma-linea" style="margin-top: 40px;"></div>
                <p class="firma-cargo">Responsable de Recursos Humanos</p>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    GAD Provincial de Esmeraldas — Dirección: 10 de Agosto entre Bolívar y Pedro Vicente Maldonado. Teléfono: 06-2721433
</div>

</body>
</html>
