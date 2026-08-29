@php
    $edad = $persona->fecha_nacimiento ? \Carbon\Carbon::parse($persona->fecha_nacimiento)->age : null;
    $antReprod = $ficha->antecedenteReproductivo;
    $esFemenino = $persona->genero === 'femenino' || $persona->genero === 'F';
@endphp
<div class="msp-page">
    <table class="msp-header">
        <tr>
            <td class="logo-cell" rowspan="2">
                @if(file_exists($logo))
                    <img src="{{ $logo }}" alt="GADPE">
                @endif
            </td>
            <td class="titulo-cell" colspan="3">
                FORMULARIO DE EVALUACIÓN MÉDICA OCUPACIONAL
                <div class="subtitulo">Gobierno Autónomo Descentralizado Provincial de Esmeraldas</div>
            </td>
        </tr>
        <tr>
            <td class="small">N° Archivo: {{ $ficha->numero_archivo ?? '-' }}</td>
            <td class="small">N° H. Clínica: {{ $persona->numero_historia ?? '-' }}</td>
            <td class="small">Fecha Atención: {{ optional($ficha->fecha_evaluacion)->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="msp-section-title">A. DATOS DEL ESTABLECIMIENTO - DATOS DEL USUARIO</div>
    <table class="msp-table">
        <tr>
            <td style="width:25%"><span class="msp-label">Institución</span><br><span class="msp-value">DISPENSARIO MEDICO LABORAL DEL GADPE</span></td>
            <td style="width:15%"><span class="msp-label">RUC</span><br><span class="msp-value">0-860000160001</span></td>
            <td style="width:15%"><span class="msp-label">CIIU</span><br><span class="msp-value">0-84110101</span></td>
            <td style="width:45%"><span class="msp-label">Establecimiento / Centro de Trabajo</span><br><span class="msp-value">GOBIERNO AUTÓNOMO DESCENTRALIZADO DE LA PROVINCIA DE ESMERALDAS</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="msp-label">Primer Apellido</span><br><span class="msp-value">{{ $persona->apellido ?? '-' }}</span></td>
            <td colspan="1"><span class="msp-label">Segundo Apellido</span><br><span class="msp-value">{{ $persona->segundo_apellido ?? '-' }}</span></td>
            <td colspan="1"><span class="msp-label">Primer Nombre</span><br><span class="msp-value">{{ $persona->nombre ?? '-' }}</span></td>
        </tr>
        <tr>
            <td><span class="msp-label">Segundo Nombre</span><br><span class="msp-value">{{ $persona->segundo_nombre ?? '-' }}</span></td>
            <td><span class="msp-label">Cédula</span><br><span class="msp-value">{{ $persona->cedula ?? '-' }}</span></td>
            <td><span class="msp-label">Sexo</span><br><span class="msp-value">{{ $persona->genero ?? '-' }}</span></td>
            <td><span class="msp-label">Fecha Nacimiento / Edad</span><br><span class="msp-value">{{ optional($persona->fecha_nacimiento)->format('d/m/Y') }} @if($edad !== null)({{ $edad }} años)@endif</span></td>
        </tr>
        <tr>
            <td><span class="msp-label">Grupo Sanguíneo</span><br><span class="msp-value">{{ $persona->tipo_sangre ?? '-' }}</span></td>
            <td><span class="msp-label">Lateralidad</span><br><span class="msp-value">{{ $ficha->lateralidad ? ucfirst($ficha->lateralidad) : '-' }}</span></td>
            <td colspan="2">
                {{-- Los CUATRO grupos del impreso. Todos se leen de la ficha:
                     la enfermedad catastrófica salía del expediente del
                     servidor, así que lo que marcaba el médico aquí y lo que
                     se imprimía podían contradecirse. --}}
                <span class="msp-label">Atención Prioritaria</span><br>
                <span class="msp-value small">
                    Embarazada: {{ $ficha->grupo_embarazada ? 'SI' : 'NO' }} |
                    Discapacidad: {{ $ficha->grupo_discapacidad ? 'SI' . ($ficha->porcentaje_discapacidad ? " ({$ficha->porcentaje_discapacidad}%)" : '') : 'NO' }} |
                    E. Catastrófica: {{ $ficha->grupo_enfermedad_catastrofica ? 'SI' : 'NO' }} |
                    Adulto Mayor: {{ $ficha->grupo_adulto_mayor ? 'SI' : 'NO' }}
                </span>
            </td>
        </tr>
    </table>

    <div class="msp-section-title">B. MOTIVO DE CONSULTA</div>
    <table class="msp-table">
        <tr>
            <td style="width:50%"><span class="msp-label">Puesto de Trabajo (CIUO)</span><br><span class="msp-value">{{ $ficha->puesto_trabajo ?? '-' }} @if($ficha->puesto_trabajo_ciuo)({{ $ficha->puesto_trabajo_ciuo }})@endif</span></td>
            <td style="width:25%"><span class="msp-label">Fecha de Atención</span><br><span class="msp-value">{{ optional($ficha->fecha_evaluacion)->format('d/m/Y') ?? '-' }}</span></td>
            <td style="width:25%"><span class="msp-label">Tipo de Evaluación</span><br><span class="msp-value">{{ $ficha->tipo_ficha->etiqueta() }}</span></td>
        </tr>
        <tr>
            <td><span class="msp-label">Fecha de Ingreso al Trabajo</span><br><span class="msp-value">{{ optional($ficha->fecha_ingreso_trabajo)->format('d/m/Y') ?? '-' }}</span></td>
            <td><span class="msp-label">Fecha de Reintegro</span><br><span class="msp-value">{{ optional($ficha->fecha_reintegro)->format('d/m/Y') ?? '-' }}</span></td>
            <td><span class="msp-label">Último Día Laboral / Salida</span><br><span class="msp-value">{{ optional($ficha->fecha_ultimo_dia_laboral)->format('d/m/Y') ?? '-' }}</span></td>
        </tr>
        <tr>
            <td colspan="3"><span class="msp-label">Observación</span><br><span class="msp-value">{{ $ficha->observaciones ?? '-' }}</span></td>
        </tr>
    </table>

    <div class="msp-section-title">C. ANTECEDENTES PERSONALES</div>
    <table class="msp-table">
        @foreach(['clinico' => 'Antecedentes Clínicos y Quirúrgicos', 'familiar' => 'Antecedentes Familiares'] as $tipoKey => $tipoLabel)
            @if(($antecedentesPorTipo[$tipoKey] ?? collect())->isNotEmpty())
                <tr>
                    <td style="width:20%" class="msp-label">{{ $tipoLabel }}</td>
                    <td>
                        @foreach($antecedentesPorTipo[$tipoKey] as $ant)
                            {{ $ant->descripcion }}@if($ant->fecha_aproximada) ({{ $ant->fecha_aproximada }})@endif<br>
                        @endforeach
                    </td>
                </tr>
            @endif
        @endforeach

        {{-- Condición especial para urgencias. Se imprime SIEMPRE, incluso
             sin responder: en el impreso son casillas fijas, y un «NO
             RESPONDE» en blanco es información. Antes se buscaban como tipos
             de antecedente, así que las columnas dedicadas de la ficha nunca
             llegaban al papel. --}}
        <tr>
            <td style="width:20%" class="msp-label">Autoriza Transfusión</td>
            <td>{{ $ficha->autoriza_transfusion === null ? 'NO RESPONDE' : ($ficha->autoriza_transfusion ? 'SI' : 'NO') }}</td>
        </tr>
        <tr>
            <td class="msp-label">Tratamiento Hormonal</td>
            <td>
                {{ $ficha->tratamiento_hormonal === null ? 'NO RESPONDE' : ($ficha->tratamiento_hormonal ? 'SI' : 'NO') }}
                @if($ficha->tratamiento_hormonal && $ficha->tratamiento_hormonal_cual)
                    — {{ $ficha->tratamiento_hormonal_cual }}
                @endif
            </td>
        </tr>
        @if($esFemenino && $antReprod)
            <tr>
                <td class="msp-label">Antecedentes Gineco Obstétricos</td>
                <td class="small">
                    FUM: {{ optional($antReprod->fecha_ultima_menstruacion)->format('d/m/Y') ?? '-' }} |
                    Gestas: {{ $antReprod->gestas ?? '-' }} | Partos: {{ $antReprod->partos ?? '-' }} |
                    Cesáreas: {{ $antReprod->cesareas ?? '-' }} | Abortos: {{ $antReprod->abortos ?? '-' }} |
                    Método Planificación: {{ strtoupper($antReprod->usa_metodo_planificacion ?? '-') }}
                    @if($antReprod->metodo_planificacion_cual) ({{ $antReprod->metodo_planificacion_cual }})@endif
                </td>
            </tr>
        @elseif(!$esFemenino && $antReprod)
            <tr>
                <td class="msp-label">Antecedentes Reproductivos Masculinos</td>
                <td class="small">
                    Exámenes: {{ $antReprod->examenes_realizados ?? '-' }} ({{ $antReprod->examenes_tiempo_anios ?? '-' }} años) |
                    Método Planificación: {{ strtoupper($antReprod->usa_metodo_planificacion ?? '-') }}
                    @if($antReprod->metodo_planificacion_cual) ({{ $antReprod->metodo_planificacion_cual }})@endif
                </td>
            </tr>
        @endif
        <tr>
            <td class="msp-label">Consumo de Sustancias</td>
            <td class="small">
                @forelse($ficha->consumoSustancias as $consumo)
                    {{ strtoupper($consumo->sustancia === 'otra' ? ($consumo->sustancia_otra_detalle ?? 'OTRA') : $consumo->sustancia) }}:
                    @if($consumo->no_consume) No consume
                    @else
                        Tiempo consumo {{ $consumo->tiempo_consumo_meses ?? '-' }} meses
                        @if($consumo->ex_consumidor) | Ex-consumidor (abstinencia {{ $consumo->tiempo_abstinencia_meses ?? '-' }} meses)@endif
                    @endif
                    <br>
                @empty
                    -
                @endforelse
            </td>
        </tr>
        <tr>
            <td class="msp-label">Estilo de Vida / Condición Preexistente</td>
            <td class="small">
                Actividad Física: {{ $ficha->actividad_fisica_cual ?? '-' }} ({{ $ficha->actividad_fisica_tiempo ?? '-' }}) |
                Medicación Habitual: {{ $ficha->medicacion_habitual_cual ?? '-' }} ({{ $ficha->medicacion_habitual_cantidad ?? '-' }})
            </td>
        </tr>
    </table>

    <div class="msp-section-title">D. ENFERMEDAD O PROBLEMA ACTUAL</div>
    <table class="msp-table">
        <tr><td class="msp-value">{{ $ficha->enfermedad_actual ?? 'No refiere' }}</td></tr>
    </table>

    <div class="msp-section-title">E. CONSTANTES VITALES Y ANTROPOMETRÍA</div>
    <table class="msp-table">
        <tr>
            <th>Temp (°C)</th><th>P.A. (mmHg)</th><th>F.C. (lat/min)</th><th>F.R. (fr/min)</th>
            <th>Sat. O2 (%)</th><th>Peso (Kg)</th><th>Talla (cm)</th><th>IMC</th>
            <th>Perím. Abd. (cm)</th>
        </tr>
        <tr class="center">
            <td>{{ $ficha->constantesVitales->temperatura_c ?? '-' }}</td>
            <td>{{ $ficha->constantesVitales ? ($ficha->constantesVitales->presion_sistolica . '/' . $ficha->constantesVitales->presion_diastolica) : '-' }}</td>
            <td>{{ $ficha->constantesVitales->frecuencia_cardiaca ?? '-' }}</td>
            <td>{{ $ficha->constantesVitales->frecuencia_respiratoria ?? '-' }}</td>
            <td>{{ $ficha->constantesVitales->saturacion_oxigeno ?? '-' }}</td>
            <td>{{ $ficha->constantesVitales->peso_kg ?? '-' }}</td>
            <td>{{ $ficha->constantesVitales->talla_cm ?? '-' }}</td>
            <td>{{ $ficha->constantesVitales->imc ?? '-' }}</td>
            <td>{{ $ficha->constantesVitales->perimetro_abdominal_cm ?? '-' }}</td>
        </tr>
    </table>

    <div class="msp-section-title">F. EXAMEN FÍSICO REGIONAL</div>
    <table class="msp-table">
        <tr>
            <th style="width:20%">Región</th><th style="width:40%">Ítem</th><th style="width:15%">Normal</th><th style="width:25%">Observación</th>
        </tr>
        @foreach($regiones as $region)
            @php $items = $examenFisicoPorRegion[$region->value] ?? collect(); @endphp
            @forelse($items as $item)
                <tr>
                    @if($loop->first)
                        <td rowspan="{{ $items->count() }}" class="msp-label">{{ $region->etiqueta() }}</td>
                    @endif
                    <td>{{ $item->item }}</td>
                    <td class="msp-check">{{ $item->normal ? 'X' : '' }}</td>
                    <td class="small">{{ !$item->normal ? $item->observacion : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="msp-label">{{ $region->etiqueta() }}</td>
                    <td colspan="3" class="small">No evaluado</td>
                </tr>
            @endforelse
        @endforeach
    </table>
</div>
