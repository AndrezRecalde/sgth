@extends('pdf.tic._base')

@php
    $por = ['impresora' => 'Impresoras', 'area' => 'Áreas', 'consumible' => 'Consumibles'][$reporte['agrupado_por']];
    $otro = $reporte['agrupado_por'] === 'consumible' ? 'Área' : 'Consumible';
@endphp

@section('contenido')
    <table class="resumen">
        <tr>
            <td><div class="cifra">{{ $reporte['cantidad'] }}</div><div class="rotulo">Unidades entregadas</div></td>
            <td><div class="cifra">{{ $reporte['entregas'] }}</div><div class="rotulo">Entregas</div></td>
            <td><div class="cifra">{{ count($reporte['grupos']) }}</div><div class="rotulo">{{ $por }}</div></td>
        </tr>
    </table>

    @forelse($reporte['grupos'] as $grupo)
        <div class="grupo">{{ $grupo['nombre'] }} <span>· {{ $grupo['cantidad'] }} en {{ $grupo['entregas'] }} {{ $grupo['entregas'] === 1 ? 'entrega' : 'entregas' }}</span></div>
        <table class="listado">
            <thead>
                <tr>
                    <th>{{ $otro }}</th>
                    <th class="num" style="width: 14%;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupo['desglose'] as $d)
                    <tr>
                        <td>{{ $d['nombre'] }}</td>
                        <td class="num">{{ $d['cantidad'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="vacio">No hubo entregas en el período.</p>
    @endforelse
@endsection
