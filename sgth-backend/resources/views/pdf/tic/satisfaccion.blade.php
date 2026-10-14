@extends('pdf.tic._base')

@php
    $promedio = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.');
    $secciones = [
        ['Por técnico', 'tecnico', 'Técnico', $reporte['por_tecnico']],
        ['Por categoría', 'categoria', 'Categoría', $reporte['por_categoria']],
    ];
@endphp

@section('contenido')
    <table class="resumen">
        <tr>
            <td><div class="cifra">{{ $reporte['encuestas'] }}</div><div class="rotulo">Encuestas</div></td>
            <td><div class="cifra">{{ $reporte['promedio'] === null ? '—' : $promedio($reporte['promedio']) . ' / 5' }}</div><div class="rotulo">Promedio</div></td>
            @foreach($reporte['distribucion'] as $nota => $total)
                <td><div class="cifra">{{ $total }}</div><div class="rotulo">{{ $nota }} {{ $nota === 1 ? 'estrella' : 'estrellas' }}</div></td>
            @endforeach
        </tr>
    </table>

    @foreach($secciones as [$seccion, $campo, $columna, $filas])
        <div class="grupo">{{ $seccion }}</div>
        @if(count($filas) === 0)
            <p class="vacio">Sin encuestas en el período.</p>
        @else
            <table class="listado">
                <thead><tr><th>{{ $columna }}</th><th class="num">Encuestas</th><th class="num">Promedio</th></tr></thead>
                <tbody>
                    @foreach($filas as $f)
                        <tr>
                            <td>{{ $f[$campo] }}</td>
                            <td class="num">{{ $f['encuestas'] }}</td>
                            <td class="num">{{ $promedio($f['promedio']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <div class="grupo">Comentarios</div>
    @if(count($reporte['comentarios']) === 0)
        <p class="vacio">Nadie dejó comentarios en el período.</p>
    @else
        <table class="listado">
            <thead>
                <tr>
                    <th style="width: 11%;">Ticket</th>
                    <th class="num" style="width: 10%;">Fecha</th>
                    <th class="num" style="width: 6%;">Nota</th>
                    <th style="width: 16%;">Técnico</th>
                    <th style="width: 14%;">Categoría</th>
                    <th>Comentario</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reporte['comentarios'] as $c)
                    <tr>
                        <td>{{ $c['folio'] }}</td>
                        <td class="num">{{ \Carbon\Carbon::parse($c['fecha'])->setTimezone(config('app.timezone'))->format('d/m/Y') }}</td>
                        <td class="num">{{ $c['calificacion'] }}</td>
                        <td>{{ $c['tecnico'] ?? '—' }}</td>
                        <td>{{ $c['categoria'] ?? '—' }}</td>
                        <td>{{ $c['comentario'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
