@php
  $logoPath = public_path('images/logo-gadpe.png');
  $logoSrc  = file_exists($logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
    : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #000;
    margin: 20px 25px;
  }
  .header-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    margin-bottom: 8px;
  }
  .logo-cell {
    width: 100px;
    border-right: 2px solid #000;
    text-align: center;
    padding: 4px;
    vertical-align: middle;
  }
  .logo-cell img { width: 88px; height: auto; }
  .title-cell {
    text-align: center;
    vertical-align: middle;
    padding: 5px 10px;
  }
  .inst { font-size:11px; font-weight:bold; text-transform:uppercase; }
  .doc-title {
    font-size: 13px;
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 3px;
  }
  .filtros {
    font-size: 10px;
    margin-bottom: 8px;
    color: #444;
  }
  .filtros span { font-weight: bold; color: #000; }
  table.data {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
  }
  table.data th {
    background: #1a5c38;
    color: #fff;
    padding: 5px 6px;
    font-size: 10px;
    text-align: left;
    border: 1px solid #fff;
  }
  table.data th.right { text-align: right; }
  table.data td {
    border: 1px solid #ddd;
    padding: 4px 6px;
    font-size: 10px;
  }
  table.data tr:nth-child(even) td { background: #f5f5f5; }
  table.data td.right { text-align: right; }
  table.data td.center { text-align: center; }
  .totales-row td {
    background: #e8f5e9 !important;
    font-weight: bold;
    border-top: 2px solid #1a5c38;
  }
  .pie {
    text-align: right;
    font-size: 8px;
    color: #aaa;
    margin-top: 6px;
  }
</style>
</head>
<body>

<table class="header-table">
  <tr>
    <td class="logo-cell">
      @if($logoSrc)
        <img src="{{ $logoSrc }}" alt="Logo GADPE">
      @else
        <div style="font-size:9px;font-weight:bold;color:#1a5c38;">GADPE</div>
      @endif
    </td>
    <td class="title-cell">
      <div class="inst">
        Gobierno Autonomo Descentralizado de la Provincia de Esmeraldas
      </div>
      <div class="doc-title">
        Consolidado de Permisos — Tipo: {{ $tipo }}
      </div>
    </td>
  </tr>
</table>

<div class="filtros">
  Periodo: <span>{{ $fechaInicio }}</span>
  al <span>{{ $fechaFin }}</span>
  &nbsp;|&nbsp;
  Generado: <span>{{ now()->format('d/m/Y H:i') }}</span>
  &nbsp;|&nbsp;
  Total servidores: <span>{{ count($consolidado) }}</span>
</div>

<table class="data">
  <thead>
    <tr>
      <th width="10%">Cedula</th>
      <th width="28%">Servidor</th>
      <th width="28%">Unidad Administrativa</th>
      <th class="right" width="10%">Permisos</th>
      <th class="right" width="10%">Minutos</th>
      <th class="right" width="7%">Tiempo</th>
      <th class="right" width="7%">Dias</th>
    </tr>
  </thead>
  <tbody>
    @forelse($consolidado as $fila)
    <tr>
      <td>{{ $fila['cedula'] }}</td>
      <td>{{ mb_strtoupper($fila['nombre'], 'UTF-8') }}</td>
      <td>{{ $fila['unidad'] }}</td>
      <td class="right">{{ $fila['total_permisos'] }}</td>
      <td class="right">{{ $fila['total_minutos'] }}</td>
      <td class="right">{{ $fila['tiempo_total'] }}</td>
      <td class="right">{{ $fila['total_dias'] }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="7" style="text-align:center; color:#999; padding:15px;">
        Sin registros en el periodo seleccionado.
      </td>
    </tr>
    @endforelse
    {{-- Fila totales --}}
    @if(count($consolidado) > 0)
    <tr class="totales-row">
      <td colspan="3" style="text-align:right;">
        TOTALES:
      </td>
      <td class="right">{{ $totales['total_permisos'] }}</td>
      <td class="right">{{ $totales['total_minutos'] }}</td>
      <td class="right">—</td>
      <td class="right">{{ $totales['total_dias'] }}</td>
    </tr>
    @endif
  </tbody>
</table>

<div class="pie">
  SGTH GADPE &nbsp;|&nbsp;
  Consolidado generado: {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
