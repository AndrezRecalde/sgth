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

/* ── ENCABEZADO ── */
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
.header-logo-cell img {
  width: 90px;
  height: auto;
}
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
  letter-spacing: 0.5px;
}
.doc-subtitle {
  font-size: 9px;
  color: #4a5568;
  margin-top: 3px;
}
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
.doc-code-label {
  font-size: 7.5px;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.doc-code-value {
  font-size: 12px;
  font-weight: bold;
  color: #1a3a5c;
  margin-top: 2px;
}
.doc-date-label {
  font-size: 7.5px;
  color: #4a5568;
  text-transform: uppercase;
  margin-top: 5px;
}
.doc-date-value {
  font-size: 10px;
  font-weight: bold;
  color: #2d3748;
}

/* ── CHECKBOXES ── */
.solicita-bar {
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 7px 14px;
  margin-bottom: 14px;
  display: table;
  width: 100%;
}
.solicita-label {
  display: table-cell;
  font-weight: bold;
  font-size: 9px;
  color: #2d3748;
  vertical-align: middle;
  padding-right: 16px;
  white-space: nowrap;
}
.solicita-items {
  display: table-cell;
  vertical-align: middle;
}
.check-item {
  display: inline-block;
  margin-right: 18px;
  vertical-align: middle;
}
.check-box {
  display: inline-block;
  width: 13px;
  height: 13px;
  border: 1.5px solid #2d3748;
  border-radius: 2px;
  text-align: center;
  line-height: 13px;
  font-size: 9px;
  vertical-align: middle;
  margin-right: 5px;
  background: white;
}
.check-box.on {
  background: #1a3a5c;
  color: white;
  border-color: #1a3a5c;
}
.check-text {
  font-size: 9px;
  color: #2d3748;
  font-weight: bold;
  vertical-align: middle;
}

/* ── SECCIÓN TÍTULOS ── */
.section-header {
  background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
  color: white;
  font-weight: bold;
  font-size: 8.5px;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 5px 10px;
  margin-top: 12px;
  margin-bottom: 0;
  border-radius: 3px 3px 0 0;
}

/* ── TABLAS DE DATOS ── */
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
  color: #1a1a2e;
  background: white;
}
table.data-table tr:nth-child(even) td {
  background: #f7fafc;
}

/* ── TABLA DE TRANSPORTE ── */
table.transport-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.transport-table th {
  background: #2d6a9f;
  color: white;
  font-size: 8px;
  padding: 5px 6px;
  border: 1px solid #2d6a9f;
  text-align: center;
  font-weight: bold;
}
table.transport-table td {
  font-size: 8.5px;
  padding: 5px 6px;
  border: 1px solid #cbd5e0;
  text-align: center;
  color: #1a1a2e;
}
table.transport-table tr:nth-child(even) td {
  background: #f0f4f8;
}
table.transport-table .td-left {
  text-align: left;
}

/* ── SERVIDORES ── */
table.serv-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.serv-table th {
  background: #2d6a9f;
  color: white;
  font-size: 8px;
  padding: 5px 8px;
  border: 1px solid #2d6a9f;
  text-align: center;
}
table.serv-table td {
  font-size: 8.5px;
  padding: 5px 8px;
  border: 1px solid #cbd5e0;
  color: #1a1a2e;
}

/* ── JUSTIFICACIÓN ── */
.justif-box {
  border: 1px solid #cbd5e0;
  border-top: none;
  padding: 10px 12px;
  font-size: 9px;
  line-height: 1.6;
  color: #2d3748;
  background: white;
  min-height: 55px;
}

