@php
  // El membrete institucional: logo arriba, marca de agua y dirección al pie.
  // Va de fondo en todas las hojas; los márgenes de la página dejan libres el
  // logo y la dirección. Es la hoja membretada de Identidad institucional
  // (V6, fase 2.2); si no hay, el sistema pone el logo arriba y la dirección
  // al pie en los mismos márgenes.
  $fondoPath = $hojaMembretada ?? '';
  $fondoSrc  = $fondoPath !== '' && file_exists($fondoPath)
    ? 'data:'.(mime_content_type($fondoPath) ?: 'image/jpeg').';base64,'.base64_encode(file_get_contents($fondoPath))
    : null;
  $logoSrc = ! $fondoSrc && ($logo ?? '') !== '' && file_exists($logo)
    ? 'data:'.(mime_content_type($logo) ?: 'image/png').';base64,'.base64_encode(file_get_contents($logo))
    : null;
  $mayus  = fn (?string $t) => mb_strtoupper(trim((string) $t), 'UTF-8');
  $nombre = $mayus(implode(' ', array_filter([
    $servidor->apellido, $servidor->segundo_apellido, $servidor->nombre, $servidor->segundo_nombre,
  ])));
  $total = $actividades->count();
  // DE y PARA dicen lo mismo que las firmas: el nombre y el cargo de cada uno.
  $cargoServidor = $mayus($cargo ?? 'Servidor');
  $nombreJefe    = $jefe['nombre'] ? $mayus($jefe['nombre']) : null;
  $cargoJefe     = $mayus($jefe['cargo']);
  // «EL 12 DE OCTUBRE…» es un solo día; «DEL … AL …», un rango.
  $enElPeriodo = str_starts_with($periodo, 'DEL ') ? 'en el período comprendido ' : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Informe de actividades laborales — {{ $nombre }}</title>
