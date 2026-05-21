<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Declaraciones Juramentadas - Contraloría</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 10px; margin: 20px; }
        h2 { font-size: 12px; text-align: center; margin-bottom: 5px; }
        p  { font-size: 9px; text-align: center; margin: 2px 0; }
        pre { font-size: 9px; margin-top: 15px; line-height: 1.8; }
        .footer { margin-top: 20px; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <h2>DECLARACIONES JURAMENTADAS DE BIENES</h2>
    <p>Contraloría General del Estado - Ecuador</p>
    <p>Formato: Acuerdo 005-CG-2019 Art.7</p>
    <p>Servidor: {{ strtoupper(($servidor->apellido ?? '').' '.($servidor->nombre ?? '')) }}</p>
    <p>Cédula: {{ $servidor->cedula }}</p>
    <p>Período: {{ $fechaInicio }} al {{ $fechaFin }}</p>
    <hr>
    <pre>{{ $lineas->implode("\n") }}</pre>
    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} desde SGTH - GAD Provincial de Esmeraldas
    </div>
</body>
</html>