<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #000;
  }

  .copia {
    width: 100%;
    padding: 8px 12px;
  }

  .separador {
    width: 100%;
    border-top: 2px dashed #555;
    border-bottom: 2px dashed #555;
    padding: 10px 0;
    text-align: center;
    font-size: 9px;
    color: #666;
    letter-spacing: 4px;
    margin: 4px 0;
  }

  /* ── HEADER ── */
  .header-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
  }
  .logo-cell {
    width: 110px;
    border-right: 2px solid #000;
    text-align: center;
    padding: 5px;
    vertical-align: middle;
  }
  .logo-cell img {
    width: 100px;
    height: auto;
  }
  .title-cell {
    text-align: center;
    vertical-align: middle;
    padding: 6px 10px;
  }
  .inst-name {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .doc-title {
    font-size: 13px;
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 3px;
  }

  /* ── DATOS ── */
  .datos-table {
    width: 100%;
    border-collapse: collapse;
    border-left: 2px solid #000;
    border-right: 2px solid #000;
    border-bottom: 0;
  }
  .datos-table td {
    border: 1px solid #bbb;
    padding: 4px 7px;
    font-size: 11px;
  }
  .lbl {
    font-weight: bold;
    background: #efefef;
    width: 30%;
    text-transform: uppercase;
    font-size: 10px;
  }
  .val-bold   { font-weight: bold; font-size: 12px; }
  .val-italic { font-style: italic; font-size: 11px; }

  /* ── OBSERVACIÓN 120px ── */
  .obs-table {
    width: 100%;
    border-collapse: collapse;
    border-left: 2px solid #000;
    border-right: 2px solid #000;
    border-bottom: 0;
  }
  .obs-table td {
    border: 1px solid #bbb;
    height: 120px;
    padding: 6px 8px;
    vertical-align: top;
    font-size: 11px;
    font-style: italic;
    color: #333;
  }

  /* ── FIRMAS ── */
  .firmas-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
  }
  .firmas-table td {
    border: 1px solid #bbb;
    padding: 4px 5px;
    width: 25%;
    text-align: center;
    vertical-align: top;
  }
  .f-header {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    padding-bottom: 3px;
    border-bottom: 1px solid #999;
    margin-bottom: 3px;
  }
  .f-espacio { height: 32px; }
  .f-linea {
    border-top: 1px solid #666;
    padding-top: 3px;
    margin-top: 3px;
  }
  .f-nombre {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .f-cargo { font-size: 8px; color: #555; }

  .qr-cell {
    text-align: center;
    vertical-align: middle;
    padding: 5px !important;
  }
  .qr-label {
    font-size: 8px;
    color: #555;
    margin-top: 3px;
  }

  .copy-label {
    text-align: right;
    font-size: 8px;
    color: #999;
    margin-top: 3px;
  }
</style>
</head>
<body>

@php
  // ── Helpers para evitar problemas de tildes en uppercase ──
  // Solo ponemos en mayúsculas los campos sin tildes problemáticas
  // usando mb_strtoupper con locale correcto

  $tipoLabels = [
    'personal'   => 'PERSONAL',
    'oficial'    => 'OFICIAL',
    'enfermedad' => 'ENFERMEDAD',
    'calamidad'  => 'CALAMIDAD DOMESTICA',
  ];

  $tipoVal = $permiso->tipo instanceof \App\Enums\TipoPermiso
    ? $permiso->tipo->value : (string)$permiso->tipo;

  // Nombre servidor — solo apellidos/nombres sin tildes críticas
  $partes = array_filter([
    $permiso->servidor->apellido         ?? null,
    $permiso->servidor->segundo_apellido ?? null,
    $permiso->servidor->nombre           ?? null,
    $permiso->servidor->segundo_nombre   ?? null,
  ]);
  $nombreServidor = mb_strtoupper(implode(' ', $partes), 'UTF-8');

  $partesJefe = $permiso->jefe ? array_filter([
    $permiso->jefe->apellido         ?? null,
    $permiso->jefe->segundo_apellido ?? null,
    $permiso->jefe->nombre           ?? null,
    $permiso->jefe->segundo_nombre   ?? null,
  ]) : [];
  $nombreJefe = $partesJefe
    ? mb_strtoupper(implode(' ', $partesJefe), 'UTF-8')
    : '';

  // Unidad — NO poner en mayúsculas para evitar tildes rotas
  // Usar el nombre tal como viene de la BD
  $unidad = $permiso->unidadAdministrativa->nombre ?? '';

  $fechaPermiso = $permiso->fecha instanceof \Carbon\Carbon
    ? $permiso->fecha->format('Y-m-d')
    : \Carbon\Carbon::parse($permiso->fecha)->format('Y-m-d');

  $fechaCreacion = $permiso->created_at
    ? \Carbon\Carbon::parse($permiso->created_at)->format('Y-m-d H:i:s')
    : now()->format('Y-m-d H:i:s');

  $horaInicio = \Carbon\Carbon::parse($permiso->hora_inicio)->format('H:i:s');
  $horaFin    = \Carbon\Carbon::parse($permiso->hora_fin)->format('H:i:s');

  $observacion = $permiso->observacion ?: 'Sin observación';

  $folio = $permiso->folio ?? 'S/N';

  $folioNum = $permiso->folio
    ? ltrim(substr($permiso->folio, strrpos($permiso->folio, '-') + 1), '0') ?: '0'
    : '0';

  $tituloDoc = $tipoVal === 'personal'
    ? 'CONCESION DE PERMISO HASTA 4 HORAS'
    : 'CONCESION DE PERMISO — ' . ($tipoLabels[$tipoVal] ?? mb_strtoupper($tipoVal, 'UTF-8'));

  // QR — genera la imagen como base64
  $urlVerificacion = url("/api/v1/asistencia/permisos/verificar/{$folio}");
  try {
      $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
          ->size(90)
          ->margin(1)
          ->generate($urlVerificacion);
      $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
  } catch(\Exception $e) {
      $qrSrc = null;
  }

  // Logo e imágenes de fondo
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

  {{-- HEADER con imagen de fondo de la copia --}}
  <table class="header-table">
    <tr>
      <td class="logo-cell">
        <img src="{{ $logoSrc }}" alt="Logo GADPE">
      </td>
      <td background="{{ $copy['bg'] }}"
          style="text-align:center; vertical-align:middle; padding:6px 10px;">
        <div class="inst-name">
          Gobierno Autonomo Descentralizado de la Provincia de Esmeraldas
        </div>
        <div class="doc-title">{{ $tituloDoc }}</div>
      </td>
    </tr>
  </table>

  {{-- DATOS --}}
  <table class="datos-table">
    <tr>
      <td class="lbl">Departamento:</td>
      <td colspan="3">{{ $unidad }}</td>
    </tr>
    <tr>
      <td class="lbl">Servidor:</td>
      <td class="val-bold" colspan="3">{{ $nombreServidor }}</td>
    </tr>
    <tr>
      <td class="lbl">Motivo del permiso:</td>
      <td class="val-bold" colspan="3">
        {{ $tipoLabels[$tipoVal] ?? mb_strtoupper($tipoVal, 'UTF-8') }}
      </td>
    </tr>
    <tr>
      <td class="lbl">Fecha del permiso:</td>
      <td class="val-bold">{{ $fechaPermiso }}</td>
      <td class="lbl">Fecha de creacion:</td>
      <td class="val-italic">{{ $fechaCreacion }}</td>
    </tr>
    <tr>
      <td class="lbl">Hora de inicio:</td>
      <td class="val-bold">{{ $horaInicio }}</td>
      <td class="lbl">Hora de finalizacion:</td>
      <td class="val-bold">{{ $horaFin }}</td>
    </tr>
  </table>

  {{-- OBSERVACIÓN 120px --}}
  <table class="obs-table">
    <tr>
      <td>{{ $observacion }}</td>
    </tr>
  </table>

  {{-- FIRMAS --}}
  <table class="firmas-table">
    <tr>
      <td>
        <div class="f-header">f: Jefe Inmediato</div>
        <div class="f-espacio"></div>
        <div class="f-linea">
          <div class="f-nombre">
            {{ $nombreJefe ?: '_____________________' }}
          </div>
          <div class="f-cargo">JEFE INMEDIATO</div>
        </div>
      </td>
      <td>
        <div class="f-header">f: Servidor</div>
        <div class="f-espacio"></div>
        <div class="f-linea">
          <div class="f-nombre">{{ $nombreServidor }}</div>
          <div class="f-cargo">SERVIDOR</div>
        </div>
      </td>
      <td>
        <div class="f-header">f: Recibido por</div>
        <div class="f-espacio"></div>
        <div class="f-linea">
          <div class="f-nombre">PERSONAL TTHH</div>
          <div class="f-cargo">TALENTO HUMANO</div>
        </div>
      </td>
      <td class="qr-cell">
        <div class="f-header">Codigo QR</div>
        @if($qrSrc)
          <img src="{{ $qrSrc }}" width="80" height="80" alt="QR">
          <div class="qr-label">Folio: {{ $folio }}</div>
        @else
          <div style="font-size:9px; margin-top:10px; color:#999;">
            {{ $folio }}
          </div>
        @endif
      </td>
    </tr>
  </table>

  <div class="copy-label">{{ $copy['label'] }} — SGTH GADPE</div>

</div>

@if(!$loop->last)
<div class="separador">
  ✂ &nbsp;&nbsp; CORTAR AQUI &nbsp;&nbsp; ✂
</div>
@endif

@endforeach

</body>
</html>
