{{-- La base de los reportes de TIC (fase 4.2): el membrete del inventario, A4
     horizontal y las tablas de listado. `thead` como grupo de encabezado
     repite los títulos de columna en cada página. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    @include('pdf.inventario._estilos')
    <style>
        .resumen { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .resumen td { border: 0.7px solid #cfdad4; padding: 5px 8px; text-align: center; }
        .resumen .cifra { font-size: 15px; font-weight: bold; color: #17593f; }
        .resumen .rotulo { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #5b6b63; }

        table.listado { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.listado thead { display: table-header-group; }
        table.listado th {
            background: #f6faf8;
            border: 0.7px solid #cfdad4;
            padding: 4px 5px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #5b6b63;
            text-align: left;
        }
        table.listado td { border: 0.7px solid #cfdad4; padding: 3px 5px; font-size: 8.5px; vertical-align: top; }
        table.listado td.num, table.listado th.num { text-align: right; white-space: nowrap; }
        table.listado tr { page-break-inside: avoid; }
        table.listado tr.total td { font-weight: bold; background: #f6faf8; }

        .grupo { font-size: 10px; font-weight: bold; color: #17593f; margin: 10px 0 4px 0; page-break-after: avoid; }
        .grupo span { font-weight: normal; color: #5b6b63; }
        .nota { font-size: 8.5px; color: #5b6b63; margin: 0 0 10px 0; }
        .vacio { font-size: 9.5px; color: #5b6b63; font-style: italic; }
    </style>
</head>
<body>
    @include('pdf.inventario._membrete')

    <div class="titulo">
        <div class="supra">Reporte de Tecnologías</div>
        <h1>{{ $titulo }}</h1>
    </div>
    <p class="nota" style="text-align: center;">{{ $subtitulo }} · Generado el {{ now()->format('d/m/Y H:i') }}</p>

    @yield('contenido')
</body>
</html>
