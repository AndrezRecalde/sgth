<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, sans-serif;
    font-size: 9.5px;
    color: #000;
  }

  /* ══ COPIA: ocupa exactamente 40% de la hoja ══ */
  .copia {
    width: 100%;
    height: 380px;
    padding: 6px 10px;
    overflow: hidden;
  }

  /* ══ SEPARADOR: 20% de la hoja ══ */
  .separador {
    width: 100%;
    height: 76px;
    display: table;
    border-top: 1.5px dashed #555;
    border-bottom: 1.5px dashed #555;
  }
  .separador-inner {
    display: table-cell;
    vertical-align: middle;
    text-align: center;
    font-size: 8px;
    color: #777;
    letter-spacing: 3px;
  }

  /* ══ HEADER ══ */
  .header-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
  }
  .logo-cell {
    width: 90px;
    border-right: 1.5px solid #000;
    text-align: center;
    padding: 3px;
    vertical-align: middle;
  }
  .logo-cell img {
    width: 82px;
    height: auto;
  }
  .title-cell {
    text-align: center;
    vertical-align: middle;
    padding: 4px 8px;
  }
  .inst-name {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .doc-title {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 2px;
  }

  /* ══ DATOS ══ */
  .datos-table {
    width: 100%;
    border-collapse: collapse;
    border-left: 1.5px solid #000;
    border-right: 1.5px solid #000;
    border-bottom: 0;
  }
  .datos-table td {
    border: 1px solid #ccc;
    padding: 2.5px 5px;
    font-size: 9px;
  }
  .lbl {
    font-weight: bold;
    background: #f0f0f0;
    width: 30%;
    text-transform: uppercase;
    font-size: 8.5px;
  }
  .val-bold { font-weight: bold; font-size: 10px; }
  .val-italic { font-style: italic; }

  /* ══ OBSERVACIÓN con fondo imagen ══ */
  .obs-row {
    position: relative;
    height: 55px;
    border: 1px solid #ccc;
    border-left: 1.5px solid #000;
    border-right: 1.5px solid #000;
    overflow: hidden;
  }
  .obs-bg {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    opacity: 0.08;
    object-fit: cover;
  }
  .obs-text {
    position: relative;
    padding: 5px 8px;
    font-size: 9px;
    font-style: italic;
    color: #333;
  }

  /* ══ FIRMAS ══ */
  .firmas-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
  }
  .firmas-table td {
    border: 1px solid #ccc;
    padding: 3px 4px;
    width: 25%;
    text-align: center;
    vertical-align: top;
  }
  .f-header {
    font-size: 8px;
    font-weight: bold;
    text-transform: uppercase;
    padding-bottom: 2px;
    border-bottom: 1px solid #bbb;
    margin-bottom: 2px;
  }
  .f-espacio { height: 28px; }
  .f-linea {
    border-top: 1px solid #666;
    padding-top: 2px;
    margin-top: 2px;
  }
  .f-nombre {
    font-size: 7.5px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .f-cargo {
    font-size: 7px;
    color: #555;
  }
  .barcode-lines {
    font-size: 26px;
    letter-spacing: -4px;
    line-height: 1;
    color: #000;
    font-family: 'Courier New', monospace;
  }
  .barcode-num {
    font-size: 8.5px;
    font-weight: bold;
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

  $nombreServidor = strtoupper(implode(' ', array_filter([
    $permiso->servidor->apellido         ?? null,
    $permiso->servidor->segundo_apellido ?? null,
    $permiso->servidor->nombre           ?? null,
    $permiso->servidor->segundo_nombre   ?? null,
  ])));

  $nombreJefe = $permiso->jefe
    ? strtoupper(implode(' ', array_filter([
        $permiso->jefe->apellido         ?? null,
        $permiso->jefe->segundo_apellido ?? null,
        $permiso->jefe->nombre           ?? null,
        $permiso->jefe->segundo_nombre   ?? null,
      ])))
    : '';

  $unidad = strtoupper(
    $permiso->unidadAdministrativa->nombre ?? ''
  );

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

  $folioNum = $permiso->folio
    ? ltrim(substr($permiso->folio, strrpos($permiso->folio, '-') + 1), '0') ?: '0'
    : '0';

  $tituloDoc = $tipoVal === 'personal'
    ? 'CONCESIÓN DE PERMISO HASTA 4 HORAS'
    : 'CONCESIÓN DE PERMISO — ' . ($tipoLabels[$tipoVal] ?? strtoupper($tipoVal));

  // Base64 de imágenes
  $logoB64  = base64_encode(file_get_contents(public_path('images/logo-gadpe.png')));
  $recepB64 = base64_encode(file_get_contents(public_path('images/recepcion-bg.png')));
  $servB64  = base64_encode(file_get_contents(public_path('images/servidor-bg.png')));

  $logoSrc  = 'data:image/png;base64,' . $logoB64;
  $recepSrc = 'data:image/png;base64,' . $recepB64;
  $servSrc  = 'data:image/png;base64,' . $servB64;

  $copies = [
    ['bg' => $servSrc,  'label' => 'COPIA SERVIDOR'],
    ['bg' => $recepSrc, 'label' => 'COPIA TALENTO HUMANO'],
  ];
@endphp

@foreach($copies as $copy)

<div class="copia">

  {{-- HEADER --}}
  <table class="header-table">
    <tr>
      <td class="logo-cell">
        <img src="{{ $logoSrc }}" alt="Logo GADPE">
      </td>
      <td class="title-cell">
        <div class="inst-name">
          Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas
        </div>
        <div class="doc-title">{{ $tituloDoc }}</div>
      </td>
    </tr>
  </table>

  {{-- DATOS --}}
  <table class="datos-table">
    <tr>
      <td class="lbl">Departamento:</td>
      <td colspan="3">{{ $unidad }} DEL GADPE</td>
    </tr>
    <tr>
      <td class="lbl">Servidor:</td>
      <td class="val-bold" colspan="3">{{ $nombreServidor }}</td>
    </tr>
    <tr>
      <td class="lbl">Motivo del permiso:</td>
      <td class="val-bold" colspan="3">
        {{ $tipoLabels[$tipoVal] ?? strtoupper($tipoVal) }}
      </td>
    </tr>
    <tr>
      <td class="lbl">Fecha del permiso:</td>
      <td class="val-bold">{{ $fechaPermiso }}</td>
      <td class="lbl">Fecha de creación:</td>
      <td class="val-italic">{{ $fechaCreacion }}</td>
    </tr>
    <tr>
      <td class="lbl">Hora de inicio:</td>
      <td class="val-bold">{{ $horaInicio }}</td>
      <td class="lbl">Hora de finalización:</td>
      <td class="val-bold">{{ $horaFin }}</td>
    </tr>
  </table>

  {{-- OBSERVACIÓN con fondo imagen --}}
  <div class="obs-row">
    <img class="obs-bg" src="{{ $copy['bg'] }}" alt="">
    <div class="obs-text">{{ $observacion }}</div>
  </div>

  {{-- LÍNEA INFERIOR DATOS --}}
  <div style="border-left:1.5px solid #000;border-right:1.5px solid #000;height:0;"></div>

  {{-- FIRMAS --}}
  <table class="firmas-table">
    <tr>
      <td>
        <div class="f-header">ƒ: Jefe Inmediato</div>
        <div class="f-espacio"></div>
        <div class="f-linea">
          <div class="f-nombre">
            {{ $nombreJefe ?: '____________________' }}
          </div>
          <div class="f-cargo">JEFE INMEDIATO</div>
        </div>
      </td>
      <td>
        <div class="f-header">ƒ: Servidor</div>
        <div class="f-espacio"></div>
        <div class="f-linea">
          <div class="f-nombre">{{ $nombreServidor }}</div>
          <div class="f-cargo">SERVIDOR</div>
        </div>
      </td>
      <td>
        <div class="f-header">ƒ: Recibido por</div>
        <div class="f-espacio"></div>
        <div class="f-linea">
          <div class="f-nombre">PERSONAL TTHH</div>
          <div class="f-cargo">TALENTO HUMANO</div>
        </div>
      </td>
      <td>
        <div class="f-header">Código de Barra</div>
        <div class="barcode-lines">||| |||| ||| ||</div>
        <div class="barcode-num">Nro. {{ $folioNum }}</div>
      </td>
    </tr>
  </table>

  <div style="text-align:right; font-size:7px; color:#aaa; margin-top:2px;">
    {{ $copy['label'] }} — SGTH GADPE
  </div>

</div>

@if(!$loop->last)
<div class="separador">
  <div class="separador-inner">
    ✂ &nbsp;&nbsp;&nbsp; CORTAR AQUÍ &nbsp;&nbsp;&nbsp; ✂
  </div>
</div>
@endif

@endforeach

</body>
</html>
