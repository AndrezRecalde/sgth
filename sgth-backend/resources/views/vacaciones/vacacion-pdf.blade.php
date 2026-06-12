@php
    $logoPath = public_path('images/logo-gadpe.png');
    $logoSrc = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;

    // Grid de motivos 3 columnas
    $motivosGrid = [
        ['vacaciones_anuales', 'permiso_cargo_vacaciones', 'licencia_sin_goce'],
        ['matrimonio', 'capacitacion', 'enfermedad'],
        ['paternidad', 'maternidad', 'estudios_sin_remuneracion'],
        ['calamidad_domestica', 'licencia_con_goce', null],
    ];

    $motivoLabels = [
        'vacaciones_anuales' => 'VACACIONES ANUALES (MAYOR 5 DIAS)',
        'permiso_cargo_vacaciones' => 'PERMISO C/ CARGO A VACACIONES (MAX 5 DIAS)',
        'licencia_sin_goce' => 'LICENCIA SIN GOCE DE HABERES',
        'matrimonio' => 'MATRIMONIO',
        'capacitacion' => 'CAPACITACION Y/O ADIESTRAMIENTO',
        'enfermedad' => 'ENFERMEDAD',
        'maternidad' => 'MATERNIDAD',
        'paternidad' => 'PATERNIDAD',
        'estudios_sin_remuneracion' => 'ESTUDIOS SIN REMUNERACION',
        'calamidad_domestica' => 'CALAMIDAD DOMESTICA',
        'licencia_con_goce' => 'LICENCIA CON GOCE DE SUELDO',
    ];

    $motivoVal =
        $vacacion->motivo instanceof \App\Enums\MotivoVacacion
            ? $vacacion->motivo->value
            : (string) ($vacacion->motivo ?? '');

    $nombreServidor = mb_strtoupper(
        implode(
            ' ',
            array_filter([
                $vacacion->servidor->apellido ?? null,
                $vacacion->servidor->segundo_apellido ?? null,
                $vacacion->servidor->nombre ?? null,
                $vacacion->servidor->segundo_nombre ?? null,
            ]),
        ),
        'UTF-8',
    );

    $nombreJefe = $vacacion->jefe
        ? mb_strtoupper(
            implode(
                ' ',
                array_filter([
                    $vacacion->jefe->apellido ?? null,
                    $vacacion->jefe->segundo_apellido ?? null,
                    $vacacion->jefe->nombre ?? null,
                    $vacacion->jefe->segundo_nombre ?? null,
                ]),
            ),
            'UTF-8',
        )
        : '';

    $nombreReemplaza = $vacacion->personaReemplaza
        ? mb_strtoupper(
            implode(
                ' ',
                array_filter([
                    $vacacion->personaReemplaza->apellido ?? null,
                    $vacacion->personaReemplaza->nombre ?? null,
                ]),
            ),
            'UTF-8',
        )
        : $vacacion->persona_reemplaza ?? '';

    $folio = $vacacion->folio ?? 'S/N';

    $fechaInicio = $vacacion->fecha_inicio ? \Carbon\Carbon::parse($vacacion->fecha_inicio)->format('d/m/Y') : '';
    $fechaFin = $vacacion->fecha_fin ? \Carbon\Carbon::parse($vacacion->fecha_fin)->format('d/m/Y') : '';
    $fechaRetorno = $vacacion->fecha_retorno ? \Carbon\Carbon::parse($vacacion->fecha_retorno)->format('d/m/Y') : '';
    $fechaEmision = $vacacion->fecha_emision
        ? \Carbon\Carbon::parse($vacacion->fecha_emision)->format('d/m/Y')
        : now()->format('d/m/Y');

    $fechaIngreso = null;
    if ($vacacion->fecha_ingreso_institucion_informe) {
        $fechaIngreso = \Carbon\Carbon::parse($vacacion->fecha_ingreso_institucion_informe)->format('d/m/Y');
    } elseif ($vacacion->servidor->fecha_ingreso_institucion ?? null) {
        $fechaIngreso = \Carbon\Carbon::parse($vacacion->servidor->fecha_ingreso_institucion)->format('d/m/Y');
    }

    $diasDerecho = $vacacion->dias_derecho ?? '';
    $periodo = $vacacion->periodo_vacaciones ?? '';
    $observacion = mb_strtoupper($vacacion->observacion ?? '', 'UTF-8');

    // QR en formato SVG para evitar fallos si no hay Imagick
    $qrSrc = null;
    try {
        $urlQr = config('app.url') . "/api/v1/asistencia/vacaciones/verificar/{$folio}";
        $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(90)->margin(1)->generate($urlQr);
        if (!empty($qrSvg)) {
            $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        }
    } catch (\Exception $e) {
        $qrSrc = null;
    }

    $tipoDiasLabel = $vacacion->tipo_dias === 'habiles' ? 'días hábiles' : 'días calendario';
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
    color: #334155;
    margin: 15px 25px; /* Reducido para que entre en una sola página */
    line-height: 1.2;
  }

  /* ── BRANDING ── */
  .text-brand { color: #1a5c38; }
  .bg-brand { background-color: #1a5c38; color: #fff; }
  
  /* ── HEADER ── */
  .header-table {
    width: 100%;
    margin-bottom: 10px;
    border-bottom: 1.5px solid #1a5c38;
    padding-bottom: 8px;
  }
  .logo-cell {
    width: 120px;
    vertical-align: middle;
  }
  .logo-cell img { width: 100px; height: auto; }
  .title-wrap {
    text-align: right;
    vertical-align: middle;
  }
  .inst-name {
    font-size: 12px;
    font-weight: bold;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .doc-title {
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
    margin-top: 4px;
    letter-spacing: 1px;
  }

  /* ── SECTIONS ── */
  .section-title {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    color: #1a5c38;
    margin-bottom: 6px;
    margin-top: 10px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 2px;
  }

  /* ── INFO SOLICITANTE HEADER ── */
  .info-header {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    background: #f8fafc;
  }
  .info-header td {
    padding: 8px 10px;
    vertical-align: middle;
    border: 1px solid #e2e8f0;
  }
  .info-left {
    width: 75%;
    border-right: 1px solid #e2e8f0;
  }
  .info-right {
    width: 25%;
    text-align: center;
  }
  .lbl {
    font-size: 9px;
    color: #64748b;
    text-transform: uppercase;
    font-weight: bold;
    margin-bottom: 2px;
  }
  .val {
    font-size: 12px;
    color: #0f172a;
    font-weight: bold;
  }
  .val-light {
    font-size: 10px;
    color: #475569;
  }
  
  /* ── GENERAL TABLES ── */
  .t {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
  }
  .t td {
    padding: 5px 8px;
    vertical-align: middle;
    border: 1px solid #e2e8f0;
  }
  .t .t-lbl {
    font-weight: bold;
    background: #f8fafc;
    color: #475569;
    font-size: 9px;
    text-transform: uppercase;
    width: 20%;
  }
  .t .t-val {
    color: #0f172a;
    font-size: 10px;
  }
  .t .t-val-bold {
    font-weight: bold;
    color: #0f172a;
    font-size: 10px;
  }

  /* ── MOTIVOS ── */
  .motivos-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    border: 1px solid #e2e8f0;
  }
  .motivos-table td {
    padding: 4px;
    font-size: 9px;
    vertical-align: middle;
    width: 33.33%;
    color: #475569;
    border: 1px solid #e2e8f0;
  }
  .motivo-sel {
    background: #f0fdf4;
    color: #1a5c38 !important;
    font-weight: bold;
  }
  .check-icon {
    display: inline-block;
    width: 10px;
    height: 10px;
    border: 1px solid #94a3b8;
    border-radius: 2px;
    margin-right: 4px;
    vertical-align: middle;
    text-align: center;
    line-height: 10px;
    font-size: 8px;
  }
  .check-icon.active {
    background: #1a5c38;
    border-color: #1a5c38;
    color: #fff;
  }

  /* ── DETALLE PERMISO ── */
  .detalle-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    border: 1px solid #e2e8f0;
  }
  .detalle-table td {
    padding: 6px;
    border: 1px solid #e2e8f0;
    text-align: center;
  }
  .dt-lbl {
    font-size: 9px;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 2px;
    font-weight: bold;
  }
  .dt-val {
    font-size: 11px;
    font-weight: bold;
    color: #0f172a;
  }

  /* ── FIRMAS ── */
  .firma-section {
    margin-top: 8px;
    page-break-inside: avoid;
  }
  .firma-single {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
  }
  .firma-single td {
    text-align: center;
    padding: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
  }
  .firma-line {
    border-top: 1px solid #94a3b8;
    margin: 20px auto 4px auto;
    width: 200px;
  }
  .f-lbl {
    font-size: 9px;
    font-weight: bold;
    color: #475569;
    text-transform: uppercase;
  }
  .f-name {
    font-size: 10px;
    font-weight: bold;
    color: #0f172a;
    text-transform: uppercase;
  }

  .firma-grid {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
  }
  .firma-grid td {
    width: 50%;
    vertical-align: top;
    padding: 0;
  }
  .firma-box-left {
    border: 1px solid #e2e8f0;
    padding: 8px;
    height: 95px;
    text-align: center;
    background: #f8fafc;
    margin-right: 4px;
  }
  .firma-box-right {
    border: 1px solid #e2e8f0;
    padding: 8px;
    height: 95px;
    text-align: center;
    background: #f8fafc;
    margin-left: 4px;
  }
  
  .aceptado-box {
    text-align: center;
    margin-top: 10px;
    font-size: 9px;
  }
  .badge {
    display: inline-block;
    padding: 2px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    margin: 0 4px;
    color: #64748b;
    background: #fff;
  }

  /* ── PIE ── */
  .pie {
    position: fixed;
    bottom: -10px;
    left: 25px;
    right: 25px;
    text-align: right;
    font-size: 8px;
    color: #94a3b8;
    border-top: 1px solid #e2e8f0;
    padding-top: 4px;
  }
  
  .qr-img {
    border: 1px solid #e2e8f0;
    padding: 2px;
    background: #fff;
    border-radius: 4px;
  }
  .qr-folio {
    font-size: 9px;
    color: #0f172a;
    margin-top: 2px;
    font-family: monospace;
    font-weight: bold;
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

{{-- ══ INFO SOLICITANTE / FOLIO ══ --}}
<table class="info-header">
  <tr>
    <td class="info-left">
      <div class="lbl">Funcionario Solicitante</div>
      <div class="val">{{ $nombreServidor }}</div>
      <div style="margin-top: 6px;">
        <span class="lbl" style="display:inline-block; margin-right: 5px;">Fecha de Emisión:</span>
        <span class="val-light">{{ $fechaEmision }}</span>
      </div>
    </td>
    <td class="info-right">
      @if($qrSrc)
        <img src="{{ $qrSrc }}" width="50" height="50" class="qr-img" alt="QR">
      @endif
      <div class="qr-folio">{{ $folio }}</div>
    </td>
  </tr>
</table>

{{-- ══ MOTIVO DE LA SOLICITUD ══ --}}
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
            {!! $m === $motivoVal ? '&#10003;' : '' !!}
          </span>
          {{ $motivoLabels[$m] ?? $m }}
        </td>
      @endif
    @endforeach
  </tr>
  @endforeach
</table>

{{-- ══ DETALLE DEL PERMISO ══ --}}
<div class="section-title">Detalle del Permiso</div>
<table class="detalle-table">
  <tr>
    <td width="25%" style="background: #f8fafc;">
      <div class="dt-lbl">Días Solicitados</div>
      <div class="dt-val">{{ $vacacion->dias_solicitados }} <span style="font-size:9px; font-weight:normal; color:#64748b;">({{ $tipoDiasLabel }})</span></div>
    </td>
    <td width="25%">
      <div class="dt-lbl">Desde</div>
      <div class="dt-val">{{ $fechaInicio ?: '—' }}</div>
    </td>
    <td width="25%">
      <div class="dt-lbl">Hasta</div>
      <div class="dt-val">{{ $fechaFin ?: '—' }}</div>
    </td>
    <td width="25%" style="background: #f0fdf4;">
      <div class="dt-lbl" style="color:#1a5c38;">Día de Retorno</div>
      <div class="dt-val" style="color:#1a5c38;">{{ $fechaRetorno ?: '—' }}</div>
    </td>
  </tr>
</table>

<table class="t">
  <tr>
    <td class="t-lbl" width="20%">Reemplazo</td>
    <td class="t-val-bold">{{ $nombreReemplaza ?: '—' }}</td>
  </tr>
  <tr>
    <td class="t-lbl">Observaciones</td>
    <td class="t-val">{{ $observacion ?: '—' }}</td>
  </tr>
</table>

{{-- ══ FIRMA DEL SOLICITANTE ══ --}}
<div class="firma-section">
  <table class="firma-single">
    <tr>
      <td style="height: 80px;">
        <div class="firma-line" style="margin-top: 35px;"></div>
        <div class="f-lbl">Firma del Solicitante</div>
        <div class="f-name" style="margin-top:2px;">{{ $nombreServidor }}</div>
      </td>
    </tr>
  </table>
</div>

{{-- ══ FIRMAS DE LOS JEFES ══ --}}
<div class="firma-section">
  <table class="firma-grid">
    <tr>
      <td>
        <div class="firma-box-left">
          <div class="firma-line" style="width: 80%; margin-top:35px;"></div>
          <div class="f-lbl">Jefe Inmediato</div>
          <div class="f-name" style="margin-top:4px;">{{ $nombreJefe ?: '_______________________' }}</div>
          <div class="aceptado-box">
            <span class="badge"><span class="check-icon"></span> Aprobado</span>
            <span class="badge"><span class="check-icon"></span> Negado</span>
          </div>
        </div>
      </td>
      <td>
        <div class="firma-box-right">
          <div class="firma-line" style="width: 80%; margin-top:35px;"></div>
          <div class="f-lbl">Director</div>
          <div class="f-name" style="margin-top:4px;">_______________________</div>
          <div class="aceptado-box">
            <span class="badge"><span class="check-icon"></span> Aprobado</span>
            <span class="badge"><span class="check-icon"></span> Negado</span>
          </div>
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- ══ USO EXCLUSIVO TALENTO HUMANO ══ --}}
<div class="section-title">Uso Exclusivo de Talento Humano</div>
<table class="t">
  <tr>
    <td class="t-lbl" width="20%">Fecha Ingreso</td>
    <td width="30%" class="t-val-bold">{{ $fechaIngreso ?: '—' }}</td>
    <td class="t-lbl" width="20%">Días de Derecho</td>
    <td width="30%" class="t-val-bold">{{ $diasDerecho ? $diasDerecho . ' días' : '—' }}</td>
  </tr>
  <tr>
    <td class="t-lbl">Período de Vac.</td>
    <td colspan="3" class="t-val-bold">{{ $periodo ?: '—' }}</td>
  </tr>
</table>

{{-- ══ FIRMAS FINALES ══ --}}
<div class="firma-section">
  <table class="firma-grid">
    <tr>
      <td>
        <div class="firma-box-left">
          <div class="firma-line" style="width: 80%; margin-top:35px;"></div>
          <div class="f-lbl">Dirección de Talento Humano</div>
          <div class="aceptado-box">
            <span class="badge"><span class="check-icon"></span> Aprobado</span>
            <span class="badge"><span class="check-icon"></span> Negado</span>
          </div>
        </div>
      </td>
      <td>
        <div class="firma-box-right">
          <div class="firma-line" style="width: 80%; margin-top:35px;"></div>
          <div class="f-lbl">Prefectura</div>
          <div class="aceptado-box">
            <span class="badge"><span class="check-icon"></span> Aprobado</span>
            <span class="badge"><span class="check-icon"></span> Negado</span>
          </div>
        </div>
      </td>
    </tr>
  </table>
</div>

<div class="pie">
  SGTH GADPE &nbsp;|&nbsp; Folio: {{ $folio }} &nbsp;|&nbsp; Generado: {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
