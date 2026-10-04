<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }} — {{ $periodo }}</title>
    <style>
        /* Sin `* { margin: 0 }`: en dompdf también borra el margen de la
           página y `@page` no lo recupera. */
        @page { margin: 30px 40px 50px; }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #222;
            line-height: 1.25;
        }
        .encabezado { text-align: center; border-bottom: 2px solid #222; padding-bottom: 8px; }
        .encabezado h1 { font-size: 14px; margin: 0; letter-spacing: 0.5px; }
        .encabezado h2 { font-size: 11px; font-weight: normal; margin: 3px 0 0; }
        .titulo { text-align: center; margin: 10px 0 2px; font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .periodo { text-align: center; font-size: 10.5px; color: #444; margin-bottom: 6px; }

        h3 { font-size: 11px; margin: 9px 0 2px; padding-bottom: 1px; border-bottom: 1px solid #999; }
        table { width: 100%; border-collapse: collapse; }
        td { border-bottom: 1px solid #e3e3e3; padding: 2px 6px; vertical-align: top; }
        td.valor { width: 22%; text-align: right; font-weight: bold; }

        /* Junto al final, sin `page-break-inside: avoid`: en dompdf eso no
           encaja el bloque, lo manda entero a la hoja siguiente. */
        .firma { margin-top: 34px; text-align: center; }
        .firma .linea { border-top: 1px solid #222; width: 260px; margin: 0 auto 4px; }

        /* Fijo y con `bottom` positivo: negativo cae fuera de la hoja. */
        .pie {
            position: fixed; bottom: 0; left: 0; right: 0;
            font-size: 8.5px; color: #666; border-top: 1px solid #ddd; padding-top: 4px;
        }
    </style>
</head>
<body>

<div class="pie">
    {{ $alcance }} · Generado por {{ $generadoPor }} el {{ now()->format('d/m/Y H:i') }} ·
    Cifras agregadas: este informe no contiene datos de pacientes.
</div>

<div class="encabezado">
    <h1>GOBIERNO AUTÓNOMO DESCENTRALIZADO PROVINCIAL DE ESMERALDAS</h1>
    <h2>Dispensario Médico</h2>
</div>

<div class="titulo">{{ mb_strtoupper($titulo) }}</div>
<div class="periodo">Período: {{ $periodo }}</div>

@foreach ($secciones as $seccion => $filas)
    <h3>{{ $seccion }}</h3>
    <table>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['indicador'] }}</td>
                <td class="valor">{{ $fila['valor'] }}</td>
            </tr>
        @endforeach
    </table>
@endforeach

<div class="firma">
    <div class="linea"></div>
    Responsable del Dispensario Médico
</div>

</body>
</html>
