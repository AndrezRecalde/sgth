{{-- Hojas A4 de nueve credenciales por cara, para recortar y plastificar
     (docs/diseno-comunicacion-social.md, 6.2; D7). Cada hoja de anversos va
     seguida de la de sus reversos, en espejo por columnas: se imprime a doble
     cara volteando por el borde largo. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Credenciales</title>
    @include('pdf.comunicacion._credencial-estilos')
    <style>
        .pagina { width: 210mm; height: 296mm; }
        .salto { page-break-after: always; }
        .instruccion {
            position: absolute; left: 0; width: 210mm; bottom: 3mm;
            font-size: 6.5pt; color: #6b7280; text-align: center;
        }
    </style>
</head>
<body>
@foreach ($hojas as $hoja)
    <div class="pagina salto">
        @foreach ($hoja['anverso'] as $i => $t)
            @if ($t)
                @include('pdf.comunicacion._credencial-anverso', ['x' => $rejilla[$i]['x'], 'y' => $rejilla[$i]['y'], 'guia' => true])
            @endif
        @endforeach
        <div class="instruccion">Anversos, hoja {{ $loop->iteration }} de {{ $loop->count }}. Imprima a doble cara volteando por el borde largo.</div>
    </div>
    <div class="pagina{{ $loop->last ? '' : ' salto' }}">
        @foreach ($hoja['reverso'] as $i => $t)
            @if ($t)
                @include('pdf.comunicacion._credencial-reverso', ['x' => $rejilla[$i]['x'], 'y' => $rejilla[$i]['y'], 'guia' => true])
            @endif
        @endforeach
        <div class="instruccion">Reversos, hoja {{ $loop->iteration }} de {{ $loop->count }}.</div>
    </div>
@endforeach
</body>
</html>
