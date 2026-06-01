<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, sans-serif;
    font-size: 10px;
    color: #222;
  }

  /* ── COPIA ── */
  .copia {
    width: 100%;
    padding: 10px 14px;
    page-break-inside: avoid;
  }

  /* ── SEPARADOR ── */
  .separador {
    border-top: 2px dashed #555;
    margin: 6px 0;
    text-align: center;
    font-size: 8px;
    color: #777;
    letter-spacing: 2px;
  }

  /* ── HEADER ── */
  .header {
    display: table;
    width: 100%;
    margin-bottom: 6px;
  }
  .header-logo {
    display: table-cell;
    width: 60px;
    vertical-align: middle;
    text-align: center;
  }
  .header-text {
    display: table-cell;
    vertical-align: middle;
    text-align: center;
    padding: 0 4px;
  }
  .header-folio {
    display: table-cell;
    width: 120px;
    vertical-align: middle;
    text-align: right;
    font-size: 9px;
  }
  .institucion {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .subtitulo {
    font-size: 8px;
    color: #444;
  }
  .titulo-doc {
    font-size: 11px;
    font-weight: bold;
    color: #1a5c38;
    text-transform: uppercase;
    margin-top: 2px;
  }
  .folio-box {
    border: 1.5px solid #1a5c38;
    padding: 3px 6px;
    border-radius: 3px;
    display: inline-block;
    font-size: 9px;
    font-weight: bold;
    color: #1a5c38;
  }
  .copy-label {
    font-size: 7px;
    color: #888;
    margin-top: 2px;
    text-align: right;
  }

  /* ── CONTENIDO ── */
  .content {
    display: table;
    width: 100%;
    margin-top: 5px;
  }
  .col-left {
    display: table-cell;
    width: 55%;
    padding-right: 8px;
    vertical-align: top;
  }
  .col-right {
    display: table-cell;
    width: 45%;
    vertical-align: top;
  }

  /* ── SECCIÓN ── */
  .section-title {
    background: #1a5c38;
    color: #fff;
    font-size: 8px;
    font-weight: bold;
    padding: 2px 5px;
    text-transform: uppercase;
    margin-bottom: 3px;
    margin-top: 5px;
  }
  .field-row {
    display: table;
    width: 100%;
    margin-bottom: 2px;
  }
  .field-label {
    display: table-cell;
    width: 38%;
    font-size: 8.5px;
    color: #555;
    font-weight: bold;
    padding: 1px 0;
  }
  .field-value {
    display: table-cell;
    font-size: 8.5px;
    border-bottom: 1px solid #ccc;
    padding: 1px 3px;
  }

  /* ── BADGE ── */
  .badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 2px;
    font-size: 8px;
    font-weight: bold;
  }
  .badge-pendiente { background: #fff3cd; color: #856404; }
  .badge-activo    { background: #cce5ff; color: #004085; }
  .badge-validado  { background: #d4edda; color: #155724; }

  /* ── FIRMAS ── */
  .firmas {
    display: table;
    width: 100%;
    margin-top: 10px;
  }
  .firma-cell {
    display: table-cell;
    width: 33%;
    text-align: center;
    padding: 0 4px;
  }
  .firma-linea {
    border-top: 1px solid #333;
    padding-top: 3px;
    margin-top: 28px;
    font-size: 7.5px;
  }
  .firma-nombre {
    font-size: 7px;
    color: #555;
    margin-top: 1px;
  }

  /* ── PIE ── */
  .pie {
    text-align: center;
    font-size: 7px;
    color: #aaa;
    margin-top: 6px;
    border-top: 1px solid #eee;
    padding-top: 3px;
  }
</style>
</head>
<body>

@php
  $tipoLabels = [
    'personal'   => 'Personal',
    'oficial'    => 'Oficial',
    'enfermedad' => 'Por Enfermedad',
    'calamidad'  => 'Calamidad Doméstica',
  ];
  $tipoVal = $permiso->tipo instanceof \App\Enums\TipoPermiso
    ? $permiso->tipo->value : (string)$permiso->tipo;

  $estadoVal = $permiso->estado instanceof \App\Enums\EstadoPermiso
    ? $permiso->estado->value : (string)$permiso->estado;

  $estadoClases = [
    'pendiente'               => 'badge-pendiente',
    'activo'                  => 'badge-activo',
    'validado_trabajo_social' => 'badge-validado',
  ];

  $nombreServidor = strtoupper(implode(' ', array_filter([
    $permiso->servidor->apellido ?? null,
    $permiso->servidor->segundo_apellido ?? null,
    $permiso->servidor->nombre ?? null,
    $permiso->servidor->segundo_nombre ?? null,
  ])));

  $nombreJefe = $permiso->jefe
    ? strtoupper(implode(' ', array_filter([
        $permiso->jefe->apellido ?? null,
        $permiso->jefe->nombre   ?? null,
      ])))
    : '____________________________';

  $fechaPermiso = $permiso->fecha instanceof \Carbon\Carbon
    ? $permiso->fecha->format('d/m/Y')
    : \Carbon\Carbon::parse($permiso->fecha)->format('d/m/Y');

  $horaInicio = \Carbon\Carbon::parse($permiso->hora_inicio)->format('H:i');
  $horaFin    = \Carbon\Carbon::parse($permiso->hora_fin)->format('H:i');
  $inicio     = \Carbon\Carbon::parse($permiso->hora_inicio);
  $fin        = \Carbon\Carbon::parse($permiso->hora_fin);
  $horas      = $inicio->diffInHours($fin);
  $mins       = $inicio->copy()->addHours($horas)->diffInMinutes($fin);
  $duracion   = $horas . 'h' . ($mins > 0 ? ' ' . $mins . 'min' : '');

  $copies = [
    ['label' => 'COPIA SERVIDOR',         'tag' => '1 de 2'],
    ['label' => 'COPIA TALENTO HUMANO',   'tag' => '2 de 2'],
  ];
@endphp

@foreach($copies as $i => $copy)

{{-- ══════════════════════════════════════════ --}}
<div class="copia">

  {{-- Header --}}
  <div class="header">
    <div class="header-text">
      <div class="institucion">
        Gobierno Autónomo Descentralizado Provincial de Esmeraldas
      </div>
      <div class="subtitulo">
        Unidad de Administración del Talento Humano
      </div>
      <div class="titulo-doc">Permiso de Ausencia Temporal</div>
    </div>
    <div class="header-folio">
      <div class="folio-box">{{ $permiso->folio ?? 'S/N' }}</div>
      <div class="copy-label">{{ $copy['label'] }}</div>
      <div class="copy-label">Emisión: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
  </div>

  {{-- Contenido en 2 columnas --}}
  <div class="content">

    {{-- COLUMNA IZQUIERDA: Datos del servidor --}}
    <div class="col-left">
      <div class="section-title">Datos del servidor</div>

      <div class="field-row">
        <div class="field-label">Nombre:</div>
        <div class="field-value">{{ $nombreServidor }}</div>
      </div>
      <div class="field-row">
        <div class="field-label">Cédula:</div>
        <div class="field-value">
          {{ $permiso->servidor->cedula ?? '—' }}
        </div>
      </div>
      <div class="field-row">
        <div class="field-label">Unidad:</div>
        <div class="field-value">
          {{ $permiso->unidadAdministrativa->nombre ?? '—' }}
        </div>
      </div>
      <div class="field-row">
        <div class="field-label">Cargo:</div>
        <div class="field-value">
          {{ $permiso->servidor->puesto?->cargo?->nombre ?? '—' }}
        </div>
      </div>
      <div class="field-row">
        <div class="field-label">Jefe inmediato:</div>
        <div class="field-value">{{ $nombreJefe }}</div>
      </div>
    </div>

    {{-- COLUMNA DERECHA: Datos del permiso --}}
    <div class="col-right">
      <div class="section-title">Datos del permiso</div>

      <div class="field-row">
        <div class="field-label">Tipo:</div>
        <div class="field-value">
          {{ $tipoLabels[$tipoVal] ?? $tipoVal }}
        </div>
      </div>
      <div class="field-row">
        <div class="field-label">Fecha:</div>
        <div class="field-value">{{ $fechaPermiso }}</div>
      </div>
      <div class="field-row">
        <div class="field-label">Hora inicio:</div>
        <div class="field-value">{{ $horaInicio }}</div>
      </div>
      <div class="field-row">
        <div class="field-label">Hora fin:</div>
        <div class="field-value">{{ $horaFin }}</div>
      </div>
      <div class="field-row">
        <div class="field-label">Duración:</div>
        <div class="field-value">{{ $duracion }}</div>
      </div>
      <div class="field-row">
        <div class="field-label">Estado:</div>
        <div class="field-value">
          <span class="badge {{ $estadoClases[$estadoVal] ?? '' }}">
            {{ strtoupper($estadoVal) }}
          </span>
        </div>
      </div>
      @if($permiso->observacion)
      <div class="field-row">
        <div class="field-label">Observación:</div>
        <div class="field-value">{{ $permiso->observacion }}</div>
      </div>
      @endif
      <div class="field-row">
        <div class="field-label">Vence el:</div>
        <div class="field-value">
          {{ $permiso->vence_en
            ? \Carbon\Carbon::parse($permiso->vence_en)->format('d/m/Y')
            : '—' }}
        </div>
      </div>
    </div>
  </div>

  {{-- Firmas --}}
  <div class="firmas">
    <div class="firma-cell">
      <div class="firma-linea">
        <strong>SERVIDOR</strong>
        <div class="firma-nombre">
          {{ strtoupper(implode(' ', array_filter([
            $permiso->servidor->apellido ?? null,
            $permiso->servidor->nombre   ?? null,
          ]))) }}
        </div>
      </div>
    </div>
    <div class="firma-cell">
      <div class="firma-linea">
        <strong>JEFE INMEDIATO</strong>
        <div class="firma-nombre">{{ $nombreJefe }}</div>
      </div>
    </div>
    <div class="firma-cell">
      <div class="firma-linea">
        <strong>TALENTO HUMANO</strong>
        <div class="firma-nombre">Director/a UATH</div>
      </div>
    </div>
  </div>

  {{-- Pie --}}
  <div class="pie">
    Documento generado electrónicamente — SGTH GAD Esmeraldas &nbsp;|&nbsp;
    Verificar en: /api/v1/permisos/verificar/{{ $permiso->folio ?? '' }}
  </div>

</div>
{{-- ══════════════════════════════════════════ --}}

@if(!$loop->last)
<div class="separador">✂ &nbsp; CORTAR AQUÍ &nbsp; ✂</div>
@endif

@endforeach

</body>
</html>
