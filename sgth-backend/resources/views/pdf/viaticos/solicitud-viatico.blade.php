<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Solicitud de Viático</title>
@include('pdf.viaticos._estilos')
</head>
<body>

@include('pdf.viaticos._membrete')

<div class="titulo">
  <div class="supra">Licencia con remuneración</div>
  <h1>Solicitud de Viático</h1>
  <div class="subtipo">Comisión de servicios institucionales</div>
  <div class="regla"></div>
</div>

<table class="cintillo">
  <tr>
    <td><span class="codigo">{{ $viatico->codigo_viatico }}</span></td>
    @php
      $fechaSolicitud = 'Esmeraldas, '
          .($viatico->fecha_solicitud ? \Illuminate\Support\Carbon::parse($viatico->fecha_solicitud) : now())
                ->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    @endphp
    <td class="lugar-fecha">{{ $fechaSolicitud }}</td>
  </tr>
</table>

{{-- ══ SERVIDOR ══ --}}
<div class="bloque-titulo">Servidor que se comisiona</div>
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
</table>

{{-- ══ COMISIÓN ══ --}}
{{-- El N° de Viático sale también aquí, no solo en el cintillo: es el dato
     con el que Financiero lo busca. --}}
<div class="bloque-titulo">Comisión</div>
<table class="datos">
  <tr>
    <td class="label">N° de Viático</td>
    <td class="fuerte">{{ $viatico->codigo_viatico }}</td>
    <td class="label">Zona</td>
    <td>{{ $zonaLabel }}</td>
  </tr>
  <tr>
    {{-- Lo calcula `DestinoDelViatico`: los cuatro documentos dicen lo mismo. --}}
    <td class="label">Destino</td>
    <td class="fuerte">{{ $destino }}</td>
    <td class="label">Noches</td>
    <td>{{ $calculo['noches'] }} noche(s)</td>
  </tr>
  <tr>
    <td class="label">Salida</td>
    <td>
      {{ $viatico->datetime_salida
          ? \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y H:i')
          : '—' }}
    </td>
    <td class="label">Regreso</td>
    <td>
      {{ $viatico->datetime_llegada
          ? \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y H:i')
          : '—' }}
    </td>
  </tr>
  <tr>
    <td class="label">Monto del viático</td>
    <td class="fuerte">$ {{ number_format($viatico->monto_calculado ?? 0, 2) }}</td>
    <td class="label">Anticipo</td>
    <td class="fuerte">
      {{--
      | Antes aquí iban cuatro casillas —«viáticos», «movilizaciones»,
      | «subsistencias», «alimentación»— que no miraban el viático: dos
      | estaban marcadas a mano en la plantilla, una nunca se marcaba y la
      | otra dependía de una comparación que nunca era cierta. Gestión
      | Financiera decidió el 2026-11-18 que no vuelvan: aquí se paga un solo
      | concepto, el viático por noche de pernocte.
      --}}
      @if($modalidadValue === 'total')
        @php
          /*
          | El anticipo es el 70 % del monto. `monto_anticipo` sigue en cero
          | hasta que Financiero lo entrega, y la solicitud se imprime mucho
          | antes: lo que aquí se anuncia es lo que se va a transferir.
          */
          $anticipoPrevisto = round(
              (float) $viatico->monto_calculado * \App\Services\Viatico\CalculoViaticoService::PORCENTAJE_JUSTIFICABLE,
              2
          );
        @endphp
        Anticipo por entregar: $ {{ number_format($anticipoPrevisto, 2) }}
      @else
        Sin anticipo
      @endif
    </td>
  </tr>
  <tr>
    <td class="label">Objeto de la comisión</td>
    <td colspan="3">{{ $viatico->justificacion ?? '—' }}</td>
  </tr>
</table>

{{-- ══ ITINERARIO ══ --}}
<div class="bloque-titulo">Itinerario de transporte</div>
<table class="listado">
  <thead>
    <tr>
      <th style="width:12%">Tipo</th>
      <th style="width:24%">Empresa</th>
      <th>Ruta</th>
      <th style="width:16%">Salida</th>
      <th style="width:16%">Llegada</th>
    </tr>
  </thead>
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
      <td>{{ $tramo->tipo_transporte }}</td>
      {{-- Sin empresa (vehículo institucional, taxi…), el tipo de transporte. --}}
      <td>{{ $tramo->empresa?->nombre ?? $tramo->catalogo?->nombre ?? '—' }}</td>
      <td><strong>{{ $orig }}</strong> → {{ $dest }}</td>
      <td>
        {{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('d/m/Y H:i') : '—' }}
      </td>
      <td>
        {{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('d/m/Y H:i') : '—' }}
      </td>
    </tr>
    @empty
    <tr><td colspan="5" class="vacio">Sin tramos registrados.</td></tr>
    @endforelse
  </tbody>
</table>

{{--
| ══ ACREDITACIÓN ══
|
| Sin cuenta principal el bloque salía con tres rayas, se firmaba así y el
| hueco solo aparecía el día de la transferencia. Ahora lo dice.
--}}
@php $cuenta = \App\Support\CuentaDeViatico::de($viatico->servidor); @endphp
<div class="bloque-titulo">Acreditación</div>
@if($cuenta)
<table class="datos">
  <tr>
    <td class="label">Institución financiera</td>
    <td>{{ $cuenta->entidadFinanciera?->nombre ?? '—' }}</td>
    <td class="label">Tipo de cuenta</td>
    <td>{{ $cuenta->tipo_cuenta ? mb_strtoupper($cuenta->tipo_cuenta, 'UTF-8') : '—' }}</td>
  </tr>
  <tr>
    <td class="label">Número de Cuenta</td>
    <td class="fuerte" colspan="3">{{ $cuenta->numero_cuenta ?? '—' }}</td>
  </tr>
</table>
@else
<div class="aviso">
  El servidor no tiene una cuenta bancaria principal registrada para viáticos.
  Debe registrarla en su expediente antes de que Gestión Financiera entregue el anticipo.
</div>
@endif

{{-- ══ CLÁUSULA ══ --}}
{{-- Es el momento en que el servidor autoriza, antes de recibir el anticipo.
     En el informe va la misma con la cifra (Gestión Financiera, 2026-11-18). --}}
<div class="clausula">
  Autorizo que los valores no justificados sean debitados de mi próxima
  remuneración mensual unificada.
</div>

{{-- ══ FIRMAS ══ --}}
<div class="firmas">
  <table>
    <tr>
      <td style="width:33%">
        <div class="firma-rotulo">Servidor solicitante</div>
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
