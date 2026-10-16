@extends('pdf.tic._base')

@section('contenido')
    <table class="resumen">
        <tr>
            <td><div class="cifra">{{ $reporte['consumibles'] }}</div><div class="rotulo">Consumibles</div></td>
            <td><div class="cifra">{{ $reporte['unidades'] }}</div><div class="rotulo">Unidades en la bodega de TIC</div></td>
            <td><div class="cifra">{{ $reporte['bajo_minimo'] }}</div><div class="rotulo">Bajo el mínimo</div></td>
        </tr>
    </table>

    @if(count($reporte['filas']))
        <table class="listado">
            <thead>
                <tr>
                    <th style="width: 11%;">Código de bodega</th>
                    <th>Descripción</th>
                    <th style="width: 14%;">Tipo</th>
                    <th style="width: 9%;">Presentación</th>
                    <th class="num" style="width: 8%;">Saldo</th>
                    <th class="num" style="width: 8%;">Mínimo</th>
                    <th style="width: 11%;">Último movimiento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reporte['filas'] as $f)
                    <tr>
                        <td>{{ $f['codigo_bodega'] ?? '—' }}</td>
                        <td>{{ $f['descripcion'] }}@unless($f['activo']) <span style="color: #5b6b63;">(inactivo)</span>@endunless</td>
                        <td>{{ $f['tipo'] }}</td>
                        <td>{{ $f['presentacion'] }}</td>
                        <td class="num"><strong>{{ $f['saldo'] }}</strong>@if($f['bajo_minimo']) <span style="color: #a15c07;">▼</span>@endif</td>
                        <td class="num">{{ $f['stock_minimo'] ?: '—' }}</td>
                        <td>{{ $f['ultimo_movimiento'] ? \Illuminate\Support\Carbon::parse($f['ultimo_movimiento'])->format('d/m/Y') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="vacio">TIC no guardaba consumibles a esa fecha.</p>
    @endif
@endsection
