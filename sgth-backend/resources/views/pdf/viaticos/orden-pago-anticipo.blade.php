<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
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
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family: 'DejaVu Sans', Arial, sans-serif;
  font-size: 9px;
  color: #1a1a2e;
  line-height: 1.45;
  padding: 24px 35px 14px;
  background: #fff;
}
.hdr { display: table; width: 100%; }
.hdr-logo { display: table-cell; width: 100px; vertical-align: middle; padding-right: 12px; }
.hdr-logo img { width: 88px; height: auto; }
.hdr-body {
  display: table-cell;
  vertical-align: middle;
  background: #1a3a5c;
  padding: 10px 16px;
  border-radius: 4px 4px 0 0;
}
.hdr-dept { font-size: 11px; font-weight: bold; color: #ffffff; text-transform: uppercase; letter-spacing: 0.5px; }
.hdr-sub { font-size: 9px; color: #a8c4e0; margin-top: 2px; }
.hdr-right { display: table-cell; width: 150px; vertical-align: middle; padding-left: 10px; }
.code-box { border: 2px solid #1a3a5c; border-radius: 5px; padding: 7px 10px; background: #f0f5fb; text-align: center; }
.code-lbl { font-size: 7px; color: #4a5568; text-transform: uppercase; letter-spacing: 0.4px; }
.code-val { font-size: 9.5px; font-weight: bold; color: #1a3a5c; margin: 2px 0; white-space: nowrap; }
.code-date { font-size: 9px; font-weight: bold; color: #2d3748; border-top: 1px solid #cbd5e0; padding-top: 3px; margin-top: 3px; }
.hdr-bar {
  margin-left: 112px;
  background: #2d6a9f;
  border-radius: 0 0 4px 0;
  padding: 4px 14px;
  margin-bottom: 12px;
  display: table;
  width: calc(100% - 112px);
}
.hdr-bar-inner { display: table; width: 100%; }
.hdr-bar-l { display: table-cell; font-size: 8px; color: #e2edf7; vertical-align: middle; }
.hdr-bar-r { display: table-cell; font-size: 8px; font-weight: bold; color: #fff; text-align: right; vertical-align: middle; }

.titulo {
  text-align: center;
  font-size: 12px;
  font-weight: bold;
  color: #1a3a5c;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  margin-bottom: 10px;
}

.benef-box { border: 1px solid #cbd5e0; padding: 7px 12px; margin-bottom: 10px; border-radius: 3px; background: #f7fafc; }
.benef-row { display: table; width: 100%; margin-bottom: 3px; }
.benef-lbl {
  display: table-cell;
  font-size: 8px;
  font-weight: bold;
  color: #4a5568;
  text-transform: uppercase;
  width: 20%;
  vertical-align: middle;
}
.benef-val { display: table-cell; font-size: 10px; font-weight: bold; color: #1a3a5c; vertical-align: middle; }
.benef-tipo { display: table-cell; font-size: 8px; text-align: right; vertical-align: middle; color: #4a5568; width: 25%; }
.tipo-badge {
  display: inline-block;
  background: #1a3a5c;
  color: white;
  font-size: 8px;
  font-weight: bold;
  padding: 2px 8px;
  border-radius: 3px;
  letter-spacing: 0.5px;
}

.sec-hdr {
  background: #1a3a5c;
  color: white;
  font-size: 8px;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 4px 10px;
  margin-top: 10px;
}
.caja {
  border: 1px solid #cbd5e0;
  border-top: none;
  padding: 8px 12px;
  font-size: 8.5px;
  line-height: 1.6;
  color: #2d3748;
  background: white;
}
.ft { width: 100%; border-collapse: collapse; }
.ft td { padding: 4px 10px; border: 1px solid #cbd5e0; border-top: none; font-size: 8.5px; }
.ft .lbl { color: #2d3748; }
.ft .val { text-align: right; font-weight: bold; color: #1a3a5c; width: 30%; }
.ft .total-row td { background: #eef4fa; font-weight: bold; font-size: 9px; }

.sin-cuenta {
  border: 1px solid #e0b000;
  border-left: 3px solid #e0b000;
  background: #fffbe8;
  padding: 7px 10px;
  font-size: 8.5px;
  color: #6b5200;
  margin-top: 10px;
  border-radius: 3px;
}

.firmas { display: table; width: 100%; margin-top: 26px; page-break-inside: avoid; }
.fc { display: table-cell; width: 50%; text-align: center; vertical-align: top; padding: 0 16px; }
.fline { height: 26px; border-bottom: 1.5px solid #1a3a5c; margin: 0 14px 5px; }
.fn { font-size: 8.5px; font-weight: bold; color: #1a3a5c; text-transform: uppercase; }
.fc-cargo { font-size: 7.5px; color: #4a5568; margin-top: 2px; }
.fc-rol { font-size: 7px; color: #718096; margin-top: 1px; text-transform: uppercase; }
.fc-aviso { font-size: 7px; color: #a05a00; margin-top: 3px; font-style: italic; }

.nota { font-size: 7.5px; color: #718096; font-style: italic; margin-top: 10px; line-height: 1.5; }
.footer { margin-top: 10px; padding-top: 5px; border-top: 1px solid #e2e8f0; display: table; width: 100%; }
.fl { display: table-cell; font-size: 7px; color: #b0bec5; vertical-align: middle; }
.fr { display: table-cell; font-size: 7px; color: #b0bec5; text-align: right; vertical-align: middle; }
</style>
</head>
<body>

{{-- ══ HEADER ══ --}}
<div class="hdr">
  <div class="hdr-logo">
    @if(file_exists($logo))
      <img src="{{ $logo }}" alt="GADPE">
    @else
      <div style="font-size:10px;font-weight:bold;color:#1a3a5c;">GADPE</div>
    @endif
  </div>
  <div class="hdr-body">
    <div class="hdr-dept">Dirección Financiera</div>
    <div class="hdr-sub">GAD Provincial de Esmeraldas</div>
  </div>
  <div class="hdr-right">
    <div class="code-box">
      <div class="code-lbl">N° de Viático</div>
      <div class="code-val">{{ $viatico->codigo_viatico }}</div>
      <div class="code-lbl" style="margin-top:3px;">Fecha</div>
      <div class="code-date">{{ $fechaOrden->format('d/m/Y') }}</div>
    </div>
  </div>
</div>
<div class="hdr-bar">
  <div class="hdr-bar-inner">
    <div class="hdr-bar-l">
      Esmeraldas, {{ $fechaOrden->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
    </div>
    <div class="hdr-bar-r">ANTICIPO: $ {{ number_format($anticipo, 2) }}</div>
  </div>
</div>

<div class="titulo">Orden de Pago de Anticipo de Viático</div>

{{-- ══ BENEFICIARIO ══ --}}
<div class="benef-box">
  <div class="benef-row">
    <div class="benef-lbl">Páguese a:</div>
    <div class="benef-val">{{ $servidorNombre }}</div>
    <div class="benef-tipo">
      TIPO: <span class="tipo-badge">PREVIA</span>
    </div>
  </div>
  <div class="benef-row">
    <div class="benef-lbl">Cédula:</div>
    <div class="benef-val">{{ $viatico->servidor?->cedula ?? '—' }}</div>
    <div class="benef-tipo"></div>
  </div>
  <div class="benef-row">
    <div class="benef-lbl">Valor:</div>
    <div class="benef-val">{{ $anticipoLetras }}</div>
    <div class="benef-tipo">
      PARTIDA: <span class="tipo-badge">{{ $viatico->partidaPresupuestaria?->codigo ?: '—' }}</span>
    </div>
  </div>
</div>

{{-- ══ CONCEPTO ══ --}}
<div class="sec-hdr">Concepto</div>
<div class="caja">
  Anticipo del <strong>{{ number_format($porcentaje, 0) }} %</strong> del viático de
  <strong>{{ $viatico->noches }}</strong> noche(s) a favor del señor(a)
  <strong>{{ $servidorNombre }}</strong>, por su comisión de servicios en
  <strong>{{ $destino }}</strong>, del
  {{ \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y') }} al
  {{ \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y') }}, para
  <strong>{{ mb_strtoupper($viatico->justificacion ?? '', 'UTF-8') }}</strong>
</div>

{{-- ══ VALORES ══ --}}
<table class="ft">
  <tr>
    <td class="lbl">Viático de {{ $viatico->noches }} noche(s)</td>
    <td class="val">$ {{ number_format($viatico->monto_calculado, 2) }}</td>
  </tr>
  <tr class="total-row">
    <td class="lbl">Anticipo a entregar ({{ number_format($porcentaje, 0) }} %)</td>
    <td class="val">$ {{ number_format($anticipo, 2) }}</td>
  </tr>
</table>

{{-- ══ TRANSFERENCIA ══ --}}
<div class="sec-hdr">Acreditación</div>
@if($cuenta)
<table class="ft">
  <tr>
    <td class="lbl">Institución financiera</td>
    <td class="val" style="text-align:left;width:auto">{{ $cuenta->entidadFinanciera?->nombre ?? '—' }}</td>
  </tr>
  <tr>
    <td class="lbl">Tipo de cuenta</td>
    <td class="val" style="text-align:left;width:auto">
      {{ $cuenta->tipo_cuenta ? mb_strtoupper($cuenta->tipo_cuenta, 'UTF-8') : '—' }}
    </td>
  </tr>
  <tr>
    <td class="lbl">Número de cuenta</td>
    <td class="val" style="text-align:left;width:auto">{{ $cuenta->numero_cuenta ?? '—' }}</td>
  </tr>
</table>
@else
<div class="sin-cuenta" style="margin-top:0;border-top:none;border-radius:0 0 3px 3px">
  El servidor no tiene una cuenta bancaria principal registrada para viáticos.
  Debe registrarla en su expediente antes de ordenar la transferencia.
</div>
@endif

{{-- Lo que Financiero ya anotó, si la entrega está registrada. --}}
@if($viatico->numero_resolucion || $viatico->fecha_anticipo)
<table class="ft">
  @if($viatico->fecha_anticipo)
  <tr>
    <td class="lbl">Entregado el</td>
    <td class="val" style="text-align:left;width:auto">
      {{ \Carbon\Carbon::parse($viatico->fecha_anticipo)->format('d/m/Y') }}
    </td>
  </tr>
  @endif
  @if($viatico->numero_resolucion)
  <tr>
    <td class="lbl">Comprobante de pago</td>
    <td class="val" style="text-align:left;width:auto">{{ $viatico->numero_resolucion }}</td>
  </tr>
  @endif
</table>
@endif

{{-- ══ FIRMAS ══ --}}
<div class="firmas">
  <div class="fc">
    <div class="fline"></div>
    @if($firmas['elaborado']['nombre'] ?? null)
      <div class="fn">{{ $firmas['elaborado']['nombre'] }}</div>
      <div class="fc-cargo">{{ $firmas['elaborado']['cargo'] }}</div>
    @else
      <div class="fn" style="color:#a0aec0">___________________________</div>
      <div class="fc-cargo" style="color:#a0aec0">Gestión Financiera</div>
    @endif
    <div class="fc-rol">Elaborado por</div>
  </div>
  <div class="fc">
    <div class="fline"></div>
    @if($firmas['director_financiero']['nombre'] ?? null)
      <div class="fn">{{ $firmas['director_financiero']['nombre'] }}</div>
      <div class="fc-cargo">{{ $firmas['director_financiero']['cargo'] }}</div>
    @else
      <div class="fn" style="color:#a0aec0">___________________________</div>
      <div class="fc-cargo" style="color:#a0aec0">Director/a Financiero/a</div>
    @endif
    <div class="fc-rol">Ordenado por</div>
    @if($firmas['director_financiero']['aviso'] ?? null)
      <div class="fc-aviso">{{ $firmas['director_financiero']['aviso'] }}</div>
    @endif
  </div>
</div>

<div class="nota">
  El servidor justifica con comprobantes el {{ number_format($porcentaje, 0) }} % que recibe por anticipado;
  lo no justificado se descuenta de su próxima remuneración. La liquidación del viático
  se respalda con su comprobante contable.<br>
  Valores en dólares de los Estados Unidos de América.
</div>

<div class="footer">
  <div class="fl">SGTH — GAD Provincial de Esmeraldas — Dirección Financiera</div>
  <div class="fr">Generado el {{ now()->format('d/m/Y H:i') }} • Documento oficial</div>
</div>

</body>
</html>
