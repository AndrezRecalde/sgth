<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family: 'DejaVu Sans', Arial, sans-serif;
  font-size: 9px;
  color: #1a1a2e;
  line-height: 1.45;
  padding: 28px 35px;
  background: #fff;
}
.hdr {
  display: table;
  width: 100%;
  margin-bottom: 0;
}
.hdr-logo {
  display: table-cell;
  width: 100px;
  vertical-align: middle;
  padding-right: 12px;
}
.hdr-logo img { width: 88px; height: auto; }
.hdr-body {
  display: table-cell;
  vertical-align: middle;
  background: #1a3a5c;
  padding: 10px 16px;
  border-radius: 4px 4px 0 0;
}
.hdr-dept {
  font-size: 11px;
  font-weight: bold;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.hdr-sub {
  font-size: 9px;
  color: #a8c4e0;
  margin-top: 2px;
}
.hdr-right {
  display: table-cell;
  width: 130px;
  vertical-align: middle;
  padding-left: 10px;
}
.code-box {
  border: 2px solid #1a3a5c;
  border-radius: 5px;
  padding: 7px 10px;
  background: #f0f5fb;
  text-align: center;
}
.code-lbl { font-size: 7px; color: #4a5568; text-transform: uppercase; letter-spacing: 0.4px; }
.code-val { font-size: 11px; font-weight: bold; color: #1a3a5c; margin: 2px 0; word-break: break-all; }
.code-date { font-size: 9px; font-weight: bold; color: #2d3748; border-top: 1px solid #cbd5e0; padding-top: 3px; margin-top: 3px; }
.hdr-bar {
  margin-left: 112px;
  background: #2d6a9f;
  border-radius: 0 0 4px 0;
  padding: 4px 14px;
  margin-bottom: 12px;
  display: table;
  width: calc(100% - 112px);
}
.hdr-bar-inner { display: table; width: 100%; }
.hdr-bar-l { display: table-cell; font-size: 8px; color: #e2edf7; vertical-align: middle; }
.hdr-bar-r { display: table-cell; font-size: 8px; font-weight: bold; color: #fff; text-align: right; vertical-align: middle; }

/* BENEFICIARIO */
.benef-box {
  border: 1px solid #cbd5e0;
  padding: 8px 12px;
  margin-bottom: 10px;
  border-radius: 3px;
  background: #f7fafc;
}
.benef-row { display: table; width: 100%; margin-bottom: 4px; }
.benef-lbl {
  display: table-cell;
  font-size: 8px;
  font-weight: bold;
  color: #4a5568;
  text-transform: uppercase;
  width: 18%;
  vertical-align: middle;
}
.benef-val {
  display: table-cell;
  font-size: 10px;
  font-weight: bold;
  color: #1a3a5c;
  vertical-align: middle;
}
.benef-tipo {
  display: table-cell;
  font-size: 8px;
  text-align: right;
  vertical-align: middle;
  color: #4a5568;
  width: 25%;
}
.tipo-badge {
  display: inline-block;
  background: #1a3a5c;
  color: white;
  font-size: 8px;
  font-weight: bold;
  padding: 2px 8px;
  border-radius: 3px;
  letter-spacing: 0.5px;
}

/* DETALLE */
.detalle-box {
  border: 1px solid #cbd5e0;
  border-top: none;
  padding: 8px 12px;
  font-size: 8.5px;
  line-height: 1.6;
  color: #2d3748;
  background: white;
  min-height: 45px;
}

/* SECCIONES */
.sec-hdr {
  background: #1a3a5c;
  color: white;
  font-size: 8px;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 4px 10px;
  margin-top: 10px;
  border-radius: 3px 3px 0 0;
  text-align: center;
}
.sec-hdr-alt {
  background: #2d6a9f;
  color: white;
  font-size: 8px;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 4px 10px;
  margin-top: 0;
  text-align: center;
}

/* TABLAS */
table.ft {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.ft td {
  font-size: 8.5px;
  padding: 4px 10px;
  border-bottom: 1px solid #e2e8f0;
  color: #2d3748;
}
table.ft td.lbl { color: #4a5568; width: 70%; }
table.ft td.val { text-align: right; font-weight: bold; color: #1a3a5c; width: 30%; }
table.ft tr:last-child td { border-bottom: none; }
table.ft tr.total-row td {
  background: #edf2f7;
  font-weight: bold;
  font-size: 9px;
  border-top: 1.5px solid #2d6a9f;
}
table.ft tr.total-row td.lbl { color: #1a3a5c; }
table.ft tr.total-row td.val { color: #1a3a5c; font-size: 10px; }

/* FORMULA */
.formula-box {
  border: 1px solid #cbd5e0;
  border-top: none;
  padding: 8px 12px;
  background: white;
}
.formula-row { display: table; width: 100%; padding: 3px 0; }
.f-lbl {
  display: table-cell;
  font-size: 8.5px;
  color: #4a5568;
  width: 70%;
}
.f-val {
  display: table-cell;
  font-size: 9px;
  font-weight: bold;
  text-align: right;
  color: #1a3a5c;
}
.formula-total {
  display: table;
  width: 100%;
  background: #1a3a5c;
  padding: 6px 12px;
  border-radius: 0 0 3px 3px;
  margin-top: 0;
}
.ft-lbl {
  display: table-cell;
  font-size: 9px;
  font-weight: bold;
  color: #a8c4e0;
  width: 70%;
}
.ft-val {
  display: table-cell;
  font-size: 11px;
  font-weight: bold;
  color: #fff;
  text-align: right;
}

/* FIRMAS */
.firmas {
  display: table;
  width: 100%;
  margin-top: 35px;
  page-break-inside: avoid;
}
.fc {
  display: table-cell;
  width: 50%;
  text-align: center;
  padding: 0 20px;
  vertical-align: bottom;
}
.fline {
  height: 38px;
  border-bottom: 1.5px solid #1a3a5c;
  margin: 0 10px 5px;
}
.fn { font-size: 8.5px; font-weight: bold; color: #1a3a5c; text-transform: uppercase; }
.fc-cargo { font-size: 7.5px; color: #4a5568; margin-top: 2px; }
.fc-rol { font-size: 7px; color: #718096; margin-top: 1px; text-transform: uppercase; }
.footer {
  margin-top: 14px;
  padding-top: 5px;
  border-top: 1px solid #e2e8f0;
  display: table;
  width: 100%;
}
.fl { display: table-cell; font-size: 7px; color: #b0bec5; vertical-align: middle; }
.fr { display: table-cell; font-size: 7px; color: #b0bec5; text-align: right; vertical-align: middle; }
.nota { font-size: 7.5px; color: #718096; font-style: italic; margin-top: 8px; }
</style>
</head>
<body>

{{-- ══ HEADER ══ --}}
<div class="hdr">
  <div class="hdr-logo">
    @if(file_exists($logo))
      <img src="{{ $logo }}" alt="GADPE">
    @else
      <div style="font-size:10px;font-weight:bold;color:#1a3a5c;">GADPE</div>
    @endif
  </div>
  <div class="hdr-body">
    <div class="hdr-dept">Dirección Financiera</div>
    <div class="hdr-sub">Departamento de Contabilidad — GAD Provincial de Esmeraldas</div>
  </div>
  <div class="hdr-right">
    <div class="code-box">
      <div class="code-lbl">Código de Licencia</div>
      <div class="code-val">{{ $viatico->codigo_viatico }}</div>
      <div class="code-lbl" style="margin-top:3px;">Fecha</div>
      <div class="code-date">
        {{ $viatico->liquidacion?->fecha_contabilizacion
            ? \Carbon\Carbon::parse($viatico->liquidacion->fecha_contabilizacion)->format('d/m/Y')
            : date('d/m/Y') }}
      </div>
    </div>
  </div>
</div>
<div class="hdr-bar">
  <div class="hdr-bar-inner">
    <div class="hdr-bar-l">
      Esmeraldas,
      {{ $viatico->liquidacion?->fecha_contabilizacion
          ? \Carbon\Carbon::parse($viatico->liquidacion->fecha_contabilizacion)
              ->locale('es')->isoFormat('MMMM D, YYYY')
          : now()->locale('es')->isoFormat('MMMM D, YYYY') }}
    </div>
    <div class="hdr-bar-r">
      TOTAL: $ {{ number_format($viatico->monto_anticipo ?? 0, 2) }}
    </div>
  </div>
</div>

{{-- ══ BENEFICIARIO ══ --}}
<div class="benef-box">
  <div class="benef-row">
    <div class="benef-lbl">A Favor de:</div>
    <div class="benef-val">
      {{ collect([
          $viatico->servidor?->apellido,
          $viatico->servidor?->segundo_apellido,
          $viatico->servidor?->nombre,
          $viatico->servidor?->segundo_nombre,
        ])->filter()->join(' ') ?: '—' }}
    </div>
    <div class="benef-tipo">
      TIPO: <span class="tipo-badge">{{ $modalidadLabel }}</span>
    </div>
  </div>
  <div class="benef-row">
    <div class="benef-lbl">Valor:</div>
    <div class="benef-val">{{ $totalLetras }}</div>
    <div class="benef-tipo"></div>
  </div>
</div>

{{-- ══ DETALLE ══ --}}
<div class="sec-hdr">Detalle</div>
<div class="detalle-box">
  @php
    $salida  = $viatico->datetime_salida
        ? \Carbon\Carbon::parse($viatico->datetime_salida)
        : null;
    $llegada = $viatico->datetime_llegada
        ? \Carbon\Carbon::parse($viatico->datetime_llegada)
        : null;

    $destino = '';
    $ultimo  = $viatico->tramos->sortBy('orden')->last();
    if ($viatico->zona === 'exterior') {
        $destino = strtoupper($viatico->pais_destino ?? '');
    } else {
        $destino = strtoupper(collect([
            $ultimo?->destinoProvincia?->nombre,
            $ultimo?->destinoCanton?->nombre,
        ])->filter()->join(' — '));
    }

    $servidor = collect([
        $viatico->servidor?->apellido,
        $viatico->servidor?->segundo_apellido,
        $viatico->servidor?->nombre,
        $viatico->servidor?->segundo_nombre,
    ])->filter()->join(' ');
  @endphp

  Por
  <strong>{{ number_format($viatico->total_dias ?? 0, 0) }}</strong>
  día(s) de viáticos a favor del señor(ra)
  <strong>{{ $servidor }}</strong>,
  quién se trasladó a la ciudad de
  <strong>{{ $destino ?: '—' }}</strong>
  del
  {{ $salida ? $salida->format('d') : '—' }}
  al
  {{ $llegada ? $llegada->format('d \d\e F \d\e\l Y') : '—' }},
  para <strong>{{ strtoupper($viatico->justificacion ?? '—') }}</strong>
</div>

{{-- ══ VIÁTICOS ══ --}}
<div class="sec-hdr">Viáticos</div>
<table class="ft">
  <tr>
    <td class="lbl">
      {{ number_format($viatico->total_dias ?? 0, 0) }} día(s) de viático(s)
    </td>
    <td class="val">
      $ {{ number_format($viatico->monto_calculado ?? 0, 2) }}
    </td>
  </tr>
  <tr class="total-row">
    <td class="lbl">TOTAL:</td>
    <td class="val">
      $ {{ number_format($viatico->monto_calculado ?? 0, 2) }}
    </td>
  </tr>
</table>

{{-- ══ VALORES A JUSTIFICAR ══ --}}
<div class="sec-hdr">Valores a Justificar</div>
@php
  $montoAsignado = (float) ($viatico->monto_calculado ?? 0);
  $anticipo      = (float) ($viatico->monto_anticipo ?? 0);
  $devengado     = round($montoAsignado * 0.30, 2);
  $aJustificar   = round($montoAsignado * 0.70, 2);

  // Separar facturas por grupo
  $hospedaje         = 0;
  $alimentacion      = 0;
  $transporteTerrestre = 0;
  $pasajeAereo       = 0;
  $combustible       = 0;
  $peaje             = 0;
  $inscripcion       = 0;
  $otro              = 0;
  $totalMovilizacion = 0;
  $totalHospAli      = 0;

  foreach ($facturasPorCategoria as $catId => $cat) {
      if (in_array($catId, [1, 2])) {
          if ($catId === 1) $hospedaje    = $cat['total'];
          if ($catId === 2) $alimentacion = $cat['total'];
          $totalHospAli += $cat['total'];
      } else {
          if ($catId === 3)  $transporteTerrestre = $cat['total'];
          if ($catId === 4)  $pasajeAereo         = $cat['total'];
          if ($catId === 5)  $combustible         = $cat['total'];
          if ($catId === 6)  $peaje               = $cat['total'];
          if ($catId === 10) $inscripcion         = $cat['total'];
          if ($catId === 13) $otro                = $cat['total'];
          $totalMovilizacion += $cat['total'];
      }
  }

  $modalidad = $viatico->modalidad_anticipo instanceof \BackedEnum
      ? $viatico->modalidad_anticipo->value
      : (string) $viatico->modalidad_anticipo;

  $diferenciaDevolver = ($modalidad === 'sin_anticipo')
      ? 0
      : (($totalHospAli >= $anticipo)
          ? 0
          : round($anticipo - $totalHospAli, 2));

  $valorMostrar = $diferenciaDevolver;
@endphp
<table class="ft">
  <tr>
    <td class="lbl">Valor devengado (30%)</td>
    <td class="val">$ {{ number_format($devengado, 2) }} (a)</td>
  </tr>
  <tr>
    <td class="lbl">Valor a justificar (70%)</td>
    <td class="val">$ {{ number_format($aJustificar, 2) }}</td>
  </tr>
</table>

{{-- ══ VALORES JUSTIFICADOS ══ --}}
<div class="sec-hdr">
  Valores Justificados — Viático Diario (H&A)
</div>
<table class="ft">
  <tr>
    <td class="lbl">Hospedaje</td>
    <td class="val">$ {{ number_format($hospedaje, 2) }}</td>
  </tr>
  <tr>
    <td class="lbl">Alimentación</td>
    <td class="val">$ {{ number_format($alimentacion, 2) }}</td>
  </tr>
  <tr class="total-row">
    <td class="lbl">
      TOTAL H&A ({{ $aJustificar > 0
        ? round(($totalHospAli/$aJustificar)*100, 1)
        : 0 }}% del 70%):
    </td>
    <td class="val">
      $ {{ number_format($totalHospAli, 2) }} (c)
    </td>
  </tr>
</table>

<div class="sec-hdr-alt">
  Movilización (rubro independiente)
</div>
<table class="ft">
  <tr>
    <td class="lbl">Pasajes terrestres / fluviales</td>
    <td class="val">$ {{ number_format($transporteTerrestre, 2) }}</td>
  </tr>
  <tr>
    <td class="lbl">Pasajes aéreos</td>
    <td class="val">$ {{ number_format($pasajeAereo, 2) }}</td>
  </tr>
  <tr>
    <td class="lbl">Combustible</td>
    <td class="val">$ {{ number_format($combustible, 2) }}</td>
  </tr>
  <tr>
    <td class="lbl">Peajes</td>
    <td class="val">$ {{ number_format($peaje, 2) }}</td>
  </tr>
  <tr class="total-row">
    <td class="lbl">TOTAL MOVILIZACIÓN:</td>
    <td class="val">
      $ {{ number_format($totalMovilizacion, 2) }} (b)
    </td>
  </tr>
</table>

{{-- ══ ANTICIPOS ══ --}}
<div class="sec-hdr">Anticipos</div>
<table class="ft">
  <tr>
    <td class="lbl">
      @if($viatico->modalidad_anticipo instanceof \BackedEnum)
        @php $mod = $viatico->modalidad_anticipo->value; @endphp
      @else
        @php $mod = (string) $viatico->modalidad_anticipo; @endphp
      @endif
      {{ $mod === 'sin_anticipo' ? 'Sin anticipo' : 'Anticipo entregado' }}
    </td>
    <td class="val">
      $ {{ number_format($viatico->monto_anticipo ?? 0, 2) }} (d)
    </td>
  </tr>
</table>

{{-- ══ LIQUIDACIÓN ══ --}}
<div class="sec-hdr">Liquidación</div>
<div class="formula-box">
  <div class="formula-row">
    <div class="f-lbl">(a) Devengado 30%:</div>
    <div class="f-val">$ {{ number_format($devengado, 2) }}</div>
  </div>
  <div class="formula-row">
    <div class="f-lbl">(b) Total movilización:</div>
    <div class="f-val">
      $ {{ number_format($totalMovilizacion, 2) }}
    </div>
  </div>
  <div class="formula-row">
    <div class="f-lbl">(c) Total H&A justificado:</div>
    <div class="f-val">
      $ {{ number_format($totalHospAli, 2) }}
    </div>
  </div>
  <div class="formula-row">
    <div class="f-lbl">(d) Anticipo entregado:</div>
    <div class="f-val">$ {{ number_format($anticipo, 2) }}</div>
  </div>
  <div class="formula-row" style="border-top:1px solid #cbd5e0;padding-top:4px;margin-top:4px;">
    <div class="f-lbl">
      <strong>A devolver: (d) - (c):</strong>
    </div>
    <div class="f-val">
      <strong>$ {{ number_format($diferenciaDevolver, 2) }}</strong>
    </div>
  </div>
</div>
<div class="formula-total">
  <div class="ft-lbl">VALOR TOTAL DEL VIÁTICO:</div>
  <div class="ft-val">
    $ {{ number_format($montoAsignado, 2) }}
  </div>
</div>

{{-- ══ FIRMAS ══ --}}
<div class="firmas">
  <div class="fc">
    <div class="fline"></div>
    @php
      $elaborado = $viatico->liquidacion?->createdBy;
    @endphp
    @if($elaborado)
      <div class="fn">{{ $elaborado->name }}</div>
    @else
      <div class="fn" style="color:#a0aec0">___________________________</div>
    @endif
    <div class="fc-cargo">
      {{ $viatico->liquidacion?->cargo_jefe_financiero ?? 'Auxiliar de Contabilidad' }}
    </div>
    <div class="fc-rol">Elaborado por</div>
  </div>
  <div class="fc">
    <div class="fline"></div>
    @if($directorFinanciero)
      <div class="fn">
        {{ collect([
            $directorFinanciero->apellido,
            $directorFinanciero->nombre,
          ])->filter()->join(' ') }}
      </div>
      <div class="fc-cargo">
        {{ $directorFinanciero->puesto?->cargo?->nombre ?? 'Director/a Financiero/a' }}
      </div>
    @else
      <div class="fn" style="color:#a0aec0">___________________________</div>
      <div class="fc-cargo" style="color:#a0aec0">Director/a Financiero/a</div>
    @endif
    <div class="fc-rol">Aprobado por</div>
  </div>
</div>

{{-- ══ NOTAS ══ --}}
<div class="nota">
  * Anexo: Solicitud e informe de licencia con remuneración, facturas de respaldo adjuntas.<br>
  ** El valor de combustible lo justifica el chofer del vehículo institucional.<br>
  *** Valores en USD. I.C.I: Índice de Cambio Internacional.
</div>

{{-- ══ PIE ══ --}}
<div class="footer">
  <div class="fl">SGTH — GAD Provincial de Esmeraldas — Dirección Financiera</div>
  <div class="fr">
    Generado el {{ date('d/m/Y H:i') }} •
    Documento oficial
  </div>
</div>

</body>
</html>
