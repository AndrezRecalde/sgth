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
    ? 'dias habiles' : 'dias calendario';
@endphp
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
    margin: 10px 14px;
  }

  /* ── HEADER ── */
  .header-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    margin-bottom: 5px;
  }
  .logo-cell {
    width: 120px;
    border-right: 2px solid #000;
    text-align: center;
    padding: 5px;
    vertical-align: middle;
  }
  .logo-cell img { width: 108px; height: auto; }
  .title-wrap {
    text-align: center;
    vertical-align: middle;
    padding: 6px 12px;
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
    border-top: 1px solid #ccc;
    padding-top: 4px;
  }

  /* ── TABLA FUNCIONARIO ── */
  .t {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    margin-bottom: 5px;
  }
  .t td {
    border: 1px solid #aaa;
    padding: 5px 7px;
    font-size: 12px;
    vertical-align: middle;
  }
  .lbl {
    font-weight: bold;
    background: #ececec;
    font-size: 11px;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .val-bold {
    font-weight: bold;
    font-size: 13px;
  }

  /* ── MOTIVOS ── */
  .motivos-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    margin-bottom: 5px;
  }
  .motivos-table td {
    border: 1px solid #bbb;
    padding: 5px 7px;
    font-size: 11px;
    vertical-align: middle;
    width: 33.33%;
  }
  /* Celda seleccionada */
  .motivo-sel {
    background: #e8f5e9;
    font-weight: bold;
    border: 1.5px solid #1a5c38 !important;
  }
  .radio-box-sel {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid #1a5c38;
    border-radius: 50%;
    background: #1a5c38;
    margin-right: 4px;
    vertical-align: middle;
  }
  .radio-box-empty {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 1.5px solid #999;
    border-radius: 50%;
    background: #fff;
    margin-right: 4px;
    vertical-align: middle;
  }

  /* ── FIRMA ── */
  .firma-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    margin-bottom: 5px;
  }
  .firma-table td {
    border: 1px solid #aaa;
    padding: 5px 7px;
    vertical-align: top;
    font-size: 12px;
  }
  .f-lbl {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    color: #fff;
    background: #2d6a4f;
    padding: 2px 5px;
    margin-bottom: 3px;
  }
  .f-space { height: 45px; }
  .f-name {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    border-top: 1.5px solid #444;
    padding-top: 3px;
    margin-top: 3px;
  }
  .aceptado {
    font-size: 11px;
    margin-top: 5px;
    line-height: 2;
  }
  .check-box {
    display: inline-block;
    width: 11px;
    height: 11px;
    border: 1.5px solid #666;
    margin-right: 5px;
    vertical-align: middle;
  }

  /* ── INFORME ── */
  .informe-header {
    background: #1a5c38;
    color: #fff;
    text-align: center;
    font-weight: bold;
    font-size: 12px;
    padding: 4px;
    text-transform: uppercase;
    border: 2px solid #000;
    border-bottom: 0;
  }

  /* ── FIRMAS FINALES ── */
  .firmas-finales {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    margin-bottom: 5px;
  }
  .firmas-finales td {
    border: 1px solid #aaa;
    padding: 5px 7px;
    width: 50%;
    vertical-align: top;
  }

  .pie {
    text-align: right;
    font-size: 8px;
    color: #aaa;
    margin-top: 4px;
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
        <div style="font-size:10px;font-weight:bold;color:#1a5c38;">GADPE</div>
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

{{-- ══ DATOS FUNCIONARIO ══ --}}
<table class="t">
  <tr>
    <td class="lbl" width="32%">
      Apellidos y Nombres del Funcionario:
    </td>
    <td class="val-bold" width="43%">{{ $nombreServidor }}</td>
    <td class="lbl" width="12%" style="text-align:center;">
      Codigo de Solicitud:
    </td>
    <td width="13%" style="text-align:center; padding:4px;">
      @if($qrSrc)
        <img src="{{ $qrSrc }}" width="70" height="70" alt="QR">
        <div style="font-size:8px;font-weight:bold;margin-top:2px;">
          {{ $folio }}
        </div>
      @else
        <div style="font-size:10px;font-weight:bold;">{{ $folio }}</div>
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
        <td class="{{ $m === $motivoVal ? 'motivo-sel' : '' }}">
          <span class="{{ $m === $motivoVal
            ? 'radio-box-sel' : 'radio-box-empty' }}"></span>
          {{ $motivoLabels[$m] ?? $m }}
        </td>
      @endif
    @endforeach
  </tr>
  @endforeach
