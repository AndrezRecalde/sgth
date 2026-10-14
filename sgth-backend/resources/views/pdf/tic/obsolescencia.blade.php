@extends('pdf.tic._base')

@php($t = $reporte['totales'])

@section('contenido')
    <table class="resumen">
        <tr>
            <td><div class="cifra">{{ $t['en_servicio'] }}</div><div class="rotulo">En servicio</div></td>
            <td><div class="cifra">{{ $t['cumplida'] }}</div><div class="rotulo">Vida útil cumplida</div></td>
            <td><div class="cifra">{{ $t['proxima'] }}</div><div class="rotulo">Se cumple en 12 meses</div></td>
            <td><div class="cifra">{{ $t['sin_fecha'] }}</div><div class="rotulo">Sin fin de vida útil</div></td>
        </tr>
    </table>
    <p class="nota">«Sin fin de vida útil»: falta la fecha de compra o el tipo no tiene años de vida útil definidos.</p>

    <div class="grupo">Resumen por tipo</div>
    <table class="listado">
        <thead>
            <tr>
                <th>Tipo</th>
                <th class="num">Vida útil</th>
                <th class="num">En servicio</th>
                <th class="num">Cumplida</th>
                <th class="num">En 12 meses</th>
                <th class="num">Sin fin de vida útil</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reporte['resumen'] as $fila)
                <tr>
                    <td>{{ $fila['tipo'] }}</td>
                    <td class="num">{{ $fila['anios_vida_util'] ? $fila['anios_vida_util'] . ' años' : 'Sin definir' }}</td>
                    <td class="num">{{ $fila['en_servicio'] }}</td>
                    <td class="num">{{ $fila['cumplida'] }}</td>
                    <td class="num">{{ $fila['proxima'] }}</td>
                    <td class="num">{{ $fila['sin_fecha'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="grupo">Equipos para reemplazo <span>· del que cumplió antes</span></div>
    @if(count($reporte['equipos']) === 0)
        <p class="vacio">Ningún equipo en servicio cumplió su vida útil ni la cumple en los próximos 12 meses.</p>
    @else
        <table class="listado">
            <thead>
                <tr>
                    <th style="width: 7%;">Código</th>
                    <th style="width: 18%;">Equipo</th>
                    <th class="num" style="width: 8%;">Compra</th>
                    <th class="num" style="width: 5%;">Años</th>
                    <th class="num" style="width: 9%;">Fin de vida útil</th>
                    <th style="width: 9%;">Situación</th>
                    <th style="width: 8%;">Condición</th>
                    <th class="num" style="width: 7%;">Correctivos</th>
                    <th>Custodio y unidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reporte['equipos'] as $e)
                    <tr>
                        <td>{{ $e['codigo_institucional'] }}</td>
                        <td>{{ trim(($e['tipo'] ?? '') . ' ' . ($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? '')) }}</td>
                        <td class="num">{{ $e['fecha_compra'] ? \Carbon\Carbon::parse($e['fecha_compra'])->format('d/m/Y') : '—' }}</td>
                        <td class="num">{{ $e['anios'] ?? '—' }}</td>
                        <td class="num">{{ \Carbon\Carbon::parse($e['fin_vida_util'])->format('d/m/Y') }}</td>
                        <td>{{ $e['situacion'] === 'cumplida' ? 'Cumplida' : 'Próxima' }}</td>
                        <td>{{ $e['condicion'] ?? '—' }}</td>
                        <td class="num">{{ $e['correctivos'] }}</td>
                        <td>{{ $e['custodio'] ?? 'Sin custodio' }}@if($e['unidad'])<br><span style="color: #5b6b63;">{{ $e['unidad'] }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="nota">Correctivos: los registrados en los últimos 12 meses, sin los anulados.</p>
    @endif
@endsection
