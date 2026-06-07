<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 10px;
    color: #1a1a1a;
    line-height: 1.4;
  }
  .header {
    display: table;
    width: 100%;
    border-bottom: 3px solid #1a5276;
    padding-bottom: 10px;
    margin-bottom: 12px;
  }
  .header-logo {
    display: table-cell;
    width: 80px;
    vertical-align: middle;
  }
  .header-logo img { width: 70px; height: auto; }
  .header-text {
    display: table-cell;
    vertical-align: middle;
    text-align: center;
    padding: 0 10px;
  }
  .header-text h1 {
    font-size: 11px;
    font-weight: bold;
    color: #1a5276;
    text-transform: uppercase;
  }
  .header-text h2 {
    font-size: 13px;
    font-weight: bold;
    color: #1a1a1a;
    text-transform: uppercase;
    margin-top: 4px;
  }
  .header-codigo {
    display: table-cell;
    width: 130px;
    vertical-align: middle;
    text-align: right;
  }
  .codigo-box {
    border: 1px solid #1a5276;
    padding: 6px 8px;
    border-radius: 4px;
  }
  .codigo-box .label {
    font-size: 8px;
    color: #666;
    text-transform: uppercase;
  }
  .codigo-box .value {
    font-size: 11px;
    font-weight: bold;
    color: #1a5276;
  }
  .section-title {
    background-color: #1a5276;
    color: white;
    font-weight: bold;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 4px 8px;
    margin: 10px 0 0 0;
  }
  table.data {
    width: 100%;
    border-collapse: collapse;
  }
  table.data th {
    background: #eaf0fb;
    font-weight: bold;
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #c8d4e8;
    text-align: left;
    width: 22%;
  }
  table.data td {
    font-size: 10px;
    padding: 4px 6px;
    border: 1px solid #c8d4e8;
    background: #fff;
  }
  table.activity {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
  }
  table.activity th {
    background: #1a5276;
    color: white;
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #1a5276;
    text-align: center;
  }
  table.activity td {
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #c8d4e8;
    background: #fff;
  }
  table.activity tr:nth-child(even) td {
    background: #f5f8ff;
  }
  table.transport {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
  }
  table.transport th {
    background: #1a5276;
    color: white;
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #1a5276;
    text-align: center;
  }
  table.transport td {
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #c8d4e8;
    text-align: center;
    background: #fff;
  }
  table.facturas {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
  }
  table.facturas th {
    background: #1a5276;
    color: white;
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #1a5276;
    text-align: center;
  }
  table.facturas td {
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #c8d4e8;
    background: #fff;
  }
  table.facturas tfoot td {
    font-weight: bold;
    background: #eaf0fb;
  }
  .resumen-box {
    display: table;
    width: 100%;
    border: 1px solid #c8d4e8;
    margin-top: 0;
  }
  .resumen-row {
    display: table-row;
  }
  .resumen-label {
    display: table-cell;
    padding: 5px 8px;
    font-size: 9px;
    color: #555;
    border-bottom: 1px solid #eee;
    width: 60%;
  }
  .resumen-value {
    display: table-cell;
    padding: 5px 8px;
    font-size: 10px;
    font-weight: bold;
    text-align: right;
    border-bottom: 1px solid #eee;
  }
  .resumen-total {
    display: table-cell;
    padding: 5px 8px;
    font-size: 11px;
    font-weight: bold;
    text-align: right;
    color: #1a5276;
  }
  .servidores-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
  }
  .servidores-table th {
    background: #1a5276;
    color: white;
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #1a5276;
  }
  .servidores-table td {
    font-size: 9px;
    padding: 4px 6px;
    border: 1px solid #c8d4e8;
    background: #fff;
  }
  .signatures {
    display: table;
    width: 100%;
    margin-top: 30px;
    page-break-inside: avoid;
  }
  .sig-cell {
    display: table-cell;
    width: 33.3%;
    text-align: center;
    padding: 0 15px;
    vertical-align: bottom;
  }
  .sig-line {
    border-top: 1px solid #1a5276;
    margin-bottom: 5px;
    margin-top: 40px;
  }
  .sig-name {
    font-size: 9px;
    font-weight: bold;
    color: #1a5276;
  }
  .sig-cargo { font-size: 8px; color: #555; }
  .footer {
    margin-top: 15px;
    border-top: 1px solid #eee;
    padding-top: 5px;
    font-size: 8px;
    color: #999;
    text-align: center;
  }
</style>
</head>
<body>

{{-- ENCABEZADO --}}
<div class="header">
  <div class="header-logo">
    @if(file_exists($logo))
      <img src="{{ $logo }}" alt="GADPE">
    @else
      <strong style="font-size:9px;color:#1a5276;">GADPE</strong>
    @endif
  </div>
  <div class="header-text">
    <h1>Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas</h1>
    <h2>Informe de Licencia con Remuneración</h2>
  </div>
  <div class="header-codigo">
    <div class="codigo-box">
      <div class="label">N° Solicitud</div>
      <div class="value">{{ $viatico->codigo_viatico }}</div>
      <div class="label" style="margin-top:4px;">Fecha Informe</div>
      <div class="value" style="font-size:9px;">
        {{ date('d/m/Y') }}
      </div>
    </div>
  </div>
</div>

{{-- DATOS GENERALES --}}
<div class="section-title">Datos Generales</div>
<table class="data">
  <tr>
    <th>Apellidos y Nombres</th>
    <td colspan="3">
      {{ collect([
          $viatico->servidor?->apellido,
          $viatico->servidor?->segundo_apellido,
          $viatico->servidor?->nombre,
          $viatico->servidor?->segundo_nombre,
        ])->filter()->join(' ') ?: '—' }}
    </td>
  </tr>
  <tr>
    <th>Cargo / Puesto</th>
    <td>{{ $viatico->servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
    <th>Unidad Administrativa</th>
    <td>{{ $viatico->servidor?->puesto?->unidadAdministrativa?->nombre ?? '—' }}</td>
  </tr>
  <tr>
    <th>Fecha / Hora Salida</th>
    <td>
      {{ $viatico->datetime_salida
          ? \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y H:i')
          : '—' }}
    </td>
    <th>Fecha / Hora Llegada</th>
    <td>
      {{ $viatico->datetime_llegada
          ? \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y H:i')
          : '—' }}
    </td>
  </tr>
</table>

{{-- SERVIDORES --}}
<div class="section-title">Servidores que Integran la Comisión</div>
<table class="servidores-table">
  <thead>
    <tr>
      <th>Cód.</th>
      <th>Apellidos y Nombres</th>
      <th>Cargo</th>
    </tr>
  </thead>
  <tbody>
    @forelse($viatico->todosServidores as $vs)
    <tr>
      <td style="text-align:center">
        {{ $vs->servidor_id }}
      </td>
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
      <td colspan="3" style="text-align:center">—</td>
    </tr>
    @endforelse
  </tbody>
</table>

{{-- INFORME DE ACTIVIDADES --}}
<div class="section-title">Informe de Actividades o Productos Alcanzados</div>
@if($viatico->liquidacion?->actividades?->count() > 0)
<table class="activity">
  <thead>
    <tr>
      <th>Fecha</th>
      <th>Hora Inicio</th>
      <th>Hora Fin</th>
      <th>Lugar</th>
      <th style="text-align:left">Descripción de la Actividad</th>
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
      <td>{{ $act->lugar ?? '—' }}</td>
      <td style="text-align:left">{{ $act->descripcion ?? '—' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else
<div style="border:1px solid #c8d4e8;padding:8px;font-size:10px;">
  {{ $viatico->justificacion ?? 'Sin descripción de actividades.' }}
</div>
@endif

{{-- TRANSPORTE --}}
<div class="section-title">Transporte</div>
<table class="transport">
  <thead>
    <tr>
      <th>Tipo</th>
      <th>Empresa / Nombre</th>
      <th>Ruta</th>
      <th>Fecha Salida</th>
      <th>Hora Salida</th>
      <th>Fecha Llegada</th>
      <th>Hora Llegada</th>
    </tr>
  </thead>
  <tbody>
    @forelse($viatico->tramos->sortBy('orden') as $tramo)
    @php
      $origen = $tramo->origen_tipo === 'nacional'
        ? collect([
            $tramo->origenProvincia?->nombre,
            $tramo->origenCanton?->nombre,
          ])->filter()->join('/')
        : collect([$tramo->origen_pais, $tramo->origen_ciudad])
            ->filter()->join('/');
      $destino = $tramo->destino_tipo === 'nacional'
        ? collect([
            $tramo->destinoProvincia?->nombre,
            $tramo->destinoCanton?->nombre,
          ])->filter()->join('/')
        : collect([$tramo->destino_pais, $tramo->destino_ciudad])
            ->filter()->join('/');
    @endphp
    <tr>
      <td>{{ strtoupper($tramo->empresa?->catalogo?->tipo_vehiculo ?? 'TERRESTRE') }}</td>
      <td>{{ $tramo->empresa?->nombre ?? '—' }}</td>
      <td>{{ $origen }} → {{ $destino }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('H:i') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('H:i') : '—' }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="7" style="text-align:center">Sin tramos registrados.</td>
    </tr>
    @endforelse
  </tbody>
</table>

{{-- GASTOS / FACTURAS --}}
<div class="section-title">Gastos</div>
@if($viatico->liquidacion?->detallesFactura?->count() > 0)
<table class="facturas">
  <thead>
    <tr>
      <th>RUC</th>
      <th>Razón Social / Proveedor</th>
      <th>N° Comprobante</th>
      <th>Tipo</th>
      <th>Categoría</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($viatico->liquidacion->detallesFactura as $f)
    <tr>
      <td>{{ $f->ruc_proveedor ?? '—' }}</td>
      <td>{{ $f->nombre_proveedor ?? '—' }}</td>
      <td>
        {{ $f->numero_factura ?? $f->numero_ticket ?? '—' }}
      </td>
      <td style="text-align:center">
        {{ strtoupper($f->tipo_comprobante ?? 'FACTURA') }}
      </td>
      <td>{{ $f->categoria?->nombre ?? '—' }}</td>
      <td style="text-align:right">
        $ {{ number_format($f->monto ?? 0, 2) }}
      </td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr>
      <td colspan="5" style="text-align:right">
        TOTAL:
      </td>
      <td style="text-align:right">
        $ {{ number_format($viatico->liquidacion->total_facturas ?? 0, 2) }}
      </td>
    </tr>
  </tfoot>
</table>

{{-- RESUMEN FINANCIERO --}}
<div class="section-title">Resumen Financiero</div>
<div class="resumen-box">
  <div class="resumen-row">
    <div class="resumen-label">Anticipo recibido:</div>
    <div class="resumen-value">
      $ {{ number_format($viatico->monto_anticipo ?? 0, 2) }}
    </div>
  </div>
  <div class="resumen-row">
    <div class="resumen-label">Total facturas presentadas:</div>
    <div class="resumen-value">
      $ {{ number_format($viatico->liquidacion->total_facturas ?? 0, 2) }}
    </div>
  </div>
  <div class="resumen-row">
    <div class="resumen-label" style="font-weight:bold;color:#1a5276;">
      {{ ($viatico->liquidacion->diferencia_devolver ?? 0) >= 0
          ? 'Valor a devolver a la institución:'
          : 'Valor a cobrar por el servidor:' }}
    </div>
    <div class="resumen-total">
      $ {{ number_format(abs($viatico->liquidacion->diferencia_devolver ?? 0), 2) }}
    </div>
  </div>
</div>
@else
<div style="border:1px solid #c8d4e8;padding:8px;font-size:10px;color:#666;">
  Sin facturas registradas en la liquidación.
</div>
@endif

{{-- AUTORIZACIÓN --}}
<div style="border:1px solid #c8d4e8;padding:6px 8px;font-size:9px;background:#fff8f0;margin-top:8px;font-style:italic;color:#555;">
  AUTORIZO QUE LOS VALORES NO JUSTIFICADOS SEAN DEBITADOS
  DE MI PRÓXIMA REMUNERACIÓN MENSUAL UNIFICADA.
</div>

{{-- FIRMAS --}}
<div class="signatures">
  <div class="sig-cell">
    <div class="sig-line"></div>
    <div class="sig-name">
      {{ collect([
          $viatico->servidor?->apellido,
          $viatico->servidor?->nombre,
        ])->filter()->join(' ') ?: '—' }}
    </div>
    <div class="sig-cargo">
      {{ $viatico->servidor?->puesto?->cargo?->nombre ?? '' }}
    </div>
    <div class="sig-cargo">SERVIDOR SOLICITANTE</div>
  </div>
  <div class="sig-cell">
    <div class="sig-line"></div>
    @php
      $jefe = $viatico->liquidacion?->jefeFinanciero;
    @endphp
    <div class="sig-name">
      {{ $jefe
          ? collect([$jefe->name])->filter()->join(' ')
          : '___________________________' }}
    </div>
    <div class="sig-cargo">DIRECTOR/A</div>
    <div class="sig-cargo">RESPONSABLE DE UNIDAD SOLICITANTE</div>
  </div>
  <div class="sig-cell">
    <div class="sig-line"></div>
    <div class="sig-name">
      {{ $prefecto
          ? collect([
              $prefecto->apellido,
              $prefecto->nombre,
            ])->filter()->join(' ')
          : 'PREFECTO/A PROVINCIAL' }}
    </div>
    <div class="sig-cargo">PREFECTO/A PROVINCIAL</div>
    <div class="sig-cargo">MÁXIMA AUTORIDAD O DELEGADO</div>
  </div>
</div>

{{-- NUEVO: también crear informe-liquidacion.blade.php --}}

<div class="footer">
  Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas —
  Sistema de Gestión del Talento Humano •
  Generado el {{ date('d/m/Y H:i') }}
</div>

</body>
</html>
