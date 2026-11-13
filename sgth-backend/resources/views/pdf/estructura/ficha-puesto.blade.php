<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Descripción y perfil del puesto</title>
    {{-- La ficha del Manual de Puestos (docs/diseno-manual-de-puestos.md, P5),
         con sus diez secciones. Va por bandas a todo el ancho y no en dos
         columnas, como el original: dompdf no parte bien entre páginas una
         celda muy alta, y una ficha con doce actividades la tiene. --}}
    <style>
        @include('pdf.estructura._ficha-estilos')
    </style>
</head>
<body>
    <footer>
        Generado por el Sistema de Gestión de Talento Humano el {{ $fecha->format('d/m/Y H:i') }} · Página <span class="pagina"></span>
    </footer>

    @include('pdf.estructura._ficha-contenido')
</body>
</html>
