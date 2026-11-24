@php
  /*
  | El permiso impreso: dos copias de media hoja A4, una para el servidor y otra
  | para Talento Humano, que se separan por el corte.
  |
  | Desde el 2026-11-24 en el estilo de los demás documentos institucionales
  | (la acción de personal, las actas del inventario, los de viáticos): el verde
  | institucional, el membrete de Comunicación Social a nombre de la Dirección
  | de Talento Humano y el pie con la dirección y el teléfono. Antes llevaba su
  | propia paleta, recuadros redondeados, una marca de agua y el nombre de la
  | institución escrito a mano. Ningún encabezado dice «Asistencia» (pedido de
  | Talento Humano).
  */
  $tipoVal = $permiso->tipo instanceof \App\Enums\TipoPermiso
    ? $permiso->tipo->value : (string) $permiso->tipo;

  $subtipo = [
    'personal'   => 'Personal · hasta 4 horas',
    'oficial'    => 'Oficial',
    'enfermedad' => 'Por enfermedad',
    'calamidad'  => 'Calamidad doméstica',
  ][$tipoVal] ?? $tipoVal;

  $nombreDe = fn ($s) => $s
    ? mb_strtoupper(implode(' ', array_filter([$s->apellido, $s->segundo_apellido, $s->nombre, $s->segundo_nombre])), 'UTF-8')
    : '';
  $nombreServidor = $nombreDe($permiso->servidor);
  $nombreJefe     = $nombreDe($permiso->jefe);
  $cargoServidor  = $permiso->servidor?->puesto?->cargo?->nombre;

  // Cuando el permiso se dirige a Talento Humano firma su jefe, no el
  // inmediato. Se lee de la columna y no comparando ids: el jefe de Talento
  // Humano es también el inmediato de quienes trabajan en esa unidad.
  $dirigidoATh = (bool) ($permiso->dirigido_a_talento_humano ?? false);
  $rotuloJefe  = $dirigidoATh ? 'Jefe de Talento Humano' : 'Jefe inmediato';
  $cargoJefe   = $dirigidoATh
    ? \App\Enums\RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO->cargoPorDefecto()
    : ($permiso->jefe?->puesto?->cargo?->nombre ?? 'Jefe inmediato');

  // Los permisos antiguos no guardaban unidad: se toma la del servidor.
  $unidad = $permiso->unidadAdministrativa->nombre
    ?? $permiso->servidor->unidadAdministrativa->nombre
    ?? '—';

  // Día/mes/año, como en la pantalla. Talento Humano quiere ver la fecha del
  // permiso, la de creación con su hora y, aparte, las horas pedidas.
  $fechaPermiso  = \Carbon\Carbon::parse($permiso->fecha)->format('d/m/Y');
  $fechaCreacion = \Carbon\Carbon::parse($permiso->created_at ?? now())->format('d/m/Y H:i');
  $horaInicio    = \Carbon\Carbon::parse($permiso->hora_inicio)->format('H:i');
  $horaFin       = \Carbon\Carbon::parse($permiso->hora_fin)->format('H:i');

  // Quien imprime no siempre puede leer el motivo: un permiso por enfermedad
  // lleva un dato de salud y uno personal, un asunto privado. La decisión la
  // toma la policy (`verObservacion`) y llega resuelta desde el controlador;
  // aquí no se razona. Si alguien renderiza la vista sin pasarla, se calla.
  $observacion = ($mostrarObservacion ?? false)
    ? ($permiso->observacion ?: 'Sin observaciones.')
    : null;

  // El motivo admite hasta 1000 caracteres y cada copia tiene media hoja: si
  // creciera, empujaría la segunda copia a otra página. Lo que se ajusta es la
  // letra: lo habitual sale al tamaño de siempre y los 1000 caben a 7 px.
  $largoObs = mb_strlen((string) $observacion, 'UTF-8');
  $tamObs   = $largoObs <= 220 ? 10 : ($largoObs <= 500 ? 8.5 : 7);

  $folio = $permiso->folio ?? 'S/N';

  // El QR es la herramienta de Talento Humano: se escanea el papel firmado y se
  // confirma o se rechaza ahí mismo. Lleva a la pantalla del permiso, no a la
  // API: quien escanea es una persona y necesita botones, no JSON. En SVG para
  // no depender de Imagick.
  $qrSrc = null;
  try {
    $urlQr = rtrim(config('app.frontend_url'), '/') . "/sgth/asistencia/permisos/{$folio}";
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($urlQr);
    $qrSrc = $qrSvg ? 'data:image/svg+xml;base64,' . base64_encode($qrSvg) : null;
  } catch (\Exception $e) {
    $qrSrc = null;
  }

  // Sin marca de agua: cada copia dice de quién es en su pie.
  $copias = ['Copia del servidor', 'Copia de Talento Humano'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Permiso {{ $folio }}</title>
<style>
  /* La hoja, partida en dos mitades exactas: la de arriba mide medio A4 y el
     corte va en su borde, así que se dobla la hoja y se corta por el doblez.
     La página no lleva margen (cada copia mide el suyo) y la de abajo no tiene
     altura fija: dos mitades de 148,5 mm exactos pueden desbordar por redondeo
     y sacar una hoja en blanco. Por lo mismo el membrete no va fijo, como en
     los otros documentos: hay uno por copia. */
  @page { margin: 0; }
  body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10px; line-height: 1.4; color: #1c2321; margin: 0; }

  .mitad-superior { position: relative; height: 148.5mm; }
  .copia { padding: 9mm 14mm 0 14mm; }

  .membrete { margin-bottom: 7px; }
  .membrete table { width: 100%; border-collapse: collapse; }
  .membrete td { border: none; padding: 0; vertical-align: middle; }
  .membrete img { max-height: 12mm; }
  .membrete .institucion { text-align: right; font-size: 8.5px; color: #5b6b63; text-transform: uppercase; letter-spacing: 0.3px; }
  .membrete .institucion strong { display: block; font-size: 10px; color: #17593f; letter-spacing: 0; margin-bottom: 1px; }

  .titulo { text-align: center; margin: 0 0 7px 0; }
  .titulo h1 { font-size: 13.5px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; margin: 0; color: #17593f; }
  .titulo .subtipo { font-size: 10px; color: #3d4d46; margin-top: 1px; }
  .titulo .regla { border-bottom: 1.6px solid #17593f; width: 48px; margin: 4px auto 0 auto; }

  .cintillo { margin-bottom: 7px; }
  .cintillo .codigo { border: 1px solid #17593f; border-radius: 2px; padding: 3px 8px; font-weight: bold; font-size: 10.5px; color: #17593f; letter-spacing: 0.5px; }

  .cuerpo { width: 100%; border-collapse: collapse; }
  .cuerpo > tbody > tr > td { border: none; padding: 0; vertical-align: top; }

  table.datos { width: 100%; border-collapse: collapse; }
  table.datos td { border: 0.7px solid #c6d2cc; padding: 3.5px 7px; font-size: 9.5px; vertical-align: middle; }
  table.datos td.label { font-weight: bold; font-size: 7.5px; letter-spacing: 0.5px; text-transform: uppercase; color: #3d4d46; background: #f2f6f4; width: 21%; white-space: nowrap; }
  table.datos td.fuerte { font-weight: bold; }

  .qr { text-align: center; padding-left: 8px; }
  .qr img { width: 21mm; height: 21mm; }
  .qr .ayuda { font-size: 7px; color: #5b6b63; margin-top: 2px; line-height: 1.25; }

  .bloque-titulo { font-size: 8px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; color: #17593f; border-bottom: 0.7px solid #cfdad4; padding-bottom: 2px; margin: 8px 0 4px 0; }
  .motivo { min-height: 9mm; text-align: justify; }
  .reservado { color: #5b6b63; font-style: italic; }

  /* El rótulo va arriba: el espacio para firmar queda antes de la línea, y las
     tres líneas caen a la misma altura aunque un nombre ocupe dos renglones. */
  .firmas { width: 100%; border-collapse: collapse; margin-top: 16px; }
  .firmas td { width: 33.3%; text-align: center; vertical-align: top; border: none; padding: 0 7px; }
  .firma-rotulo { font-size: 7.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #6b7c74; margin-bottom: 34px; }
  .firma-linea { border-top: 0.9px solid #1c2321; width: 92%; margin: 0 auto 3px auto; }
  .firma-nombre { font-weight: bold; font-size: 8.5px; }
  .firma-cargo { font-size: 8px; color: #3d4d46; margin-top: 1px; }
  .firma-vacia { font-size: 8px; color: #9aa8a1; }

  .pie { margin-top: 8px; border-top: 0.7px solid #cfdad4; padding-top: 3px; font-size: 7.5px; color: #5b6b63; }
  .pie table { width: 100%; border-collapse: collapse; }
  .pie td { border: none; padding: 0; }
  .pie .copia-de { text-align: right; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #17593f; }

  /* Sin tijeras: el símbolo no está en todas las fuentes y salía «?». */
  .corte { position: absolute; top: 148.5mm; left: 0; width: 100%; border-top: 0.8px dashed #9aa8a1; }
  .corte-texto { position: absolute; top: 147.3mm; left: 0; width: 100%; text-align: center; font-size: 7px; color: #6b7c74; letter-spacing: 2px; }
  .corte-texto span { background: #fff; padding: 0 8px; }
</style>
</head>
<body>
@foreach($copias as $copia)
@if($loop->first)<div class="mitad-superior">@endif
<div class="copia">

  @include('pdf._membrete-institucional', ['dependencia' => 'Dirección de Talento Humano'])

  <div class="titulo">
    <h1>Concesión de permiso</h1>
    <div class="subtipo">{{ $subtipo }}</div>
    <div class="regla"></div>
  </div>

  <div class="cintillo"><span class="codigo">{{ $folio }}</span></div>

  <table class="cuerpo">
    <tr>
      <td>
        <table class="datos">
          <tr>
            <td class="label">Servidor</td>
            <td class="fuerte" colspan="3">{{ $nombreServidor }}</td>
          </tr>
          <tr>
            <td class="label">Unidad</td>
            <td colspan="3">{{ $unidad }}</td>
          </tr>
          <tr>
            <td class="label">Fecha del permiso</td>
            <td class="fuerte" style="width: 30%;">{{ $fechaPermiso }}</td>
            <td class="label">Fecha de creación</td>
            <td>{{ $fechaCreacion }}</td>
          </tr>
          <tr>
            <td class="label">Hora de inicio</td>
            <td class="fuerte">{{ $horaInicio }}</td>
            <td class="label">Hora de fin</td>
            <td class="fuerte">{{ $horaFin }}</td>
          </tr>
        </table>
      </td>
      <td class="qr" style="width: 25mm;">
        @if($qrSrc)
          <img src="{{ $qrSrc }}" alt="QR {{ $folio }}">
          <div class="ayuda">Escanee para abrir el permiso en el SGTH</div>
        @endif
      </td>
    </tr>
  </table>

  <div class="bloque-titulo">Motivo</div>
  @if($observacion !== null)
    <div class="motivo" style="font-size: {{ $tamObs }}px;">{{ $observacion }}</div>
  @else
    <div class="motivo reservado">Reservado: es un dato privado del servidor. Talento Humano lo consulta en el sistema.</div>
  @endif

  <table class="firmas">
    <tr>
      <td>
        <div class="firma-rotulo">Autoriza · {{ $rotuloJefe }}</div>
        <div class="firma-linea"></div>
        {{-- Sin jefe asignado queda el espacio, para que las tres firmas no se
             descuadren, y el cargo dice quién debe firmar. --}}
        <div class="firma-nombre">@if($nombreJefe !== ''){{ $nombreJefe }}@else&nbsp;@endif</div>
        <div class="firma-cargo">{{ $cargoJefe }}</div>
      </td>
      <td>
        <div class="firma-rotulo">Solicita · Servidor</div>
        <div class="firma-linea"></div>
        <div class="firma-nombre">{{ $nombreServidor }}</div>
        <div class="firma-cargo">{{ $cargoServidor ?? 'Servidor' }}</div>
      </td>
      <td>
        {{-- Los papeles los recibe Talento Humano (el rol de Recepción ya no se
             asigna): quien lo reciba escribe su nombre y la fecha. --}}
        <div class="firma-rotulo">Recibe · Talento Humano</div>
        <div class="firma-linea"></div>
        <div class="firma-vacia">Nombre, firma y fecha de recepción</div>
      </td>
    </tr>
  </table>

  <div class="pie">
    <table>
      <tr>
        <td>
          @if(($identidad['direccion'] ?? '') !== '')
            {{ rtrim($identidad['direccion'], '.') }} · Teléfono {{ $identidad['telefono'] ?? '' }}
          @endif
        </td>
        <td class="copia-de">{{ $copia }}</td>
      </tr>
    </table>
  </div>
</div>
@if($loop->first)
  <div class="corte"></div>
  <div class="corte-texto"><span>CORTAR AQUÍ</span></div>
</div>
@endif
@endforeach
</body>
</html>
