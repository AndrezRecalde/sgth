@php
    $evaluadorServidor = $ficha->evaluador?->servidor;
    $evaluadorNombre = $evaluadorServidor ? trim($evaluadorServidor->nombre . ' ' . $evaluadorServidor->apellido) : '-';
@endphp
<div class="msp-page">
    <table class="msp-header">
        <tr>
            <td class="logo-cell">
                @if(file_exists($logo))
                    <img src="{{ $logo }}" alt="GADPE">
                @endif
            </td>
            <td class="titulo-cell">
                J-P. RESULTADOS, DIAGNÓSTICO Y APTITUD MÉDICA
            </td>
        </tr>
    </table>

    <div class="msp-section-title">J. RESULTADOS DE EXÁMENES GENERALES Y ESPECÍFICOS</div>
    <table class="msp-table">
        <tr><th style="width:35%">Nombre del Examen</th><th style="width:15%">Fecha</th><th style="width:15%">Tipo</th><th style="width:35%">Resultados</th></tr>
        @forelse($ficha->examenes as $examen)
            <tr>
                <td>{{ $examen->nombre_examen }}</td>
                <td class="center">{{ optional($examen->fecha_examen)->format('d/m/Y') ?? '-' }}</td>
                <td class="center">{{ $examen->tipo->etiqueta() }}</td>
                <td class="small">{{ $examen->resultado ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="small center">Sin registros</td></tr>
        @endforelse
    </table>

    <div class="msp-section-title">K. DIAGNÓSTICO (PRE: PRESUNTIVO / DEF: DEFINITIVO)</div>
    <table class="msp-table">
        <tr><th style="width:15%">CIE-10</th><th style="width:55%">Descripción</th><th style="width:15%">PRE</th><th style="width:15%">DEF</th></tr>
        @forelse($ficha->diagnosticos as $diagnostico)
            <tr>
                <td class="center">{{ $diagnostico->diagnostico->codigo ?? '-' }}</td>
                <td>{{ $diagnostico->diagnostico->descripcion ?? '-' }}</td>
                <td class="msp-check">{{ $diagnostico->tipo === 'presuntivo' ? 'X' : '' }}</td>
                <td class="msp-check">{{ $diagnostico->tipo === 'definitivo' ? 'X' : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="small center">Sin registros</td></tr>
        @endforelse
    </table>

    <div class="msp-section-title">L. APTITUD MÉDICA PARA EL TRABAJO</div>
    <table class="msp-table">
        <tr>
            @foreach(\App\Enums\AptitudMedica::cases() as $opcion)
                <td class="center" style="width:25%">
                    <span class="msp-check">{{ $ficha->aptitud === $opcion ? 'X' : '' }}</span> {{ $opcion->etiqueta() }}
                </td>
            @endforeach
        </tr>
        <tr>
            <td colspan="4"><span class="msp-label">Observaciones / Restricciones</span><br><span class="msp-value">{{ $ficha->restricciones ?? '-' }}</span></td>
        </tr>
    </table>

    <div class="msp-section-title">M. RECOMENDACIONES Y/O TRATAMIENTO</div>
    <table class="msp-table">
        <tr><td><span class="msp-label">Recomendaciones</span><br><span class="msp-value">{{ $ficha->recomendaciones ?? '-' }}</span></td></tr>
        <tr><td><span class="msp-label">Tratamiento</span><br><span class="msp-value">{{ $ficha->tratamiento ?? '-' }}</span></td></tr>
    </table>

    @if($ficha->tipo_ficha === \App\Enums\TipoFichaFemo::RETIRO)
        <div class="msp-section-title">N. RETIRO (EVALUACIÓN)</div>
        <table class="msp-table">
            <tr>
                <td style="width:50%"><span class="msp-label">Se Realiza la Evaluación</span><br><span class="msp-value">{{ $ficha->se_realiza_evaluacion_retiro === null ? '-' : ($ficha->se_realiza_evaluacion_retiro ? 'SI' : 'NO') }}</span></td>
                <td style="width:50%"><span class="msp-label">Condición de Salud Relacionada con el Trabajo</span><br><span class="msp-value">{{ $ficha->condicion_relacionada_trabajo === null ? '-' : ($ficha->condicion_relacionada_trabajo ? 'SI' : 'NO') }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="msp-label">Observación</span><br><span class="msp-value">{{ $ficha->observacion_retiro ?? '-' }}</span></td>
            </tr>
        </table>
    @endif

    <div class="msp-section-title">O. DATOS DEL PROFESIONAL</div>
    <table class="msp-table">
        <tr>
            <td style="width:45%"><span class="msp-label">Nombres y Apellidos del Profesional</span><br><span class="msp-value">{{ $evaluadorNombre }}</span></td>
            <td style="width:25%"><span class="msp-label">Cédula</span><br><span class="msp-value">{{ $evaluadorServidor->cedula ?? '-' }}</span></td>
            {{-- Registro profesional ante el ACESS. El impreso lo pide junto al
                 nombre y es lo que da validez a la firma. --}}
            <td style="width:30%"><span class="msp-label">Código Médico</span><br><span class="msp-value">{{ $evaluadorServidor->codigo_medico ?? '-' }}</span></td>
        </tr>
    </table>

    <div class="msp-section-title">P. FIRMAS</div>
    <table class="footer-firma">
        <tr>
            <td>
                <div class="firma-linea">Firma del Trabajador / Servidor</div>
            </td>
            <td>
                <div class="firma-linea">{{ $evaluadorNombre }}<br>Firma y Sello del Profesional</div>
            </td>
        </tr>
    </table>
</div>
