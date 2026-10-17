@php
  // El membrete institucional como en el informe de actividades: la hoja
  // membretada de fondo, o el logo arriba y la dirección al pie.
  $fondoPath = $hojaMembretada ?? '';
  $fondoSrc  = $fondoPath !== '' && file_exists($fondoPath)
    ? 'data:'.(mime_content_type($fondoPath) ?: 'image/jpeg').';base64,'.base64_encode(file_get_contents($fondoPath))
    : null;
  $logoSrc = ! $fondoSrc && ($logo ?? '') !== '' && file_exists($logo)
    ? 'data:'.(mime_content_type($logo) ?: 'image/png').';base64,'.base64_encode(file_get_contents($logo))
    : null;
  $fotoSrc = ($foto ?? null) && file_exists($foto)
    ? 'data:'.(mime_content_type($foto) ?: 'image/jpeg').';base64,'.base64_encode(file_get_contents($foto))
    : null;
  $mayus = fn (?string $t) => mb_strtoupper(trim((string) $t), 'UTF-8');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Hoja de vida — {{ $nombre_completo }}</title>
<style>
  /* Sin `* { margin: 0 }`: en dompdf también borra el margen de la página. */
  @page { margin: 42mm 20mm 26mm 20mm; }
  body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #222; line-height: 1.45; }

  .fondo { position: fixed; top: -42mm; left: -20mm; width: 210mm; height: 297mm; z-index: -1; }
  .fondo img { width: 210mm; height: 297mm; }
  .cabecera { position: fixed; top: -34mm; left: 0; right: 0; text-align: center; }
  .cabecera img { max-height: 24mm; max-width: 120mm; }
  .pie {
    position: fixed; bottom: -16mm; left: 0; right: 0; text-align: center;
    border-top: 1px solid #8a8a8a; padding-top: 4px; font-size: 8.5px; color: #444;
  }

  .titulo { text-align: center; margin: 0 0 12px; }
  .titulo .inst { font-size: 11px; font-weight: bold; letter-spacing: 0.3px; }
  .titulo .doc { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; margin-top: 3px; }

  .persona { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  .persona td { vertical-align: top; padding: 0; }
  .persona .foto { width: 31mm; padding-right: 10px; }
  .persona .foto img { width: 30mm; height: 40mm; border: 1px solid #bbb; }
  .persona .nombre { font-size: 14px; font-weight: bold; margin: 2px 0 2px; }
  .persona .cedula { font-size: 10px; color: #555; margin: 0 0 8px; }
  .persona .cargo { font-size: 10.5px; font-weight: bold; margin: 0; }
  .persona .unidad { font-size: 9.5px; color: #444; margin: 0; }

  h3 {
    font-size: 10.5px; letter-spacing: 0.4px; margin: 14px 0 6px;
    padding-bottom: 2px; border-bottom: 1px solid #8a8a8a;
  }

  .datos { width: 100%; border-collapse: collapse; }
  .datos td { padding: 2px 0; vertical-align: top; }
  .datos .et { width: 28%; color: #555; }

  .tabla { width: 100%; border-collapse: collapse; }
  .tabla th {
    background: #8a8a8a; color: #fff; font-size: 8.5px; padding: 4px 6px;
    text-align: left; border: 1px solid #fff;
  }
  .tabla td { padding: 4px 6px; border: 1px solid #ccc; vertical-align: top; font-size: 9px; }
  .tabla .sub { display: block; font-size: 8px; color: #666; margin-top: 1px; }
  .tabla .fecha { white-space: nowrap; }
  .vacio { color: #777; font-style: italic; margin: 0; }

  .nota { margin-top: 18px; font-size: 8px; color: #555; text-align: justify; }
</style>
</head>
<body>

@if ($fondoSrc)
  <div class="fondo"><img src="{{ $fondoSrc }}" alt=""></div>
@else
  @if ($logoSrc)
    <div class="cabecera"><img src="{{ $logoSrc }}" alt=""></div>
  @endif
  <div class="pie">
    Dirección: {{ $identidad['direccion'] ?? '' }}@if (! empty($identidad['telefono'])) · Teléfono: {{ $identidad['telefono'] }}@endif
  </div>
@endif

<div class="titulo">
  <div class="inst">{{ $mayus($identidad['nombre_oficial'] ?? '') }}</div>
  <div class="doc">HOJA DE VIDA</div>
</div>

<table class="persona">
  <tr>
    @if ($fotoSrc)
      <td class="foto"><img src="{{ $fotoSrc }}" alt=""></td>
    @endif
    <td>
      <p class="nombre">{{ $mayus($nombre_completo) }}</p>
      <p class="cedula">C.I. {{ $cedula }}</p>
      @if ($cargo_actual)
        <p class="cargo">{{ $cargo_actual }}</p>
      @endif
      @if ($unidad_actual)
        <p class="unidad">{{ $unidad_actual }}</p>
      @endif
    </td>
  </tr>
</table>

<h3>DATOS PERSONALES</h3>
@if (count($personales))
  <table class="datos">
    @foreach ($personales as $etiqueta => $valor)
      <tr><td class="et">{{ $etiqueta }}</td><td>{{ $valor }}</td></tr>
    @endforeach
  </table>
@else
  <p class="vacio">Sin datos personales registrados en el expediente.</p>
@endif

<h3>FORMACIÓN ACADÉMICA</h3>
@if (count($estudios))
  <table class="tabla">
    <thead>
      <tr>
        <th style="width: 38%;">Título</th>
        <th style="width: 32%;">Institución</th>
        <th style="width: 14%;">Período</th>
        <th style="width: 16%;">Registro SENESCYT</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($estudios as $e)
        <tr>
          <td>{{ $e['titulo'] }}@if ($e['nivel'])<span class="sub">{{ $e['nivel'] }}</span>@endif</td>
          <td>{{ $e['institucion'] }}@if ($e['extranjero'])<span class="sub">En el exterior</span>@endif</td>
          <td class="fecha">{{ $e['periodo'] }}</td>
          <td>{{ $e['senescyt'] ?: '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@else
  <p class="vacio">Sin títulos registrados en el expediente.</p>
@endif

@if (count($capacitaciones))
  <h3>CAPACITACIÓN</h3>
  <table class="tabla">
    <thead>
      <tr>
        <th style="width: 50%;">Curso o capacitación</th>
        <th style="width: 32%;">Institución</th>
        <th style="width: 18%;">Período</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($capacitaciones as $c)
        <tr>
          <td>{{ $c['titulo'] }}</td>
          <td>{{ $c['institucion'] }}</td>
          <td class="fecha">{{ $c['periodo'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

<h3>TRAYECTORIA EN LA INSTITUCIÓN</h3>
@if ($regimen || $ingreso)
  <table class="datos" style="margin-bottom: 6px;">
    @if ($regimen)<tr><td class="et">Régimen laboral</td><td>{{ $regimen }}</td></tr>@endif
    @if ($ingreso)<tr><td class="et">Ingreso a la institución</td><td>{{ $ingreso }}</td></tr>@endif
  </table>
@endif
@if (count($trayectoria))
  <table class="tabla">
    <thead>
      <tr>
        <th style="width: 30%;">Puesto</th>
        <th style="width: 32%;">Unidad administrativa</th>
        <th style="width: 18%;">Modalidad</th>
        <th style="width: 10%;">Desde</th>
        <th style="width: 10%;">Hasta</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($trayectoria as $t)
        <tr>
          <td>{{ $t['puesto'] ?? '—' }}</td>
          <td>{{ $t['unidad'] ?? '—' }}</td>
          <td>{{ $t['nombramiento'] ?? '—' }}</td>
          <td class="fecha">{{ $t['desde'] ?? '—' }}</td>
          <td class="fecha">{{ $t['hasta'] ?? 'Actualidad' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@else
  <p class="vacio">Sin vínculos registrados en el expediente.</p>
@endif

@if (count($subrogaciones))
  <h3>SUBROGACIONES Y ENCARGOS</h3>
  <table class="tabla">
    <thead>
      <tr>
        <th style="width: 14%;">Tipo</th>
        <th style="width: 30%;">Puesto</th>
        <th style="width: 36%;">Unidad administrativa</th>
        <th style="width: 10%;">Desde</th>
        <th style="width: 10%;">Hasta</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($subrogaciones as $s)
        <tr>
          <td>{{ $s['tipo'] ?? '—' }}</td>
          <td>{{ $s['puesto'] ?? '—' }}</td>
          <td>{{ $s['unidad'] ?? '—' }}</td>
          <td class="fecha">{{ $s['desde'] ?? '—' }}</td>
          <td class="fecha">{{ $s['hasta'] ?? 'Actualidad' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

<p class="nota">
  Generada el {{ now()->format('d/m/Y') }} desde el Portal del Servidor con los datos del
  expediente. Es un documento informativo: no reemplaza al certificado laboral, que emite
  la Dirección de Administración de Talento Humano.
</p>

</body>
</html>
