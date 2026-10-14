@extends('pdf.tic._base')

@php
    $t = $reporte['totales'];
    $valor = fn ($v, $sufijo = '') => $v === null ? '—' : number_format((float) $v, is_float($v) ? 1 : 0, ',', '.') . $sufijo;
    $secciones = [
        ['Por técnico', 'tecnico', 'Técnico', $reporte['por_tecnico']],
        ['Por categoría', 'categoria', 'Categoría', $reporte['por_categoria']],
    ];
@endphp

@section('contenido')
    <table class="resumen">
        <tr>
            <td><div class="cifra">{{ $t['abiertos'] }}</div><div class="rotulo">Recibidos</div></td>
            <td><div class="cifra">{{ $t['resueltos'] }}</div><div class="rotulo">Resueltos</div></td>
            <td><div class="cifra">{{ $valor($t['cumplimiento_sla'], ' %') }}</div><div class="rotulo">Dentro del plazo</div></td>
            <td><div class="cifra">{{ $valor($t['horas_resolucion'], ' h') }}</div><div class="rotulo">Tiempo de resolución</div></td>
            <td><div class="cifra">{{ $valor($t['satisfaccion'], ' / 5') }}</div><div class="rotulo">Satisfacción</div></td>
        </tr>
    </table>
    <p class="nota">Recibidos: abiertos en el período. Resueltos: resueltos en el período. Tiempo de resolución: promedio en horas hábiles, sin contar la espera del usuario.</p>

    @foreach($secciones as [$seccion, $campo, $columna, $filas])
        <div class="grupo">{{ $seccion }}</div>
        @if(count($filas) === 0)
            <p class="vacio">Sin tickets en el período.</p>
        @else
            <table class="listado">
                <thead>
                    <tr>
                        <th>{{ $columna }}</th>
                        <th class="num">Recibidos</th>
                        <th class="num">Resueltos</th>
                        <th class="num">En plazo</th>
                        <th class="num">Tiempo de resolución</th>
                        <th class="num">Reabiertos</th>
                        <th class="num">Satisfacción</th>
                        <th class="num">Encuestas</th>
                        @if($campo === 'tecnico')<th class="num">En curso hoy</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($filas as $f)
                        <tr>
                            <td>{{ $f[$campo] }}</td>
                            <td class="num">{{ $f['abiertos'] }}</td>
                            <td class="num">{{ $f['resueltos'] }}</td>
                            <td class="num">{{ $valor($f['cumplimiento_sla'], ' %') }}</td>
                            <td class="num">{{ $valor($f['horas_resolucion'], ' h') }}</td>
                            <td class="num">{{ $f['reabiertos'] }}</td>
                            <td class="num">{{ $valor($f['satisfaccion']) }}</td>
                            <td class="num">{{ $f['encuestas'] }}</td>
                            @if($campo === 'tecnico')<td class="num">{{ $f['en_curso'] }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
@endsection