/* ── CUENTA BANCARIA ── */
.cuenta-wrap {
  display: table;
  width: 100%;
  border: 1px solid #cbd5e0;
  border-top: none;
  background: white;
}
.cuenta-cell {
  display: table-cell;
  padding: 7px 10px;
  border-right: 1px solid #e2e8f0;
  vertical-align: middle;
}
.cuenta-cell:last-child { border-right: none; }
.cuenta-lbl {
  font-size: 7.5px;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.cuenta-val {
  font-size: 10px;
  font-weight: bold;
  color: #1a3a5c;
  margin-top: 2px;
}

/* ── CLÁUSULA ── */
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

/* ── FIRMAS ── */
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
.firma-nombre {
  font-size: 9px;
  font-weight: bold;
  color: #1a3a5c;
  text-transform: uppercase;
}
.firma-cargo {
  font-size: 8px;
  color: #4a5568;
  margin-top: 2px;
}
.firma-rol {
  font-size: 7.5px;
  color: #718096;
  margin-top: 1px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

/* ── PIE ── */
.footer {
  margin-top: 18px;
  padding-top: 6px;
  border-top: 1px solid #e2e8f0;
  display: table;
  width: 100%;
}
.footer-left {
  display: table-cell;
  font-size: 7.5px;
  color: #a0aec0;
  vertical-align: middle;
}
.footer-right {
  display: table-cell;
  font-size: 7.5px;
  color: #a0aec0;
  text-align: right;
  vertical-align: middle;
}
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
    <div class="doc-title">Solicitud de Viático</div>
    <div class="doc-subtitle">Licencia con Remuneración — Comisión de Servicios</div>
  </div>
  <div class="header-right-cell">
    <div class="doc-code-box">
      <div class="doc-code-label">N° Solicitud</div>
      <div class="doc-code-value">{{ $viatico->codigo_viatico }}</div>
      <div class="doc-date-label">Fecha de Solicitud</div>
      <div class="doc-date-value">
        {{ $viatico->fecha_solicitud
            ? \Carbon\Carbon::parse($viatico->fecha_solicitud)->format('d/m/Y')
            : date('d/m/Y') }}
      </div>
    </div>
  </div>
</div>

{{-- ══ CHECKBOXES ══ --}}
<div class="solicita-bar">
  <span class="solicita-label">A SOLICITAR:</span>
  <span class="solicita-items">
    <span class="check-item">
      <span class="check-box on">✓</span>
      <span class="check-text">VIÁTICOS</span>
    </span>
    <span class="check-item">
      <span class="check-box on">✓</span>
      <span class="check-text">MOVILIZACIONES</span>
    </span>
    <span class="check-item">
      <span class="check-box {{ $viatico->zona === 'exterior' ? 'on' : '' }}">
        {{ $viatico->zona === 'exterior' ? '✓' : '' }}
      </span>
      <span class="check-text">SUBSISTENCIAS</span>
    </span>
    <span class="check-item">
      <span class="check-box"></span>
      <span class="check-text">ALIMENTACIÓN</span>
    </span>
  </span>
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
    <th>Zona del Viaje</th>
    <td>
      @switch($viatico->zona)
        @case('dentro_provincia') Dentro de la Provincia @break
        @case('fuera_provincia')  Fuera de la Provincia  @break
        @case('exterior')         Exterior — Internacional @break
        @default {{ $viatico->zona }}
      @endswitch
    </td>
    <th>Código de Solicitud</th>
    <td><strong>{{ $viatico->codigo_viatico }}</strong></td>
  </tr>
  <tr>
    <th>Provincia / Ciudad Destino</th>
    <td>
      @if($viatico->zona === 'exterior')
        {{ $viatico->pais_destino ?? '—' }}
      @else
        @php
          $ultimo = $viatico->tramos->sortBy('orden')->last();
          $ciudad = $ultimo?->destino_ciudad ?? '—';
          $prov   = $ultimo?->destinoProvincia?->nombre ?? '';
        @endphp
        {{ collect([$prov, $ciudad])->filter()->join(' — ') }}
      @endif
    </td>
    <th>Total Días</th>
    <td>
      <strong>{{ number_format($viatico->total_dias ?? 0, 0) }}</strong>
      día(s)
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
  <tr>
    <th>Monto Calculado</th>
    <td>
      <strong style="color:#1a3a5c;font-size:10px;">
        $ {{ number_format($viatico->monto_calculado ?? 0, 2) }}
      </strong>
    </td>
    <th>Modalidad Anticipo</th>
    <td>
      @switch($viatico->modalidad_anticipo)
        @case('total')        Anticipo Total (100%) @break
        @case('parcial')      Anticipo Parcial @break
        @case('sin_anticipo') Sin Anticipo @break
        @default {{ $viatico->modalidad_anticipo }}
      @endswitch
    </td>
  </tr>
</table>

{{-- ══ SERVIDORES ══ --}}
<div class="section-header">Servidores que Integran la Comisión</div>
<table class="serv-table">
  <thead>
    <tr>
      <th style="width:8%;text-align:center">N°</th>
      <th style="text-align:left">Apellidos y Nombres</th>
      <th style="text-align:left">Cargo</th>
      <th style="width:15%;text-align:center">Condición</th>
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
          ])->filter()->join(' ') ?: '—' }}
      </td>
      <td>{{ $vs->servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
      <td style="text-align:center">
        <strong>{{ $vs->es_titular ? 'Titular' : 'Acompañante' }}</strong>
      </td>
    </tr>
    @empty
    <tr>
      <td colspan="4" style="text-align:center;color:#718096">
        Sin servidores registrados.
      </td>
    </tr>
    @endforelse
  </tbody>
</table>

{{-- ══ JUSTIFICACIÓN ══ --}}
<div class="section-header">Descripción de las Actividades a Realizarse</div>
<div class="justif-box">{{ $viatico->justificacion ?? '—' }}</div>

{{-- ══ TRANSPORTE ══ --}}
<div class="section-header">Itinerario de Transporte</div>
<table class="transport-table">
  <thead>
    <tr>
      <th>Tipo</th>
      <th>Empresa / Nombre</th>
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
          ])->filter()->unique()->join(' / ')
        : collect([$tramo->origen_pais, $tramo->origen_ciudad])
            ->filter()->join(' / ');

      $dest = $tramo->destino_tipo === 'nacional'
        ? collect([
            $tramo->destinoProvincia?->nombre,
            $tramo->destinoCanton?->nombre ?: $tramo->destino_ciudad,
          ])->filter()->unique()->join(' / ')
        : collect([$tramo->destino_pais, $tramo->destino_ciudad])
            ->filter()->join(' / ');
    @endphp
    <tr>
      <td>{{ strtoupper($tramo->empresa?->catalogo?->tipo_vehiculo ?? 'TERRESTRE') }}</td>
      <td class="td-left">{{ $tramo->empresa?->nombre ?? '—' }}</td>
      <td class="td-left"><strong>{{ $orig }}</strong> → {{ $dest }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('H:i') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('H:i') : '—' }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="7" style="text-align:center;color:#718096">
        Sin tramos de transporte registrados.
      </td>
    </tr>
    @endforelse
  </tbody>
