<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nómina de Servidores</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            color: #222;
            margin: 0;
            padding: 20px 25px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header img {
            max-width: 110px;
        }
        .header h1 {
            font-size: 14px;
            margin: 4px 0 0 0;
        }
        .header h2 {
            font-size: 10px;
            font-weight: normal;
            margin: 2px 0 0 0;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 4px 5px;
            text-align: left;
        }
        th {
            background-color: #0d6efd;
            color: #fff;
            font-size: 8.5px;
            text-transform: uppercase;
        }
        td {
            font-size: 8.5px;
        }
        tr:nth-child(even) td {
            background-color: #f7f7f7;
        }
        .footer {
            margin-top: 10px;
            text-align: right;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(file_exists($logo))
            <img src="{{ $logo }}" alt="Logo GADPE">
        @endif
        <h1>NÓMINA DE SERVIDORES</h1>
        <h2>GAD Provincial de Esmeraldas — Generado el {{ $fecha->locale('es')->isoFormat('DD [de] MMMM [del] YYYY, HH:mm') }}</h2>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Cédula</th>
                <th>Nombres y Apellidos</th>
                <th>Cargo</th>
                <th>Gestión</th>
                <th>Tipo de Nombramiento</th>
                <th>R.M.U</th>
                <th>Fecha de Ingreso</th>
                <th>Fecha de Salida</th>
            </tr>
        </thead>
        <tbody>
            @foreach($servidores as $fila)
            <tr>
                <td>{{ $fila['ITEM'] }}</td>
                <td>{{ $fila['CÉDULA'] }}</td>
                <td>{{ $fila['NOMBRES Y APELLIDOS'] }}</td>
                <td>{{ $fila['CARGO'] ?? '—' }}</td>
                <td>{{ $fila['GESTIÓN'] ?? '—' }}</td>
                <td>{{ $fila['TIPO DE NOMBRAMIENTO'] ?? '—' }}</td>
                <td>{{ $fila['R.M.U'] !== null ? '$ '.number_format((float) $fila['R.M.U'], 2) : '—' }}</td>
                <td>{{ $fila['FECHA DE INGRESO'] ?? '—' }}</td>
                <td>{{ $fila['FECHA DE SALIDA'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Total de servidores: {{ $servidores->count() }}
    </div>
</body>
</html>
