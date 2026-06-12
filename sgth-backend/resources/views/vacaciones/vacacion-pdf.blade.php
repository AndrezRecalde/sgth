@php
  $logoPath = public_path('images/logo-gadpe.png');
  $logoSrc  = file_exists($logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
    : null;

  // Grid de motivos 3 columnas
  $motivosGrid = [
    ['vacaciones_anuales', 'permiso_cargo_vacaciones', 'licencia_sin_goce'],
    ['matrimonio',         'capacitacion',             'enfermedad'],
    ['paternidad',         'maternidad',               'estudios_sin_remuneracion'],
    ['calamidad_domestica','licencia_con_goce',        null],
  ];

  $motivoLabels = [
    'vacaciones_anuales'        => 'VACACIONES ANUALES (MAYOR 5 DIAS)',
    'permiso_cargo_vacaciones'  => 'PERMISO C/ CARGO A VACACIONES (MAX 5 DIAS)',
    'licencia_sin_goce'         => 'LICENCIA SIN GOCE DE HABERES',
    'matrimonio'                => 'MATRIMONIO',
    'capacitacion'              => 'CAPACITACION Y/O ADIESTRAMIENTO',
    'enfermedad'                => 'ENFERMEDAD',
    'maternidad'                => 'MATERNIDAD',
    'paternidad'                => 'PATERNIDAD',
    'estudios_sin_remuneracion' => 'ESTUDIOS SIN REMUNERACION',
    'calamidad_domestica'       => 'CALAMIDAD DOMESTICA',
    'licencia_con_goce'         => 'LICENCIA CON GOCE DE SUELDO',
  ];

  $motivoVal = $vacacion->motivo instanceof \App\Enums\MotivoVacacion
    ? $vacacion->motivo->value
    : (string)($vacacion->motivo ?? '');

  $nombreServidor = mb_strtoupper(implode(' ', array_filter([
    $vacacion->servidor->apellido         ?? null,
    $vacacion->servidor->segundo_apellido ?? null,
    $vacacion->servidor->nombre           ?? null,
    $vacacion->servidor->segundo_nombre   ?? null,
  ])), 'UTF-8');

  $nombreJefe = $vacacion->jefe
    ? mb_strtoupper(implode(' ', array_filter([
        $vacacion->jefe->apellido         ?? null,
        $vacacion->jefe->segundo_apellido ?? null,
        $vacacion->jefe->nombre           ?? null,
        $vacacion->jefe->segundo_nombre   ?? null,
      ])), 'UTF-8')
    : '';

  $nombreReemplaza = $vacacion->personaReemplaza
    ? mb_strtoupper(implode(' ', array_filter([
        $vacacion->personaReemplaza->apellido ?? null,
        $vacacion->personaReemplaza->nombre   ?? null,
      ])), 'UTF-8')
    : ($vacacion->persona_reemplaza ?? '');

  $folio = $vacacion->folio ?? 'S/N';

  $fechaInicio  = $vacacion->fecha_inicio
    ? \Carbon\Carbon::parse($vacacion->fecha_inicio)->format('d/m/Y') : '';
  $fechaFin     = $vacacion->fecha_fin
    ? \Carbon\Carbon::parse($vacacion->fecha_fin)->format('d/m/Y') : '';
  $fechaRetorno = $vacacion->fecha_retorno
    ? \Carbon\Carbon::parse($vacacion->fecha_retorno)->format('d/m/Y') : '';
  $fechaEmision = $vacacion->fecha_emision
    ? \Carbon\Carbon::parse($vacacion->fecha_emision)->format('d/m/Y')
    : now()->format('d/m/Y');

  $fechaIngreso = null;
  if ($vacacion->fecha_ingreso_institucion_informe) {
    $fechaIngreso = \Carbon\Carbon::parse(
      $vacacion->fecha_ingreso_institucion_informe
    )->format('d/m/Y');
  } elseif ($vacacion->servidor->fecha_ingreso_institucion ?? null) {
    $fechaIngreso = \Carbon\Carbon::parse(
      $vacacion->servidor->fecha_ingreso_institucion
    )->format('d/m/Y');
  }

  $diasDerecho = $vacacion->dias_derecho ?? '';
  $periodo     = $vacacion->periodo_vacaciones ?? '';
  $observacion = mb_strtoupper($vacacion->observacion ?? '', 'UTF-8');

  // QR en formato SVG para evitar fallos si no hay Imagick
  $qrSrc = null;
  try {
    $urlQr = config('app.url') .
      "/api/v1/asistencia/vacaciones/verificar/{$folio}";
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
        ->size(90)->margin(1)->generate($urlQr);
    if (!empty($qrSvg)) {
        $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
    }
  } catch(\Exception $e) {
    $qrSrc = null;
  }

  $tipoDiasLabel = ($vacacion->tipo_dias === 'habiles')
    ? 'días hábiles' : 'días calendario';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Helvetica', 'Arial', sans-serif;
    font-size: 11px;
    color: #334155;
    margin: 30px;
    line-height: 1.4;
  }

  /* ── BRANDING ── */
  .text-brand { color: #1a5c38; }
  .bg-brand { background-color: #1a5c38; color: #fff; }
  
  /* ── HEADER ── */
  .header-table {
    width: 100%;
    margin-bottom: 25px;
    border-bottom: 2px solid #1a5c38;
    padding-bottom: 15px;
  }
  .logo-cell {
    width: 140px;
    vertical-align: middle;
  }
  .logo-cell img { width: 120px; height: auto; }
  .title-wrap {
    text-align: right;
    vertical-align: middle;
  }
  .inst-name {
    font-size: 14px;
    font-weight: bold;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .doc-title {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    margin-top: 6px;
    letter-spacing: 1px;
  }

  /* ── SECTIONS ── */
  .section-title {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    color: #1a5c38;
    margin-bottom: 8px;
    margin-top: 15px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 4px;
  }

  /* ── GENERAL TABLES ── */
  .t {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
  }
  .t td {
    padding: 8px 10px;
    vertical-align: middle;
    border: 1px solid #e2e8f0;
  }
  .t .lbl {
    font-weight: bold;
    background: #f8fafc;
    color: #475569;
    font-size: 10px;
    text-transform: uppercase;
  }
  .t .val-bold {
    font-weight: bold;
    color: #0f172a;
    font-size: 12px;
  }

  /* ── MOTIVOS ── */
  .motivos-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
  }
  .motivos-table td {
    padding: 6px 4px;
    font-size: 10px;
    vertical-align: middle;
    width: 33.33%;
    color: #475569;
  }
  .motivo-sel {
    color: #1a5c38;
    font-weight: bold;
  }
  .check-icon {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 1px solid #94a3b8;
    border-radius: 3px;
    margin-right: 6px;
    vertical-align: middle;
    text-align: center;
    line-height: 12px;
    font-size: 9px;
  }
  .check-icon.active {
    background: #1a5c38;
    border-color: #1a5c38;
    color: #fff;
  }

  /* ── FIRMAS ── */
  .firma-grid {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    page-break-inside: avoid;
  }
  .firma-grid td {
    width: 50%;
    vertical-align: top;
    padding: 0 8px;
  }
  .firma-grid td:first-child { padding-left: 0; }
  .firma-grid td:last-child { padding-right: 0; }
  .firma-box {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 15px;
    height: 120px;
    text-align: center;
    background: #f8fafc;
  }
  .firma-line {
    border-top: 1px solid #94a3b8;
    margin: 45px auto 8px auto;
    width: 85%;
  }
  .f-lbl {
    font-size: 10px;
    font-weight: bold;
    color: #475569;
    text-transform: uppercase;
  }
  .f-name {
    font-size: 11px;
    font-weight: bold;
    color: #0f172a;
    text-transform: uppercase;
  }
  .aceptado-box {
    text-align: center;
    margin-top: 10px;
    font-size: 10px;
  }
  .badge {
    display: inline-block;
    padding: 4px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    margin: 0 5px;
    color: #64748b;
    background: #fff;
  }

  /* ── PIE ── */
  .pie {
    position: fixed;
    bottom: -15px;
    left: 30px;
    right: 30px;
    text-align: right;
    font-size: 9px;
    color: #94a3b8;
    border-top: 1px solid #e2e8f0;
    padding-top: 8px;
  }
  
  .qr-box {
    text-align: center;
  }
  .qr-box img {
    border: 1px solid #e2e8f0;
    padding: 2px;
    background: #fff;
    border-radius: 4px;
  }
  .qr-folio {
    font-size: 9px;
    color: #64748b;
    margin-top: 4px;
    font-family: monospace;
  }
</style>
</head>
<body>

{{-- ══ HEADER ══ --}}
<table class="header-table">
  <tr>
    <td class="logo-cell">
      @if($logoSrc)
        <img src="{{ $logoSrc }}" alt="Logo GADPE">
      @else
        <div style="font-size:16px;font-weight:bold;color:#1a5c38;">GADPE</div>
      @endif
    </td>
    <td class="title-wrap">
      <div class="inst-name">
        Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas
      </div>
      <div class="doc-title">
        Solicitud de Licencia, Permiso y Vacaciones
      </div>
    </td>
  </tr>
</table>

<div class="section-title">Información del Solicitante</div>
<table class="t">
  <tr>
    <td class="lbl" width="20%">Funcionario</td>
    <td class="val-bold" width="60%">{{ $nombreServidor }}</td>
    <td width="20%" rowspan="2" class="qr-box">
      @if($qrSrc)
        <img src="{{ $qrSrc }}" width="65" height="65" alt="QR">
        <div class="qr-folio">{{ $folio }}</div>
      @else
        <div class="qr-folio" style="margin-top: 25px;">{{ $folio }}</div>
      @endif
    </td>
  </tr>
  <tr>
    <td class="lbl">Emisión</td>
    <td class="val-bold">{{ $fechaEmision }}</td>
  </tr>
</table>

<div class="section-title">Motivo de la Solicitud</div>
<table class="motivos-table">
  @foreach($motivosGrid as $fila)
  <tr>
    @foreach($fila as $m)
      @if($m === null)
        <td></td>
      @else
        <td class="{{ $m === $motivoVal ? 'motivo-sel' : '' }}">
          <span class="check-icon {{ $m === $motivoVal ? 'active' : '' }}">
            @if($m === $motivoVal)
              &#10003;
            @endif
          </span>
          {{ $motivoLabels[$m] ?? $m }}
        </td>
      @endif
    @endforeach
  </tr>
  @endforeach
</table>

<div class="section-title">Detalle del Permiso</div>
<table class="t">
  <tr>
    <td class="lbl" width="20%">Días Solicitados</td>
    <td class="val-bold" width="30%">
      {{ $vacacion->dias_solicitados }}
      <span style="font-size:10px; font-weight:normal; color:#64748b;">
        ({{ $tipoDiasLabel }})
      </span>
    </td>
    <td class="lbl" width="15%">Desde</td>
    <td class="val-bold" width="35%">{{ $fechaInicio ?: '—' }}</td>
  </tr>
  <tr>
    <td class="lbl">Día de Retorno</td>
    <td class="val-bold">{{ $fechaRetorno ?: '—' }}</td>
    <td class="lbl">Hasta</td>
    <td class="val-bold">{{ $fechaFin ?: '—' }}</td>
  </tr>
</table>

<table class="t">
  <tr>
    <td class="lbl" width="20%">Reemplazo</td>
    <td class="val-bold">{{ $nombreReemplaza ?: '—' }}</td>
  </tr>
  <tr>
    <td class="lbl">Observaciones</td>
    <td>{{ $observacion ?: '—' }}</td>
  </tr>
</table>

<div style="height: 15px;"></div>

{{-- ══ FIRMAS: BLOQUE 1 ══ --}}
<table class="firma-grid">
  <tr>
    <td>
      <div class="firma-box">
        <div class="firma-line"></div>
        <div class="f-lbl">Firma del Solicitante</div>
        <div class="f-name" style="margin-top:4px;">{{ $nombreServidor }}</div>
      </div>
    </td>
    <td>
      <div class="firma-box">
        <div class="firma-line"></div>
        <div class="f-lbl">Jefe Inmediato / Director</div>
        <div class="f-name" style="margin-top:4px;">{{ $nombreJefe ?: '________________________________' }}</div>
        <div class="aceptado-box">
          <span class="badge"><span class="check-icon"></span> Aprobado</span>
          <span class="badge"><span class="check-icon"></span> Negado</span>
        </div>
      </div>
    </td>
  </tr>
</table>

<div class="section-title" style="margin-top: 25px;">Uso Exclusivo de Talento Humano</div>
<table class="t">
  <tr>
    <td class="lbl" width="20%">Fecha Ingreso</td>
    <td width="30%" class="val-bold">{{ $fechaIngreso ?: '—' }}</td>
    <td class="lbl" width="20%">Días de Derecho</td>
    <td width="30%" class="val-bold">{{ $diasDerecho ? $diasDerecho . ' días' : '—' }}</td>
  </tr>
  <tr>
    <td class="lbl">Período de Vac.</td>
    <td colspan="3" class="val-bold">{{ $periodo ?: '—' }}</td>
  </tr>
</table>

{{-- ══ FIRMAS: BLOQUE 2 ══ --}}
<table class="firma-grid">
  <tr>
    <td>
      <div class="firma-box">
        <div class="firma-line"></div>
        <div class="f-lbl">Talento Humano</div>
        <div class="aceptado-box" style="margin-top:15px;">
          <span class="badge"><span class="check-icon"></span> Aprobado</span>
          <span class="badge"><span class="check-icon"></span> Negado</span>
        </div>
      </div>
    </td>
    <td>
      <div class="firma-box">
        <div class="firma-line"></div>
        <div class="f-lbl">Prefectura</div>
        <div class="aceptado-box" style="margin-top:15px;">
          <span class="badge"><span class="check-icon"></span> Aprobado</span>
          <span class="badge"><span class="check-icon"></span> Negado</span>
        </div>
      </div>
    </td>
  </tr>
</table>

<div class="pie">
  SGTH GADPE &nbsp;|&nbsp; Folio: {{ $folio }} &nbsp;|&nbsp; Generado: {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
