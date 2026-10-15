{{-- La credencial en hojas del tamaño de la tarjeta, para la impresora de PVC
     y para la credencial digital: anverso y reverso seguidos, por cada una
     (docs/diseno-comunicacion-social.md, 6.2; D7). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Credencial</title>
    @include('pdf.comunicacion._credencial-estilos')
    <style>
        .pagina { width: 54mm; height: 85.6mm; }
        .salto { page-break-after: always; }
    </style>
</head>
<body>
@foreach ($tarjetas as $t)
    <div class="pagina salto">
        @include('pdf.comunicacion._credencial-anverso', ['x' => 0, 'y' => 0, 'guia' => false])
    </div>
    <div class="pagina{{ $loop->last ? '' : ' salto' }}">
        @include('pdf.comunicacion._credencial-reverso', ['x' => 0, 'y' => 0, 'guia' => false])
    </div>
@endforeach
</body>
</html>
