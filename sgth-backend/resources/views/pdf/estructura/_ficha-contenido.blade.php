{{-- El cuerpo de la ficha «Descripción y perfil del puesto», con sus diez
     secciones (docs/diseno-manual-de-puestos.md, P5). Lo incluyen la ficha
     suelta y el manual por gestión, cada una con los datos de
     FichaDelPuestoPdfService::datos() y la identidad del membrete. --}}
@include('pdf._membrete-institucional', ['dependencia' => 'Dirección de Gestión de Talento Humano'])
<h1>DESCRIPCIÓN Y PERFIL DEL PUESTO</h1>

@php($o = fn ($valor) => filled($valor) ? e($valor) : '<span class="vacio">—</span>')

{{-- Banda 1: 1. Identificación · 3. Relaciones · 4. Instrucción formal --}}
<table class="ficha">
    {{-- Los anchos van en las columnas: con `colspan` en la cabecera, un
         ancho en la celda se medía sobre toda la tabla. --}}
    <colgroup>
        <col style="width: 13%;"><col style="width: 21%;">
        <col style="width: 26%;">
        <col style="width: 12%;"><col style="width: 28%;">
    </colgroup>
    <tr>
        <th class="seccion" colspan="2">1. DATOS DE IDENTIFICACIÓN DEL PUESTO</th>
        <th class="seccion">3. RELACIONES INTERNAS Y EXTERNAS</th>
        <th class="seccion" colspan="2">4. INSTRUCCIÓN FORMAL REQUERIDA</th>
    </tr>
    @php($filas = collect($identificacion))
    @php($instruccion = collect($instruccion))
    @foreach($filas as $etiqueta => $valor)
        <tr>
            <td class="etiqueta">{{ $etiqueta }}:</td>
            <td>{!! $o($valor) !!}</td>
            @if($loop->first)
                <td rowspan="{{ $filas->count() }}" class="texto">{!! $o($relaciones) !!}</td>
                <td class="etiqueta" rowspan="{{ $filas->count() - 3 }}">Nivel de instrucción:</td>
                <td rowspan="{{ $filas->count() - 3 }}">
                    @forelse($instruccion as $alternativa)
                        {{ $loop->first ? '' : 'o ' }}{{ $alternativa['nivel'] }}<br>
                    @empty
                        <span class="vacio">—</span>
                    @endforelse
                </td>
            @elseif($loop->iteration === $filas->count() - 2)
                <td class="etiqueta" rowspan="3">Área de conocimiento:</td>
                <td rowspan="3" class="texto">{!! $o($area) !!}</td>
            @endif
        </tr>
    @endforeach
</table>

{{-- Banda 2: 2. Misión · 5. Experiencia · 6. Capacitación --}}
<table class="ficha">
    <colgroup>
        <col style="width: 36%;">
        <col style="width: 12%;"><col style="width: 27%;">
        <col style="width: 25%;">
    </colgroup>
    <tr>
        <th class="seccion">2. MISIÓN</th>
        <th class="seccion" colspan="2">5. EXPERIENCIA LABORAL REQUERIDA</th>
        <th class="seccion">6. CAPACITACIÓN REQUERIDA PARA EL PUESTO</th>
    </tr>
    <tr>
        <td rowspan="2" class="texto">{!! $o($mision) !!}</td>
        <td class="etiqueta">Tiempo de experiencia:</td>
        <td>
            @forelse($instruccion as $alternativa)
                {{ $alternativa['experiencia'] }} ({{ $alternativa['nivel'] }})<br>
            @empty
                <span class="vacio">—</span>
            @endforelse
        </td>
        <td rowspan="2" class="texto">{!! $o($capacitacion) !!}</td>
    </tr>
    <tr>
        <td class="etiqueta">Especificidad de la experiencia:</td>
        <td class="texto">{!! $o($especificidad) !!}</td>
    </tr>
</table>

{{-- Banda 3: 7. Actividades esenciales · 8. Conocimientos adicionales --}}
<table class="ficha">
    <thead>
        <tr>
            <th class="seccion" style="width: 60%;">7. ACTIVIDADES ESENCIALES</th>
            <th class="seccion" style="width: 40%;">8. CONOCIMIENTOS ADICIONALES RELACIONADOS A LAS ACTIVIDADES ESENCIALES</th>
        </tr>
    </thead>
    <tbody>
        @forelse($actividades as $actividad)
            <tr>
                <td>{{ $actividad['descripcion'] }}</td>
                <td>{!! $o($actividad['conocimientos']) !!}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="vacio centro">El puesto no tiene actividades registradas.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Bandas 4 y 5: 9. Competencias técnicas · 10. Competencias conductuales --}}
@foreach([['9. COMPETENCIAS TÉCNICAS', $tecnicas], ['10. COMPETENCIAS CONDUCTUALES', $conductuales]] as [$titulo, $lista])
    {{-- El título va en su propia tabla: con una fila de `colspan` al
         principio, dompdf ignoraba los anchos de las columnas. --}}
    <table class="ficha" style="margin-bottom: 0;">
        <tr><th class="seccion">{{ $titulo }}</th></tr>
    </table>
    <table class="ficha">
        <thead>
            <tr>
                <th class="sub" style="width: 22%;">Denominación de la competencia</th>
                <th class="sub" style="width: 8%;">Nivel</th>
                <th class="sub" style="width: 65%;">Comportamiento observable</th>
                <th class="sub" style="width: 5%;">N.º</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lista as $competencia)
                <tr>
                    <td>{{ $competencia['nombre'] }}</td>
                    <td class="centro">{{ $competencia['nivel'] }}</td>
                    <td>{!! $o($competencia['comportamiento']) !!}</td>
                    <td class="centro">{{ $competencia['numero'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio centro">El puesto no tiene competencias de este tipo.</td></tr>
            @endforelse
        </tbody>
    </table>
@endforeach
