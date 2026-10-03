@php
    // La edad a la fecha de la atención, no a la de impresión: reimprimir
    // una ficha un año después no puede cambiarle la edad al paciente.
    $edad = $persona->fecha_nacimiento && $ficha->fecha_evaluacion
        ? \Carbon\Carbon::parse($persona->fecha_nacimiento)->diff($ficha->fecha_evaluacion)->y
        : null;
    $esMasculino = $persona->genero === 'masculino' || $persona->genero === 'M';
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
            <td class="small">Fecha Atención: {{ optional($ficha->fecha_evaluacion)->format('Y/m/d') }}</td>
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
            {{-- Casillas Hombre / Mujer, como el impreso. --}}
            <td><span class="msp-label">Sexo</span><br><span class="msp-value">Hombre [{{ $esMasculino ? 'X' : ' ' }}] &nbsp; Mujer [{{ $esFemenino ? 'X' : ' ' }}]</span></td>
            <td><span class="msp-label">Fecha Nacimiento / Edad</span><br><span class="msp-value">{{ optional($persona->fecha_nacimiento)->format('Y/m/d') }} @if($edad !== null)({{ $edad }} años)@endif</span></td>
        </tr>
        <tr>
            <td><span class="msp-label">Grupo Sanguíneo</span><br><span class="msp-value">{{ $persona->tipo_sangre ?? '-' }}</span></td>
            <td><span class="msp-label">Lateralidad</span><br><span class="msp-value">{{ $ficha->lateralidad ? ucfirst($ficha->lateralidad) : '-' }}</span></td>
            <td colspan="2">
                {{-- Los cuatro grupos del impreso más lactancia, que añade el
                     formato del GADPE. Todos se leen de la ficha:
                     la enfermedad catastrófica salía del expediente del
                     servidor, así que lo que marcaba el médico aquí y lo que
                     se imprimía podían contradecirse. --}}
                <span class="msp-label">Grupo de Atención Prioritaria</span><br>
                <span class="msp-value small">
                    Embarazada: {{ $ficha->grupo_embarazada ? 'SI' : 'NO' }} |
                    Discapacidad: {{ $ficha->grupo_discapacidad ? 'SI' . ($ficha->porcentaje_discapacidad ? " ({$ficha->porcentaje_discapacidad}%)" : '') : 'NO' }} |
                    E. Catastrófica: {{ $ficha->grupo_enfermedad_catastrofica ? 'SI' : 'NO' }} |
                    Lactancia: {{ $ficha->grupo_lactancia ? 'SI' : 'NO' }} |
                    Adulto Mayor: {{ $ficha->grupo_adulto_mayor ? 'SI' : 'NO' }}
                </span>
            </td>
        </tr>
    </table>

    <div class="msp-section-title">B. MOTIVO DE CONSULTA</div>
    <table class="msp-table">
        <tr>
            <td style="width:50%"><span class="msp-label">Puesto de Trabajo (CIUO)</span><br><span class="msp-value">{{ $ficha->puesto_trabajo ?? '-' }} @if($ficha->puesto_trabajo_ciuo)({{ $ficha->puesto_trabajo_ciuo }})@endif</span></td>
            <td style="width:25%"><span class="msp-label">Fecha de Atención</span><br><span class="msp-value">{{ optional($ficha->fecha_evaluacion)->format('Y/m/d') ?? '-' }}</span></td>
            <td style="width:25%"><span class="msp-label">Tipo de Evaluación</span><br><span class="msp-value">{{ $ficha->tipo_ficha->etiqueta() }}</span></td>
        </tr>
        <tr>
            <td><span class="msp-label">Fecha de Ingreso al Trabajo</span><br><span class="msp-value">{{ optional($ficha->fecha_ingreso_trabajo)->format('Y/m/d') ?? '-' }}</span></td>
            <td><span class="msp-label">Fecha de Reintegro</span><br><span class="msp-value">{{ optional($ficha->fecha_reintegro)->format('Y/m/d') ?? '-' }}</span></td>
            <td><span class="msp-label">Último Día Laboral / Salida</span><br><span class="msp-value">{{ optional($ficha->fecha_ultimo_dia_laboral)->format('Y/m/d') ?? '-' }}</span></td>
        </tr>
        <tr>
            <td colspan="3"><span class="msp-label">Observación</span><br><span class="msp-value">{{ $ficha->observaciones ?? '-' }}</span></td>
        </tr>
    </table>

    <div class="msp-section-title">C. ANTECEDENTES PERSONALES</div>
    <table class="msp-table">
        {{-- Todos los tipos que el asistente ofrece llegan al papel. Antes solo
             se recorrían «clínico» y «familiar», y un antecedente quirúrgico
             registrado no se imprimía. --}}
        @foreach([
            'Antecedentes Clínicos y Quirúrgicos' => ['clinico', 'quirurgico'],
            'Antecedentes Familiares' => ['familiar'],
            'Otros Antecedentes' => ['ginecologico', 'reproductivo_masculino', 'transfusion', 'tratamiento_hormonal', 'otro'],
        ] as $tipoLabel => $tipos)
            @php
                $delGrupo = collect($tipos)->flatMap(fn ($t) => $antecedentesPorTipo[$t] ?? collect());
            @endphp
            @if($delGrupo->isNotEmpty() || $tipoLabel !== 'Otros Antecedentes')
                <tr>
                    <td style="width:20%" class="msp-label">{{ $tipoLabel }}</td>
                    <td>
                        @forelse($delGrupo as $ant)
                            {{ $ant->descripcion }}@if($ant->fecha_aproximada) ({{ $ant->fecha_aproximada }})@endif<br>
                        @empty
                            -
                        @endforelse
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
                    FUM: {{ optional($antReprod->fecha_ultima_menstruacion)->format('Y/m/d') ?? '-' }} |
                    Gestas: {{ $antReprod->gestas ?? '-' }} | Partos: {{ $antReprod->partos ?? '-' }} |
                    Cesáreas: {{ $antReprod->cesareas ?? '-' }} | Abortos: {{ $antReprod->abortos ?? '-' }} |
                    Método Planificación: {{ strtoupper($antReprod->usa_metodo_planificacion ?? '-') }}
                    @if($antReprod->metodo_planificacion_cual) ({{ $antReprod->metodo_planificacion_cual }})@endif
                    <br>Exámenes realizados: {{ $antReprod->examenes_realizados ?? '-' }} ({{ $antReprod->examenes_tiempo_anios ?? '-' }} años)
                    @if($antReprod->examenes_resultado) | Resultado: {{ $antReprod->examenes_resultado }}@endif
                </td>
            </tr>
        @elseif(!$esFemenino && $antReprod)
            <tr>
                <td class="msp-label">Antecedentes Reproductivos Masculinos</td>
                <td class="small">
                    Exámenes: {{ $antReprod->examenes_realizados ?? '-' }} ({{ $antReprod->examenes_tiempo_anios ?? '-' }} años)
                    @if($antReprod->examenes_resultado) — Resultado: {{ $antReprod->examenes_resultado }}@endif |
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
        <tr>
            <td class="msp-label">Observación</td>
            <td class="small">{{ $ficha->observacion_antecedentes ?? '-' }}</td>
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
    {{-- Como el impreso: las regiones en columnas, la X marca EVIDENCIA DE
         PATOLOGÍA y se describe abajo con su numeral. Antes había una
         columna «Normal» con la X al revés, y los ítems no tocados en el
         asistente salían como «No evaluado». En una sola columna, las 31
         filas sacaban la sección a una cuarta hoja. --}}
    @php
        $conPatologia = [];
        $columnasF = collect($regiones)->groupBy(fn ($r) => match (true) {
            $r->numero() <= 4 => 0,
            $r->numero() <= 8 => 1,
            default => 2,
        });
    @endphp
    <table class="msp-table">
        <tr>
            @foreach($columnasF as $columna)
                <td class="msp-col-f">
                    <table class="msp-f">
                        @foreach($columna as $region)
                            @php $registrados = ($examenFisicoPorRegion[$region->value] ?? collect())->keyBy('item'); @endphp
                            <tr><td colspan="2" class="msp-label">{{ $region->numero() }}. {{ $region->etiqueta() }}</td></tr>
                            @foreach($region->items() as $indice => $nombreItem)
                                @php
                                    $item = $registrados[$nombreItem] ?? null;
                                    $marca = $item && ! $item->normal;
                                    if ($marca) {
                                        $conPatologia[] = $region->numero().chr(97 + $indice).'. '.$nombreItem
                                            .($item->observacion ? ': '.$item->observacion : '');
                                    }
                                @endphp
                                <tr>
                                    <td>{{ chr(97 + $indice) }}. {{ $nombreItem }}</td>
                                    <td class="msp-check">{{ $marca ? 'X' : '' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </table>
                </td>
            @endforeach
        </tr>
        <tr>
            <td colspan="3" class="small">
                <span class="msp-label">Si existe evidencia de patología marcar con «X» y describir con el numeral · Observación</span><br>
                @forelse($conPatologia as $linea)
                    {{ $linea }}<br>
                @empty
                    Sin evidencia de patología.<br>
                @endforelse
                @if($ficha->observacion_examen_fisico){{ $ficha->observacion_examen_fisico }}@endif
            </td>
        </tr>
    </table>
    <div class="msp-pie">SNS-MSP/HCU-form.123/2025 · Evaluación Médica Ocupacional · 1/3</div>
</div>
