<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'DejaVu Sans', Arial, sans-serif;
  font-size: 9.5px;
  color: #1a1a2e;
  line-height: 1.5;
  padding: 28px 35px;
  background: #ffffff;
}
.header-wrap {
  display: table;
  width: 100%;
  border-bottom: 3px solid #1a3a5c;
  padding-bottom: 14px;
  margin-bottom: 16px;
}
.header-logo-cell {
  display: table-cell;
  width: 100px;
  vertical-align: middle;
}
.header-logo-cell img { width: 90px; height: auto; }
.header-center-cell {
  display: table-cell;
  vertical-align: middle;
  text-align: center;
  padding: 0 12px;
}
.inst-name {
  font-size: 9px;
  font-weight: bold;
  color: #1a3a5c;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 4px;
}
.doc-title {
  font-size: 14px;
  font-weight: bold;
  color: #1a1a2e;
  text-transform: uppercase;
}
.doc-subtitle { font-size: 9px; color: #4a5568; margin-top: 3px; }
.header-right-cell {
  display: table-cell;
  width: 140px;
  vertical-align: middle;
  text-align: right;
}
.doc-code-box {
  border: 2px solid #1a3a5c;
  border-radius: 5px;
  padding: 8px 10px;
  background: #f0f4f8;
}
.doc-code-label { font-size: 7.5px; color: #4a5568; text-transform: uppercase; }
.doc-code-value { font-size: 12px; font-weight: bold; color: #1a3a5c; margin-top: 2px; }
.doc-date-label { font-size: 7.5px; color: #4a5568; text-transform: uppercase; margin-top: 5px; }
.doc-date-value { font-size: 10px; font-weight: bold; color: #2d3748; }
.section-header {
  background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
  color: white;
  font-weight: bold;
  font-size: 8.5px;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 5px 10px;
  margin-top: 12px;
  border-radius: 3px 3px 0 0;
}
table.data-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.data-table th {
  background: #edf2f7;
  font-weight: bold;
  font-size: 8.5px;
  padding: 5px 8px;
  border: 1px solid #cbd5e0;
  color: #2d3748;
  text-align: left;
  white-space: nowrap;
}
table.data-table td {
  font-size: 9px;
  padding: 5px 8px;
  border: 1px solid #cbd5e0;
  background: white;
}
table.std-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.std-table th {
  background: #2d6a9f;
  color: white;
  font-size: 8px;
  padding: 5px 7px;
  border: 1px solid #2d6a9f;
  text-align: center;
  font-weight: bold;
}
table.std-table td {
  font-size: 8.5px;
  padding: 5px 7px;
  border: 1px solid #cbd5e0;
  color: #1a1a2e;
}
table.std-table tr:nth-child(even) td { background: #f0f4f8; }
.text-content-box {
  border: 1px solid #cbd5e0;
  border-top: none;
  padding: 10px 12px;
  font-size: 9px;
  line-height: 1.6;
  color: #2d3748;
  background: white;
  min-height: 45px;
}
.resumen-box {
  border: 1px solid #cbd5e0;
  border-top: none;
  background: white;
}
.resumen-row {
  display: table;
  width: 100%;
  border-bottom: 1px solid #e2e8f0;
}
.resumen-row:last-child { border-bottom: none; }
.resumen-lbl {
  display: table-cell;
  padding: 6px 10px;
  font-size: 9px;
  color: #4a5568;
  width: 65%;
}
.resumen-val {
  display: table-cell;
  padding: 6px 10px;
  font-size: 10px;
  font-weight: bold;
  text-align: right;
  color: #2d3748;
}
.resumen-total-lbl {
  display: table-cell;
  padding: 8px 10px;
  font-size: 10px;
  font-weight: bold;
  color: #1a3a5c;
  width: 65%;
  background: #edf2f7;
}
.resumen-total-val {
  display: table-cell;
  padding: 8px 10px;
  font-size: 12px;
  font-weight: bold;
  text-align: right;
  color: #1a3a5c;
  background: #edf2f7;
}
.clausula {
  margin-top: 10px;
  border-left: 3px solid #e53e3e;
  padding: 7px 10px;
  font-size: 8.5px;
  color: #742a2a;
  background: #fff5f5;
  border-radius: 0 3px 3px 0;
  font-style: italic;
}
.firmas-wrap {
  display: table;
  width: 100%;
  margin-top: 35px;
  page-break-inside: avoid;
}
.firma-cell {
  display: table-cell;
  width: 33.33%;
  text-align: center;
  padding: 0 12px;
  vertical-align: bottom;
}
.firma-espacio {
  height: 40px;
  border-bottom: 1.5px solid #1a3a5c;
  margin: 0 10px 6px;
}
.firma-nombre { font-size: 9px; font-weight: bold; color: #1a3a5c; text-transform: uppercase; }
.firma-cargo { font-size: 8px; color: #4a5568; margin-top: 2px; }
.firma-rol { font-size: 7.5px; color: #718096; margin-top: 1px; text-transform: uppercase; letter-spacing: 0.3px; }
.footer {
  margin-top: 18px;
  padding-top: 6px;
  border-top: 1px solid #e2e8f0;
  display: table;
  width: 100%;
}
.footer-left { display: table-cell; font-size: 7.5px; color: #a0aec0; vertical-align: middle; }
.footer-right { display: table-cell; font-size: 7.5px; color: #a0aec0; text-align: right; vertical-align: middle; }
</style>
</head>
<body>

{{-- ══ ENCABEZADO ══ --}}
<div class="header-wrap">
  <div class="header-logo-cell">
    @if(file_exists($logo))
      <img src="{{ $logo }}" alt="GADPE">
    @else
      <div style="font-size:11px;font-weight:bold;color:#1a3a5c;">GADPE</div>
    @endif
  </div>
  <div class="header-center-cell">
    <div class="inst-name">Gobierno Autónomo Descentralizado<br>de la Provincia de Esmeraldas</div>
    <div class="doc-title">Informe de Licencia con Remuneración</div>
    <div class="doc-subtitle">Comisión de Servicios — Liquidación de Viático</div>
  </div>
  <div class="header-right-cell">
    <div class="doc-code-box">
      <div class="doc-code-label">N° Solicitud</div>
      <div class="doc-code-value">{{ $viatico->codigo_viatico }}</div>
      <div class="doc-date-label">Fecha del Informe</div>
      <div class="doc-date-value">{{ date('d/m/Y') }}</div>
    </div>
  </div>
</div>

{{-- ══ DATOS GENERALES ══ --}}
<div class="section-header">Datos Generales del Servidor</div>
<table class="data-table">
  <tr>
    <th style="width:22%">Apellidos y Nombres</th>
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
    <td style="width:28%">
      {{ $viatico->servidor?->puesto?->cargo?->nombre ?? '—' }}
    </td>
    <th style="width:22%">Unidad Administrativa</th>
    <td>
      {{ $viatico->servidor?->puesto?->unidadAdministrativa?->nombre ?? '—' }}
    </td>
  </tr>
  <tr>
    <th>Fecha / Hora de Salida</th>
    <td>
      {{ $viatico->datetime_salida
          ? \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y  H:i')
          : '—' }}
    </td>
    <th>Fecha / Hora de Regreso</th>
    <td>
      {{ $viatico->datetime_llegada
          ? \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y  H:i')
          : '—' }}
    </td>
  </tr>
</table>

{{-- ══ SERVIDORES ══ --}}
<div class="section-header">Servidores que Integran la Comisión</div>
<table class="std-table">
  <thead>
    <tr>
      <th style="width:8%">Cód.</th>
      <th style="text-align:left">Apellidos y Nombres</th>
      <th style="text-align:left">Cargo</th>
    </tr>
  </thead>
  <tbody>
    @forelse($viatico->todosServidores as $vs)
    <tr>
      <td style="text-align:center">{{ $vs->servidor_id }}</td>
      <td>
        {{ collect([
            $vs->servidor?->apellido,
            $vs->servidor?->nombre,
          ])->filter()->join(' ') ?: '—' }}
      </td>
      <td>{{ $vs->servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="3" style="text-align:center;color:#718096">—</td>
    </tr>
    @endforelse
  </tbody>
</table>

{{-- ══ ACTIVIDADES ══ --}}
<div class="section-header">Informe de Actividades o Productos Alcanzados</div>
@if($viatico->liquidacion?->actividades?->count() > 0)
<table class="std-table">
  <thead>
    <tr>
      <th style="width:12%">Fecha</th>
      <th style="width:9%">H. Inicio</th>
      <th style="width:9%">H. Fin</th>
      <th style="width:20%;text-align:left">Lugar</th>
      <th style="text-align:left">Descripción de la Actividad</th>
    </tr>
  </thead>
  <tbody>
    @foreach($viatico->liquidacion->actividades->sortBy('orden') as $act)
    <tr>
      <td style="text-align:center">
        {{ $act->fecha
            ? \Carbon\Carbon::parse($act->fecha)->format('d/m/Y')
            : '—' }}
      </td>
      <td style="text-align:center">{{ $act->hora_inicio ?? '—' }}</td>
      <td style="text-align:center">{{ $act->hora_fin ?? '—' }}</td>
      <td>{{ $act->lugar ?? '—' }}</td>
      <td>{{ $act->descripcion ?? '—' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else
<div class="text-content-box">
  {{ $viatico->justificacion ?? 'Sin descripción de actividades.' }}
</div>
@endif

{{-- ══ TRANSPORTE ══ --}}
<div class="section-header">Itinerario de Transporte</div>
<table class="std-table">
  <thead>
    <tr>
      <th>Tipo</th>
      <th>Empresa</th>
      <th>Ruta</th>
      <th>Fecha Salida</th>
      <th>Hora</th>
      <th>Fecha Llegada</th>
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
          ])->filter()->unique()->join('/')
        : collect([$tramo->origen_pais, $tramo->origen_ciudad])
            ->filter()->join('/');
      $dest = $tramo->destino_tipo === 'nacional'
        ? collect([
            $tramo->destinoProvincia?->nombre,
            $tramo->destinoCanton?->nombre ?: $tramo->destino_ciudad,
          ])->filter()->unique()->join('/')
        : collect([$tramo->destino_pais, $tramo->destino_ciudad])
            ->filter()->join('/');
    @endphp
    <tr>
      <td>{{ strtoupper($tramo->empresa?->catalogo?->tipo_vehiculo ?? 'TERRESTRE') }}</td>
      <td style="text-align:left">{{ $tramo->empresa?->nombre ?? '—' }}</td>
      <td style="text-align:left"><strong>{{ $orig }}</strong> → {{ $dest }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('H:i') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('H:i') : '—' }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="7" style="text-align:center;color:#718096">
        Sin tramos registrados.
      </td>
    </tr>
    @endforelse
  </tbody>
</table>

{{-- ══ GASTOS ══ --}}
<div class="section-header">Gastos — Comprobantes de Respaldo</div>
@if($viatico->liquidacion?->detallesFactura?->count() > 0)
<table class="std-table">
  <thead>
    <tr>
      <th style="text-align:left">RUC</th>
      <th style="text-align:left">Razón Social / Proveedor</th>
      <th>N° Comprobante</th>
      <th>Tipo</th>
      <th style="text-align:left">Categoría</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($viatico->liquidacion->detallesFactura as $f)
    <tr>
      <td>{{ $f->ruc_proveedor ?? '—' }}</td>
      <td>{{ $f->nombre_proveedor ?? '—' }}</td>
      <td style="text-align:center">
        {{ $f->numero_factura ?? $f->numero_ticket ?? '—' }}
      </td>
      <td style="text-align:center">
        {{ strtoupper($f->tipo_comprobante ?? 'FACTURA') }}
      </td>
      <td>{{ $f->categoria?->nombre ?? '—' }}</td>
      <td style="text-align:right">
        <strong>$ {{ number_format($f->monto ?? 0, 2) }}</strong>
      </td>
    </tr>
    @endforeach
  </tbody>
</table>

{{-- ══ RESUMEN FINANCIERO ══ --}}
<div class="section-header">Resumen Financiero</div>
<div class="resumen-box">
  <div class="resumen-row">
    <div class="resumen-lbl">Anticipo recibido:</div>
    <div class="resumen-val">$ {{ number_format($viatico->monto_anticipo ?? 0, 2) }}</div>
  </div>
  <div class="resumen-row">
    <div class="resumen-lbl">Total comprobantes presentados:</div>
    <div class="resumen-val">
      $ {{ number_format($viatico->liquidacion->total_facturas ?? 0, 2) }}
    </div>
  </div>
  <div class="resumen-row">
    <div class="resumen-total-lbl">
      {{ ($viatico->liquidacion->diferencia_devolver ?? 0) >= 0
          ? '★ Valor a devolver a la institución:'
          : '★ Valor a cobrar por el servidor:' }}
    </div>
    <div class="resumen-total-val">
      $ {{ number_format(abs($viatico->liquidacion->diferencia_devolver ?? 0), 2) }}
    </div>
  </div>
</div>
@else
<div class="text-content-box" style="color:#718096">
  Sin comprobantes registrados en la liquidación.
</div>
@endif

{{-- ══ CLÁUSULA ══ --}}
<div class="clausula">
  ★ AUTORIZO QUE LOS VALORES NO JUSTIFICADOS SEAN DEBITADOS
  DE MI PRÓXIMA REMUNERACIÓN MENSUAL UNIFICADA.
</div>

{{-- ══ FIRMAS ══ --}}
<div class="firmas-wrap">
  <div class="firma-cell">
    <div class="firma-espacio"></div>
    <div class="firma-nombre">
      {{ collect([
          $viatico->servidor?->apellido,
          $viatico->servidor?->nombre,
        ])->filter()->join(' ') ?: '—' }}
    </div>
    <div class="firma-cargo">
      {{ $viatico->servidor?->puesto?->cargo?->nombre ?? '' }}
    </div>
    <div class="firma-rol">Servidor Solicitante</div>
  </div>
  <div class="firma-cell">
    <div class="firma-espacio"></div>
    @if($jefeUnidad)
      <div class="firma-nombre">
        {{ collect([
            $jefeUnidad->apellido,
            $jefeUnidad->nombre,
          ])->filter()->join(' ') }}
      </div>
      <div class="firma-cargo">
        {{ $jefeUnidad->puesto?->cargo?->nombre ?? 'Director/a' }}
      </div>
    @else
      <div class="firma-nombre" style="color:#a0aec0">___________________________</div>
      <div class="firma-cargo" style="color:#a0aec0">Director/a</div>
    @endif
    <div class="firma-rol">Responsable de Unidad Solicitante</div>
  </div>
  <div class="firma-cell">
    <div class="firma-espacio"></div>
    @if($prefecto)
      <div class="firma-nombre">
        {{ collect([
            $prefecto->apellido,
            $prefecto->nombre,
          ])->filter()->join(' ') }}
      </div>
      <div class="firma-cargo">
        {{ $prefecto->puesto?->cargo?->nombre ?? 'Prefecto/a Provincial' }}
      </div>
    @else
      <div class="firma-nombre" style="color:#a0aec0">___________________________</div>
      <div class="firma-cargo">Prefecto/a Provincial</div>
    @endif
    <div class="firma-rol">Máxima Autoridad o Delegado</div>
  </div>
</div>

{{-- ══ PIE ══ --}}
<div class="footer">
  <div class="footer-left">SGTH — GAD Provincial de Esmeraldas</div>
  <div class="footer-right">
    Generado el {{ date('d/m/Y H:i') }} •
    Documento oficial — No requiere sello húmedo
  </div>
</div>

</body>
</html>
