{{--
| El consolidado de permisos por servidor en un período, y el ausentismo por
| enfermedad de Riesgos Laborales, que es el mismo informe con el tipo fijo
| (`AusentismoSsoController`). El encabezado lo decide cada controlador.
|
| Desde el 2026-11-24 en el estilo de los documentos institucionales, como los
| reportes de TIC: el CSS de casa del inventario, A4 apaisado, membrete y pie
| fijos en cada hoja y los títulos de columna repetidos si pasa a otra. Antes
| llevaba una cabecera de recuadro negro, otro verde y el nombre de la
| institución escrito a mano. Sin firmas (decidido con el usuario).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $encabezado['titulo'] }}</title>
@include('pdf.inventario._estilos')
<style>
  .titulo { margin-bottom: 10px; }
  .titulo .subtipo { font-size: 11px; color: #3d4d46; margin-top: 1px; }
  .titulo .regla { border-bottom: 1.6px solid #17593f; width: 56px; margin: 5px auto 0 auto; }

  .alcance { font-size: 9.5px; color: #3d4d46; margin: 0 0 6px 0; }
  .alcance strong { color: #1c2321; }

  .resumen { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .resumen td { border: 0.7px solid #cfdad4; padding: 5px 8px; text-align: center; width: 25%; }
  .resumen .cifra { font-size: 15px; font-weight: bold; color: #17593f; }
  .resumen .rotulo { font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; color: #5b6b63; }

  table.listado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  /* Repite los títulos de columna si el listado pasa a la hoja siguiente. */
  table.listado thead { display: table-header-group; }
  table.listado th {
    background: #f2f6f4;
    border: 0.7px solid #c6d2cc;
    padding: 4px 6px;
    font-size: 8px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #3d4d46;
    text-align: left;
  }
  table.listado td { border: 0.7px solid #c6d2cc; padding: 3px 6px; font-size: 9.5px; vertical-align: top; }
  table.listado tr { page-break-inside: avoid; }
  table.listado .num { text-align: right; white-space: nowrap; }
  table.listado td.n { color: #6b7c74; }
  table.listado td.cedula { white-space: nowrap; }
  table.listado tr.total td { font-weight: bold; background: #f2f6f4; }
  .vacio { color: #5b6b63; font-style: italic; text-align: center; padding: 12px; }

  .nota { font-size: 8.5px; color: #5b6b63; margin: 0; }
</style>
</head>
<body>
@include('pdf._membrete-institucional', ['dependencia' => $encabezado['dependencia']])
@include('pdf._pie-institucional')

<div class="titulo">
  @if($encabezado['supra'])<div class="supra">{{ $encabezado['supra'] }}</div>@endif
  <h1>{{ $encabezado['titulo'] }}</h1>
  <div class="subtipo">@if($subtipo){{ $subtipo }} · @endif{{ $periodo }}</div>
  <div class="regla"></div>
</div>

<p class="alcance">
  <strong>{{ $alcance }}</strong>
  @if($esEnfermedad)
    · Incluye los permisos por enfermedad y los reposos médicos aprobados, en días calendario.
  @endif
</p>

<table class="resumen">
  <tr>
    <td><div class="cifra">{{ count($consolidado) }}</div><div class="rotulo">Servidores</div></td>
    <td><div class="cifra">{{ $totales['total_permisos'] }}</div><div class="rotulo">{{ $rotuloCuenta }}</div></td>
    <td><div class="cifra">{{ $totales['tiempo_total'] }}</div><div class="rotulo">Horas</div></td>
    <td><div class="cifra">{{ number_format($totales['total_dias'], 2) }}</div><div class="rotulo">Días de jornada</div></td>
  </tr>
</table>

<table class="listado">
  <thead>
    <tr>
      <th class="num" style="width: 4%;">N.º</th>
      <th style="width: 9%;">Cédula</th>
      <th style="width: 27%;">Servidor</th>
      <th style="width: 36%;">Unidad administrativa</th>
      <th class="num" style="width: 7%;">{{ $rotuloCuenta }}</th>
      <th class="num" style="width: 6%;">Minutos</th>
      <th class="num" style="width: 6%;">Horas</th>
      <th class="num" style="width: 5%;">Días</th>
    </tr>
  </thead>
  <tbody>
    @forelse($consolidado as $fila)
      <tr>
        <td class="num n">{{ $loop->iteration }}</td>
        <td class="cedula">{{ $fila['cedula'] }}</td>
        <td>{{ $fila['nombre'] }}</td>
        <td>{{ $fila['unidad'] }}</td>
        <td class="num">{{ $fila['total_permisos'] }}</td>
        <td class="num">{{ $fila['total_minutos'] }}</td>
        <td class="num">{{ $fila['tiempo_total'] }}</td>
        <td class="num">{{ number_format($fila['total_dias'], 2) }}</td>
      </tr>
    @empty
      <tr><td colspan="8" class="vacio">Sin registros en el período.</td></tr>
    @endforelse
    @if(count($consolidado))
      <tr class="total">
        <td colspan="4" class="num">Total</td>
        <td class="num">{{ $totales['total_permisos'] }}</td>
        <td class="num">{{ $totales['total_minutos'] }}</td>
        <td class="num">{{ $totales['tiempo_total'] }}</td>
        <td class="num">{{ number_format($totales['total_dias'], 2) }}</td>
      </tr>
    @endif
  </tbody>
</table>

<p class="nota">Horas y días de jornada: una jornada son 8 horas. Generado el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }}.</p>
</body>
</html>
