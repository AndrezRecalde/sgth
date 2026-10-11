<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de registro interno</title>
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
        table.datos td, table.datos th { border: 0.7px solid #c6d2cc; padding: 5px 8px; font-size: 10px; vertical-align: middle; }
        table.datos td.label {
            font-weight: bold; font-size: 8.5px; letter-spacing: 0.5px; text-transform: uppercase;
            color: #3d4d46; background: #f2f6f4; width: 26%;
        }
        table.datos th {
            font-size: 8.5px; letter-spacing: 0.5px; text-transform: uppercase; color: #3d4d46;
            background: #f2f6f4; text-align: left;
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
    </style>
</head>
<body>
@php
    // La de una terminación con algo que pagar (fase 4.5) y la del reintegro
    // por despido ineficaz (fase 4.5b); las demás no la traen.
    $liquidacion ??= null;
    $reintegro = $movimiento->esReintegroPorDespidoIneficaz();
    $terminacion = $reintegro ? $movimiento->movimientoRelacionado : null;
    $fmtFecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '—';
    $dinero = fn ($v) => $v !== null ? '$ '.number_format((float) $v, 2) : '—';
    $nombre = trim(($servidor?->apellido ?? '').' '.($servidor?->segundo_apellido ?? '').' '.($servidor?->nombre ?? '').' '.($servidor?->segundo_nombre ?? ''));
    $causal = $movimiento->causal();
    $baseLegal = $causal?->esDelCodigoDelTrabajo()
        ? $causal->baseLegal()
        : $movimiento->clase?->baseLegalEnElCodigoDelTrabajo();
    $esIngreso = (bool) $movimiento->tipo_movimiento?->creaVinculo();
    $cambia = $movimiento->cambiaElContratoDeTrabajo();

    // Lo que no cambia sigue igual: en el cambio, lo vacío es «sin cambio».
    $propuesta = [
        'unidad'       => $movimiento->unidadDestino?->nombre,
        'puesto'       => $movimiento->puestoDestino?->cargo?->nombre,
        'remuneracion' => $movimiento->remuneracion_propuesta,
        'lugar'        => filled($movimiento->lugar_trabajo) ? $movimiento->lugar_trabajo : null,
    ];
    // Una terminación no deja situación propuesta: cierra el vínculo.
    $sinCambio = $movimiento->cierraElVinculo() ? '—' : 'Sin cambio';
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
                Dirección de Talento Humano
            </td>
        </tr>
    </table>
</div>

<div class="footer">{{ $movimiento->codigo_registro }}</div>

<div class="titulo">
    <div class="supra">Código del Trabajo</div>
    <h1>Hoja de registro interno</h1>
    <div class="acto">{{ $movimiento->etiqueta() }}{{ $causal ? ' — '.$causal->etiqueta() : '' }}</div>
    <div class="regla"></div>
</div>

@if($anulada)
    <div class="anulada">
        <p class="sello">Registro anulado</p>
        <div class="detalle">
            Este acto quedó sin efecto{{ $movimiento->motivo_anulacion ? ': '.$movimiento->motivo_anulacion : '.' }}
        </div>
    </div>
@endif

<table class="cintillo">
    <tr>
        <td><span class="codigo">N.º {{ $movimiento->codigo_registro }}</span></td>
        <td class="lugar-fecha">Esmeraldas, {{ $fmtFecha($movimiento->fecha_registro) }}</td>
    </tr>
</table>

<div class="bloque-titulo">Trabajador</div>
<table class="datos">
    <tr><td class="label">Nombres y apellidos</td><td class="mayus">{{ $nombre ?: '—' }}</td></tr>
    <tr><td class="label">Cédula</td><td>{{ $servidor?->cedula ?? '—' }}</td></tr>
</table>

<div class="bloque-titulo">Situación actual y propuesta</div>
<table class="datos">
    <tr>
        <th style="width: 26%;"></th>
        <th>Actual</th>
        <th>Propuesta</th>
    </tr>
    <tr>
        <td class="label">Unidad</td>
        <td>{{ $esIngreso ? 'Sin vínculo previo' : ($movimiento->unidadOrigen?->nombre ?? '—') }}</td>
        <td>{{ $propuesta['unidad'] ?? ($cambia || ! $esIngreso ? $sinCambio : '—') }}</td>
    </tr>
    <tr>
        <td class="label">Puesto</td>
        <td>{{ $esIngreso ? '—' : ($movimiento->puestoOrigen?->cargo?->nombre ?? '—') }}</td>
        <td>{{ $propuesta['puesto'] ?? ($cambia || ! $esIngreso ? $sinCambio : '—') }}</td>
    </tr>
    <tr>
        <td class="label">Remuneración</td>
        <td>{{ $esIngreso ? '—' : $dinero($movimiento->remuneracion_origen) }}</td>
        <td>{{ $propuesta['remuneracion'] !== null ? $dinero($propuesta['remuneracion']) : ($cambia || ! $esIngreso ? $sinCambio : '—') }}</td>
    </tr>
    <tr>
        <td class="label">Lugar de trabajo</td>
        <td>{{ $esIngreso ? '—' : ($movimiento->lugar_trabajo_origen ?: '—') }}</td>
        <td>{{ $propuesta['lugar'] ?? ($cambia || ! $esIngreso ? $sinCambio : '—') }}</td>
    </tr>
</table>

<div class="bloque-titulo">Acto</div>
<table class="datos">
    <tr><td class="label">Rige desde</td><td>{{ $fmtFecha($movimiento->fecha_efectiva) }}</td></tr>
    @if($movimiento->fecha_inicio)
        <tr><td class="label">Período</td><td>del {{ $fmtFecha($movimiento->fecha_inicio) }} al {{ $fmtFecha($movimiento->fecha_fin) }}</td></tr>
    @endif
    @if($esIngreso && $movimiento->numero_contrato)
        <tr><td class="label">Contrato de trabajo</td><td>N.º {{ $movimiento->numero_contrato }}</td></tr>
    @endif
    @if($movimiento->resolucion_numero)
        <tr><td class="label">{{ $cambia ? 'Adenda' : 'Documento de origen' }}</td><td>N.º {{ $movimiento->resolucion_numero }}</td></tr>
    @endif
    @if($reintegro)
        <tr>
            <td class="label">Deja sin efecto</td>
            <td>{{ $terminacion?->causal()?->etiqueta() ?? $terminacion?->etiqueta() ?? '—' }}{{ $terminacion?->codigo_registro ? ' ('.$terminacion->codigo_registro.')' : '' }}, del {{ $fmtFecha($terminacion?->fecha_efectiva) }}</td>
        </tr>
        <tr>
            <td class="label">Orden judicial</td>
            <td>Causa N.º {{ $movimiento->causa_judicial_numero }}, {{ $movimiento->unidad_judicial }}: {{ $movimiento->ordenJudicial() }}</td>
        </tr>
    @endif
    @if($movimiento->fecha_presentacion_renuncia)
        <tr><td class="label">Renuncia presentada</td><td>El {{ $fmtFecha($movimiento->fecha_presentacion_renuncia) }}</td></tr>
    @endif
    @if($movimiento->fecha_consentimiento)
        <tr><td class="label">Consentimiento del trabajador</td><td>Por escrito, del {{ $fmtFecha($movimiento->fecha_consentimiento) }}</td></tr>
    @endif
    @if($descuento)
        <tr><td class="label">Descuento referencial</td><td>{{ $descuento['detalle'] }}: {{ $dinero($descuento['monto']) }}</td></tr>
    @endif
    @if($baseLegal)
        <tr><td class="label">Base legal</td><td>{{ $baseLegal }}</td></tr>
    @endif
</table>

@if($liquidacion)
    <div class="bloque-titulo">{{ $liquidacion['tipo'] === 'reintegro' ? 'Remuneraciones pendientes (referencial)' : 'Liquidación referencial' }}</div>
    <table class="datos">
        <tr>
            <th>Rubro</th>
            <th>Detalle</th>
            <th style="width: 18%; text-align: right;">Valor</th>
        </tr>
        @foreach($liquidacion['rubros'] as $rubro)
            <tr>
                <td>{{ $rubro['concepto'] }}<br><span style="font-size: 8.5px; color: #5b6b63;">{{ $rubro['base_legal'] }}</span></td>
                <td>{{ $rubro['detalle'] }}</td>
                <td style="text-align: right;">{{ $dinero($rubro['monto']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" class="label">Total referencial</td>
            <td style="text-align: right; font-weight: bold;">{{ $dinero($liquidacion['total']) }}</td>
        </tr>
    </table>
    <div class="nota">
        @if($liquidacion['tipo'] === 'reintegro')
            Con una remuneración de {{ $dinero($liquidacion['remuneracion']) }}
            @if($liquidacion['dias_fuera'])
                y {{ $liquidacion['dias_fuera'] }} días fuera, del {{ $fmtFecha($liquidacion['desde']) }} al {{ $fmtFecha($liquidacion['hasta']) }}.
            @else
                y sin días fuera.
            @endif
        @else
            Sobre una remuneración de {{ $dinero($liquidacion['remuneracion']) }} y servicio desde el
            {{ $fmtFecha($liquidacion['desde']) }}.
        @endif
        {{ $liquidacion['nota'] }}
    </div>
@endif

<div class="bloque-titulo">Explicación</div>
<div class="explicacion">{{ $movimiento->descripcion }}</div>

<div class="nota">
    A los obreros los rige el Código del Trabajo, no la Ley Orgánica de Servicio Público: no se les emite acción de
    personal. Su contrato y sus adendas los firman el empleador y el trabajador; esta hoja deja constancia del acto en
    el expediente.
</div>

<table class="firmas">
    <tr>
        <td>
            <div class="firma-rotulo">{{ $firmaTalentoHumano['rotulo'] }}</div>
            <div class="firma-linea"></div>
            <p class="firma-nombre">{{ $firmaTalentoHumano['nombre'] ?? '' }}</p>
            <p class="firma-cargo">{{ $firmaTalentoHumano['cargo'] }}</p>
        </td>
    </tr>
</table>
</body>
</html>
