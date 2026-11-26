@php
  /*
  | La solicitud de vacaciones impresa, en el estilo de los demás documentos
  | institucionales (la acción de personal, las actas del inventario, los de
  | viáticos): verde institucional, membrete de Comunicación Social a nombre de
  | la Dirección de Talento Humano y pie con la dirección y el teléfono.
  |
  | Es la hoja que el servidor saca estando la solicitud AUTORIZADA, hace firmar
  | por su jefe inmediato y baja a la ventanilla de Talento Humano, donde la
  | firman además la Dirección de Talento Humano y Prefectura. Se imprime una
  | sola vez y esa misma hoja se archiva.
  |
  | Por eso NO lleva el estado del sistema: una etiqueta envejecería dentro del
  | expediente y diría «pendiente» de algo que el jefe ya concedió. Lo que vale
  | son las cuatro firmas, y la fecha de impresión deja constancia de cuándo se
  | sacó. El aviso de abajo no narra el trámite: impide que una hoja sin valor
  | circule como buena.
  |
  | Lleva marco desde el 2026-11-25 (segunda vuelta): encierra el cuerpo y deja
  | fuera el membrete y el pie, que hacen de cabecera y pie de página. Con él,
  | las secciones pasaron de rótulo subrayado a banda con caja y las cuatro
  | firmas y la nota final entraron en recuadro; sueltos dentro del marco se
  | leían como un descuido.
  |
  | Cambió el 2026-11-25. Antes era carta —el único exportable del sistema que
  | no era A4—, con el nombre institucional escrito a mano y equivocado, una
  | grilla de once motivos con casillas de los que solo dos se pueden pedir
  | (`MotivoVacacion::seSolicita()`), casillas «Aprobado/Negado» en blanco
  | aunque el estado se conociera, y una firma de «Director», rol que se fundió
  | en jefe-unidad. No imprimía la cédula, el cargo ni la unidad, que el
  | controlador ya cargaba, y la observación salía en mayúsculas.
  */
  $nombreDe = fn ($s) => $s
    ? trim(implode(' ', array_filter([$s->apellido, $s->segundo_apellido, $s->nombre, $s->segundo_nombre])))
    : '';

  $nombreServidor  = $nombreDe($vacacion->servidor);
  $nombreJefe      = $nombreDe($vacacion->jefe);
  $nombreReemplaza = $nombreDe($vacacion->personaReemplaza);

  $cargoServidor = $vacacion->servidor?->puesto?->cargo?->nombre;
  $cargoJefe     = $vacacion->jefe?->puesto?->cargo?->nombre;

  // Las que concedió el Consejo llevan su resolución (fase 4.1d).
  $porResolucion = filled($vacacion->resolucion_numero);

  // Las solicitudes antiguas no guardaban unidad: se toma la del servidor.
  $unidad = $vacacion->unidadAdministrativa?->nombre
    ?? $vacacion->servidor?->unidadAdministrativa?->nombre
    ?? '—';

  $fecha = fn ($f) => $f ? \Illuminate\Support\Carbon::parse($f)->format('d/m/Y') : '—';

  $lugarFecha = 'Esmeraldas, '
    .($vacacion->fecha_emision ? \Illuminate\Support\Carbon::parse($vacacion->fecha_emision) : now())
      ->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

  $motivoEnum = $vacacion->motivo instanceof \App\Enums\MotivoVacacion
    ? $vacacion->motivo
    : \App\Enums\MotivoVacacion::tryFrom((string) ($vacacion->motivo ?? ''));

  $estadoVal  = (string) $vacacion->estado;
  $sinValidez = $estadoVal === \App\Enums\EstadoVacacion::ANULADA->value;

  // La frase del aviso se arma aquí y no en el cuerpo: encadenar `@if` sin
  // espacios para que no sobren blancos antes del punto es lo que rompe la
  // plantilla.
  $detalleAnulacion = 'Esta solicitud está anulada';
  if ($vacacion->anulado_en) {
      $detalleAnulacion .= ' desde el '.\Illuminate\Support\Carbon::parse($vacacion->anulado_en)->format('d/m/Y');
  }
  if (filled($vacacion->motivo_anulacion)) {
      // El motivo suele venir con punto y quedaban dos.
      $detalleAnulacion .= ', por este motivo: '.rtrim(trim($vacacion->motivo_anulacion), '.');
  }
  $detalleAnulacion .= '.';

  $sinCeros = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

  // El QR lleva a la solicitud en el SGTH, no a la API: quien escanea es una
  // persona y necesita la pantalla. En SVG para no depender de Imagick.
  $qrSrc = null;
  try {
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($urlQr);
    $qrSrc = $qrSvg ? 'data:image/svg+xml;base64,'.base64_encode($qrSvg) : null;
  } catch (\Exception $e) {
    $qrSrc = null;
  }

  $tipoDias = $vacacion->tipo_dias === 'habiles' ? 'hábiles' : 'calendario';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Solicitud de vacaciones {{ $vacacion->folio ?? '' }}</title>
