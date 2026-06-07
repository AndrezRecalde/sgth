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
  .header-logo img {
    width: 70px;
    height: auto;
  }
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
    letter-spacing: 0.5px;
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
  .checkboxes {
    display: table;
    width: 100%;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    padding: 6px 10px;
    background: #f8f9fa;
    border-radius: 3px;
  }
  .checkbox-item {
    display: table-cell;
    padding-right: 20px;
    font-size: 10px;
  }
  .checkbox {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 1px solid #333;
    margin-right: 4px;
    vertical-align: middle;
    text-align: center;
    line-height: 12px;
    font-size: 9px;
    background: white;
  }
  .checkbox.checked { background: #1a5276; color: white; }
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
    margin-bottom: 0;
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
  table.transport tr:nth-child(even) td {
    background: #f5f8ff;
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
  .justificacion-box {
    border: 1px solid #c8d4e8;
    padding: 8px;
    font-size: 10px;
    line-height: 1.5;
    background: #fff;
    min-height: 50px;
    margin-bottom: 0;
  }
  .autorizacion-box {
    border: 1px solid #c8d4e8;
    padding: 6px 8px;
    font-size: 9px;
    background: #fff8f0;
    margin-top: 8px;
    font-style: italic;
    color: #555;
  }
  .cuenta-box {
    display: table;
    width: 100%;
    border: 1px solid #c8d4e8;
    background: #fff;
  }
  .cuenta-cell {
    display: table-cell;
    padding: 5px 8px;
    border-right: 1px solid #c8d4e8;
    font-size: 9px;
    vertical-align: middle;
  }
  .cuenta-cell:last-child { border-right: none; }
  .cuenta-label { color: #666; font-size: 8px; text-transform: uppercase; }
  .cuenta-value { font-weight: bold; font-size: 10px; }
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
    margin-top: 35px;
  }
  .sig-name {
    font-size: 9px;
    font-weight: bold;
    color: #1a5276;
  }
  .sig-cargo {
    font-size: 8px;
    color: #555;
  }
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
    <h2>Solicitud de Viático</h2>
  </div>
  <div class="header-codigo">
    <div class="codigo-box">
      <div class="label">N° Solicitud</div>
      <div class="value">{{ $viatico->codigo_viatico }}</div>
      <div class="label" style="margin-top:4px;">Fecha</div>
      <div class="value" style="font-size:9px;">
        {{ $viatico->fecha_solicitud
            ? \Carbon\Carbon::parse($viatico->fecha_solicitud)
                ->format('d/m/Y')
            : date('d/m/Y') }}
      </div>
    </div>
  </div>
</div>

{{-- CHECKBOXES TIPO --}}
<div class="checkboxes">
  <span style="font-weight:bold;font-size:9px;margin-right:15px;">
    A SOLICITAR:
  </span>
  <span class="checkbox-item">
    <span class="checkbox checked">✓</span> VIÁTICOS
  </span>
  <span class="checkbox-item">
    <span class="checkbox checked">✓</span> MOVILIZACIONES
  </span>
  <span class="checkbox-item">
    <span class="checkbox">
      {{ $viatico->zona === 'exterior' ? '✓' : '' }}
    </span> SUBSISTENCIAS
  </span>
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
    <th>Zona del viaje</th>
    <td>
      @switch($viatico->zona)
        @case('dentro_provincia') Dentro de la Provincia @break
        @case('fuera_provincia')  Fuera de la Provincia  @break
        @case('exterior')         Exterior (Internacional) @break
        @default {{ $viatico->zona }}
      @endswitch
    </td>
    <th>Nro. Código</th>
    <td>{{ $viatico->codigo_viatico }}</td>
  </tr>
  <tr>
    <th>Fecha / Hora de Salida</th>
    <td>
      {{ $viatico->datetime_salida
          ? \Carbon\Carbon::parse($viatico->datetime_salida)
              ->format('d/m/Y H:i')
          : '—' }}
    </td>
    <th>Fecha / Hora de Llegada</th>
    <td>
      {{ $viatico->datetime_llegada
          ? \Carbon\Carbon::parse($viatico->datetime_llegada)
              ->format('d/m/Y H:i')
          : '—' }}
    </td>
  </tr>
  <tr>
    <th>Total Días</th>
    <td>{{ number_format($viatico->total_dias ?? 0, 0) }} día(s)</td>
    <th>Monto Calculado</th>
    <td>$ {{ number_format($viatico->monto_calculado ?? 0, 2) }}</td>
  </tr>
</table>

{{-- SERVIDORES EN COMISIÓN --}}
<div class="section-title">Servidores que Integran la Comisión</div>
<table class="servidores-table">
  <thead>
    <tr>
      <th>N°</th>
      <th>Apellidos y Nombres</th>
      <th>Cargo</th>
      <th>Condición</th>
    </tr>
  </thead>
  <tbody>
    @forelse($viatico->todosServidores as $vs)
    <tr>
      <td style="text-align:center">{{ $loop->iteration }}</td>
      <td>
        {{ collect([
            $vs->servidor?->apellido,
            $vs->servidor?->segundo_apellido,
            $vs->servidor?->nombre,
            $vs->servidor?->segundo_nombre,
          ])->filter()->join(' ') ?: '—' }}
      </td>
      <td>{{ $vs->servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
      <td style="text-align:center">
        {{ $vs->es_titular ? 'Titular' : 'Acompañante' }}
      </td>
    </tr>
    @empty
    <tr>
      <td colspan="4" style="text-align:center">
        Sin servidores registrados.
      </td>
    </tr>
    @endforelse
  </tbody>
</table>

{{-- DESCRIPCIÓN / JUSTIFICACIÓN --}}
<div class="section-title">Descripción de las Actividades a Realizarse</div>
<div class="justificacion-box">
  {{ $viatico->justificacion ?? '—' }}
</div>

{{-- TRANSPORTE / ITINERARIO --}}
<div class="section-title">Transporte</div>
<table class="transport">
  <thead>
    <tr>
      <th>Tipo de Transporte</th>
      <th>Nombre / Empresa</th>
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
      $origenNombre = $tramo->origen_tipo === 'nacional'
        ? collect([
            $tramo->origenProvincia?->nombre,
            $tramo->origenCanton?->nombre,
            $tramo->origen_ciudad,
          ])->filter()->unique()->join(' / ')
        : collect([
            $tramo->origen_pais,
            $tramo->origen_ciudad,
          ])->filter()->join(' / ');

      $destinoNombre = $tramo->destino_tipo === 'nacional'
        ? collect([
            $tramo->destinoProvincia?->nombre,
            $tramo->destinoCanton?->nombre,
            $tramo->destino_ciudad,
          ])->filter()->unique()->join(' / ')
        : collect([
            $tramo->destino_pais,
            $tramo->destino_ciudad,
          ])->filter()->join(' / ');
    @endphp
    <tr>
      <td>
        {{ strtoupper($tramo->empresa?->catalogo?->tipo_vehiculo ?? 'TERRESTRE') }}
      </td>
      <td>{{ $tramo->empresa?->nombre ?? '—' }}</td>
      <td>{{ $origenNombre }} → {{ $destinoNombre }}</td>
      <td>
        {{ $tramo->datetime_salida
            ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('d/m/Y')
            : '—' }}
      </td>
      <td>
        {{ $tramo->datetime_salida
            ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('H:i')
            : '—' }}
      </td>
      <td>
        {{ $tramo->datetime_llegada
            ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('d/m/Y')
            : '—' }}
      </td>
      <td>
        {{ $tramo->datetime_llegada
            ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('H:i')
            : '—' }}
      </td>
    </tr>
    @empty
    <tr>
      <td colspan="7" style="text-align:center">
        Sin tramos de transporte registrados.
      </td>
    </tr>
    @endforelse
  </tbody>
</table>

{{-- DATOS BANCARIOS --}}
@php
  $cuenta = $viatico->servidor?->cuentasBancarias?->first();
@endphp
<div class="section-title">Datos para Transferencia</div>
<div class="cuenta-box">
  <div class="cuenta-cell">
    <div class="cuenta-label">Tipo de Cuenta</div>
    <div class="cuenta-value">
      {{ $cuenta?->tipo_cuenta
          ? strtoupper($cuenta->tipo_cuenta)
          : '—' }}
    </div>
  </div>
  <div class="cuenta-cell">
    <div class="cuenta-label">N° de Cuenta</div>
    <div class="cuenta-value">
      {{ $cuenta?->numero_cuenta ?? '—' }}
    </div>
  </div>
  <div class="cuenta-cell">
    <div class="cuenta-label">Institución Financiera</div>
    <div class="cuenta-value">
      {{ $cuenta?->entidadFinanciera?->nombre ?? '—' }}
    </div>
  </div>
</div>

{{-- CLÁUSULA DE AUTORIZACIÓN --}}
<div class="autorizacion-box">
  AUTORIZO QUE LOS VALORES NO JUSTIFICADOS SEAN
  DEBITADOS DE MI PRÓXIMA REMUNERACIÓN MENSUAL UNIFICADA.
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
    <div class="sig-name">
      {{ collect([
          $viatico->servidor?->apellido,
          $viatico->servidor?->nombre,
        ])->filter()->join(' ') ?: '—' }}
    </div>
    <div class="sig-cargo">
      {{ $viatico->servidor?->puesto?->cargo?->nombre ?? '' }}
    </div>
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

{{-- PIE DE PÁGINA --}}
<div class="footer">
  Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas —
  Sistema de Gestión del Talento Humano •
  Generado el {{ date('d/m/Y H:i') }}
</div>

</body>
</html>