</table>

{{-- ══ FECHAS ══ --}}
<table class="t">
  <tr>
    <td class="lbl" width="32%">Numero de Dias que Solicita:</td>
    <td class="val-bold" width="13%">
      {{ $vacacion->dias_solicitados }}
      <span style="font-size:10px;font-weight:normal;">
        ({{ $tipoDiasLabel }})
      </span>
    </td>
    <td class="lbl" width="10%">Desde:</td>
    <td class="val-bold" width="15%">{{ $fechaInicio }}</td>
    <td class="lbl" width="10%">Hasta:</td>
    <td class="val-bold" width="20%">{{ $fechaFin }}</td>
  </tr>
  <tr>
    <td class="lbl" colspan="2">Dia de Ingreso a sus Labores:</td>
    <td colspan="4" style="font-size:13px; font-weight:bold;">
      {{ $fechaRetorno ?: '—' }}
    </td>
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
    <td width="45%" style="text-align:center; vertical-align:middle;">
      <div class="f-lbl">Fecha de Emision</div>
      <div style="font-size:18px; font-weight:bold;
                  padding:10px 0; text-align:center;">
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
      <div style="height:22px;">&nbsp;</div>
      <div class="aceptado">
        <span class="check-box"></span>ACEPTADO<br>
        <span class="check-box"></span>NEGADO
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
      <div style="font-weight:bold; font-size:12px; margin:5px 0;">
        {{ $nombreJefe ?: '________________________________' }}
      </div>
      <div class="aceptado">
        <span class="check-box"></span>ACEPTADO<br>
        <span class="check-box"></span>NEGADO
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
    <td style="height:60px; vertical-align:top;">
      {{ $observacion ?: '—' }}
    </td>
  </tr>
</table>

{{-- ══ PERSONA QUE REEMPLAZA ══ --}}
<table class="t">
  <tr>
    <td class="lbl" width="28%">Persona que me Reemplazara:</td>
    <td style="font-weight:bold;">
      {{ $nombreReemplaza ?: '—' }}
    </td>
  </tr>
</table>

{{-- ══ INFORME DE VACACIONES ══ --}}
<div class="informe-header">Informe de Vacaciones</div>
<table class="t" style="border-top:0; margin-top:0; margin-bottom:5px;">
  <tr>
    <td class="lbl" width="24%">Fecha de Ingreso:</td>
    <td width="26%">{{ $fechaIngreso ?: '—' }}</td>
    <td class="lbl" width="24%">Tiene Derecho a:</td>
    <td width="26%">
      {{ $diasDerecho ? $diasDerecho . ' dias' : '—' }}
    </td>
  </tr>
  <tr>
    <td class="lbl">Vacaciones Corresponden al Periodo de:</td>
    <td colspan="3">{{ $periodo ?: '—' }}</td>
  </tr>
</table>

{{-- ══ FIRMAS FINALES ══ --}}
<table class="firmas-finales">
  <tr>
    <td style="text-align:center;">
      <div class="f-lbl">f: Direccion de Talento Humano</div>
      <div style="height:50px;"></div>
      <table width="100%" style="border:none;">
        <tr>
          <td style="border:none;width:50%;font-size:11px;">
            <span class="check-box"></span>ACEPTADO
          </td>
          <td style="border:none;width:50%;font-size:11px;">
            <span class="check-box"></span>NEGADO
          </td>
        </tr>
      </table>
    </td>
    <td style="text-align:center;">
      <div class="f-lbl">f: Prefectura</div>
      <div style="height:50px;"></div>
      <table width="100%" style="border:none;">
        <tr>
          <td style="border:none;width:50%;font-size:11px;">
            <span class="check-box"></span>ACEPTADO
          </td>
          <td style="border:none;width:50%;font-size:11px;">
            <span class="check-box"></span>NEGADO
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<div class="pie">
  SGTH GADPE &nbsp;|&nbsp;
  Folio: {{ $folio }} &nbsp;|&nbsp;
  Generado: {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
