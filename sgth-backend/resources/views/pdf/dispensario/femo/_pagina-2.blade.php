<div class="msp-page">
    <table class="msp-header">
        <tr>
            <td class="logo-cell">
                @if(file_exists($logo))
                    <img src="{{ $logo }}" alt="GADPE">
                @endif
            </td>
            <td class="titulo-cell">
                G. FACTORES DE RIESGO DEL TRABAJO ACTUAL
                <div class="subtitulo">Puesto de trabajo: {{ $ficha->puesto_trabajo ?? '-' }}</div>
            </td>
        </tr>
    </table>

    @if($actividadesRiesgo->isEmpty())
        <table class="msp-table">
            <tr><td class="small center">Sin actividades evaluadas para esta ficha.</td></tr>
        </table>
    @else
        @php $anchoCol = round(70 / max($actividadesRiesgo->count(), 1), 1); @endphp
        <table class="msp-table">
            <tr>
                <th style="width:15%">Categoría</th>
                <th style="width:15%">Factor</th>
                @foreach($actividadesRiesgo as $actividad)
                    <th style="width:{{ $anchoCol }}%">{{ $actividad->actividad }}</th>
                @endforeach
            </tr>
            @foreach($categoriasRiesgo as $categoria)
                @php $factoresCategoria = $filasRiesgoPorCategoria[$categoria->value] ?? collect(); @endphp
                @forelse($factoresCategoria as $factor => $actividadIds)
                    <tr>
                        @if($loop->first)
                            <td rowspan="{{ $factoresCategoria->count() }}" class="msp-label">{{ $categoria->etiqueta() }}</td>
                        @endif
                        <td class="small">{{ $factor }}</td>
                        @foreach($actividadesRiesgo as $actividad)
                            <td class="msp-check">{{ $actividadIds->contains($actividad->id) ? 'X' : '' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td class="msp-label">{{ $categoria->etiqueta() }}</td>
                        <td colspan="{{ $actividadesRiesgo->count() + 1 }}" class="small">Sin registros</td>
                    </tr>
                @endforelse
            @endforeach
            <tr>
                <td colspan="2" class="msp-label">MEDIDAS PREVENTIVAS</td>
                @foreach($actividadesRiesgo as $actividad)
                    <td class="small">{{ $actividad->medida_preventiva ?? '-' }}</td>
                @endforeach
            </tr>
        </table>
    @endif

    <div class="msp-section-title">H. ACTIVIDAD LABORAL / INCIDENTES / ACCIDENTES / ENFERMEDADES OCUPACIONALES</div>
    <table class="msp-table">
        <tr>
            <th>Centro de Trabajo</th><th>Actividades</th><th>Período</th>
            <th>Tipo de Evento</th><th>Calif. IESS</th><th>Fecha</th><th>Especificar</th>
        </tr>
        @forelse($ficha->empleosAnteriores as $empleo)
            @php
                $inicioEmpleo = optional($empleo->fecha_inicio)->format('d/m/Y');
                $finEmpleo = $empleo->fecha_fin ? optional($empleo->fecha_fin)->format('d/m/Y') : 'Actual';
            @endphp
            <tr>
                <td>{{ $empleo->centro_trabajo }}</td>
                <td class="small">{{ $empleo->actividades_desempenadas ?? '-' }}</td>
                <td class="small">{{ $inicioEmpleo ? "{$inicioEmpleo} - {$finEmpleo}" : '-' }}</td>
                <td class="center">{{ $empleo->tipo_evento_laboral->etiqueta() }}</td>
                <td class="msp-check">{{ $empleo->calificado_iess === null ? '-' : ($empleo->calificado_iess ? 'SI' : 'NO') }}</td>
                <td class="center">{{ optional($empleo->fecha_evento)->format('d/m/Y') ?? '-' }}</td>
                <td class="small">{{ $empleo->especificar ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="small center">Sin registros</td></tr>
        @endforelse
    </table>

    <div class="msp-section-title">I. ACTIVIDADES EXTRA LABORALES</div>
    <table class="msp-table">
        <tr>
            <td style="width:75%"><span class="msp-label">Descripción</span><br><span class="msp-value">{{ $ficha->actividad_extralaboral_descripcion ?? '-' }}</span></td>
            <td style="width:25%"><span class="msp-label">Fecha</span><br><span class="msp-value">{{ optional($ficha->actividad_extralaboral_fecha)->format('d/m/Y') ?? '-' }}</span></td>
        </tr>
    </table>
</div>
