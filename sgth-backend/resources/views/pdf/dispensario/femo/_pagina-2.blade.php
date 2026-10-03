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
                @forelse($factoresCategoria as $factor => $fila)
                    <tr>
                        @if($loop->first)
                            <td rowspan="{{ $factoresCategoria->count() }}" class="msp-label">{{ $categoria->etiqueta() }}</td>
                        @endif
                        {{-- La subcategoría solo la usa «De seguridad»; en el
                             resto viaja nula y el factor va solo. --}}
                        <td class="small">
                            @if($fila['subcategoria'])
                                <strong>{{ $etiquetasSubcategoria[$fila['subcategoria']] ?? $fila['subcategoria'] }}</strong> —
                            @endif
                            {{ $factor }}@if($fila['detalle'] ?? null): {{ $fila['detalle'] }}@endif
                        </td>
                        @foreach($actividadesRiesgo as $actividad)
                            <td class="msp-check">{{ $fila['actividades']->contains($actividad->id) ? 'X' : '' }}</td>
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
            {{-- «Trabajo» es la columna ANTERIOR / ACTUAL del impreso. --}}
            <th>Centro de Trabajo</th><th>Actividades</th><th>Trabajo</th><th>Tiempo de Trabajo</th>
            <th>Tipo de Evento</th><th>Calif. IESS</th><th>Fecha</th><th>Especificar</th><th>Observaciones</th>
        </tr>
        @forelse($ficha->empleosAnteriores as $empleo)
            @php
                $inicioEmpleo = optional($empleo->fecha_inicio)->format('Y/m/d');
                $finEmpleo = $empleo->fecha_fin ? optional($empleo->fecha_fin)->format('Y/m/d') : 'Actual';
                // «Tiempo de trabajo» del impreso, en meses, como lo escribe
                // el Dispensario («192 M»). Sin fecha de fin, hasta la atención.
                $mesesEmpleo = $empleo->fecha_inicio
                    ? (int) $empleo->fecha_inicio->diffInMonths($empleo->fecha_fin ?? $ficha->fecha_evaluacion)
                    : null;
            @endphp
            <tr>
                <td>{{ $empleo->centro_trabajo }}</td>
                <td class="small">{{ $empleo->actividades_desempenadas ?? '-' }}</td>
                <td class="center small">{{ $empleo->es_trabajo_actual ? 'ACTUAL' : 'ANTERIOR' }}</td>
                <td class="small">{{ $mesesEmpleo !== null ? "{$mesesEmpleo} M" : '-' }}@if($inicioEmpleo)<br>{{ $inicioEmpleo }} - {{ $finEmpleo }}@endif</td>
                <td class="center">{{ $empleo->tipo_evento_laboral->etiqueta() }}</td>
                <td class="msp-check">{{ $empleo->calificado_iess === null ? '-' : ($empleo->calificado_iess ? 'SI' : 'NO') }}</td>
                <td class="center">{{ optional($empleo->fecha_evento)->format('Y/m/d') ?? '-' }}</td>
                <td class="small">{{ $empleo->especificar ?? '-' }}</td>
                <td class="small">{{ $empleo->observaciones ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="small center">Sin registros</td></tr>
        @endforelse
    </table>

    <div class="msp-section-title">I. ACTIVIDADES EXTRA LABORALES</div>
    <table class="msp-table">
        <tr>
            <td style="width:75%"><span class="msp-label">Tipo de Actividad</span><br><span class="msp-value">{{ $ficha->actividad_extralaboral_descripcion ?? '-' }}</span></td>
            <td style="width:25%"><span class="msp-label">Fecha</span><br><span class="msp-value">{{ optional($ficha->actividad_extralaboral_fecha)->format('Y/m/d') ?? '-' }}</span></td>
        </tr>
    </table>
    <div class="msp-pie">SNS-MSP/HCU-form.123/2025 · Evaluación Médica Ocupacional · 2/3</div>
</div>
