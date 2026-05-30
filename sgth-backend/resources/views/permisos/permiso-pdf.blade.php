<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
  .header { text-align: center; margin-bottom: 20px; }
  .header h2 { margin: 0; font-size: 16px; }
  .header p  { margin: 2px 0; font-size: 11px; }
  .folio { text-align: right; font-size: 11px; color: #555; }
  table { width: 100%; border-collapse: collapse; margin: 15px 0; }
  td, th { border: 1px solid #ccc; padding: 6px 10px; }
  th { background: #f0f0f0; font-weight: bold; width: 35%; }
  .section-title {
    background: #2d6a4f; color: white;
    padding: 5px 10px; font-weight: bold;
    margin-top: 15px; font-size: 12px;
  }
  .firmas { margin-top: 40px; }
  .firma-col {
    display: inline-block; width: 45%;
    text-align: center; margin: 0 2%;
  }
  .firma-linea {
    border-top: 1px solid #000;
    margin-top: 50px; padding-top: 5px;
    font-size: 10px;
  }
  .badge {
    display: inline-block; padding: 2px 8px;
    border-radius: 3px; font-size: 10px;
    font-weight: bold;
  }
  .badge-pendiente  { background: #fff3cd; color: #856404; }
  .badge-activo     { background: #cce5ff; color: #004085; }
  .badge-validado   { background: #d4edda; color: #155724; }
</style>
</head>
<body>

<div class="header">
  <h2>GOBIERNO AUTÓNOMO DESCENTRALIZADO PROVINCIAL DE ESMERALDAS</h2>
  <p>UNIDAD DE ADMINISTRACIÓN DEL TALENTO HUMANO</p>
  <p><strong>PERMISO DE AUSENCIA TEMPORAL</strong></p>
</div>

<div class="folio">
  <strong>Folio:</strong> {{ $permiso->folio ?? 'S/N' }}<br>
  <strong>Fecha emisión:</strong> {{ now()->format('d/m/Y H:i') }}
</div>

<div class="section-title">DATOS DEL SERVIDOR</div>
<table>
  <tr>
    <th>Nombre completo</th>
    <td>
      {{ strtoupper(implode(' ', array_filter([
        $permiso->servidor->apellido ?? null,
        $permiso->servidor->segundo_apellido ?? null,
        $permiso->servidor->nombre ?? null,
        $permiso->servidor->segundo_nombre ?? null,
      ]))) }}
    </td>
  </tr>
  <tr>
    <th>Cédula de identidad</th>
    <td>{{ $permiso->servidor->cedula ?? '—' }}</td>
  </tr>
  <tr>
    <th>Unidad administrativa</th>
    <td>
      {{ $permiso->unidadAdministrativa->nombre ?? '—' }}
    </td>
  </tr>
  <tr>
    <th>Cargo</th>
    <td>
      {{ $permiso->servidor->puesto?->cargo?->nombre ?? '—' }}
    </td>
  </tr>
</table>

<div class="section-title">DATOS DEL PERMISO</div>
<table>
  <tr>
    <th>Tipo de permiso</th>
    <td>
      @php
        $tipos = [
          'personal'   => 'Personal',
          'oficial'    => 'Oficial',
          'enfermedad' => 'Por Enfermedad',
          'calamidad'  => 'Calamidad Doméstica',
        ];
        $tipoVal = $permiso->tipo instanceof \App\Enums\TipoPermiso
          ? $permiso->tipo->value : (string)$permiso->tipo;
      @endphp
      {{ $tipos[$tipoVal] ?? $tipoVal }}
    </td>
  </tr>
  <tr>
    <th>Fecha del permiso</th>
    <td>
      @php
        $fecha = $permiso->fecha instanceof \Carbon\Carbon
          ? $permiso->fecha
          : \Carbon\Carbon::parse($permiso->fecha);
      @endphp
      {{ $fecha->format('d/m/Y') }}
    </td>
  </tr>
  <tr>
    <th>Hora inicio</th>
    <td>{{ \Carbon\Carbon::parse($permiso->hora_inicio)->format('H:i') }}</td>
  </tr>
  <tr>
    <th>Hora fin</th>
    <td>{{ \Carbon\Carbon::parse($permiso->hora_fin)->format('H:i') }}</td>
  </tr>
  <tr>
    <th>Duración</th>
    <td>
      @php
        $inicio = \Carbon\Carbon::parse($permiso->hora_inicio);
        $fin    = \Carbon\Carbon::parse($permiso->hora_fin);
        $horas  = $inicio->diffInHours($fin);
        $mins   = $inicio->copy()->addHours($horas)->diffInMinutes($fin);
      @endphp
      {{ $horas }}h {{ $mins > 0 ? $mins . 'min' : '' }}
    </td>
  </tr>
  @if($permiso->observacion)
  <tr>
    <th>Observación</th>
    <td>{{ $permiso->observacion }}</td>
  </tr>
  @endif
  <tr>
    <th>Estado</th>
    <td>
      @php
        $estadoVal = $permiso->estado instanceof \App\Enums\EstadoPermiso
          ? $permiso->estado->value : (string)$permiso->estado;
        $clases = [
          'pendiente'               => 'badge-pendiente',
          'activo'                  => 'badge-activo',
          'validado_trabajo_social' => 'badge-validado',
        ];
      @endphp
      <span class="badge {{ $clases[$estadoVal] ?? '' }}">
        {{ strtoupper($estadoVal) }}
      </span>
    </td>
  </tr>
  <tr>
    <th>Vence el</th>
    <td>
      {{ $permiso->vence_en
          ? \Carbon\Carbon::parse($permiso->vence_en)->format('d/m/Y')
          : '—' }}
    </td>
  </tr>
</table>

<div class="firmas">
  <table style="border: none; width: 100%;">
    <tr>
      <td style="border: none; text-align: center; width: 33%;">
        <div style="margin-top: 50px; border-top: 1px solid #000; padding-top: 5px; font-size: 10px;">
          <strong>SERVIDOR</strong><br>
          {{ strtoupper(implode(' ', array_filter([
            $permiso->servidor->apellido ?? null,
            $permiso->servidor->nombre   ?? null,
          ]))) }}
        </div>
      </td>
      <td style="border: none; text-align: center; width: 33%;">
        <div style="margin-top: 50px; border-top: 1px solid #000; padding-top: 5px; font-size: 10px;">
          <strong>JEFE INMEDIATO</strong><br>
          {{ strtoupper(implode(' ', array_filter([
            $permiso->jefe->apellido ?? null,
            $permiso->jefe->nombre   ?? null,
          ]))) ?: '____________________' }}
        </div>
      </td>
      <td style="border: none; text-align: center; width: 33%;">
        <div style="margin-top: 50px; border-top: 1px solid #000; padding-top: 5px; font-size: 10px;">
          <strong>TALENTO HUMANO</strong><br>
          Director/a UATH
        </div>
      </td>
    </tr>
  </table>
</div>

<div style="margin-top: 20px; font-size: 9px; color: #888; text-align: center;">
  Documento generado electrónicamente — SGTH GAD Esmeraldas<br>
  Verificar en: /api/v1/permisos/verificar/{{ $permiso->folio ?? '' }}
</div>

</body>
</html>