@include('pdf._estilos-documento')
<style>
  /* Lo propio de esta hoja; el resto es el estilo de casa. */

  .cintillo .impreso { font-size: 8px; color: #6b7c74; margin-top: 1px; }

  /* El marco de la hoja. Encierra solo el cuerpo: uno que abarcara también
     el membrete tendría que rodear el logo, que arranca a 5 mm del borde del
     papel, y su raya de arriba caería en el milímetro 4 —hay impresoras de
     oficina que no imprimen los primeros 5 y se la comerían por un lado sí y
     por otro no.

     Las medidas salen de la hoja, no del ojo: A4 es 210×297 mm y los márgenes
     de `@page` son 26/14/20/14, así que la caja de contenido va de x=14 a
     x=196 y de y=26 a y=277. El marco se sitúa respecto de ella con
     desplazamientos negativos y queda de y=24 —2 mm por debajo del membrete—
     a y=273, y de x=8 a x=202. Ojo con el filete del pie: `bottom: -14mm` no
     lo baja 14 mm por debajo de la caja de contenido, lo deja justo en su
     borde inferior, y un marco calculado sin mirar el PDF le cruza el renglón
     de la dirección.

     Va `position: fixed` porque es lo que dompdf repite en todas las hojas,
     igual que el membrete y el pie. */
  .marco {
    position: fixed;
    top: -2mm;
    left: -6mm;
    width: 193.6mm;
    height: 249mm;
    border: 1.1px solid #17593f;
  }

  /* Las secciones, en banda pegada a su tabla. El borde inferior lo pone la
     banda y no la tabla de abajo porque en «Vacaciones solicitadas» la tabla
     ocupa solo el 73 % del ancho —el resto es el QR— y sin él la banda quedaba
     abierta por ese lado. */
  .bloque-titulo {
    border: 0.7px solid #c6d2cc;
    background: #eaf1ee;
    padding: 4px 8px;
    margin: 9px 0 0 0;
  }
  table.datos { margin-bottom: 0; }

  /* El QR se queda sin caja a propósito: su columna mide unos 8 mm más que la
     tabla de al lado —24 de imagen más el pie «Escanee para…»— y como las dos
     celdas de una fila comparten alto, el recuadro colgaba por debajo de la
     tabla y rompía la retícula. */

  /* La observación, en caja de alto fijo, y el alto se reserva entero escriba
     el servidor una línea o las tres: así la hoja con observación es siempre
     la misma hoja. Importa más con marco que sin él, porque el marco no crece
     con el texto —es un elemento fijo— y un cuerpo que se pase se le sale por
     abajo.

     14 mm y 300 caracteres son la misma decisión tomada dos veces: medido
     sobre el PDF, en esta caja entran unos 100 caracteres por renglón, así que
     300 son tres renglones y tres renglones son 14 mm. El tope vive en
     `StoreVacacionRequest`; si se mueve uno hay que mover el otro. */
  table.datos td.obs { height: 14mm; vertical-align: top; }

  /* El QR al costado de las fechas: se escanea el papel y se abre la
     solicitud en el sistema. */
  .cuerpo { width: 100%; border-collapse: collapse; }
  .cuerpo > tbody > tr > td { border: none; padding: 0; vertical-align: top; }
  .qr { text-align: center; padding-left: 10px; width: 27mm; }
  .qr img { width: 24mm; height: 24mm; }
  .qr .ayuda { font-size: 7.5px; color: #5b6b63; margin-top: 3px; line-height: 1.25; }

  /* Las casillas que Talento Humano llena a mano: alto suficiente para
     escribir un número y dos o tres tramos. */
  table.datos td.escribir { height: 9mm; }

  .aviso.invalida { border-left-color: #8f2f2f; background: #fdf2f2; color: #7a2727; }

  /* La nota del circuito, también en caja: suelta bajo los recuadros de firma
     y dentro del marco, parecía un pie de página perdido. */
  .nota {
    border: 0.7px solid #c6d2cc;
    background: #f8fbf9;
    padding: 6px 9px;
    margin-top: 9px;
  }

  /* `table-layout: fixed`: sin él las columnas se reparten por el contenido, y
     un cargo largo en la primera estruja a la otra hasta partirle el rótulo en
     dos líneas, con su raya más abajo. */
  /* Las firmas se despegan del último bloque: pegadas a la casilla que Talento
     Humano llena a mano, el rótulo de la primera parecía parte de esa tabla. */
  .firmas { margin-top: 10px; }
  .firmas table { table-layout: fixed; }
  /* Cada firma en su recuadro: en un papel cuyo oficio es recoger cuatro
     firmas de puño y letra, la caja dice dónde firmar. El recuadro sustituye
     al aire que antes separaba las dos filas (`padding-top: 26px`), que ahora
     sería un hueco entre cajas. */
  .firmas td {
    width: 50%;
    border: 0.7px solid #c6d2cc;
    padding: 6px 8px 8px 8px;
  }
  .firmas tr.segunda-fila td { padding-top: 6px; }
  /* El hueco entre el rótulo y la raya es donde se firma y se sella. Con los
     34 px de antes quedaban 9 mm: dan para una rúbrica, no para un sello
     encima. Con 60 son 16.

     No más, y el motivo es que el hueco se paga cuatro veces —dos filas— y la
     hoja no da para tanto. El peor caso es una solicitud anulada, que añade el
     aviso rojo de arriba, con la observación al tope: con 76 px el cuerpo se
     salía del marco y la segunda fila de firmas se iba a una segunda hoja. */
  .firmas .firma-rotulo { margin-bottom: 60px; }
</style>
</head>
<body>

{{-- El marco va el primero: es fijo y no participa del flujo. --}}
<div class="marco"></div>

@include('pdf._membrete-institucional', ['dependencia' => 'Dirección de Talento Humano'])
@include('pdf._pie-institucional')

{{-- El motivo no va aquí de subtítulo: es un dato de la solicitud y se lee en
     su bloque, junto a las fechas y los días. Ponerlo en los dos sitios sería
     decir lo mismo a tres centímetros de distancia. --}}
<div class="titulo">
  <h1>Solicitud de vacaciones</h1>
  <div class="regla"></div>
</div>

<table class="cintillo">
  <tr>
    <td><span class="codigo">{{ $vacacion->folio ?? 'S/N' }}</span></td>
    <td class="lugar-fecha">
      {{ $lugarFecha }}
      <div class="impreso">Impreso el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }}</div>
    </td>
  </tr>
</table>

@if($sinValidez)
  <div class="aviso invalida">
    <strong>Sin validez.</strong>
    {{ $detalleAnulacion }}
    No autoriza ausentarse del puesto de trabajo.
  </div>
@endif

{{-- ══ SERVIDOR ══ --}}
<div class="bloque-titulo">Servidor solicitante</div>
<table class="datos">
  <tr>
    <td class="label">Apellidos y nombres</td>
    <td class="mayus fuerte" colspan="3">{{ $nombreServidor }}</td>
  </tr>
  <tr>
    <td class="label">Cédula</td>
    <td style="width: 29%;">{{ $vacacion->servidor?->cedula ?? '—' }}</td>
    <td class="label">Fecha de ingreso</td>
    <td>{{ $fecha($vacacion->servidor?->fecha_ingreso_institucion) }}</td>
  </tr>
  <tr>
    <td class="label">Cargo</td>
    <td>{{ $cargoServidor ?? '—' }}</td>
    <td class="label">Unidad</td>
    <td>{{ $unidad }}</td>
  </tr>
</table>

{{-- ══ VACACIONES ══ --}}
<div class="bloque-titulo">Vacaciones solicitadas</div>
<table class="cuerpo">
  <tr>
    <td>
      <table class="datos">
        <tr>
          <td class="label">Motivo</td>
          <td class="fuerte" colspan="3">{{ $motivoEnum?->etiqueta() ?? '—' }}</td>
        </tr>
        <tr>
          <td class="label">Desde</td>
          <td class="fuerte" style="width: 29%;">{{ $fecha($vacacion->fecha_inicio) }}</td>
          <td class="label">Hasta</td>
          <td class="fuerte">{{ $fecha($vacacion->fecha_fin) }}</td>
        </tr>
        <tr>
          <td class="label">Días solicitados</td>
          <td class="fuerte">{{ $sinCeros((float) $vacacion->dias_solicitados) }} días {{ $tipoDias }}</td>
          <td class="label">Se reintegra el</td>
          <td class="fuerte">{{ $fecha($vacacion->fecha_retorno) }}</td>
        </tr>
        <tr>
          <td class="label">Lo reemplaza</td>
          <td class="mayus" colspan="3">{{ $nombreReemplaza !== '' ? $nombreReemplaza : 'No se asigna reemplazo' }}</td>
        </tr>
        {{-- Las de una autoridad electa las concede el Consejo Provincial por
             resolución, no su jefe inmediato (COOTAD Art. 47 s; fase 4.1d).
             Sin esta fila, el papel no diría quién las autorizó. --}}
        @if($porResolucion)
          <tr>
            <td class="label">Concedidas por</td>
            <td colspan="3">
              Resolución del Consejo Provincial N.º {{ $vacacion->resolucion_numero }} (COOTAD Art. 47 s)
            </td>
          </tr>
        @endif
      </table>
    </td>
    <td class="qr">
      @if($qrSrc)
        <img src="{{ $qrSrc }}" alt="QR {{ $vacacion->folio ?? '' }}">
        <div class="ayuda">Escanee para abrir la solicitud en el SGTH</div>
      @endif
    </td>
  </tr>
</table>

@if(trim((string) $vacacion->observacion) !== '')
  <div class="bloque-titulo">Observación</div>
  <table class="datos">
    <tr><td class="obs" style="text-align: justify;">{{ $vacacion->observacion }}</td></tr>
  </table>
@endif

{{-- ══ TALENTO HUMANO ══ --}}
{{-- Las dos casillas van en blanco, a propósito. La hoja se imprime estando
     autorizada, antes de pasar por ventanilla: en ese momento los días no han
     salido de ningún período y el saldo que quedará todavía no existe. Las
     escribe a mano quien registra, con los valores que el sistema le muestra
     al hacerlo.

     Antes había aquí «Días de derecho», que eran los días que generó el
     período de ese año: no es el saldo, así que quien veía «30» con 20 ya
     gozados creía que los tenía. Se retiró (decisión del usuario, 2026-11-25). --}}
<div class="bloque-titulo">Uso exclusivo de Talento Humano</div>
<table class="datos">
  {{-- «Se descuenta de» se escribe más largo que el saldo: ahí van uno o dos
       tramos («2026: 10 días · 2027: 2 días») y en el saldo solo un número. --}}
  <tr>
    <td class="label" style="width: 17%;">Se descuenta de</td>
    <td class="escribir" style="width: 38%;"></td>
    <td class="label" style="width: 17%;">Saldo restante</td>
    <td class="escribir"></td>
  </tr>
</table>

{{-- ══ FIRMAS ══ --}}
{{-- Las cuatro del circuito, en dos filas: en una sola, cada columna se queda
     en 45 mm y «Dirección de Talento Humano» no cabe de un renglón. Los
     rótulos son el cargo a secas, como en la solicitud de viático: ponerle un
     verbo a cada uno obliga a inventar dos «autoriza» distintos. --}}
<div class="firmas">
  <table>
    <tr>
      <td>
        <div class="firma-rotulo">Servidor solicitante</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">{{ $nombreServidor }}</p>
        <p class="firma-cargo">{{ $cargoServidor ?? 'Servidor' }}</p>
      </td>
      <td>
        {{-- A una autoridad electa no la autoriza un jefe: la autorización es
             la resolución del Consejo, y ponerle una línea de firma de «jefe
             inmediato» diría que alguien la concedió, que no es verdad. --}}
        <div class="firma-rotulo">{{ $porResolucion ? 'Consejo Provincial' : 'Jefe inmediato' }}</div>
        <div class="firma-linea"></div>
        {{-- Sin jefe asignado queda el espacio, para que las firmas no se
             descuadren, y el cargo dice quién debe firmar. --}}
        @if($porResolucion)
          <p class="firma-nombre">Resolución N.º {{ $vacacion->resolucion_numero }}</p>
          <p class="firma-cargo">Concedidas por resolución, sin firma en esta hoja</p>
        @else
          <p class="firma-nombre">@if($nombreJefe !== ''){{ $nombreJefe }}@else&nbsp;@endif</p>
          <p class="firma-cargo">{{ $cargoJefe ?? 'Jefe inmediato' }}</p>
        @endif
      </td>
    </tr>
    <tr class="segunda-fila">
      <td>
        <div class="firma-rotulo">Dirección de Talento Humano</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">@if($firmanteTh !== ''){{ $firmanteTh }}@else&nbsp;@endif</p>
        <p class="firma-cargo">{{ $cargoTh }}</p>
      </td>
      <td>
        <div class="firma-rotulo">Prefectura</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">@if($firmanteAutoridad !== ''){{ $firmanteAutoridad }}@else&nbsp;@endif</p>
        <p class="firma-cargo">{{ $cargoAutoridad }}</p>
      </td>
    </tr>
  </table>
</div>

{{-- El circuito de la hoja, dicho como un hecho y no como una orden.
     En imperativo («Entregue este documento… para su registro») solo valía
     para la primera impresión: en una reimpresión, que saca Talento Humano
     de una solicitud YA registrada, mandaba a hacer algo que ya ocurrió. Y
     condicionarlo al estado sería volver a meter el estado en el papel por
     la puerta de atrás, que es justo lo que este documento evita. Así es
     cierto antes y después, y sigue explicando el trámite a quien lo ve por
     primera vez. --}}
{{-- Y a una autoridad electa el trámite no le aplica: no tiene jefe inmediato
     que firme ni papel que bajar a ventanilla, porque la resolución del
     Consejo ya la concedió y el registro se hizo al recibirla (fase 4.1d).
     Dejar la nota de siempre contradiría todo lo de arriba. --}}
<p class="nota">
  @if($porResolucion)
    Las vacaciones de una autoridad electa las concede el Consejo Provincial por resolución
    (COOTAD Art. 47 s): esta hoja deja constancia de su registro, no se baja a firmar.
  @else
    Este documento se entrega firmado por el servidor y su jefe inmediato en la
    ventanilla de Talento Humano, que lo registra en el sistema.
  @endif
</p>

</body>
</html>
