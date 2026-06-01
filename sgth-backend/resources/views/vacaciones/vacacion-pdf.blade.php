@php
  $logoPath = public_path('images/logo-gadpe.png');
  $logoSrc  = file_exists($logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
    : null;

  $motivoLabels = [
    'vacaciones_anuales'        => 'VACACIONES ANUALES (MAYOR 5 DIAS)',
    'permiso_cargo_vacaciones'  => 'PERMISO CON CARGO A VACACIONES (MAX 5 DIAS)',
    'licencia_sin_goce'         => 'LICENCIA SIN GOCE DE HABERES',
    'matrimonio'                => 'MATRIMONIO',
    'capacitacion'              => 'CAPACITACION Y/O ADIESTRAMIENTO',
    'enfermedad'                => 'ENFERMEDAD',
    'maternidad'                => 'MATERNIDAD',
    'paternidad'                => 'PATERNIDAD',
    'estudios_sin_remuneracion' => 'PERMISO PARA REALIZAR ESTUDIOS SIN REMUNERACION',
    'calamidad_domestica'       => 'CALAMIDAD DOMESTICA',
    'licencia_con_goce'         => 'LICENCIA CON GOCE DE SUELDO',
  ];

  // Grid de 3 columnas para los motivos
  $motivosGrid = [
    ['vacaciones_anuales', 'permiso_cargo_vacaciones', 'licencia_sin_goce'],
    ['matrimonio', 'capacitacion', 'enfermedad'],
    ['paternidad', 'maternidad', 'estudios_sin_remuneracion'],
    ['calamidad_domestica', 'licencia_con_goce', null],
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
    ? \Carbon\Carbon::parse($vacacion->fecha_inicio)->format('Y-m-d') : '';
  $fechaFin     = $vacacion->fecha_fin
    ? \Carbon\Carbon::parse($vacacion->fecha_fin)->format('Y-m-d') : '';
  $fechaRetorno = $vacacion->fecha_retorno
    ? \Carbon\Carbon::parse($vacacion->fecha_retorno)->format('Y-m-d') : '';
  $fechaEmision = $vacacion->fecha_emision
    ? \Carbon\Carbon::parse($vacacion->fecha_emision)->format('Y-m-d')
    : now()->format('Y-m-d');

  $fechaIngreso = null;
  if ($vacacion->fecha_ingreso_institucion_informe) {
    $fechaIngreso = \Carbon\Carbon::parse(
      $vacacion->fecha_ingreso_institucion_informe
    )->format('Y-m-d');
  } elseif ($vacacion->servidor->fecha_ingreso_institucion ?? null) {
    $fechaIngreso = \Carbon\Carbon::parse(
      $vacacion->servidor->fecha_ingreso_institucion
    )->format('Y-m-d');
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
        ->size(75)->margin(1)->generate($urlQr);
    if (!empty($qrSvg)) {
        $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
    }
  } catch(\Exception $e) {
    $qrSrc = null;
  }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #000;
    margin: 12px 15px;
  }

  /* ── HEADER ── */
  .header-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
    margin-bottom: 4px;
  }
  .logo-cell {
    width: 110px;
    border-right: 1.5px solid #000;
    text-align: center;
    padding: 4px;
    vertical-align: middle;
  }
  .logo-cell img { width: 100px; height: auto; }
  .title-wrap {
    text-align: center;
    vertical-align: middle;
    padding: 5px 10px;
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
    border-top: 1px solid #ccc;
    padding-top: 3px;
  }

  /* ── TABLAS GENERALES ── */
  .t {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
    margin-bottom: 4px;
  }
  .t td {
    border: 1px solid #bbb;
    padding: 3px 6px;
    font-size: 11px;
    vertical-align: middle;
  }
  .lbl {
    font-weight: bold;
    background: #f0f0f0;
    font-size: 10px;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .val-bold { font-weight: bold; font-size: 12px; }

  /* ── MOTIVOS ── */
  .motivos-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
    margin-bottom: 4px;
  }
  .motivos-table td {
    border: 1px solid #ccc;
    padding: 3px 5px;
    font-size: 10px;
    vertical-align: middle;
    width: 33.33%;
  }
  .radio-sel   { color: #1a5c38; font-size: 14px; font-weight: bold; }
  .radio-empty { color: #bbb; font-size: 14px; }

  /* ── FIRMAS ── */
  .firma-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
    margin-bottom: 4px;
  }
  .firma-table td {
    border: 1px solid #bbb;
    padding: 4px 6px;
    vertical-align: top;
    font-size: 11px;
  }
  .f-lbl {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    border-bottom: 1px solid #ccc;
    padding-bottom: 2px;
    margin-bottom: 2px;
  }
  .f-space { height: 38px; }
  .f-name {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    border-top: 1px solid #666;
    padding-top: 2px;
    margin-top: 2px;
  }
  .aceptado {
    font-size: 10px;
    margin-top: 3px;
    line-height: 1.8;
  }

  /* ── SECCIÓN INFORME ── */
  .informe-header {
    background: #1a5c38;
    color: #fff;
    text-align: center;
    font-weight: bold;
    font-size: 11px;
    padding: 3px;
    text-transform: uppercase;
  }

  /* ── FIRMAS FINALES ── */
  .firmas-finales {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
    margin-bottom: 4px;
  }
  .firmas-finales td {
    border: 1px solid #bbb;
    padding: 4px 6px;
    width: 50%;
    text-align: center;
    vertical-align: top;
  }
  .pie {
    text-align: right;
    font-size: 8px;
    color: #aaa;
    margin-top: 5px;
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
        <div style="font-size:9px; font-weight:bold; color:#1a5c38;">
          GADPE
        </div>
      @endif
    </td>
    <td class="title-wrap">
      <div class="inst-name">
        Gobierno Autonomo Descentralizado de la Provincia de Esmeraldas
      </div>
      <div class="doc-title">
        Solicitud de Licencia, Permiso y Vacaciones
      </div>
    </td>
  </tr>
</table>

{{-- ══ DATOS FUNCIONARIO + QR ══ --}}
<table class="t">
  <tr>
    <td class="lbl" width="30%">Apellidos y Nombres del Funcionario:</td>
    <td class="val-bold" width="45%">{{ $nombreServidor }}</td>
    <td class="lbl" width="12%" style="text-align:center;">
      Codigo de Solicitud:
    </td>
    <td width="13%" style="text-align:center; padding:3px;">
      @if($qrSrc)
        <img src="{{ $qrSrc }}" width="60" height="60" alt="QR">
        <br>
        <span style="font-size:8px; font-weight:bold;">
          {{ $folio }}
        </span>
      @else
        <span style="font-size:10px; font-weight:bold;">
          {{ $folio }}
        </span>
      @endif
    </td>
  </tr>
</table>

{{-- ══ GRID DE MOTIVOS ══ --}}
<table class="motivos-table">
  @foreach($motivosGrid as $fila)
  <tr>
    @foreach($fila as $m)
      @if($m === null)
        <td></td>
      @else
        <td>
          @if($m === $motivoVal)
            <span class="radio-sel">&#9679;</span>
          @else
            <span class="radio-empty">&#9675;</span>
          @endif
          &nbsp;{{ $motivoLabels[$m] ?? $m }}
        </td>
      @endif
    @endforeach
  </tr>
  @endforeach
</table>

{{-- ══ FECHAS ══ --}}
<table class="t">
  <tr>
    <td class="lbl" width="30%">Numero de Dias que Solicita:</td>
    <td class="val-bold" width="15%">
      {{ $vacacion->dias_solicitados }}
      <span style="font-size:9px; font-weight:normal;">
        ({{ $vacacion->tipo_dias === 'habiles' ? 'habiles' : 'calendario' }})
      </span>
    </td>
    <td class="lbl" width="10%">Desde:</td>
    <td class="val-bold" width="15%">{{ $fechaInicio }}</td>
    <td class="lbl" width="10%">Hasta:</td>
    <td class="val-bold" width="20%">{{ $fechaFin }}</td>
  </tr>
  <tr>
    <td class="lbl" colspan="2">Dia de Ingreso a sus Labores:</td>
    <td colspan="4">{{ $fechaRetorno ?: '—' }}</td>
  </tr>
</table>

{{-- ══ FIRMA SOLICITANTE ══ --}}
<table class="firma-table">
  <tr>
    <td width="55%">
      <div class="f-lbl">f: Firma del Solicitante</div>
      <div class="f-space"></div>
      <div class="f-name">{{ $nombreServidor }}</div>
    </td>
    <td width="45%" style="text-align:center;">
      <div class="f-lbl">Fecha de Emision</div>
      <div style="font-size:14px; font-weight:bold;
                  padding:8px 0; text-align:center;">
        {{ $fechaEmision }}
      </div>
    </td>
  </tr>
</table>

{{-- ══ FIRMA JEFE DEPARTAMENTAL ══ --}}
<table class="firma-table">
  <tr>
    <td width="55%">
      <div class="f-lbl">f: Firma Jefe Departamental</div>
      <div class="f-space"></div>
    </td>
    <td width="45%">
      <div class="f-lbl">Jefe Departamental:</div>
      <div style="min-height:20px; font-weight:bold; font-size:11px;">
        &nbsp;
      </div>
      <div class="aceptado">
        &#9675; &nbsp;ACEPTADO<br>
        &#9675; &nbsp;NEGADO
      </div>
    </td>
  </tr>
</table>

{{-- ══ FIRMA DIRECTOR ══ --}}
<table class="firma-table">
  <tr>
    <td width="55%">
      <div class="f-lbl">f: Firma Director</div>
      <div class="f-space"></div>
    </td>
    <td width="45%">
      <div class="f-lbl">Jefe Departamental:</div>
      <div style="font-weight:bold; font-size:11px; margin:3px 0;">
        {{ $nombreJefe ?: '____________________________' }}
      </div>
      <div class="aceptado">
        &#9675; &nbsp;ACEPTADO<br>
        &#9675; &nbsp;NEGADO
      </div>
    </td>
  </tr>
</table>

{{-- ══ OBSERVACIONES ══ --}}
<table class="t">
  <tr>
    <td class="lbl" width="28%" style="vertical-align:top;">
      Observaciones:
    </td>
    <td style="height:50px; vertical-align:top;">
      {{ $observacion ?: '—' }}
    </td>
  </tr>
</table>

{{-- ══ PERSONA QUE REEMPLAZA ══ --}}
<table class="t">
  <tr>
    <td class="lbl" width="28%">Persona que me Reemplazara:</td>
    <td>{{ $nombreReemplaza ?: '—' }}</td>
  </tr>
</table>

{{-- ══ INFORME DE VACACIONES ══ --}}
<div class="informe-header">Informe de Vacaciones</div>
<table class="t" style="margin-top:0; border-top:0;">
  <tr>
    <td class="lbl" width="22%">Fecha de Ingreso:</td>
    <td width="28%">{{ $fechaIngreso ?: '—' }}</td>
    <td class="lbl" width="22%">Tiene Derecho a:</td>
    <td width="28%">
      {{ $diasDerecho ? $diasDerecho . ' dias' : '—' }}
    </td>
  </tr>
  <tr>
    <td class="lbl" width="22%">
      Vacaciones Corresponden al Periodo de:
    </td>
    <td colspan="3">{{ $periodo ?: '—' }}</td>
  </tr>
</table>

{{-- ══ FIRMAS FINALES ══ --}}
<table class="firmas-finales">
  <tr>
    <td>
      <div class="f-lbl">f: Direccion de Talento Humano</div>
      <div class="f-space"></div>
      <table width="100%" style="border:none;">
        <tr>
          <td style="border:none; width:50%; font-size:10px;">
            &#9675; &nbsp;ACEPTADO
          </td>
          <td style="border:none; width:50%; font-size:10px;">
            &#9675; &nbsp;NEGADO
          </td>
        </tr>
      </table>
    </td>
    <td>
      <div class="f-lbl">f: Prefectura</div>
      <div class="f-space"></div>
      <table width="100%" style="border:none;">
        <tr>
          <td style="border:none; width:50%; font-size:10px;">
            &#9675; &nbsp;ACEPTADO
          </td>
          <td style="border:none; width:50%; font-size:10px;">
            &#9675; &nbsp;NEGADO
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<div class="pie">
  SGTH GADPE &nbsp;|&nbsp;
  Folio: {{ $folio }} &nbsp;|&nbsp;
  Generado: {{ now()->format('Y-m-d H:i') }}
</div>

</body>
</html>