</table>

{{-- ══ DATOS BANCARIOS ══ --}}
@php $cuenta = $viatico->servidor?->cuentasBancarias?->first(); @endphp
<div class="section-header">Datos para Transferencia Bancaria</div>
<div class="cuenta-wrap">
  <div class="cuenta-cell" style="width:28%">
    <div class="cuenta-lbl">Tipo de Cuenta</div>
    <div class="cuenta-val">
      {{ $cuenta?->tipo_cuenta ? strtoupper($cuenta->tipo_cuenta) : '—' }}
    </div>
  </div>
  <div class="cuenta-cell" style="width:32%">
    <div class="cuenta-lbl">Número de Cuenta</div>
    <div class="cuenta-val">{{ $cuenta?->numero_cuenta ?? '—' }}</div>
  </div>
  <div class="cuenta-cell">
    <div class="cuenta-lbl">Institución Financiera</div>
    <div class="cuenta-val">
      {{ $cuenta?->entidadFinanciera?->nombre ?? '—' }}
    </div>
  </div>
</div>

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
      <div class="firma-nombre" style="color:#a0aec0">
        ___________________________
      </div>
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
      <div class="firma-nombre" style="color:#a0aec0">
        ___________________________
      </div>
      <div class="firma-cargo">Prefecto/a Provincial</div>
    @endif
    <div class="firma-rol">Máxima Autoridad o Delegado</div>
  </div>
</div>

{{-- ══ PIE ══ --}}
<div class="footer">
  <div class="footer-left">
    SGTH — GAD Provincial de Esmeraldas
  </div>
  <div class="footer-right">
    Generado el {{ date('d/m/Y H:i') }} •
    Documento oficial — No requiere sello húmedo
  </div>
</div>

</body>
</html>
