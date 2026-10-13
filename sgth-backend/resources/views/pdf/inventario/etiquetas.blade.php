<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas de equipos informáticos</title>
    <style>
        /* dompdf: tablas, no flexbox. La suelta ocupa su hoja de 70 × 40 mm; en
           A4 van 3 por fila y 8 filas (8 × 34 mm caben en los 277 mm útiles),
           con la línea de corte punteada. Si una fila no cabe, dompdf la manda
           a la hoja siguiente y deja una en blanco. */
        @page { margin: {{ $suelta ? '0' : '10mm 6mm' }}; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; margin: 0; color: #1c2321; }
        table.hoja { width: 100%; border-collapse: collapse; }
        td.etiqueta {
            width: 33.33%;
            height: {{ $suelta ? '34mm' : '31mm' }};
            border: {{ $suelta ? 'none' : '0.5px dashed #b9c6bf' }};
            padding: {{ $suelta ? '2mm 3mm' : '1.5mm 2mm' }};
            vertical-align: middle;
        }
        .interna { width: 100%; border-collapse: collapse; }
        .interna td { border: none; padding: 0; vertical-align: middle; }
        .qr { width: 23mm; }
        .qr img { width: 22mm; height: 22mm; }
        .institucion { font-size: 6px; letter-spacing: 0.4px; text-transform: uppercase; color: #5b6b63; }
        .codigo { font-size: 15px; font-weight: bold; letter-spacing: 0.5px; color: #17593f; margin: 1px 0; }
        .detalle { font-size: 6.5px; color: #3d4d46; line-height: 1.3; }
        .serie { font-size: 6px; color: #5b6b63; margin-top: 1px; }
    </style>
</head>
<body>
@php($filas = $suelta ? $etiquetas->chunk(1) : $etiquetas->chunk(3))
<table class="hoja">
    @foreach($filas as $fila)
        <tr>
            @foreach($fila as $e)
                <td class="etiqueta" @if($suelta) style="width: 100%;" @endif>
                    <table class="interna">
                        <tr>
                            <td class="qr">
                                @if($e['qr'])
                                    <img src="{{ $e['qr'] }}" alt="QR">
                                @endif
                            </td>
                            <td>
                                <div class="institucion">GAD Provincial de Esmeraldas · TIC</div>
                                <div class="codigo">{{ $e['codigo'] }}</div>
                                <div class="detalle">{{ $e['detalle'] }}</div>
                                @if($e['serie'])
                                    <div class="serie">S/N {{ $e['serie'] }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            @endforeach
            @for($i = $fila->count(); $i < ($suelta ? 1 : 3); $i++)
                <td class="etiqueta"></td>
            @endfor
        </tr>
    @endforeach
</table>
</body>
</html>
