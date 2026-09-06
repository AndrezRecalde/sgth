@php
  $servPath  = public_path('images/servidor-bg.png');
  $recepPath = public_path('images/recepcion-bg.png');
  $logoPath  = public_path('images/logo-gadpe.png');

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

  // Quien imprime no siempre puede leer el motivo: un permiso por enfermedad
  // lleva un dato de salud y uno personal, un asunto privado. La decisión la
  // toma la policy y llega resuelta desde el controlador; aquí no se razona.
  // Por si alguien renderiza esta vista sin pasarla, el valor seguro es callar.
  $puedeVerObservacion = $mostrarObservacion ?? false;

  $observacion = $puedeVerObservacion
    ? mb_strtoupper($permiso->observacion ?: 'SIN OBSERVACIONES', 'UTF-8')
    : 'RESERVADO';

  $folio = $permiso->folio ?? 'S/N';

  $tituloDoc = $tipoVal === 'personal'
    ? 'CONCESIÓN DE PERMISO HASTA 4 HORAS'
    : 'CONCESIÓN DE PERMISO - ' . ($tipoLabels[$tipoVal] ?? mb_strtoupper($tipoVal, 'UTF-8'));

  // QR en formato SVG para evitar fallos si no hay Imagick
  $qrSrc = null;
  try {
    // Esta ruta apuntaba a `/api/v1/asistencia/permisos/verificar/...`, que no
    // existe: la pública es `/api/v1/permisos/verificar/...`. Todo QR impreso
    // hasta ahora daba 404. Y aunque hubiera acertado, quien escanea con el
    // celular es una persona —el guardia de la puerta, el jefe que recibe el
    // papel—, no un cliente HTTP: se manda a la página del frontend, que
    // consulta ese endpoint y muestra el resultado legible.
    $urlQr = rtrim(config('app.frontend_url'), '/') . "/verificar-permiso/{$folio}";
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
      ->size(100)->margin(1)->generate($urlQr);
    if (!empty($qrSvg)) {
      $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
    }
  } catch(\Exception $e) {
    $qrSrc = null;
  }

  // Logo base64
  $logoSrc = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;

  $copies = [
    ['bgClass' => 'bg-servidor',  'label' => 'COPIA SERVIDOR'],
    ['bgClass' => 'bg-recepcion', 'label' => 'COPIA TALENTO HUMANO'],
  ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-size: 10px;
    color: #1e293b;
    margin: 15px 25px;
    line-height: 1.2;
  }

  .text-brand { color: #15803d; }
  
  .separador {
    width: 100%;
    border-top: 1.5px dashed #94a3b8;
    padding: 10px 0;
    text-align: center;
    font-size: 9px;
    color: #64748b;
    letter-spacing: 2px;
    margin: 10px 0;
  }

  .copia {
    width: 100%;
    position: relative;
    padding: 5px 0;
    page-break-inside: avoid;
  }

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
    margin-bottom: 8px;
    border-bottom: 1.5px solid #15803d;
    padding-bottom: 8px;
  }
  .logo-cell { width: 120px; vertical-align: middle; }
  .logo-cell img { width: 100px; height: auto; }
  .title-wrap { text-align: right; vertical-align: middle; }
  .inst-name { font-size: 12px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
  .doc-title { font-size: 10px; color: #64748b; text-transform: uppercase; margin-top: 4px; letter-spacing: 1px; }

  /* ── INFO BOXES ── */
  .info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
  }
  .info-table td {
    border: 1px solid #cbd5e1;
    padding: 6px 8px;
    vertical-align: middle;
  }
  .t-lbl { font-weight: bold; background: #f8fafc; color: #475569; font-size: 9px; text-transform: uppercase; width: 22%; }
  .t-val { color: #0f172a; font-size: 11px; }
  .t-val-bold { font-weight: bold; color: #0f172a; font-size: 11px; }
  .t-val-upper { font-weight: bold; color: #0f172a; font-size: 11px; text-transform: uppercase; }

  .obs-box {
    border: 1px solid #cbd5e1;
    padding: 8px;
    height: 55px;
    font-size: 10px;
    color: #334155;
    background: #fff;
    margin-bottom: 12px;
  }
  .obs-lbl {
    font-size: 9px;
    font-weight: bold;
    color: #64748b;
    margin-bottom: 4px;
    text-transform: uppercase;
  }

  /* ── FIRMAS ── */
  .firmas-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
  .firmas-table td { width: 25%; vertical-align: top; padding: 0 4px; text-align: center; }
  
  .firma-box { border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 6px; padding: 8px; text-align: center; height: 125px; }
  .firma-line { border-top: 1px solid #64748b; margin: 0 auto; width: 85%; }
  .f-lbl { font-size: 8px; font-weight: bold; color: #475569; text-transform: uppercase; margin-bottom: 4px; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; }
  .f-name { font-size: 9px; font-weight: bold; color: #0f172a; text-transform: uppercase; margin-top: 4px; line-height: 1.1; }
  .f-cargo { font-size: 8px; color: #64748b; margin-top: 2px; }

  .qr-box { border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; padding: 8px; text-align: center; height: 125px; }
  .qr-img { border: 1px solid #e2e8f0; padding: 2px; background: #fff; border-radius: 4px; margin: 0 auto; }
  .qr-folio { font-size: 9px; color: #0f172a; margin-top: 6px; font-family: monospace; font-weight: bold; }

  .copy-label { text-align: right; font-size: 8px; color: #94a3b8; margin-top: 4px; }
</style>
</head>
<body>

@foreach($copies as $i => $copy)

<div class="copia {{ $copy['bgClass'] }}">

  {{-- ══ HEADER ══ --}}
  <table class="header-table">
    <tr>
      <td class="logo-cell">
        @if($logoSrc)
          <img src="{{ $logoSrc }}" alt="Logo GADPE">
        @else
          <div style="font-size:16px;font-weight:bold;color:#15803d;">GADPE</div>
        @endif
      </td>
      <td class="title-wrap">
        <div class="inst-name">
          Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas
        </div>
        <div class="doc-title">{{ $tituloDoc }}</div>
      </td>
    </tr>
  </table>

  {{-- ══ DATOS ══ --}}
  <table class="info-table">
    <tr>
      <td class="t-lbl">Departamento</td>
      <td class="t-val-upper" colspan="3">{{ $unidad }}</td>
    </tr>
    <tr>
      <td class="t-lbl">Servidor</td>
      <td class="t-val-bold" colspan="3">{{ $nombreServidor }}</td>
    </tr>
    <tr>
      <td class="t-lbl">Motivo del Permiso</td>
      <td class="t-val-bold" colspan="3">{{ $tipoLabels[$tipoVal] ?? mb_strtoupper($tipoVal, 'UTF-8') }}</td>
    </tr>
    <tr>
      <td class="t-lbl">Fecha del Permiso</td>
      <td class="t-val-bold" width="30%">{{ $fechaPermiso }}</td>
      <td class="t-lbl" width="22%">Fecha de Creación</td>
      <td class="t-val-bold">{{ $fechaCreacion }}</td>
    </tr>
    <tr>
      <td class="t-lbl" style="color: #15803d;">Hora de Inicio</td>
      <td class="t-val-bold" style="color: #15803d;">{{ $horaInicio }}</td>
      <td class="t-lbl" style="color: #15803d;">Hora de Fin</td>
      <td class="t-val-bold" style="color: #15803d;">{{ $horaFin }}</td>
    </tr>
  </table>

  {{-- ══ OBSERVACIÓN ══ --}}
  <div class="obs-box">
    <div class="obs-lbl">Observaciones</div>
    <div>{{ $observacion }}</div>
  </div>

  {{-- ══ FIRMAS ══ --}}
  <table class="firmas-table">
    <tr>
      <td>
        <div class="firma-box">
          <div class="f-lbl">Firma: Jefe Inmediato</div>
          <div style="height: 55px;"></div>
          <div class="firma-line"></div>
          <div class="f-name">{{ $nombreJefe ?: '__________________' }}</div>
          <div class="f-cargo">JEFE INMEDIATO</div>
        </div>
      </td>
      <td>
        <div class="firma-box">
          <div class="f-lbl">Firma: Servidor</div>
          <div style="height: 55px;"></div>
          <div class="firma-line"></div>
          <div class="f-name">{{ $nombreServidor }}</div>
          <div class="f-cargo">SERVIDOR</div>
        </div>
      </td>
      <td>
        <div class="firma-box">
          <div class="f-lbl">Talento Humano</div>
          <div style="height: 55px;"></div>
          <div class="firma-line"></div>
          <div class="f-name">PERSONAL TTHH</div>
          <div class="f-cargo">RECIBIDO POR</div>
        </div>
      </td>
      <td>
        <div class="qr-box">
          <div class="f-lbl" style="border:none;">Código QR</div>
          @if($qrSrc)
            <img src="{{ $qrSrc }}" width="70" height="70" class="qr-img" alt="QR {{ $folio }}">
            <div class="qr-folio">{{ $folio }}</div>
          @else
            <div style="font-size:10px; font-weight:bold; margin-top:20px;">{{ $folio }}</div>
          @endif
        </div>
      </td>
    </tr>
  </table>

  <div class="copy-label">{{ $copy['label'] }} — SGTH GADPE</div>

</div>

@if(!$loop->last)
<div class="separador">
  ✂ &nbsp;&nbsp;&nbsp; CORTAR AQUÍ &nbsp;&nbsp;&nbsp; ✂
</div>
@endif

@endforeach

</body>
</html>
