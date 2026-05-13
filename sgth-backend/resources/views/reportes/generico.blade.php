<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $metadata['reporte'] ?? 'Reporte Institucional' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-width: 150px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <!-- Logo institucional GAD / Ministerio -->
        <h2>{{ $metadata['reporte'] ?? 'Reporte Institucional SGTH' }}</h2>
        <p>Generado el: {{ \Carbon\Carbon::parse($metadata['fecha'] ?? now())->format('d/m/Y H:i') }}</p>
    </div>

    @if(!empty($datos))
        <table>
            <thead>
                <tr>
                    @foreach(array_keys((array) $datos[0]) as $cabecera)
                        <th>{{ strtoupper(str_replace('_', ' ', $cabecera)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($datos as $fila)
                    <tr>
                        @foreach((array) $fila as $valor)
                            <td>{{ is_array($valor) ? json_encode($valor) : $valor }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No se encontraron registros para este reporte.</p>
    @endif
</body>
</html>
