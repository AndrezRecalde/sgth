<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de acto de autoridad electa</title>
    <style>
        /* El mismo membrete y la misma tipografía que la acción de personal:
           dompdf no soporta flexbox ni grid, así que todo va con tablas. */
        @page { margin: 26mm 14mm 20mm 14mm; }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.45;
            color: #1c2321;
            margin: 0;
            padding: 0;
        }

        .membrete { position: fixed; top: -21mm; left: 0; right: 0; height: 17mm; }
        .membrete table { width: 100%; border-collapse: collapse; }
        .membrete td { vertical-align: middle; border: none; padding: 0; }
        .membrete img { max-height: 15mm; }
        .membrete .institucion {
            text-align: right; font-size: 9.5px; color: #5b6b63;
            text-transform: uppercase; letter-spacing: 0.3px;
        }
        .membrete .institucion strong {
            display: block; font-size: 11px; color: #17593f; letter-spacing: 0; margin-bottom: 1px;
        }

        .footer {
            position: fixed; bottom: -14mm; left: 0; right: 0; text-align: center;
            font-size: 11px; color: #5b6b63; border-top: 0.7px solid #cfdad4; padding-top: 4px;
        }

        .titulo { text-align: center; margin: 0 0 12px 0; }
        .titulo .supra { font-size: 9px; letter-spacing: 2px; color: #5b6b63; text-transform: uppercase; }
        .titulo h1 {
            font-size: 17px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase;
            margin: 2px 0 0 0; color: #17593f;
        }
        .titulo .acto { font-size: 11px; color: #3d4d46; margin-top: 1px; }
        .titulo .regla { border-bottom: 1.6px solid #17593f; width: 56px; margin: 7px auto 0 auto; }

        .cintillo { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .cintillo td { border: none; padding: 0; font-size: 9.5px; }
        .cintillo .codigo {
            border: 1px solid #17593f; border-radius: 2px; padding: 4px 9px; font-weight: bold;
            font-size: 11px; color: #17593f; letter-spacing: 0.5px;
        }
        .cintillo .lugar-fecha { text-align: right; color: #3d4d46; }

        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.datos td { border: 0.7px solid #c6d2cc; padding: 5px 8px; font-size: 10px; vertical-align: middle; }
        table.datos td.label {
            font-weight: bold; font-size: 8.5px; letter-spacing: 0.5px; text-transform: uppercase;
            color: #3d4d46; background: #f2f6f4; width: 26%;
        }
        table.datos td.mayus { text-transform: uppercase; }

        .bloque-titulo {
            font-size: 9px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; color: #17593f;
            border-bottom: 0.7px solid #cfdad4; padding-bottom: 3px; margin: 0 0 6px 0;
        }
        .explicacion { text-align: justify; font-size: 10.5px; margin-bottom: 14px; }
        .nota { font-size: 9.5px; color: #3d4d46; margin-bottom: 14px; }

        .firmas { width: 100%; margin-top: 40px; }
        .firmas td { text-align: center; vertical-align: bottom; border: none; padding: 0 12px; }
        .firma-rotulo {
            font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase;
            color: #6b7c74; margin-bottom: 46px;
        }
        .firma-linea { border-top: 0.9px solid #1c2321; width: 50%; margin: 0 auto 4px auto; }
        .firma-nombre { font-weight: bold; font-size: 10.5px; margin: 0; }
        .firma-cargo { font-size: 9.5px; color: #3d4d46; margin: 1px 0 0 0; }

        .anulada { border: 2.5px solid #a3161b; background: #fdf2f2; padding: 10px 14px; margin-bottom: 14px; }
        .anulada .sello {
            font-size: 17px; font-weight: bold; letter-spacing: 2px; color: #a3161b;
            text-transform: uppercase; margin: 0 0 4px 0;
        }
        .anulada .detalle { font-size: 10px; color: #6b2226; }

@include('pdf.expediente.partials.vista-previa-estilos')
    </style>
</head>
<body>
@if($vistaPrevia ?? false)
    @include('pdf.expediente.partials.vista-previa-marca')
@endif
@php
    // La vista previa de lo que todavía no se registra (fase 6.2).
    $vistaPrevia ??= false;
    $fmtFecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '—';
    $nombre = trim(($servidor?->apellido ?? '').' '.($servidor?->segundo_apellido ?? '').' '.($servidor?->nombre ?? '').' '.($servidor?->segundo_nombre ?? ''));
    $dignidad = $movimiento->puestoOrigen?->cargo?->nombre ?? $movimiento->puestoDestino?->cargo?->nombre;
    $causal = $movimiento->causal();
@endphp

@include('pdf._membrete-institucional', ['dependencia' => 'Dirección de Talento Humano'])

<div class="footer">{{ $vistaPrevia ? 'BORRADOR' : $movimiento->codigo_registro }}</div>

<div class="titulo">
    <div class="supra">Constancia</div>
    <h1>Acto de autoridad electa</h1>
    <div class="acto">{{ $movimiento->etiqueta() }}{{ $causal ? ' — '.$causal->etiqueta() : '' }}</div>
    <div class="regla"></div>
</div>

@if($vistaPrevia)
    @include('pdf.expediente.partials.vista-previa-sello')
@endif

@if($anulada)
    <div class="anulada">
        <p class="sello">Acto anulado</p>
        <div class="detalle">
            Este acto quedó sin efecto{{ $movimiento->motivo_anulacion ? ': '.$movimiento->motivo_anulacion : '.' }}
        </div>
    </div>
@endif

<table class="cintillo">
    <tr>
        <td><span class="codigo">{{ $vistaPrevia ? 'BORRADOR' : 'N.º '.$movimiento->codigo_registro }}</span></td>
        <td class="lugar-fecha">Esmeraldas, {{ $vistaPrevia && ! $movimiento->fecha_registro ? 'la fecha se fija al registrar' : $fmtFecha($movimiento->fecha_registro) }}</td>
    </tr>
</table>

<div class="bloque-titulo">Autoridad</div>
<table class="datos">
    <tr><td class="label">Nombres y apellidos</td><td class="mayus">{{ $nombre ?: '—' }}</td></tr>
    <tr><td class="label">Cédula</td><td>{{ $servidor?->cedula ?? '—' }}</td></tr>
    <tr><td class="label">Dignidad</td><td>{{ $dignidad ?? '—' }}</td></tr>
</table>

<div class="bloque-titulo">Acto</div>
<table class="datos">
    <tr><td class="label">Rige desde</td><td>{{ $fmtFecha($movimiento->fecha_efectiva) }}</td></tr>
    @if($movimiento->fecha_inicio)
        <tr><td class="label">Período</td><td>del {{ $fmtFecha($movimiento->fecha_inicio) }} al {{ $fmtFecha($movimiento->fecha_fin) }}</td></tr>
    @endif
    @if($movimiento->tipo_movimiento === \App\Enums\TipoMovimientoPersonal::INICIO_PERIODO)
        <tr><td class="label">Fin del período</td><td>{{ $fmtFecha($movimiento->fecha_fin_propuesta) }}</td></tr>
        @if($movimiento->subtipoEfectivo() === \App\Enums\SubtipoMovimientoPersonal::DESIGNACION_DEL_CONSEJO)
            {{-- Designado por el Consejo, de una terna del Prefecto (2026-10-20). --}}
            <tr><td class="label">Designación</td><td>Resolución del Consejo Provincial N.º {{ $movimiento->acto_origen_numero }}, del {{ $fmtFecha($movimiento->acto_origen_fecha) }} (COOTAD, Art. 47 literal n)</td></tr>
        @else
            <tr><td class="label">Credencial del CNE</td><td>N.º {{ $movimiento->credencial_cne_numero }}, del {{ $fmtFecha($movimiento->credencial_cne_fecha) }}</td></tr>
        @endif
        @if($movimiento->acta_posesion_numero)
            <tr><td class="label">Acta de posesión</td><td>N.º {{ $movimiento->acta_posesion_numero }}</td></tr>
        @endif
    @endif
    @if($movimiento->con_remuneracion !== null)
        <tr><td class="label">Remuneración</td><td>{{ $movimiento->con_remuneracion ? 'Con remuneración' : 'Sin remuneración' }}</td></tr>
    @endif
    @if($movimiento->prorroga_causa)
        <tr><td class="label">Prórroga</td><td>{{ $movimiento->prorroga_causa->etiqueta() }} (COOTAD Art. 47 s)</td></tr>
    @endif
    @if($movimiento->resolucion_numero)
        <tr><td class="label">Documento de origen</td><td>{{ $movimiento->resolucion_numero }}</td></tr>
    @endif
</table>

<div class="bloque-titulo">Explicación</div>
<div class="explicacion">{{ $movimiento->descripcion }}</div>

<div class="nota">
    A las autoridades de elección popular no se les emite acción de personal: este acto nace de su documento de
    origen. Talento Humano deja constancia de su registro en el expediente.
</div>

<table class="firmas">
    <tr>
        <td>
            <div class="firma-rotulo">{{ $firmaTalentoHumano['rotulo'] }}</div>
            <div class="firma-linea"></div>
            <p class="firma-nombre">{{ $firmaTalentoHumano['nombre'] ?? '' }}</p>
            <p class="firma-cargo">{{ $firmaTalentoHumano['cargo'] }}</p>
            @if(!empty($firmaTalentoHumano['provisional']))
                <p class="firma-provisional">{{ $firmaTalentoHumano['nombre'] ? 'Provisional: se sella al suscribir' : 'Sin firmante: así no se podría suscribir' }}</p>
            @endif
        </td>
    </tr>
</table>
</body>
</html>
