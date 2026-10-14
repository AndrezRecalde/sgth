@extends('pdf.tic._base')

@php($porUbicacion = $reporte['agrupado_por'] === 'ubicacion')

@section('contenido')
    <table class="resumen">
        <tr>
            <td><div class="cifra">{{ $reporte['total'] }}</div><div class="rotulo">Equipos en servicio</div></td>
            <td><div class="cifra">{{ count($reporte['grupos']) }}</div><div class="rotulo">{{ $porUbicacion ? 'Ubicaciones' : 'Unidades' }}</div></td>
        </tr>
    </table>

    @forelse($reporte['grupos'] as $grupo)
        <div class="grupo">{{ $grupo['nombre'] }} <span>· {{ $grupo['total'] }} {{ $grupo['total'] === 1 ? 'equipo' : 'equipos' }}</span></div>
        <table class="listado">
            <thead>
                <tr>
                    <th style="width: 7%;">Código</th>
                    <th style="width: 14%;">Tipo</th>
                    <th style="width: 15%;">Marca y modelo</th>
                    <th style="width: 11%;">Serie</th>
                    <th style="width: 9%;">Estado</th>
                    <th style="width: 7%;">Condición</th>
                    <th style="width: 17%;">Custodio</th>
                    <th>{{ $porUbicacion ? 'Unidad' : 'Ubicación' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupo['equipos'] as $e)
                    <tr>
                        <td>{{ $e['codigo_institucional'] }}</td>
                        <td>{{ $e['tipo'] }}</td>
                        <td>{{ trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? '')) ?: '—' }}</td>
                        <td>{{ $e['numero_serie'] ?? '—' }}</td>
                        <td>{{ $e['estado'] }}</td>
                        <td>{{ $e['condicion'] ?? '—' }}</td>
                        <td>{{ $e['custodio'] ?? 'Sin custodio' }}@if($e['usuario'])<br><span style="color: #5b6b63;">Usa: {{ $e['usuario'] }}</span>@endif</td>
                        <td>{{ ($porUbicacion ? $e['unidad'] : $e['ubicacion']) ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="vacio">No hay equipos en servicio con estos filtros.</p>
    @endforelse
@endsection
