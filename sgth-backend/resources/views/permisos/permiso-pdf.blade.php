@php
  $servPath  = public_path('images/servidor-bg.png');
  $recepPath = public_path('images/recepcion-bg.png');
  $logoPath  = public_path('images/logo-gadpe.png');
@endphp
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
    margin: 0 18px;
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

  /* ── TABLAS PRINCIPALES con fondo por copia ── */
  .bg-servidor {
    background-image: url('{{ $servPath }}');
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
  }
  .bg-recepcion {
    background-image: url('{{ $recepPath }}');
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
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
    background-color: #ffffff;
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
    background-color: rgba(255,255,255,0.82);
  }
  .lbl {
    font-weight: bold;
    background-color: rgba(239,239,239,0.9) !important;
    width: 28%;
    font-size: 11px;
    text-transform: uppercase;
  }
  .val-bold  { font-weight: bold; font-size: 13px; text-transform: uppercase; }
  .val-italic { font-style: italic; font-size: 12px; }
  .val-upper { font-size: 12px; text-transform: uppercase; }

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
    height: 130px;
    padding: 7px 10px;
    vertical-align: top;
    font-size: 12px;
    text-transform: uppercase;
    color: #333;
    background-color: rgba(255,255,255,0.82);
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
    background-color: rgba(255,255,255,0.82);
  }
  .f-header {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    padding-bottom: 4px;
    border-bottom: 1.5px solid #777;
    margin-bottom: 4px;
  }
  .f-espacio { height: 65px; }
  .f-linea {
    border-top: 1.5px solid #333;
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
    padding: 5px !important;
  }
  .qr-label {
    font-size: 9px;
    color: #444;
    margin-top: 3px;
    font-weight: bold;
  }
  .copy-label {
    text-align: right;
    font-size: 9px;
    color: #888;
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

  $unidad = mb_strtoupper(
    $permiso->unidadAdministrativa->nombre ?? '', 'UTF-8'
  );

  $fechaPermiso = $permiso->fecha instanceof \Carbon\Carbon
    ? $permiso->fecha->format('Y-m-d')
    : \Carbon\Carbon::parse($permiso->fecha)->format('Y-m-d');

  $fechaCreacion = $permiso->created_at
    ? \Carbon\Carbon::parse($permiso->created_at)->format('Y-m-d H:i:s')
    : now()->format('Y-m-d H:i:s');

  $horaInicio = \Carbon\Carbon::parse($permiso->hora_inicio)->format('H:i:s');
  $horaFin    = \Carbon\Carbon::parse($permiso->hora_fin)->format('H:i:s');

  $observacion = mb_strtoupper(
    $permiso->observacion ?: 'SIN OBSERVACION', 'UTF-8'
  );

  $folio = $permiso->folio ?? 'S/N';

  $tituloDoc = $tipoVal === 'personal'
    ? 'CONCESION DE PERMISO HASTA 4 HORAS'
    : 'CONCESION DE PERMISO - ' .
      ($tipoLabels[$tipoVal] ?? mb_strtoupper($tipoVal, 'UTF-8'));

  // QR
  $qrSrc = null;
  try {
    $urlQr = config('app.url') .
      "/api/v1/asistencia/permisos/verificar/{$folio}";
    $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
      ->size(100)->margin(1)->generate($urlQr);
    if (!empty($qrPng)) {
      $qrSrc = 'data:image/png;base64,' . base64_encode($qrPng);
    }
  } catch(\Exception $e) {
    $qrSrc = null;
  }

  // Logo base64
  $logoSrc = 'data:image/png;base64,' .
    base64_encode(file_get_contents($logoPath));

  $copies = [
    ['bgClass' => 'bg-servidor',  'label' => 'COPIA SERVIDOR'],
    ['bgClass' => 'bg-recepcion', 'label' => 'COPIA TALENTO HUMANO'],
  ];
@endphp

@foreach($copies as $i => $copy)

{{-- HEADER --}}
<table class="header-table {{ $copy['bgClass'] }}">
  <tr>
    <td class="logo-cell">
      <img src="{{ $logoSrc }}" alt="Logo GADPE">
    </td>
    <td class="title-cell">
      <div class="inst-name">
        Gobierno Autonomo Descentralizado de la Provincia de Esmeraldas
      </div>
      <div class="doc-title">{{ $tituloDoc }}</div>
    </td>
  </tr>
</table>

{{-- DATOS --}}
<table class="datos-table {{ $copy['bgClass'] }}">
  <tr>
    <td class="lbl">DEPARTAMENTO:</td>
    <td class="val-upper" colspan="3">{{ $unidad }}</td>
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
<table class="obs-table {{ $copy['bgClass'] }}">
  <tr>
    <td>{{ $observacion }}</td>
  </tr>
</table>

{{-- FIRMAS --}}
<table class="firmas-table {{ $copy['bgClass'] }}">
  <tr>
    <td>
      <div class="f-header">F: Jefe Inmediato</div>
      <div class="f-espacio"></div>
      <div class="f-linea">
        <div class="f-nombre">
          {{ $nombreJefe ?: '__________________' }}
        </div>
        <div class="f-cargo">JEFE INMEDIATO</div>
      </div>
    </td>
    <td>
      <div class="f-header">F: Servidor</div>
      <div class="f-espacio"></div>
      <div class="f-linea">
        <div class="f-nombre">{{ $nombreServidor }}</div>
        <div class="f-cargo">SERVIDOR</div>
      </div>
    </td>
    <td>
      <div class="f-header">F: Recibido Por</div>
      <div class="f-espacio"></div>
      <div class="f-linea">
        <div class="f-nombre">PERSONAL TTHH</div>
        <div class="f-cargo">TALENTO HUMANO</div>
      </div>
    </td>
    <td class="qr-cell">
      <div class="f-header">Codigo QR</div>
      @if($qrSrc)
        <img src="{{ $qrSrc }}"
             width="95" height="95" alt="QR {{ $folio }}">
        <div class="qr-label">{{ $folio }}</div>
      @else
        <div style="font-size:11px; font-weight:bold;
                    margin-top:20px;">
          {{ $folio }}
        </div>
      @endif
    </td>
  </tr>
</table>

<div class="copy-label">{{ $copy['label'] }} — SGTH GADPE</div>

@if(!$loop->last)
<div class="separador">
  ✂ &nbsp;&nbsp;&nbsp; CORTAR AQUI &nbsp;&nbsp;&nbsp; ✂
</div>
@endif

@endforeach

</body>
</html>
