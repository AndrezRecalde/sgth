<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, sans-serif;
    font-size: 10px;
    color: #000;
  }

  .copia {
    width: 100%;
    padding: 8px 10px;
  }

  .separador {
    border-top: 2px dashed #666;
    margin: 4px 0;
    font-size: 7px;
    color: #666;
    text-align: center;
    letter-spacing: 3px;
    padding: 1px 0;
  }

  /* ── HEADER ── */
  .header-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
    margin-bottom: 0;
  }
  .header-table td {
    padding: 4px 8px;
    vertical-align: middle;
  }
  .header-logo-cell {
    width: 80px;
    border-right: 1.5px solid #000;
    text-align: center;
  }
  .header-logo-text {
    font-size: 14px;
    font-weight: bold;
    color: #2d6a2d;
    letter-spacing: -1px;
  }
  .header-logo-sub {
    font-size: 6px;
    color: #2d6a2d;
  }
  .header-title-cell {
    text-align: center;
  }
  .header-inst {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .header-doc {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 2px;
  }

  /* ── TABLA DATOS ── */
  .datos-table {
    width: 100%;
    border-collapse: collapse;
    border-left: 1.5px solid #000;
    border-right: 1.5px solid #000;
    border-bottom: 1.5px solid #000;
  }
  .datos-table td {
    border: 1px solid #ccc;
    padding: 3px 6px;
    font-size: 9.5px;
  }
  .datos-table .label {
    font-weight: bold;
    background: #f5f5f5;
    width: 28%;
    text-transform: uppercase;
    font-size: 9px;
  }
  .datos-table .value {
    font-weight: normal;
  }
  .datos-table .value-bold {
    font-weight: bold;
    font-size: 10px;
  }

  /* ── FILA FECHA/HORA ── */
  .row-4col td {
    width: 25%;
  }

  /* ── OBSERVACION ── */
  .obs-cell {
    height: 45px;
    font-style: italic;
    color: #444;
    position: relative;
    vertical-align: top;
  }
  .watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-15deg);
    font-size: 22px;
    font-weight: bold;
    color: rgba(0,0,0,0.06);
    text-transform: uppercase;
    letter-spacing: 3px;
    pointer-events: none;
    white-space: nowrap;
  }

  /* ── FIRMAS ── */
  .firmas-table {
    width: 100%;
    border-collapse: collapse;
    border-left: 1.5px solid #000;
    border-right: 1.5px solid #000;
    border-bottom: 1.5px solid #000;
  }
  .firmas-table td {
    border: 1px solid #ccc;
    padding: 3px 5px;
    width: 25%;
    text-align: center;
    vertical-align: top;
  }
  .firma-header {
    font-size: 8px;
    font-weight: bold;
    text-transform: uppercase;
    border-bottom: 1px solid #ccc;
    padding-bottom: 2px;
    margin-bottom: 2px;
  }
  .firma-espacio {
    height: 30px;
  }
  .firma-nombre {
    font-size: 8px;
    font-weight: bold;
    text-transform: uppercase;
    border-top: 1px solid #999;
    padding-top: 2px;
    margin-top: 2px;
  }
  .firma-cargo {
    font-size: 7px;
    color: #555;
  }

  /* ── CÓDIGO DE BARRAS ── */
  .barcode-cell {
    vertical-align: middle;
  }
  .barcode-num {
    font-size: 9px;
    font-weight: bold;
    margin-top: 3px;
  }
  .barcode-lines {
    font-size: 28px;
    letter-spacing: -3px;
    line-height: 1;
    color: #000;
    font-family: 'Courier New', monospace;
  }
</style>
</head>
<body>

