<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Informe de Liquidación</title>
@include('pdf.viaticos._estilos')
</head>
<body>

@include('pdf.viaticos._membrete')

<div class="titulo">
  <div class="supra">Licencia con remuneración</div>
  <h1>Informe de Liquidación</h1>
  <div class="subtipo">Comisión de servicios institucionales</div>
  <div class="regla"></div>
</div>

<table class="cintillo">
  <tr>
    <td><span class="codigo">{{ $viatico->codigo_viatico }}</span></td>
    {{--
    | La fecha de la liquidación, no la de hoy: reimprimir un informe ya
    | firmado le cambiaba la fecha.
    --}}
    @php
      $fechaInforme = $viatico->liquidacion?->fecha_liquidacion
          ? 'Esmeraldas, '.\Illuminate\Support\Carbon::parse($viatico->liquidacion->fecha_liquidacion)
                ->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
          : 'Esmeraldas';
    @endphp
    <td class="lugar-fecha">{{ $fechaInforme }}</td>
  </tr>
</table>

{{-- ══ SERVIDOR Y COMISIÓN ══ --}}
<div class="bloque-titulo">Servidor y comisión</div>
<table class="datos">
  <tr>
    <td class="label">Apellidos y nombres</td>
    <td class="mayus fuerte" colspan="3">{{ $servidorNombre }}</td>
  </tr>
  <tr>
    <td class="label">Cargo</td>
    <td>{{ $viatico->servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
    <td class="label">Unidad</td>
    <td>{{ $viatico->servidor?->puesto?->unidadAdministrativa?->nombre ?? '—' }}</td>
  </tr>
  <tr>
    <td class="label">Destino</td>
    <td class="fuerte">{{ $destino }}</td>
    <td class="label">Período</td>
    <td>
      {{ $viatico->datetime_salida
          ? \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y H:i')
          : '—' }}
      →
      {{ $viatico->datetime_llegada
          ? \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y H:i')
          : '—' }}
      · {{ $calculo['noches'] }} noche(s)
    </td>
  </tr>
</table>

{{-- ══ ACTIVIDADES ══ --}}
<div class="bloque-titulo">Actividades o productos alcanzados</div>
@if($viatico->liquidacion?->actividades?->count() > 0)
<table class="listado">
  <thead>
    <tr>
      <th style="width:12%">Fecha</th>
      <th style="width:9%">Desde</th>
      <th style="width:9%">Hasta</th>
      <th style="width:22%">Lugar</th>
      <th>Descripción</th>
    </tr>
  </thead>
  <tbody>
    @foreach($viatico->liquidacion->actividades->sortBy('orden') as $act)
    <tr>
      <td>{{ $act->fecha ? \Carbon\Carbon::parse($act->fecha)->format('d/m/Y') : '—' }}</td>
      {{-- `08:00:00` es como lo guarda Postgres; en el papel van las horas. --}}
      <td>{{ $act->hora_inicio ? substr($act->hora_inicio, 0, 5) : '—' }}</td>
      <td>{{ $act->hora_fin ? substr($act->hora_fin, 0, 5) : '—' }}</td>
      <td>{{ $act->lugar ?? '—' }}</td>
      <td>{{ $act->descripcion ?? '—' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else
<p class="parrafo">{{ $viatico->justificacion ?? 'Sin descripción de actividades.' }}</p>
@endif

{{-- ══ ITINERARIO ══ --}}
{{-- Un renglón por tramo, no una tabla: el itinerario completo ya va en la
     solicitud, que se anexa a este informe, y en tabla empujaba las firmas a
     una segunda hoja. --}}
<div class="bloque-titulo">Itinerario de transporte</div>
<table class="listado">
  <tbody>
    @forelse($viatico->tramos->sortBy('orden') as $tramo)
    @php
      $orig = $tramo->origen_tipo === 'nacional'
        ? collect([
            $tramo->origenProvincia?->nombre,
            $tramo->origenCanton?->nombre ?: $tramo->origen_ciudad,
          ])->filter()->unique()->join(' / ')
        : collect([$tramo->origen_pais, $tramo->origen_ciudad])->filter()->join(' / ');
      $dest = $tramo->destino_tipo === 'nacional'
        ? collect([
            $tramo->destinoProvincia?->nombre,
            $tramo->destinoCanton?->nombre ?: $tramo->destino_ciudad,
          ])->filter()->unique()->join(' / ')
        : collect([$tramo->destino_pais, $tramo->destino_ciudad])->filter()->join(' / ');
    @endphp
    <tr>
      {{-- El accesor pone la tilde: la columna guarda «aereo». --}}
      <td style="width:12%">{{ $tramo->tipo_transporte }}</td>
      <td style="width:24%">{{ $tramo->empresa?->nombre ?? $tramo->catalogo?->nombre ?? '—' }}</td>
      <td><strong>{{ $orig }}</strong> → {{ $dest }}</td>
      <td class="num" style="width:30%">
        {{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('d/m/Y H:i') : '—' }}
        →
        {{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('H:i') : '—' }}
      </td>
    </tr>
    @empty
    <tr><td class="vacio">Sin tramos registrados.</td></tr>
    @endforelse
  </tbody>
</table>

{{-- ══ COMPROBANTES ══ --}}
<div class="bloque-titulo">Comprobantes de respaldo</div>
@php $comprobantes = $viatico->liquidacion?->detallesFactura ?? collect(); @endphp
@if($comprobantes->isNotEmpty())
<table class="listado">
  <thead>
    <tr>
      <th style="width:15%">RUC</th>
      <th style="width:21%">Proveedor</th>
      <th style="width:16%">Comprobante</th>
      <th style="width:9%">Tipo</th>
      {{--
      | La fecha del comprobante: solo valen los fechados dentro del viaje
      | (Gestión Financiera, 2026-09-15), y sin ella quien revisa el papel no
      | podía comprobarlo.
      --}}
      <th style="width:11%">Fecha</th>
      <th>Categoría</th>
      <th class="num" style="width:12%">Valor</th>
    </tr>
  </thead>
  <tbody>
    @foreach($comprobantes as $f)
    <tr>
      <td>{{ $f->ruc_proveedor ?? '—' }}</td>
      <td>{{ $f->nombre_proveedor ?? '—' }}</td>
      <td>{{ $f->numero_factura ?? $f->numero_ticket ?? '—' }}</td>
      <td>{{ mb_strtoupper($f->tipo_comprobante ?? 'factura', 'UTF-8') }}</td>
      <td>{{ $f->fecha_factura ? \Carbon\Carbon::parse($f->fecha_factura)->format('d/m/Y') : '—' }}</td>
      <td>{{ $f->categoria?->nombre ?? '—' }}</td>
      <td class="num">$ {{ number_format($f->monto ?? 0, 2) }}</td>
    </tr>
    @endforeach
    <tr class="total">
      <td colspan="6">Total de comprobantes</td>
      <td class="num">$ {{ number_format($comprobantes->sum('monto'), 2) }}</td>
    </tr>
  </tbody>
</table>
@else
<p class="parrafo vacio">Sin comprobantes registrados en la liquidación.</p>
@endif

{{--
| ══ RESUMEN FINANCIERO ══
|
| Siempre: el `@if` de los comprobantes se lo llevaba por delante, y el informe
| de quien no justificó nada —justo el que termina en una devolución— salía sin
| una sola cifra.
--}}
<div class="bloque-titulo">Resumen Financiero</div>
<table class="cifras">
  <tr>
    <td>Viático por {{ $calculo['noches'] }} noche(s)</td>
    <td class="val">$ {{ number_format($calculo['derecho'], 2) }}</td>
  </tr>
  <tr>
    <td>70 % a justificar con comprobantes</td>
    <td class="val">$ {{ number_format($calculo['tope_justificable'], 2) }}</td>
  </tr>
  <tr>
    <td>Comprobantes presentados</td>
    <td class="val">$ {{ number_format($calculo['total_comprobantes'], 2) }}</td>
  </tr>
  <tr>
    <td>Justificado (hasta el 70 %)</td>
    <td class="val">$ {{ number_format($calculo['justificado'], 2) }}</td>
  </tr>
  @if($calculo['excedente'] > 0)
  <tr>
    <td>Excedente a cargo del servidor</td>
    <td class="val">$ {{ number_format($calculo['excedente'], 2) }}</td>
  </tr>
  @endif
  <tr>
    <td>30 % reconocido sin comprobante</td>
    <td class="val">$ {{ number_format($calculo['reconocido_sin_comprobante'], 2) }}</td>
  </tr>
  <tr class="total">
    <td>Total reconocido al servidor</td>
    <td class="val">$ {{ number_format($calculo['reconocido'], 2) }}</td>
  </tr>
  @if($calculo['anticipo'] > 0)
  <tr>
    <td>Anticipo entregado{{ $anticipoDetalle }}</td>
    <td class="val">$ {{ number_format($calculo['anticipo'], 2) }}</td>
  </tr>
  @endif
  <tr class="cierre">
    <td>{{ $calculo['saldo'] < 0 ? 'A devolver a la institución' : 'A pagar al servidor' }}</td>
    <td class="val">$ {{ number_format(abs($calculo['saldo']), 2) }}</td>
  </tr>
</table>

{{-- ══ CLÁUSULA ══ --}}
{{-- La autorización general se firma en la solicitud, antes de recibir el
     anticipo. Aquí, que ya se conoce el resultado, va con la cifra, y solo
     cuando hay algo que devolver: un informe que pide autorizar un débito
     inexistente hace dudar a quien firma.
     Respondido por Gestión Financiera el 2026-11-18 (D5). --}}
@if(($calculo['saldo'] ?? 0) < 0)
<div class="clausula">
  Autorizo que se debite de mi próxima remuneración mensual unificada el valor
  no justificado de $ {{ number_format(abs($calculo['saldo']), 2) }}.
</div>
@endif

{{-- ══ FIRMAS ══ --}}
<div class="firmas">
  <table>
    <tr>
      <td style="width:33%">
        <div class="firma-rotulo">Servidor comisionado</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">{{ $servidorNombre }}</p>
        <p class="firma-cargo">{{ $viatico->servidor?->puesto?->cargo?->nombre ?? '' }}</p>
      </td>
      <td style="width:33%">
        <div class="firma-rotulo">Responsable de la unidad</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">{{ $firmas['jefe_unidad']['nombre'] ?? '' }}</p>
        <p class="firma-cargo">{{ $firmas['jefe_unidad']['cargo'] ?? 'Director/a de Unidad' }}</p>
        @if($firmas['jefe_unidad']['aviso'] ?? null)
          <p class="firma-aviso">{{ $firmas['jefe_unidad']['aviso'] }}</p>
        @endif
      </td>
      <td style="width:34%">
        <div class="firma-rotulo">Máxima autoridad o delegado</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">{{ $firmas['maxima_autoridad']['nombre'] ?? '' }}</p>
        <p class="firma-cargo">{{ $firmas['maxima_autoridad']['cargo'] ?? 'Prefecto/a Provincial' }}</p>
        @if($firmas['maxima_autoridad']['aviso'] ?? null)
          <p class="firma-aviso">{{ $firmas['maxima_autoridad']['aviso'] }}</p>
        @endif
      </td>
    </tr>
  </table>
</div>

</body>
</html>
