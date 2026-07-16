<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formulario 028 - Evaluación Médica Ocupacional</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #000;
            margin: 0;
            padding: 15px;
        }
        .msp-page {
            page-break-after: always;
        }
        .msp-page:last-child {
            page-break-after: avoid;
        }
        .msp-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .msp-header td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: middle;
        }
        .msp-header .logo-cell {
            width: 70px;
            text-align: center;
        }
        .msp-header .logo-cell img {
            max-width: 60px;
            max-height: 45px;
        }
        .msp-header .titulo-cell {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
        .msp-header .titulo-cell .subtitulo {
            font-weight: normal;
            font-size: 8px;
        }
        .msp-section-title {
            background-color: #d9d9d9;
            font-weight: bold;
            font-size: 9px;
            padding: 3px 4px;
            border: 1px solid #000;
            margin-top: 4px;
        }
        table.msp-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }
        table.msp-table th, table.msp-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            vertical-align: top;
            text-align: left;
        }
        table.msp-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .msp-label {
            font-weight: bold;
            font-size: 7px;
            text-transform: uppercase;
            color: #333;
        }
        .msp-value {
            font-size: 9px;
            min-height: 10px;
        }
        .msp-check {
            text-align: center;
            width: 20px;
            font-weight: bold;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .small { font-size: 7px; }
        .footer-firma {
            margin-top: 20px;
            width: 100%;
        }
        .footer-firma td {
            text-align: center;
            width: 50%;
            padding-top: 25px;
        }
        .firma-linea {
            border-top: 1px solid #000;
            width: 80%;
            margin: 0 auto;
            padding-top: 3px;
            font-size: 8px;
        }
    </style>
</head>
<body>
    @include('pdf.dispensario.femo._pagina-1', ['ficha' => $ficha, 'persona' => $persona, 'examenFisicoPorRegion' => $examenFisicoPorRegion, 'antecedentesPorTipo' => $antecedentesPorTipo, 'regiones' => $regiones, 'logo' => $logo])
    @include('pdf.dispensario.femo._pagina-2', ['ficha' => $ficha, 'actividadesRiesgo' => $actividadesRiesgo, 'filasRiesgoPorCategoria' => $filasRiesgoPorCategoria, 'categoriasRiesgo' => $categoriasRiesgo, 'logo' => $logo])
    @include('pdf.dispensario.femo._pagina-3', ['ficha' => $ficha, 'logo' => $logo])
</body>
</html>