@php
  $tipoLabels = [
    'personal'   => 'PERSONAL',
    'oficial'    => 'OFICIAL',
    'enfermedad' => 'ENFERMEDAD',
    'calamidad'  => 'CALAMIDAD DOMÉSTICA',
  ];
  $tipoVal = $permiso->tipo instanceof \App\Enums\TipoPermiso
    ? $permiso->tipo->value : (string)$permiso->tipo;

  $estadoVal = $permiso->estado instanceof \App\Enums\EstadoPermiso
    ? $permiso->estado->value : (string)$permiso->estado;

  $nombreServidor = strtoupper(implode(' ', array_filter([
    $permiso->servidor->apellido          ?? null,
    $permiso->servidor->segundo_apellido  ?? null,
    $permiso->servidor->nombre            ?? null,
    $permiso->servidor->segundo_nombre    ?? null,
  ])));

  $nombreJefe = $permiso->jefe
    ? strtoupper(implode(' ', array_filter([
        $permiso->jefe->apellido          ?? null,
        $permiso->jefe->segundo_apellido  ?? null,
        $permiso->jefe->nombre            ?? null,
        $permiso->jefe->segundo_nombre    ?? null,
      ])))
    : '';

  $unidad = strtoupper($permiso->unidadAdministrativa->nombre ?? '');

  $fechaPermiso = $permiso->fecha instanceof \Carbon\Carbon
    ? $permiso->fecha->format('Y-m-d')
    : \Carbon\Carbon::parse($permiso->fecha)->format('Y-m-d');

  $fechaCreacion = $permiso->created_at
    ? \Carbon\Carbon::parse($permiso->created_at)->format('Y-m-d H:i:s')
    : now()->format('Y-m-d H:i:s');

  $horaInicio = \Carbon\Carbon::parse($permiso->hora_inicio)->format('H:i:s');
  $horaFin    = \Carbon\Carbon::parse($permiso->hora_fin)->format('H:i:s');

  $observacion = $permiso->observacion
    ? strtoupper($permiso->observacion)
    : 'SIN OBSERVACIÓN';

  // Folio numérico para código de barras
  $folioNum = $permiso->folio
    ? ltrim(substr($permiso->folio, strrpos($permiso->folio, '-') + 1), '0') ?: '0'
    : '0';

  $copies = [
    ['watermark' => 'SERVIR'],
    ['watermark' => 'RECIBÍ'],
  ];

  // Título según tipo
  $tituloDoc = $tipoVal === 'personal'
    ? 'CONCESIÓN DE PERMISO HASTA 4 HORAS'
    : 'CONCESIÓN DE PERMISO — ' . ($tipoLabels[$tipoVal] ?? strtoupper($tipoVal));
@endphp

@foreach($copies as $copy)

<div class="copia">

  {{-- HEADER --}}
  <table class="header-table">
    <tr>
      <td class="header-logo-cell">
        <div class="header-logo-text">Esmeraldas</div>
        <div class="header-logo-sub">PREFECTURA</div>
        <div class="header-logo-sub">¡Los buenos somos más!</div>
      </td>
      <td class="header-title-cell">
        <div class="header-inst">
          Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas
        </div>
        <div class="header-doc">{{ $tituloDoc }}</div>
      </td>
    </tr>
  </table>

  {{-- DATOS --}}
  <table class="datos-table">
    <tr>
      <td class="label">Departamento:</td>
      <td class="value" colspan="3">{{ $unidad }} DEL GADPE</td>
    </tr>
    <tr>
      <td class="label">Servidor:</td>
      <td class="value-bold" colspan="3">{{ $nombreServidor }}</td>
    </tr>
    <tr>
      <td class="label">Motivo del permiso:</td>
      <td class="value-bold" colspan="3">{{ $tipoLabels[$tipoVal] ?? strtoupper($tipoVal) }}</td>
    </tr>
    <tr class="row-4col">
      <td class="label">Fecha del permiso:</td>
      <td class="value-bold">{{ $fechaPermiso }}</td>
      <td class="label">Fecha de creación:</td>
      <td class="value" style="font-style:italic;">{{ $fechaCreacion }}</td>
    </tr>
    <tr class="row-4col">
      <td class="label">Hora de inicio:</td>
      <td class="value-bold">{{ $horaInicio }}</td>
      <td class="label">Hora de finalización:</td>
      <td class="value-bold">{{ $horaFin }}</td>
    </tr>
    <tr>
      <td colspan="4" class="obs-cell" style="position:relative; padding:5px 8px;">
        {{ $observacion }}
        <div class="watermark">{{ $copy['watermark'] }}</div>
      </td>
    </tr>
  </table>

  {{-- FIRMAS --}}
  <table class="firmas-table">
    <tr>
      <td>
        <div class="firma-header">ƒ: Jefe Inmediato</div>
        <div class="firma-espacio"></div>
        <div class="firma-nombre">{{ $nombreJefe ?: '____________________' }}</div>
        <div class="firma-cargo">JEFE INMEDIATO</div>
      </td>
      <td>
        <div class="firma-header">ƒ: Servidor</div>
        <div class="firma-espacio"></div>
        <div class="firma-nombre">{{ $nombreServidor }}</div>
        <div class="firma-cargo">SERVIDOR</div>
      </td>
      <td>
        <div class="firma-header">ƒ: Recibido por</div>
        <div class="firma-espacio"></div>
        <div class="firma-nombre">PERSONAL TTHH</div>
        <div class="firma-cargo">TALENTO HUMANO</div>
      </td>
      <td class="barcode-cell">
        <div class="firma-header">Código de Barra</div>
        <div class="barcode-lines">||| |||| ||| ||</div>
        <div class="barcode-num">Nro. {{ $folioNum }}</div>
      </td>
    </tr>
  </table>

</div>

@if(!$loop->last)
<div class="separador">✂ &nbsp;&nbsp; CORTAR AQUÍ &nbsp;&nbsp; ✂</div>
@endif

@endforeach

</body>
</html>