<style>
  /* Sin `* { margin: 0 }`: en dompdf también borra el margen de la página y
     `@page` no lo recupera. Arriba, el alto del logo del membrete; abajo, el
     de la dirección. */
  @page { margin: 42mm 20mm 26mm 20mm; }
  body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #222; line-height: 1.45; }

  /* Fijo y con desplazamientos negativos hasta el borde de la hoja: así cubre
     la página entera, márgenes incluidos, y se repite en cada hoja. */
  .fondo { position: fixed; top: -42mm; left: -20mm; width: 210mm; height: 297mm; z-index: -1; }
  .fondo img { width: 210mm; height: 297mm; }

  /* Sin hoja membretada: el logo y la dirección en los mismos márgenes. */
  .cabecera { position: fixed; top: -34mm; left: 0; right: 0; text-align: center; }
  .cabecera img { max-height: 24mm; max-width: 120mm; }
  .pie {
    position: fixed; bottom: -16mm; left: 0; right: 0; text-align: center;
    border-top: 1px solid #8a8a8a; padding-top: 4px; font-size: 8.5px; color: #444;
  }

  .titulo { text-align: center; margin: 0 0 14px; }
  .titulo .inst { font-size: 11px; font-weight: bold; letter-spacing: 0.3px; }
  .titulo .doc { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; margin-top: 3px; }

  .memo { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  .memo td { padding: 3px 0; vertical-align: top; }
  .memo .et { width: 17%; font-weight: bold; }
  .memo .dos { width: 2%; font-weight: bold; }
  .memo .valor { font-weight: bold; }
  .memo .sub { font-size: 8.5px; font-style: italic; font-weight: normal; color: #555; }

  .separador { border: 0; border-top: 1px solid #bbb; margin: 6px 0 12px; }
  p { text-align: justify; margin: 0 0 10px; }
  h3 { font-size: 11px; margin: 14px 0 6px; letter-spacing: 0.3px; }

  .total { background: #8a8a8a; color: #fff; font-size: 9px; padding: 4px 8px; }
  .detalle { width: 100%; border-collapse: collapse; }
  .detalle th {
    background: #8a8a8a; color: #fff; font-size: 9px; padding: 5px 8px;
    text-align: center; border: 1px solid #fff;
  }
  .detalle td { padding: 6px 8px; border: 1px solid #ccc; vertical-align: top; background: #fff; }
  .detalle tr:nth-child(even) td { background: #f1f1f1; }
  .detalle .fecha { width: 19%; text-align: center; white-space: nowrap; }
  .detalle .categoria { display: block; font-size: 8px; color: #666; margin-top: 2px; white-space: normal; }
  .detalle .producto { display: block; font-size: 8.5px; font-style: italic; color: #555; margin-top: 3px; }
  .vacio { text-align: center; color: #777; padding: 14px; }

  /* Una fila sola: dompdf no parte una fila de tabla entre dos hojas. */
  .firmas { width: 100%; margin-top: 60px; border-collapse: collapse; }
  .firmas td { width: 50%; text-align: center; vertical-align: top; padding: 0 16px; }
  .firmas .linea { border-top: 1px solid #222; width: 210px; margin: 0 auto 4px; }
  .firmas .cargo { font-size: 8.5px; color: #444; }
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
  <div class="doc">INFORME DE ACTIVIDADES LABORALES</div>
</div>

<table class="memo">
  <tr>
    <td class="et">DE</td><td class="dos">:</td>
    <td class="valor">
      {{ $nombre }}
      <br><span class="sub">{{ $cargoServidor }}</span>
    </td>
  </tr>
  <tr>
    <td class="et">PARA</td><td class="dos">:</td>
    <td class="valor">
      {{-- Sin titular ni encargo registrado: el cargo sin nombre, antes que
           atribuirle a alguien un informe que no recibió. --}}
      @if ($nombreJefe)
        {{ $nombreJefe }}
        <br><span class="sub">{{ $cargoJefe }}</span>
      @else
        {{ $cargoJefe }}
      @endif
    </td>
  </tr>
  <tr>
    <td class="et">PERÍODO</td><td class="dos">:</td>
    <td>{{ $periodo }}</td>
  </tr>
  <tr>
    <td class="et">ASUNTO</td><td class="dos">:</td>
    <td class="valor">INFORME DE ACTIVIDADES LABORALES</td>
  </tr>
  <tr>
    <td class="et">FECHA</td><td class="dos">:</td>
    <td>ESMERALDAS, {{ $mayus(now()->locale('es')->translatedFormat('d \d\e F \d\e Y')) }}</td>
  </tr>
</table>

<hr class="separador">

<p>
  Por medio del presente, pongo en su conocimiento las actividades laborales que realicé
  {{ $enElPeriodo }}<b>{{ $periodo }}</b>@if ($cargo) como {{ $cargo }}@endif
  @if ($unidad) en <b>{{ $mayus($unidad) }}</b>@endif.
  Se detallan a continuación en orden cronológico, para su revisión y constancia.
  {{-- Un informe filtrado tiene que decirlo: si no, parece el período entero. --}}
  @if ($categoria || $buscar)
    Este informe incluye solo las actividades
    {{ collect([$categoria ? "de la categoría «{$categoria}»" : null, $buscar ? "que mencionan «{$buscar}»" : null])->filter()->join(' y ') }}.
  @endif
</p>

<h3>DETALLE DE ACTIVIDADES REALIZADAS</h3>
<div class="total">Total de actividades registradas: <b>{{ $total }}</b></div>
<table class="detalle">
  <thead>
    <tr>
      <th>FECHA</th>
      <th>DESCRIPCIÓN DE LA ACTIVIDAD</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($actividades as $a)
      <tr>
        <td class="fecha">
          {{ $a->fecha->format('d/m/Y') }}
          <span class="categoria">{{ $a->categoria->etiqueta() }}</span>
        </td>
        <td>
          {!! nl2br(e($a->descripcion)) !!}
          @if ($a->producto_entregable)
            <span class="producto">Producto o entregable: {{ $a->producto_entregable }}</span>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="2" class="vacio">No hay actividades anotadas en el período.</td></tr>
    @endforelse
  </tbody>
</table>

<h3>CONCLUSIÓN</h3>
<p>
  Las actividades detalladas corresponden a las funciones de mi puesto y se realizaron
  {{ $enElPeriodo }}<b>{{ $periodo }}</b>. Quedo a su disposición para cualquier aclaración o información
  adicional que considere necesaria.
</p>

<p>Atentamente,</p>

<table class="firmas">
  <tr>
    <td>
      <div class="linea"></div>
      <div><b>{{ $nombre }}</b></div>
      <div class="cargo">{{ $cargoServidor }}</div>
    </td>
    <td>
      <div class="linea"></div>
      <div><b>{{ $nombreJefe ?? ' ' }}</b></div>
      <div class="cargo">{{ $cargoJefe }}</div>
    </td>
  </tr>
</table>

</body>
</html>
