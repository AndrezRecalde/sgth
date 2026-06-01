<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, sans-serif;
    font-size: 13px;
    color: #000;
  }

  .copia {
    width: 100%;
    padding: 10px 14px;
  }

  .separador {
    width: 100%;
    border-top: 2px dashed #555;
    border-bottom: 2px dashed #555;
    padding: 12px 0;
    text-align: center;
    font-size: 10px;
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
    width: 130px;
    border-right: 2px solid #000;
    text-align: center;
    padding: 6px;
    vertical-align: middle;
    background: #fff;
  }
  .logo-cell img {
    width: 118px;
    height: auto;
  }
  .title-cell {
    text-align: center;
    vertical-align: middle;
    padding: 8px 12px;
  }
  .inst-name {
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .doc-title {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 4px;
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
    padding: 5px 8px;
    font-size: 12px;
  }
  .lbl {
    font-weight: bold;
    background: #efefef;
    width: 30%;
    font-size: 11px;
  }
  .val-bold   { font-weight: bold; font-size: 13px; }
  .val-italic { font-style: italic; font-size: 12px; }

  /* ── OBSERVACIÓN ── */
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
    padding: 7px 10px;
    vertical-align: top;
    font-size: 12px;
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
    padding: 5px 6px;
    width: 25%;
    text-align: center;
    vertical-align: top;
  }
  .f-header {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    padding-bottom: 4px;
    border-bottom: 1px solid #999;
    margin-bottom: 4px;
  }
  .f-espacio { height: 50px; }
  .f-linea {
    border-top: 1.5px solid #444;
    padding-top: 4px;
    margin-top: 4px;
  }
  .f-nombre {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .f-cargo { font-size: 9px; color: #555; margin-top: 2px; }

  .qr-cell {
    text-align: center;
    vertical-align: middle;
    padding: 6px !important;
  }
  .qr-label {
    font-size: 9px;
    color: #555;
    margin-top: 4px;
    font-weight: bold;
  }

  .copy-label {
    text-align: right;
    font-size: 9px;
    color: #999;
    margin-top: 4px;
  }
</style>
</head>
<body>

@php
  $tipoLabels = [
    'personal'   => 'PERSONAL',
    'oficial'    => 'OFICIAL',
    'enfermedad' => 'ENFERMEDAD',
    'calamidad'  => 'CALAMIDAD DOMESTICA',
  ];

  $tipoVal = $permiso->tipo instanceof \App\Enums\TipoPermiso
    ? $permiso->tipo->value : (string)$permiso->tipo;

  $nombreServidor = mb_strtoupper(implode(' ', array_filter([
    $permiso->servidor->apellido         ?? null,
    $permiso->servidor->segundo_apellido ?? null,
    $permiso->servidor->nombre           ?? null,
    $permiso->servidor->segundo_nombre   ?? null,
  ])), 'UTF-8');

  $nombreJefe = $permiso->jefe
    ? mb_strtoupper(implode(' ', array_filter([
        $permiso->jefe->apellido         ?? null,
        $permiso->jefe->segundo_apellido ?? null,
        $permiso->jefe->nombre           ?? null,
        $permiso->jefe->segundo_nombre   ?? null,
      ])), 'UTF-8')
    : '';

  $unidad = $permiso->unidadAdministrativa->nombre ?? '';

  $fechaPermiso = $permiso->fecha instanceof \Carbon\Carbon
    ? $permiso->fecha->format('Y-m-d')
    : \Carbon\Carbon::parse($permiso->fecha)->format('Y-m-d');

  $fechaCreacion = $permiso->created_at
    ? \Carbon\Carbon::parse($permiso->created_at)->format('Y-m-d H:i:s')
    : now()->format('Y-m-d H:i:s');

  $horaInicio = \Carbon\Carbon::parse($permiso->hora_inicio)->format('H:i:s');
  $horaFin    = \Carbon\Carbon::parse($permiso->hora_fin)->format('H:i:s');

  $observacion = $permiso->observacion ?: 'Sin observacion';

  $folio = $permiso->folio ?? 'S/N';

  $tituloDoc = $tipoVal === 'personal'
    ? 'CONCESION DE PERMISO HASTA 4 HORAS'
    : 'CONCESION DE PERMISO - ' . ($tipoLabels[$tipoVal] ?? mb_strtoupper($tipoVal, 'UTF-8'));

  // QR
  $urlVerificacion = url("/api/v1/asistencia/permisos/verificar/{$folio}");
  try {
      $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
          ->size(100)->margin(1)->generate($urlVerificacion);
      $qrSrc = 'data:image/png;base64,' . base64_encode($qrPng);
  } catch(\Exception $e) {
      $qrSrc = null;
  }

  // Imágenes base64
  $logoSrc = 'data:image/png;base64,' .
      base64_encode(file_get_contents(public_path('images/logo-gadpe.png')));

  // Para los fondos usamos la ruta absoluta del servidor
  // DomPDF necesita isRemoteEnabled o rutas con file://
  $servPath  = 'file:///' . str_replace('\\', '/', public_path('images/servidor-bg.png'));
  $recepPath = 'file:///' . str_replace('\\', '/', public_path('images/recepcion-bg.png'));

  $copies = [
    ['bgPath' => $servPath,  'label' => 'COPIA SERVIDOR'],
    ['bgPath' => $recepPath, 'label' => 'COPIA TALENTO HUMANO'],
  ];
@endphp

@foreach($copies as $copy)

<div class="copia">

  {{-- HEADER: fondo imagen en la celda del título --}}
  <table class="header-table">
    <tr>
      <td class="logo-cell">
        <img src="{{ $logoSrc }}" alt="Logo GADPE">
      </td>
      <td background="{{ $copy['bgPath'] }}"
          style="text-align:center;
                 vertical-align:middle;
                 padding:8px 12px;">
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
      <td class="lbl">DEPARTAMENTO:</td>
      <td colspan="3">{{ $unidad }}</td>
    </tr>
    <tr>
      <td class="lbl">SERVIDOR:</td>
      <td class="val-bold" colspan="3">{{ $nombreServidor }}</td>
    </tr>
    <tr>
      <td class="lbl">MOTIVO DEL PERMISO:</td>
      <td class="val-bold" colspan="3">
        {{ $tipoLabels[$tipoVal] ?? mb_strtoupper($tipoVal, 'UTF-8') }}
      </td>
    </tr>
    <tr>
      <td class="lbl">FECHA DEL PERMISO:</td>
      <td class="val-bold">{{ $fechaPermiso }}</td>
      <td class="lbl">FECHA DE CREACION:</td>
      <td class="val-italic">{{ $fechaCreacion }}</td>
    </tr>
    <tr>
      <td class="lbl">HORA DE INICIO:</td>
      <td class="val-bold">{{ $horaInicio }}</td>
      <td class="lbl">HORA DE FINALIZACION:</td>
      <td class="val-bold">{{ $horaFin }}</td>
    </tr>
  </table>

  {{-- OBSERVACIÓN --}}
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
            {{ $nombreJefe ?: '__________________' }}
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
          <img src="{{ $qrSrc }}" width="90" height="90" alt="QR">
          <div class="qr-label">{{ $folio }}</div>
        @else
          <div style="font-size:10px; margin-top:15px;">
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
  ✂ &nbsp;&nbsp;&nbsp; CORTAR AQUI &nbsp;&nbsp;&nbsp; ✂
</div>
@endif

@endforeach

</body>
</html>
