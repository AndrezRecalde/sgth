<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family: 'DejaVu Sans', Arial, sans-serif;
  font-size: 9px;
  color: #1a1a2e;
  line-height: 1.45;
  padding: 30px 38px;
  background: #fff;
}

/* ══ HEADER ══ */
.hdr {
  display: table;
  width: 100%;
  margin-bottom: 0;
}
.hdr-logo {
  display: table-cell;
  width: 105px;
  vertical-align: middle;
  padding-right: 14px;
}
.hdr-logo img { width: 95px; height: auto; }
.hdr-body {
  display: table-cell;
  vertical-align: middle;
  background: #1a3a5c;
  padding: 12px 16px;
  border-radius: 4px 4px 0 0;
}
.hdr-inst {
  font-size: 8px;
  color: #a8c4e0;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 3px;
}
.hdr-title {
  font-size: 15px;
  font-weight: bold;
  color: #ffffff;
  letter-spacing: 0.3px;
}
.hdr-sub {
  font-size: 8px;
  color: #c5daf0;
  margin-top: 3px;
}
.hdr-right {
  display: table-cell;
  /* 130px partía el código en dos líneas: «GADPE-15-2026-» / «00004». */
  width: 150px;
  vertical-align: middle;
  padding-left: 10px;
}
.code-box {
  border: 2px solid #1a3a5c;
  border-radius: 5px;
  padding: 8px 10px;
  background: #f0f5fb;
  text-align: center;
}
.code-lbl {
  font-size: 7px;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.code-val {
  font-size: 9.5px;
  font-weight: bold;
  color: #1a3a5c;
  margin: 2px 0;
  white-space: nowrap;
}
.code-date {
  font-size: 9px;
  font-weight: bold;
  color: #2d3748;
  border-top: 1px solid #cbd5e0;
  padding-top: 4px;
  margin-top: 4px;
}
.code-date-lbl {
  font-size: 7px;
  color: #718096;
  text-transform: uppercase;
}

/* ══ BARRA INFERIOR DEL HEADER ══ */
.hdr-bar {
  margin-left: 119px;
  background: #2d6a9f;
  border-radius: 0 0 4px 0;
  padding: 5px 16px;
  margin-bottom: 12px;
}
.hdr-bar-txt {
  font-size: 8px;
  color: #e2edf7;
  font-weight: bold;
}

/* ══ SECTION ══ */
.sec-hdr {
  background: #1a3a5c;
  color: white;
  font-size: 8px;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 4px 10px;
  margin-top: 10px;
  border-radius: 3px 3px 0 0;
}
.sec-hdr-alt {
  background: #2d6a9f;
  color: white;
  font-size: 8px;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 4px 10px;
  margin-top: 10px;
  border-radius: 3px 3px 0 0;
}

/* ══ DATA TABLE ══ */
table.dt {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.dt th {
  background: #edf2f7;
  font-size: 8px;
  font-weight: bold;
  padding: 4px 8px;
  border: 1px solid #cbd5e0;
  color: #2d3748;
  text-align: left;
  white-space: nowrap;
  width: 22%;
}
table.dt td {
  font-size: 8.5px;
  padding: 4px 8px;
  border: 1px solid #cbd5e0;
  color: #1a1a2e;
  background: white;
}
table.dt tr:nth-child(even) td { background: #f7fafc; }

/* ══ GRID TABLE ══ */
table.gt {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #cbd5e0;
  border-top: none;
}
table.gt th {
  background: #2d6a9f;
  color: white;
  font-size: 7.5px;
  padding: 4px 6px;
  border: 1px solid #2d6a9f;
  text-align: center;
  font-weight: bold;
}
table.gt td {
  font-size: 8px;
  padding: 4px 6px;
  border: 1px solid #cbd5e0;
  color: #1a1a2e;
  text-align: center;
}
table.gt .tl { text-align: left; }
table.gt tr:nth-child(even) td { background: #f0f5fb; }

/* ══ JUSTIFICACIÓN ══ */
.jbox {
  border: 1px solid #cbd5e0;
  border-top: none;
  padding: 9px 11px;
  font-size: 8.5px;
  line-height: 1.6;
  color: #2d3748;
  background: white;
  min-height: 50px;
}

/* ══ CUENTA ══ */
.cuenta-wrap {
  display: table;
  width: 100%;
  border: 1px solid #cbd5e0;
  border-top: none;
}
.cc {
  display: table-cell;
  padding: 6px 10px;
  border-right: 1px solid #e2e8f0;
  vertical-align: middle;
}
.cc:last-child { border-right: none; }
.cc-lbl {
  font-size: 7px;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.cc-val {
  font-size: 10px;
  font-weight: bold;
  color: #1a3a5c;
  margin-top: 2px;
}

.sin-cuenta {
  border: 1px solid #cbd5e0;
  border-top: none;
  padding: 8px 11px;
  font-size: 8px;
  color: #8a6d3b;
  background: #fffbea;
}

/* ══ CLÁUSULA ══ */
.clausula {
  margin-top: 10px;
  border-left: 3px solid #c0392b;
  padding: 6px 10px;
  font-size: 8px;
  color: #742a2a;
  background: #fff5f5;
  border-radius: 0 3px 3px 0;
  font-style: italic;
}

/* ══ FIRMAS ══ */
.firmas {
  display: table;
  width: 100%;
  margin-top: 38px;
  page-break-inside: avoid;
}
.fc {
  display: table-cell;
  width: 33.33%;
  text-align: center;
  padding: 0 10px;
  vertical-align: bottom;
}
.fline {
  height: 38px;
  border-bottom: 1.5px solid #1a3a5c;
  margin: 0 8px 5px;
}
.fn { font-size: 8.5px; font-weight: bold; color: #1a3a5c; text-transform: uppercase; }
.fc-cargo { font-size: 7.5px; color: #4a5568; margin-top: 2px; }
.fc-rol { font-size: 7px; color: #718096; margin-top: 1px; text-transform: uppercase; letter-spacing: 0.3px; }

/* ══ PIE ══ */
.footer {
  margin-top: 16px;
  padding-top: 5px;
  border-top: 1px solid #e2e8f0;
  display: table;
  width: 100%;
}
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
      <div style="font-size:10px;font-weight:bold;color:#1a3a5c;text-align:center;">GADPE</div>
    @endif
  </div>
  <div class="hdr-body">
    <div class="hdr-inst">{{ $identidad['nombre_oficial'] ?? '' }}</div>
    <div class="hdr-title">Solicitud de Viático</div>
    <div class="hdr-sub">Licencia con Remuneración — Comisión de Servicios Institucionales</div>
  </div>
  <div class="hdr-right">
    <div class="code-box">
      <div class="code-lbl">N° de Viático</div>
      <div class="code-val">{{ $viatico->codigo_viatico }}</div>
      <div class="code-date-lbl">Fecha</div>
      <div class="code-date">
        {{ $viatico->fecha_solicitud
            ? \Carbon\Carbon::parse($viatico->fecha_solicitud)->format('d/m/Y')
            : date('d/m/Y') }}
      </div>
    </div>
  </div>
</div>

{{-- ══ BARRA CHECKBOXES ══ --}}
<div class="hdr-bar">
  {{--
  | Antes aquí iban cuatro casillas —«viáticos», «movilizaciones»,
  | «subsistencias», «alimentación»— que no miraban el viático: las dos
  | primeras estaban marcadas a mano en la plantilla, la cuarta nunca se
  | marcaba y la tercera dependía de `$viatico->zona === 'exterior'`, que
  | compara un enum con un texto y nunca era cierto. El papel afirmaba cosas
  | que nadie había pedido. Mientras Gestión Financiera dice qué marca cada
  | una, la barra lleva lo mismo que la del informe: lo que sí se sabe.
  --}}
  <div class="hdr-bar-txt">
    Período:
    {{ $viatico->datetime_salida
        ? \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y H:i')
        : '—' }}
    →
    {{ $viatico->datetime_llegada
        ? \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y H:i')
        : '—' }}
    &nbsp;|&nbsp;
    {{ $calculo['noches'] }} noche(s)
    &nbsp;|&nbsp;
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
  </div>
</div>

{{-- ══ DATOS GENERALES ══ --}}
<div class="sec-hdr">Datos Generales del Servidor</div>
<table class="dt">
  <tr>
    <th>Apellidos y Nombres</th>
    <td colspan="3">
      <strong>{{ $servidorNombre }}</strong>
    </td>
  </tr>
  <tr>
    <th>Cargo / Puesto</th>
    <td>{{ $viatico->servidor?->puesto?->cargo?->nombre ?? '—' }}</td>
    <th>Unidad Administrativa</th>
    <td>{{ $viatico->servidor?->puesto?->unidadAdministrativa?->nombre ?? '—' }}</td>
  </tr>
  <tr>
    <th>Zona del Viaje</th>
    <td>{{ $zonaLabel }}</td>
    <th>Código del Viático</th>
    <td><strong>{{ $viatico->codigo_viatico }}</strong></td>
  </tr>
  <tr>
    <th>Provincia / Ciudad Destino</th>
    {{-- Lo calcula `DestinoDelViatico`: los tres documentos dicen lo mismo. --}}
    <td>{{ $destino }}</td>
    <th>Noches</th>
    <td><strong>{{ $calculo["noches"] }}</strong> noche(s)</td>
  </tr>
  <tr>
    <th>Fecha / Hora de Salida</th>
    <td>
      {{ $viatico->datetime_salida
          ? \Carbon\Carbon::parse($viatico->datetime_salida)->format('d/m/Y  H:i')
          : '—' }}
    </td>
    <th>Fecha / Hora de Regreso</th>
    <td>
      {{ $viatico->datetime_llegada
          ? \Carbon\Carbon::parse($viatico->datetime_llegada)->format('d/m/Y  H:i')
          : '—' }}
    </td>
  </tr>
  <tr>
    <th>Monto Calculado</th>
    <td>
      <strong style="color:#1a3a5c;font-size:10px;">
        $ {{ number_format($viatico->monto_calculado ?? 0, 2) }}
      </strong>
    </td>
    <th>Modalidad Anticipo</th>
    <td>
      @php
        $porcentaje = ($modalidadValue === 'total')
            ? ' (70%)' : '';
        echo $modalidadLabel . $porcentaje;
      @endphp
    </td>
  </tr>
</table>

{{-- ══ JUSTIFICACIÓN ══ --}}
<div class="sec-hdr">Descripción de las Actividades a Realizarse</div>
<div class="jbox">{{ $viatico->justificacion ?? '—' }}</div>

{{-- ══ TRANSPORTE ══ --}}
<div class="sec-hdr-alt">Itinerario de Transporte</div>
<table class="gt">
  <thead>
    <tr>
      <th>Tipo</th>
      <th class="tl">Empresa / Nombre</th>
      <th class="tl">Ruta</th>
      <th>F. Salida</th>
      <th>Hora</th>
      <th>F. Llegada</th>
      <th>Hora</th>
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
      <td>{{ $tramo->tipo_transporte }}</td>
      {{-- Sin empresa (vehículo institucional, taxi…), el tipo de transporte. --}}
      <td class="tl">{{ $tramo->empresa?->nombre ?? $tramo->catalogo?->nombre ?? '—' }}</td>
      <td class="tl"><strong>{{ $orig }}</strong> → {{ $dest }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_salida ? \Carbon\Carbon::parse($tramo->datetime_salida)->format('H:i') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('d/m/Y') : '—' }}</td>
      <td>{{ $tramo->datetime_llegada ? \Carbon\Carbon::parse($tramo->datetime_llegada)->format('H:i') : '—' }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="7" style="color:#718096">Sin tramos registrados.</td>
    </tr>
    @endforelse
  </tbody>
</table>

{{--
| ══ CUENTA BANCARIA ══
|
| Sin cuenta principal el bloque salía con tres rayas, se firmaba así y el
| hueco solo aparecía el día de la transferencia. Ahora lo dice.
--}}
@php $cuenta = $viatico->servidor?->cuentasBancarias?->first(); @endphp
<div class="sec-hdr">Datos para Transferencia Bancaria</div>
@if($cuenta)
<div class="cuenta-wrap">
  <div class="cc" style="width:28%">
    <div class="cc-lbl">Tipo de Cuenta</div>
    <div class="cc-val">{{ $cuenta->tipo_cuenta ? mb_strtoupper($cuenta->tipo_cuenta, 'UTF-8') : '—' }}</div>
  </div>
  <div class="cc" style="width:32%">
    <div class="cc-lbl">Número de Cuenta</div>
    <div class="cc-val">{{ $cuenta->numero_cuenta ?? '—' }}</div>
  </div>
  <div class="cc">
    <div class="cc-lbl">Institución Financiera</div>
    <div class="cc-val">{{ $cuenta->entidadFinanciera?->nombre ?? '—' }}</div>
  </div>
</div>
@else
<div class="sin-cuenta">
  El servidor no tiene una cuenta bancaria principal registrada para viáticos.
  Debe registrarla en su expediente antes de que Gestión Financiera entregue el anticipo.
</div>
@endif

{{-- ══ CLÁUSULA ══ --}}
<div class="clausula">
  ★ AUTORIZO QUE LOS VALORES NO JUSTIFICADOS SEAN DEBITADOS
  DE MI PRÓXIMA REMUNERACIÓN MENSUAL UNIFICADA.
</div>

{{-- ══ FIRMAS ══ --}}
<div class="firmas">
  <div class="fc">
    <div class="fline"></div>
    <div class="fn">{{ $servidorNombre }}</div>
    <div class="fc-cargo">{{ $viatico->servidor?->puesto?->cargo?->nombre ?? '' }}</div>
    <div class="fc-rol">Servidor Solicitante</div>
  </div>
  <div class="fc">
    <div class="fline"></div>
    @if($firmas['jefe_unidad']['nombre'] ?? null)
      <div class="fn">{{ $firmas['jefe_unidad']['nombre'] }}</div>
      <div class="fc-cargo">{{ $firmas['jefe_unidad']['cargo'] }}</div>
    @else
      <div class="fn" style="color:#a0aec0">___________________________</div>
      <div class="fc-cargo" style="color:#a0aec0">Director/a de Unidad</div>
    @endif
    <div class="fc-rol">Responsable de Unidad Solicitante</div>
  </div>
  <div class="fc">
    <div class="fline"></div>
    @if($firmas['maxima_autoridad']['nombre'] ?? null)
      <div class="fn">{{ $firmas['maxima_autoridad']['nombre'] }}</div>
      <div class="fc-cargo">{{ $firmas['maxima_autoridad']['cargo'] }}</div>
    @else
      <div class="fn" style="color:#a0aec0">___________________________</div>
      <div class="fc-cargo">Prefecto/a Provincial</div>
    @endif
    <div class="fc-rol">Máxima Autoridad o Delegado</div>
  </div>
</div>

{{-- ══ PIE ══ --}}
<div class="footer">
  <div class="fl">SGTH — GAD Provincial de Esmeraldas</div>
  <div class="fr">
    Generado el {{ date('d/m/Y H:i') }} •
    Documento oficial — No requiere sello húmedo
  </div>
</div>

</body>
</html>
