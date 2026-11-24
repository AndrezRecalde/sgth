<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Comprobante Contable</title>
@include('pdf.viaticos._estilos')
</head>
<body>

@include('pdf.viaticos._membrete')

<div class="titulo">
  <div class="supra">Pago definitivo</div>
  <h1>Comprobante Contable</h1>
  <div class="subtipo">Liquidación de viático</div>
  <div class="regla"></div>
</div>

@php
  $fechaCierre = $viatico->liquidacion?->fecha_contabilizacion
      ? \Illuminate\Support\Carbon::parse($viatico->liquidacion->fecha_contabilizacion)
      : now();
@endphp
<table class="cintillo">
  <tr>
    <td><span class="codigo">{{ $viatico->codigo_viatico }}</span></td>
    {{-- «5 de diciembre de 2026», no «diciembre 5, 2026». --}}
    <td class="lugar-fecha">{{ 'Esmeraldas, '.$fechaCierre->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</td>
  </tr>
</table>

{{-- ══ BENEFICIARIO ══ --}}
<div class="bloque-titulo">Beneficiario</div>
<table class="datos">
  <tr>
    <td class="label">A favor de</td>
    <td class="mayus fuerte">{{ $servidorNombre }}</td>
    <td class="label">Cédula</td>
    <td>{{ $viatico->servidor?->cedula ?? '—' }}</td>
  </tr>
  {{-- Las dos cifras en letras: la única que había era la liquidada, de modo
       que el papel decía «ciento sesenta dólares» mientras se transferían
       cuarenta y ocho, y en un documento de pago prevalece la que está en
       letras (Gestión Financiera, 2026-11-18, D3). --}}
  <tr>
    <td class="label">Valor liquidado</td>
    <td class="fuerte" colspan="3">
      {{ $totalLetras }} — $ {{ number_format($calculo['reconocido'], 2) }}
    </td>
  </tr>
  @php $saldo = (float) $calculo['saldo']; @endphp
  <tr>
    <td class="label">
      @if($saldo > 0) A pagar
      @elseif($saldo < 0) A devolver
      @else Saldo
      @endif
    </td>
    <td class="fuerte" colspan="3">
      {{ $saldoLetras }} — $ {{ number_format(abs($saldo), 2) }}
    </td>
  </tr>
  <tr>
    {{-- El respaldo que asigna Financiero: el número del comprobante de pago
         que emite su propio sistema, confirmado el 2026-11-23 (S1). --}}
    <td class="label">Comprobante de pago</td>
    <td>{{ $viatico->numero_resolucion ?: '—' }}</td>
    <td class="label">Partida</td>
    <td>
      {{ $viatico->partidaPresupuestaria?->codigo ?: '—' }}
      {{-- El «TIPO» es la etapa del pago, no la modalidad del anticipo: este
           documento es siempre el definitivo (2026-11-23, S2). --}}
      · Tipo {{ $modalidadLabel }}
    </td>
  </tr>
</table>

{{-- ══ DETALLE ══ --}}
<div class="bloque-titulo">Detalle</div>
@php
  $salida  = $viatico->datetime_salida ? \Carbon\Carbon::parse($viatico->datetime_salida) : null;
  $llegada = $viatico->datetime_llegada ? \Carbon\Carbon::parse($viatico->datetime_llegada) : null;

  // El destino es a dónde fue, no el último tramo: ese es el regreso, y
  // la frase decía que el servidor se trasladó a la ciudad de la que salió.
  $ciudadDestino = mb_strtoupper($destinoCiudades, 'UTF-8');

  /*
  | «del 1 al 3 de diciembre de 2026», y con el mes nombrado dos veces si el
  | viaje lo cruza: antes salía «del 01 al 3 de diciembre del 2026», con el
  | cero de más, el «del» por «de» y, en un viaje del 30 de noviembre al 2 de
  | diciembre, sin noviembre por ningún lado.
  */
  $fechas = '—';
  if ($salida && $llegada) {
      $mismoMes = $salida->isSameMonth($llegada) && $salida->year === $llegada->year;
      $fechas = $mismoMes
          ? 'del '.$salida->day.' al '.$llegada->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
          : 'del '.$salida->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            .' al '.$llegada->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
  }
@endphp
<p class="parrafo">
  Por <strong>{{ $calculo['noches'] }}</strong> noche(s) de viáticos a favor del señor(ra)
  <strong>{{ $servidorNombre }}</strong>, quien se trasladó a la ciudad de
  <strong>{{ $ciudadDestino }}</strong> {{ $fechas }}, para
  <strong>{{ mb_strtoupper($viatico->justificacion ?? '—', 'UTF-8') }}</strong>
</p>

{{--
| ══ VALORES JUSTIFICADOS ══
|
| Las filas salen de las categorías que el servidor usó, no de una lista
| escrita a mano: antes se imprimían seis y se sumaban ocho, así que el total
| no coincidía con lo que se veía.
--}}
@php
  $bloques = [
    ['titulo' => 'Viáticos (hospedaje y alimentación)', 'datos' => $facturasPorCategoria['viatico']],
    ['titulo' => 'Movilización',                        'datos' => $facturasPorCategoria['movilizacion']],
  ];
@endphp
<div class="bloque-titulo">Valores justificados</div>
<table class="listado">
  <thead>
    <tr><th>Concepto</th><th class="num" style="width:20%">Valor</th></tr>
  </thead>
  <tbody>
    @foreach($bloques as $bloque)
      @forelse($bloque['datos']['filas'] as $fila)
      <tr>
        <td>{{ $fila['nombre'] }}</td>
        <td class="num">$ {{ number_format($fila['total'], 2) }}</td>
      </tr>
      @empty
      <tr>
        <td class="vacio">Sin comprobantes en este grupo</td>
        <td class="num">$ 0.00</td>
      </tr>
      @endforelse
      <tr class="total">
        <td>{{ $bloque['titulo'] }}</td>
        <td class="num">$ {{ number_format($bloque['datos']['total'], 2) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

{{-- ══ LIQUIDACIÓN ══ --}}
<div class="bloque-titulo">Liquidación</div>
<table class="cifras">
  <tr>
    <td>Viático de {{ $calculo['noches'] }} noche(s)</td>
    <td class="val">$ {{ number_format($calculo['derecho'], 2) }}</td>
  </tr>
  <tr>
    <td>(a) Reconocido sin comprobante (30 %)</td>
    <td class="val">$ {{ number_format($calculo['reconocido_sin_comprobante'], 2) }}</td>
  </tr>
  <tr>
    <td>(b) Justificado con comprobantes, hasta el 70 %</td>
    <td class="val">$ {{ number_format($calculo['justificado'], 2) }}</td>
  </tr>
  <tr class="total">
    <td>Valor liquidado (a) + (b)</td>
    <td class="val">$ {{ number_format($calculo['reconocido'], 2) }}</td>
  </tr>
  @php $mod = $modalidadValue ?? ((string) $viatico->modalidad_anticipo); @endphp
  <tr>
    <td>
      {{ $mod === 'sin_anticipo' ? 'Sin anticipo' : '(c) Anticipo entregado'.$anticipoDetalle }}
    </td>
    <td class="val">$ {{ number_format($calculo['anticipo'], 2) }}</td>
  </tr>
  <tr class="cierre">
    <td>
      {{ $saldo < 0 ? 'A devolver a la institución' : 'A pagar al servidor' }} (a) + (b) − (c)
    </td>
    <td class="val">$ {{ number_format(abs($saldo), 2) }}</td>
  </tr>
</table>

<div class="nota">
  Anexos: solicitud e informe de licencia con remuneración, con las facturas de respaldo.
  El valor de combustible lo justifica el chofer del vehículo institucional.
  Valores en dólares de los Estados Unidos de América.
</div>

{{-- ══ FIRMAS ══ --}}
<div class="firmas">
  <table>
    <tr>
      <td style="width:50%">
        <div class="firma-rotulo">Elaborado por</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">{{ $elaborado['nombre'] ?? '' }}</p>
        <p class="firma-cargo">{{ $elaborado['cargo'] ?? 'Gestión Financiera' }}</p>
      </td>
      <td style="width:50%">
        <div class="firma-rotulo">Aprobado por</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">{{ $firmas['director_financiero']['nombre'] ?? '' }}</p>
        <p class="firma-cargo">{{ $firmas['director_financiero']['cargo'] ?? 'Director/a Financiero/a' }}</p>
        @if($firmas['director_financiero']['aviso'] ?? null)
          <p class="firma-aviso">{{ $firmas['director_financiero']['aviso'] }}</p>
        @endif
      </td>
    </tr>
  </table>
</div>

</body>
</html>
