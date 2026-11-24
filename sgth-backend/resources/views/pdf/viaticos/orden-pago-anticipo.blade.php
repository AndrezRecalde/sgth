<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de Pago de Anticipo</title>
{{--
| La orden de pago del anticipo del viático.
|
| Pedida por Gestión Financiera (2026-11-23, S3 y S4): se imprime desde que el
| viático está aprobado, antes de que el dinero salga, y es el papel con el que
| se pide el egreso. Por eso no es un segundo «comprobante contable» —el SGTH no
| lleva la contabilidad— y lleva el tipo PREVIA, mientras que el comprobante del
| viático completo es el DEFINITIVA.
|
| Dos firmas, no las tres del resto del trámite: el Director Financiero, que
| ordena el pago, y el funcionario de Financiero que lo prepara.
--}}
@include('pdf.viaticos._estilos')
</head>
<body>

@include('pdf.viaticos._membrete')

<div class="titulo">
  <div class="supra">Pago previo</div>
  <h1>Orden de Pago de Anticipo</h1>
  <div class="subtipo">Viático de comisión de servicios</div>
  <div class="regla"></div>
</div>

<table class="cintillo">
  <tr>
    <td><span class="codigo">{{ $viatico->codigo_viatico }}</span></td>
    <td class="lugar-fecha">{{ 'Esmeraldas, '.$fechaOrden->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</td>
  </tr>
</table>

{{-- ══ BENEFICIARIO ══ --}}
<div class="bloque-titulo">Beneficiario</div>
<table class="datos">
  <tr>
    <td class="label">Páguese a</td>
    <td class="mayus fuerte">{{ $servidorNombre }}</td>
    <td class="label">Cédula</td>
    <td>{{ $viatico->servidor?->cedula ?? '—' }}</td>
  </tr>
  <tr>
    <td class="label">Valor</td>
    <td class="fuerte" colspan="3">
      {{ $anticipoLetras }} — $ {{ number_format($anticipo, 2) }}
    </td>
  </tr>
  <tr>
    <td class="label">Partida</td>
    <td>{{ $viatico->partidaPresupuestaria?->codigo ?: '—' }}</td>
    <td class="label">Tipo de pago</td>
    <td class="fuerte">PREVIA</td>
  </tr>
</table>

{{-- ══ CONCEPTO ══ --}}
<div class="bloque-titulo">Concepto</div>
<p class="parrafo">
  Anticipo del <strong>{{ number_format($porcentaje, 0) }} %</strong> del viático de
  <strong>{{ $viatico->noches }}</strong> noche(s) a favor del señor(a)
  <strong>{{ $servidorNombre }}</strong>, por su comisión de servicios en
  <strong>{{ $destino }}</strong>, del
  {{ \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y') }} al
  {{ \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y') }}, para
  {{ $viatico->justificacion ?? '—' }}
</p>

<table class="cifras">
  <tr>
    <td>Viático de {{ $viatico->noches }} noche(s)</td>
    <td class="val">$ {{ number_format($viatico->monto_calculado, 2) }}</td>
  </tr>
  <tr class="cierre">
    <td>Anticipo a entregar ({{ number_format($porcentaje, 0) }} %)</td>
    <td class="val">$ {{ number_format($anticipo, 2) }}</td>
  </tr>
</table>

{{-- ══ ACREDITACIÓN ══ --}}
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
    <td class="label">Número de cuenta</td>
    <td class="fuerte">{{ $cuenta->numero_cuenta ?? '—' }}</td>
    {{-- Lo que Financiero ya anotó, si la entrega está registrada. --}}
    <td class="label">Comprobante de pago</td>
    <td>
      @if($viatico->numero_resolucion || $viatico->fecha_anticipo)
        {{ $viatico->numero_resolucion ?: '—' }}
        @if($viatico->fecha_anticipo)
          · {{ \Carbon\Carbon::parse($viatico->fecha_anticipo)->format('d/m/Y') }}
        @endif
      @else
        <span class="vacio">Por registrar</span>
      @endif
    </td>
  </tr>
</table>
@else
<div class="aviso">
  El servidor no tiene una cuenta bancaria principal registrada para viáticos.
  Debe registrarla en su expediente antes de ordenar la transferencia.
</div>
@endif

<div class="nota">
  El servidor justifica con comprobantes el {{ number_format($porcentaje, 0) }} % que recibe
  por anticipado; lo no justificado se descuenta de su próxima remuneración. La liquidación
  del viático se respalda con su comprobante contable.
</div>

{{-- ══ FIRMAS ══ --}}
<div class="firmas">
  <table>
    <tr>
      <td style="width:50%">
        <div class="firma-rotulo">Elaborado por</div>
        <div class="firma-linea"></div>
        <p class="firma-nombre">{{ $firmas['elaborado']['nombre'] ?? '' }}</p>
        <p class="firma-cargo">{{ $firmas['elaborado']['cargo'] ?? 'Gestión Financiera' }}</p>
      </td>
      <td style="width:50%">
        <div class="firma-rotulo">Ordenado por</div>
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
