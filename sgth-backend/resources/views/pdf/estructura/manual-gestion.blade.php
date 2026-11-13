<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Manual de Puestos — {{ $gestion }}</title>
    {{-- El Manual de Puestos de una gestión (docs/diseno-manual-de-puestos.md,
         P5, PR 14), como la «Distribución del Manual de Puestos 2018-2019»:
         portada, distribución de los puestos por subproceso y la ficha de cada
         uno. Sin el diagrama del proceso, que es de Calidad (v1.6). --}}
    <style>
        @include('pdf.estructura._ficha-estilos')

        .salto { page-break-before: always; }

        .portada { text-align: center; }
        .portada .titulo {
            margin-top: 120px;
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #2f3a8f;
        }
        .portada .gestion { margin-top: 16px; font-size: 16px; font-weight: bold; }
        .portada .dependencia { margin-top: 6px; font-size: 10px; color: #4b5563; }
        .portada .detalle { margin-top: 70px; font-size: 9px; color: #6b7280; line-height: 1.6; }

        .subtitulo { text-align: center; font-size: 9px; margin: -4px 0 8px 0; }
        table.ficha td.total { font-weight: bold; background: #f5f6fb; }
    </style>
</head>
<body>
    <footer>
        Manual de Puestos · {{ $gestion }} · Generado por el Sistema de Gestión de Talento Humano el {{ $fecha->format('d/m/Y H:i') }} · Página <span class="pagina"></span>
    </footer>

    {{-- 1. Portada --}}
    <div class="portada">
        @include('pdf._membrete-institucional', ['dependencia' => 'Dirección de Gestión de Talento Humano'])
        <div class="titulo">MANUAL DE PUESTOS</div>
        <div class="gestion">{{ mb_strtoupper($gestion) }}</div>
        <div class="dependencia">Descripción y perfil de los puestos de plantilla</div>
        <div class="detalle">
            {{ $total_puestos }} {{ $total_puestos === 1 ? 'puesto' : 'puestos' }} ·
            {{ $total_plazas }} {{ $total_plazas === 1 ? 'plaza' : 'plazas' }}<br>
            Generado el {{ $fecha->format('d/m/Y') }}
        </div>
    </div>

    {{-- 2. La distribución: cuántos puestos de cada denominación, por subproceso --}}
    <div class="salto"></div>
    @include('pdf._membrete-institucional', ['dependencia' => 'Dirección de Gestión de Talento Humano'])
    <h1>DISTRIBUCIÓN DE PUESTOS</h1>
    <p class="subtitulo">{{ $gestion }}</p>

    {{-- Los anchos en la cabecera, como en las competencias de la ficha: con
         `colgroup`, dompdf los repartía iguales. --}}
    <table class="ficha">
        <thead>
            <tr>
                <th class="sub" style="width: 50%;">Denominación del puesto</th>
                <th class="sub" style="width: 30%;">Grupo ocupacional</th>
                <th class="sub" style="width: 10%;">Grado</th>
                <th class="sub" style="width: 10%;">Plazas</th>
            </tr>
        </thead>
        <tbody>
            @foreach($distribucion as $grupo)
                <tr>
                    <th class="seccion" colspan="4">
                        {{ $grupo['subproceso'] ? 'SUBPROCESO: '.mb_strtoupper($grupo['subproceso']) : 'GESTIÓN: '.mb_strtoupper($gestion) }}
                    </th>
                </tr>
                @foreach($grupo['puestos'] as $fila)
                    <tr>
                        <td>{{ $fila['denominacion'] ?? '—' }}</td>
                        <td>{{ $fila['grupo'] ?? '—' }}</td>
                        <td class="centro">{{ $fila['grado'] ?? '—' }}</td>
                        <td class="centro">{{ $fila['plazas'] }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr>
                <td class="total" colspan="3">Total de la gestión: {{ $total_puestos }} {{ $total_puestos === 1 ? 'puesto' : 'puestos' }}</td>
                <td class="total centro">{{ $total_plazas }}</td>
            </tr>
        </tbody>
    </table>

    {{-- 3. La ficha de cada puesto, en el orden de la distribución --}}
    @foreach($fichas as $ficha)
        <div class="salto"></div>
        @include('pdf.estructura._ficha-contenido', $ficha)
    @endforeach
</body>
</html>
