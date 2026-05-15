<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado Laboral</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
        }
        .header h2 {
            font-size: 14px;
            font-weight: normal;
            margin: 0;
        }
        .content {
            margin-bottom: 30px;
            text-align: justify;
        }
        .servidor-info {
            font-weight: bold;
            margin: 15px 0;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            font-size: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .footer {
            margin-top: 50px;
        }
        .fecha {
            text-align: right;
            margin-bottom: 80px;
        }
        .firma-box {
            text-align: center;
            width: 300px;
            margin: 0 auto;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        .firma-nombre {
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .firma-cargo {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(file_exists(public_path('images/logo-gad.png')))
            <img src="{{ public_path('images/logo-gad.png') }}" class="logo" alt="Logo GAD">
        @else
            <h1>GAD PROVINCIAL DE ESMERALDAS</h1>
        @endif
        <h1>CERTIFICADO LABORAL</h1>
        <h2>Unidad de Administración de Talento Humano - UATH</h2>
    </div>

    <div class="content">
        <p>La Dirección de Administración de Talento Humano del Gobierno Autónomo Descentralizado Provincial de Esmeraldas, certifica que el/la servidor/a:</p>
        
        <p class="servidor-info">{{ $servidor->nombre_completo ?? $servidor->nombre . ' ' . $servidor->apellido }}<br>
        C.I.: {{ $servidor->cedula }}</p>

        <p>Ha prestado sus servicios institucionales en los siguientes períodos:</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th>Tipo Nombramiento</th>
                <th>N° Contrato</th>
                <th>Unidad Administrativa</th>
                <th>Puesto</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($servidor->contratos as $index => $contrato)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $contrato->tipo_nombramiento->etiqueta() }}</td>
                <td>{{ $contrato->numero_contrato ?? 'N/A' }}</td>
                <td>{{ $contrato->unidadAdministrativa->nombre ?? 'N/A' }}</td>
                <td>{{ $contrato->puesto->denominacion ?? 'N/A' }}</td>
                <td>{{ \Carbon\Carbon::parse($contrato->fecha_inicio)->format('d/m/Y') }}</td>
                <td>{{ $contrato->fecha_fin ? \Carbon\Carbon::parse($contrato->fecha_fin)->format('d/m/Y') : 'Hasta la actualidad' }}</td>
                <td>{{ ucfirst($contrato->estado->value) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="fecha">
            Esmeraldas, {{ \Carbon\Carbon::now()->locale('es')->isoFormat('DD \d\e MMMM \d\e YYYY') }}
        </div>

        <div class="firma-box">
            <p class="firma-nombre">Director/a de UATH</p>
            <p class="firma-cargo">Director de Administración de Talento Humano<br>GAD Provincial de Esmeraldas</p>
        </div>
    </div>
</body>
</html>
