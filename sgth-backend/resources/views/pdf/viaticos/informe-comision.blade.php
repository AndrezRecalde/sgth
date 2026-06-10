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
  padding: 30px 38px;
  background: #fff;
}
.hdr {
  display: table;
  width: 100%;
  margin-bottom: 0;
}
.hdr-logo {
  display: table-cell;
  width: 105px;
  vertical-align: middle;
  padding-right: 14px;
}
.hdr-logo img { width: 95px; height: auto; }
.hdr-body {
  display: table-cell;
  vertical-align: middle;
  background: #1a3a5c;
  padding: 12px 16px;
  border-radius: 4px 4px 0 0;
}
.hdr-inst {
  font-size: 8px;
  color: #a8c4e0;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 3px;
}
.hdr-title {
  font-size: 15px;
  font-weight: bold;
  color: #ffffff;
  letter-spacing: 0.3px;
}
.hdr-sub { font-size: 8px; color: #c5daf0; margin-top: 3px; }
.hdr-right {
  display: table-cell;
  width: 130px;
  vertical-align: middle;
  padding-left: 10px;
}
.code-box {
  border: 2px solid #1a3a5c;
  border-radius: 5px;
  padding: 8px 10px;
  background: #f0f5fb;
  text-align: center;
}
.code-lbl { font-size: 7px; color: #4a5568; text-transform: uppercase; letter-spacing: 0.5px; }
.code-val { font-size: 11px; font-weight: bold; color: #1a3a5c; margin: 2px 0; word-break: break-all; }
.code-date { font-size: 9px; font-weight: bold; color: #2d3748; border-top: 1px solid #cbd5e0; padding-top: 4px; margin-top: 4px; }
.code-date-lbl { font-size: 7px; color: #718096; text-transform: uppercase; }
.hdr-bar {
  margin-left: 119px;
  background: #2d6a9f;
  border-radius: 0 0 4px 0;
  padding: 5px 16px;
  margin-bottom: 12px;
}
.hdr-bar-txt {
  font-size: 8px;
  color: #e2edf7;
  font-weight: bold;
}
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
}
.sec-hdr-alt {
  background: #2d6a9f;
  color: white;
  font-size: 8px;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 4px 10px;
  margin-top: 10px;
  border-radius: 3px 3px 0 0;
}
table.dt {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.dt th {
  background: #edf2f7;
  font-size: 8px;
  font-weight: bold;
  padding: 4px 8px;
  border: 1px solid #cbd5e0;
  color: #2d3748;
  text-align: left;
  white-space: nowrap;
  width: 22%;
}
table.dt td {
  font-size: 8.5px;
  padding: 4px 8px;
  border: 1px solid #cbd5e0;
  color: #1a1a2e;
  background: white;
}
table.dt tr:nth-child(even) td { background: #f7fafc; }
table.gt {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.gt th {
  background: #2d6a9f;
  color: white;
  font-size: 7.5px;
  padding: 4px 6px;
  border: 1px solid #2d6a9f;
  text-align: center;
  font-weight: bold;
}
table.gt td {
  font-size: 8px;
  padding: 4px 6px;
  border: 1px solid #cbd5e0;
  color: #1a1a2e;
  text-align: center;
}
table.gt .tl { text-align: left; }
table.gt tr:nth-child(even) td { background: #f0f5fb; }
.tbox {
  border: 1px solid #cbd5e0;
  border-top: none;
  padding: 9px 11px;
  font-size: 8.5px;
  line-height: 1.6;
  color: #2d3748;
  background: white;
  min-height: 45px;
}
.resumen-wrap {
  border: 1px solid #cbd5e0;
  border-top: none;
  background: white;
}
.res-row {
  display: table;
  width: 100%;
  border-bottom: 1px solid #e2e8f0;
}
.res-row:last-child { border-bottom: none; }
.res-lbl {
  display: table-cell;
  padding: 5px 10px;
  font-size: 8.5px;
  color: #4a5568;
  width: 65%;
}
.res-val {
  display: table-cell;
  padding: 5px 10px;
  font-size: 9.5px;
  font-weight: bold;
  text-align: right;
  color: #2d3748;
}
.res-total-lbl {
  display: table-cell;
  padding: 7px 10px;
  font-size: 9.5px;
  font-weight: bold;
  color: #1a3a5c;
  width: 65%;
  background: #edf2f7;
}
.res-total-val {
  display: table-cell;
  padding: 7px 10px;
  font-size: 12px;
  font-weight: bold;
  text-align: right;
  color: #1a3a5c;
  background: #edf2f7;
}
.clausula {
  margin-top: 10px;
  border-left: 3px solid #c0392b;
  padding: 6px 10px;
  font-size: 8px;
  color: #742a2a;
  background: #fff5f5;
  border-radius: 0 3px 3px 0;
  font-style: italic;
}
.firmas {
  display: table;
  width: 100%;
  margin-top: 38px;
  page-break-inside: avoid;
}
.fc {
  display: table-cell;
  width: 33.33%;
  text-align: center;
  padding: 0 10px;
  vertical-align: bottom;
}
.fline {
  height: 38px;
  border-bottom: 1.5px solid #1a3a5c;
  margin: 0 8px 5px;
}
.fn { font-size: 8.5px; font-weight: bold; color: #1a3a5c; text-transform: uppercase; }
.fc-cargo { font-size: 7.5px; color: #4a5568; margin-top: 2px; }
.fc-rol { font-size: 7px; color: #718096; margin-top: 1px; text-transform: uppercase; letter-spacing: 0.3px; }
.footer {
  margin-top: 16px;
  padding-top: 5px;
  border-top: 1px solid #e2e8f0;
  display: table;
  width: 100%;
}
.fl { display: table-cell; font-size: 7px; color: #b0bec5; vertical-align: middle; }
.fr { display: table-cell; font-size: 7px; color: #b0bec5; text-align: right; vertical-align: middle; }
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
    <div class="hdr-inst">Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas</div>
    <div class="hdr-title">Informe de Licencia con Remuneración</div>
    <div class="hdr-sub">Comisión de Servicios — Liquidación de Viático</div>
  </div>
  <div class="hdr-right">
    <div class="code-box">
      <div class="code-lbl">N° Solicitud</div>
      <div class="code-val">{{ $viatico->codigo_viatico }}</div>
      <div class="code-date-lbl">Fecha del Informe</div>
      <div class="code-date">{{ date('d/m/Y') }}</div>
    </div>
  </div>
</div>
<div class="hdr-bar">
  <div class="hdr-bar-txt">
    Período de Comisión:
    {{ $viatico->datetime_salida
        ? \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y H:i')
        : '—' }}
    →
    {{ $viatico->datetime_llegada
        ? \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y H:i')
        : '—' }}
    &nbsp;|&nbsp;
    {{ number_format($viatico->total_dias ?? 0, 0) }} día(s) &nbsp;|&nbsp;
    Anticipo: $ {{ number_format($viatico->monto_anticipo ?? 0, 2) }}
  </div>
</div>

{{-- ══ DATOS GENERALES ══ --}}
<div class="sec-hdr">Datos Generales del Servidor</div>
<table class="dt">
  <tr>
    <th>Apellidos y Nombres</th>
    <td colspan="3">
      <strong>
      {{ collect([
          $viatico->servidor?->apellido,
          $viatico->servidor?->segundo_apellido,
          $viatico->servidor?->nombre,
          $viatico->servidor?->segundo_nombre,
        ])->filter()->join(' ') ?: '—' }}
      </strong>
    </td>
  </tr>
  <tr>
    <th>Cargo / Puesto</th>
    <td>{{ $viatico->servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
    <th>Unidad Administrativa</th>
    <td>{{ $viatico->servidor?->puesto?->unidadAdministrativa?->nombre ?? '—' }}</td>
  </tr>
  <tr>
    <th>Fecha / Hora de Salida</th>
    <td>
      {{ $viatico->datetime_salida
          ? \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y H:i')
          : '—' }}
    </td>
    <th>Fecha / Hora de Regreso</th>
    <td>
      {{ $viatico->datetime_llegada
          ? \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y H:i')
          : '—' }}
    </td>
  </tr>
</table>

{{-- ══ SERVIDORES ══ --}}
<div class="sec-hdr-alt">Servidores que Integran la Comisión</div>
<table class="gt">
  <thead>
    <tr>
      <th style="width:8%">Cód.</th>
      <th class="tl">Apellidos y Nombres</th>
      <th class="tl">Cargo</th>
    </tr>
  </thead>
  <tbody>
    @forelse($viatico->todosServidores as $vs)
    <tr>
      <td>{{ $vs->servidor_id }}</td>
      <td class="tl">
        {{ collect([
            $vs->servidor?->apellido,
            $vs->servidor?->nombre,
          ])->filter()->join(' ') ?: '—' }}
      </td>
      <td class="tl">{{ $vs->servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="3" style="color:#718096">—</td></tr>
    @endforelse
  </tbody>
</table>

{{-- ══ ACTIVIDADES ══ --}}
<div class="sec-hdr">Informe de Actividades o Productos Alcanzados</div>
@if($viatico->liquidacion?->actividades?->count() > 0)
<table class="gt">
  <thead>
    <tr>
      <th style="width:12%">Fecha</th>
      <th style="width:9%">H. Inicio</th>
      <th style="width:9%">H. Fin</th>
      <th style="width:22%" class="tl">Lugar</th>
      <th class="tl">Descripción de la Actividad</th>
    </tr>
  </thead>
  <tbody>
    @foreach($viatico->liquidacion->actividades->sortBy('orden') as $act)
    <tr>
      <td>
        {{ $act->fecha
            ? \Carbon\Carbon::parse($act->fecha)->format('d/m/Y')
            : '—' }}
      </td>
      <td>{{ $act->hora_inicio ?? '—' }}</td>
      <td>{{ $act->hora_fin ?? '—' }}</td>
      <td class="tl">{{ $act->lugar ?? '—' }}</td>
      <td class="tl">{{ $act->descripcion ?? '—' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else
<div class="tbox">
  {{ $viatico->justificacion ?? 'Sin descripción de actividades.' }}
</div>
@endif

{{-- ══ TRANSPORTE ══ --}}
<div class="sec-hdr-alt">Itinerario de Transporte</div>
<table class="gt">
  <thead>
    <tr>
      <th>Tipo</th>
      <th class="tl">Empresa</th>
      <th class="tl">Ruta</th>
      <th>F. Salida</th>
      <th>Hora</th>
      <th>F. Llegada</th>
      <th>Hora</th>
    </tr>
  </thead>
  <tbody>
    @forelse($viatico->tramos->sortBy('orden') as $tramo)
    @php
      $orig = $tramo->origen_tipo === 'nacional'
        ? collect([
            $tramo->origenProvincia?->nombre,
            $tramo->origenCanton?->nombre ?: $tramo->origen_ciudad,
          ])->filter()->unique()->join(' / ')
        : collect([$tramo->origen_pais, $tramo->origen_ciudad])->filter()->join(' / ');
      $dest = $tramo->destino_tipo === 'nacional'
        ? collect([
            $tramo->destinoProvincia?->nombre,
            $tramo->destinoCanton?->nombre ?: $tramo->destino_ciudad,
          ])->filter()->unique()->join(' / ')
        : collect([$tramo->destino_pais, $tramo->destino_ciudad])->filter()->join(' / ');
    @endphp
    <tr>
      <td>{{ strtoupper($tramo->empresa?->catalogo?->tipo_vehiculo ?? 'TERRESTRE') }}</td>
      <td class="tl">{{ $tramo->empresa?->nombre ?? '—' }}</td>
      <td class="tl"><strong>{{ $orig }}</strong> → {{ $dest }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('H:i') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('H:i') : '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="7" style="color:#718096">Sin tramos registrados.</td></tr>
    @endforelse
  </tbody>
</table>

{{-- ══ GASTOS ══ --}}
<div class="sec-hdr">Gastos — Comprobantes de Respaldo</div>
@if($viatico->liquidacion?->detallesFactura?->count() > 0)
<table class="gt">
  <thead>
    <tr>
      <th class="tl">RUC</th>
      <th class="tl">Razón Social / Proveedor</th>
      <th>N° Comprobante</th>
      <th>Tipo</th>
      <th class="tl">Categoría</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($viatico->liquidacion->detallesFactura as $f)
    <tr>
      <td class="tl">{{ $f->ruc_proveedor ?? '—' }}</td>
      <td class="tl">{{ $f->nombre_proveedor ?? '—' }}</td>
      <td>{{ $f->numero_factura ?? $f->numero_ticket ?? '—' }}</td>
      <td>{{ strtoupper($f->tipo_comprobante ?? 'FACTURA') }}</td>
      <td class="tl">{{ $f->categoria?->nombre ?? '—' }}</td>
      <td><strong>$ {{ number_format($f->monto ?? 0, 2) }}</strong></td>
    </tr>
    @endforeach
  </tbody>
</table>

{{-- ══ RESUMEN FINANCIERO ══ --}}
<div class="sec-hdr-alt">Resumen Financiero</div>
  @php
    $montoAsignado  = (float) ($viatico->monto_calculado ?? 0);
    $anticipo       = (float) ($viatico->monto_anticipo ?? 0);
    $monto70        = round($montoAsignado * 0.70, 2);
    $monto30        = round($montoAsignado * 0.30, 2);

    // Separar facturas por grupo
    $totalHospAli = 0;
    $totalMovilizacion = 0;
    foreach ($viatico->liquidacion?->detallesFactura ?? [] as $f) {
        if ($f->categoria?->grupo === 'viatico') {
            $totalHospAli += (float) $f->monto;
        } else {
            $totalMovilizacion += (float) $f->monto;
        }
    }
    $totalFacturas = $totalHospAli + $totalMovilizacion;

    $porcentajeHA = $monto70 > 0
        ? min(round(($totalHospAli / $monto70) * 100, 1), 100)
        : 0;
    $justificadoCompleto = $totalHospAli >= $monto70;

    $modalidad = $viatico->modalidad_anticipo instanceof \BackedEnum
        ? $viatico->modalidad_anticipo->value
        : (string) $viatico->modalidad_anticipo;

    if ($modalidad === 'sin_anticipo') {
        $diferenciaDevolver = 0;
    } else {
        $diferenciaDevolver = ($totalHospAli >= $anticipo ||
                               $totalFacturas >= $montoAsignado)
            ? 0
            : round($anticipo - $totalHospAli, 2);
    }
  @endphp

  {{-- Viático diario H&A --}}
  <div class="resumen-wrap">
    <div class="res-row">
      <div class="res-lbl">
        Monto total asignado:
      </div>
      <div class="res-val">
        $ {{ number_format($montoAsignado, 2) }}
      </div>
    </div>
    <div class="res-row">
      <div class="res-lbl">
        70% a justificar (Hospedaje y Alimentación):
      </div>
      <div class="res-val">
        $ {{ number_format($monto70, 2) }}
      </div>
    </div>
    @if($anticipo > 0)
    <div class="res-row">
      <div class="res-lbl">
        Anticipo entregado:
      </div>
      <div class="res-val">
        $ {{ number_format($anticipo, 2) }}
      </div>
    </div>
    @endif
    <div class="res-row">
      <div class="res-lbl">
        Total H&A presentado ({{ $porcentajeHA }}%):
      </div>
      <div class="res-val"
        style="color:{{ $justificadoCompleto ? '#2d6a4f' : '#e67e22' }}">
        $ {{ number_format($totalHospAli, 2) }}
      </div>
    </div>
    <div class="res-row">
      <div class="res-lbl">
        30% devengado (sin comprobante):
      </div>
      <div class="res-val">
        $ {{ number_format($monto30, 2) }}
      </div>
    </div>
    @if($diferenciaDevolver > 0)
    <div class="res-row" style="background:#fff0f0;">
      <div class="res-total-lbl" style="color:#c0392b;">
        ★ A devolver a la institución:
      </div>
      <div class="res-total-val" style="color:#c0392b;">
        $ {{ number_format($diferenciaDevolver, 2) }}
      </div>
    </div>
    @else
    <div class="res-row">
      <div class="res-total-lbl">
        ★ A devolver a la institución:
      </div>
      <div class="res-total-val">
        $ 0.00
      </div>
    </div>
    @endif
  </div>

  {{-- Movilización --}}
  @if($totalMovilizacion > 0)
  <div style="margin-top:8px;">
    <div class="sec-hdr-alt" style="margin-top: 0;">
      Movilización (rubro independiente)
    </div>
    <div class="resumen-wrap">
      @foreach($viatico->liquidacion->detallesFactura as $f)
        @if($f->categoria?->grupo !== 'viatico')
        <div class="res-row">
          <div class="res-lbl">
            {{ $f->categoria?->nombre ?? '—' }}
            ({{ $f->nombre_proveedor ?? '—' }}):
          </div>
          <div class="res-val">
            $ {{ number_format($f->monto ?? 0, 2) }}
          </div>
        </div>
        @endif
      @endforeach
      <div class="res-row">
        <div class="res-total-lbl">
          Total Movilización:
        </div>
        <div class="res-total-val">
          $ {{ number_format($totalMovilizacion, 2) }}
        </div>
      </div>
    </div>
  </div>
  @endif
@else
<div class="tbox" style="color:#718096">
  Sin comprobantes registrados en la liquidación.
</div>
@endif

{{-- ══ CLÁUSULA ══ --}}
<div class="clausula">
  ★ AUTORIZO QUE LOS VALORES NO JUSTIFICADOS SEAN DEBITADOS
  DE MI PRÓXIMA REMUNERACIÓN MENSUAL UNIFICADA.
</div>

{{-- ══ FIRMAS ══ --}}
<div class="firmas">
  <div class="fc">
    <div class="fline"></div>
    <div class="fn">
      {{ collect([$viatico->servidor?->apellido, $viatico->servidor?->nombre])->filter()->join(' ') ?: '—' }}
    </div>
    <div class="fc-cargo">{{ $viatico->servidor?->puesto?->cargo?->nombre ?? '' }}</div>
    <div class="fc-rol">Servidor Solicitante</div>
  </div>
  <div class="fc">
    <div class="fline"></div>
    @if($jefeUnidad)
      <div class="fn">{{ collect([$jefeUnidad->apellido, $jefeUnidad->nombre])->filter()->join(' ') }}</div>
      <div class="fc-cargo">{{ $jefeUnidad->puesto?->cargo?->nombre ?? 'Director/a' }}</div>
    @else
      <div class="fn" style="color:#a0aec0">___________________________</div>
      <div class="fc-cargo" style="color:#a0aec0">Director/a de Unidad</div>
    @endif
    <div class="fc-rol">Responsable de Unidad Solicitante</div>
  </div>
  <div class="fc">
    <div class="fline"></div>
    @if($prefecto)
      <div class="fn">{{ collect([$prefecto->apellido, $prefecto->nombre])->filter()->join(' ') }}</div>
      <div class="fc-cargo">{{ $prefecto->puesto?->cargo?->nombre ?? 'Prefecto/a Provincial' }}</div>
    @else
      <div class="fn" style="color:#a0aec0">___________________________</div>
      <div class="fc-cargo">Prefecto/a Provincial</div>
    @endif
    <div class="fc-rol">Máxima Autoridad o Delegado</div>
  </div>
</div>

{{-- ══ PIE ══ --}}
<div class="footer">
  <div class="fl">SGTH — GAD Provincial de Esmeraldas</div>
  <div class="fr">
    Generado el {{ date('d/m/Y H:i') }} •
    Documento oficial — No requiere sello húmedo
  </div>
</div>

</body>
</html>
