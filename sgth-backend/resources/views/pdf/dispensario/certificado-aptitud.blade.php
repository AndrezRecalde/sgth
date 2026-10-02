@php
    use Illuminate\Support\Carbon;

    $fecha = fn ($f) => $f ? Carbon::parse($f)->format('d/m/Y') : '—';

    $aptitud = $ficha->aptitud;
    // El tono solo acompaña a la lectura que el dato ya tiene. «No apto» y
    // «en observación» condicionan de verdad la ubicación en el puesto.
    $destacar = $aptitud?->value === 'no_apto' || $aptitud?->value === 'en_observacion';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    {{-- Sin selector universal: en dompdf un `*` con margin/padding en cero se
         aplica también a @page y deja la hoja sin márgenes. Cada elemento
         declara los suyos. --}}
    <style>
        @page { margin: 1.5cm 2cm 1.6cm 2cm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            color: #222222;
            line-height: 1.3;
        }

        .encabezado { text-align: center; margin-bottom: 14px; }
        .encabezado img { height: 36px; }
        .institucion { font-size: 12pt; font-weight: bold; margin: 6px 0 0 0; }
        .titulo { font-size: 13pt; font-weight: bold; margin: 11px 0 2px 0; }
        .unidad { font-size: 9.5pt; color: #555555; margin: 0; }

        p { margin: 0 0 8px 0; }
        .persona { text-align: center; font-weight: bold; margin: 12px 0; }
        .persona .cedula { display: block; font-weight: normal; font-size: 10pt; }

        table.datos { width: 100%; border-collapse: collapse; margin: 6px 0 12px 0; }
        table.datos td { border: none; padding: 2px 0; font-size: 10pt; }
        table.datos td.etiqueta { color: #555555; width: 38%; }

        .dictamen {
            border: 1px solid #cccccc;
            background-color: #f2f2f2;
            padding: 8px 10px;
            margin: 10px 0;
            text-align: center;
        }
        .dictamen .rotulo {
            font-size: 9pt;
            color: #555555;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }
        .dictamen .valor { font-size: 13pt; font-weight: bold; margin: 0; }
        .dictamen .valor.destacado { color: #99341d; }

        .restricciones { margin: 10px 0; }
        .restricciones .rotulo { font-weight: bold; margin: 0 0 3px 0; font-size: 10pt; }
        {{-- Sin `border-radius` en `.texto`: un borde de un solo lado con
             esquinas redondeadas no se dibuja igual en dompdf que en el
             navegador. --}}
        .restricciones .texto {
            border-left: 3px solid #cccccc;
            padding-left: 10px;
            margin: 0;
        }

        .alcance {
            font-size: 8pt;
            color: #555555;
            border-top: 1px solid #dddddd;
            padding-top: 5px;
            margin-top: 10px;
        }

        .lugar { margin-top: 10px; margin-bottom: 0; }

        {{-- Sin `page-break-inside: avoid`: dompdf no parte el bloque, lo
             empuja entero a la hoja siguiente, y este certificado cabe de
             sobra en una.

             El aire sobre la firma tiene techo: con un texto de restricciones
             largo —el de la prueba, ~470 caracteres— a partir de unos 64px el
             bloque se va a una segunda hoja y la firma queda sola. 48px da
             separación de documento firmado y deja margen de sobra. --}}
        .firma { margin-top: 48px; text-align: center; }
        .firma .linea { border-top: 1px solid #222222; width: 62%; margin: 0 auto 4px auto; }
        .firma .nombre { font-weight: bold; margin: 0; }
        .firma .cargo { font-size: 9pt; color: #555555; margin: 0; }
    </style>
</head>
<body>

<div class="encabezado">
    @if (file_exists($logo))
        <img src="{{ $logo }}" alt="">
    @endif
    <p class="institucion">GOBIERNO AUTÓNOMO DESCENTRALIZADO PROVINCIAL DE ESMERALDAS</p>
    <p class="titulo">CERTIFICADO DE APTITUD MÉDICA OCUPACIONAL</p>
    <p class="unidad">Dispensario Médico Laboral del GADPE</p>
</div>

<p>
    El Dispensario Médico Laboral del Gobierno Autónomo Descentralizado
    Provincial de Esmeraldas certifica que {{ $tratamiento }}:
</p>

<p class="persona">
    {{ $paciente['nombre'] }}
    <span class="cedula">C.I. {{ $paciente['cedula'] }}</span>
</p>

<p>
    fue sometido/a a evaluación médica ocupacional, con el siguiente resultado:
</p>

<table class="datos">
    <tr>
        <td class="etiqueta">Tipo de evaluación</td>
        <td>{{ $tipoEvaluacion }}</td>
    </tr>
    <tr>
        <td class="etiqueta">Fecha de la evaluación</td>
        <td>{{ $fecha($ficha->fecha_evaluacion) }}</td>
    </tr>
    <tr>
        <td class="etiqueta">Puesto evaluado</td>
        <td>{{ $puesto['cargo'] ?? 'No consta en el expediente' }}</td>
    </tr>
    <tr>
        <td class="etiqueta">Unidad administrativa</td>
        <td>{{ $puesto['unidad'] ?? 'No consta en el expediente' }}</td>
    </tr>
</table>

<div class="dictamen">
    <p class="rotulo">Aptitud médica para el trabajo</p>
    <p @class(['valor', 'destacado' => $destacar])>
        {{ $aptitud?->etiqueta() ?? 'No consta' }}
    </p>
</div>

@if ($ficha->restricciones)
    <div class="restricciones">
        <p class="rotulo">Restricciones y recomendaciones para el puesto</p>
        <p class="texto">{{ $ficha->restricciones }}</p>
    </div>
@endif

@if ($rigeHasta)
    <p>
        Esta aptitud rige hasta el <strong>{{ $fecha($rigeHasta) }}</strong>, fecha
        en la que corresponde la siguiente evaluación médica ocupacional periódica.
    </p>
@endif

<p class="alcance">
    Este certificado acredita únicamente la aptitud para el desempeño del puesto
    y sus restricciones. No contiene información clínica: los antecedentes, el
    examen físico y los diagnósticos constan en la ficha de evaluación médica
    ocupacional (formulario 028 del Ministerio de Salud Pública), que es parte de
    la historia clínica del servidor y reposa bajo custodia del Dispensario
    Médico Laboral.
</p>

<p class="lugar">
    Esmeraldas, {{ $emitidoEn->translatedFormat('d \d\e F \d\e Y') }}
</p>

<div class="firma">
    <div class="linea"></div>
    <p class="nombre">
        @if ($evaluador)
            {{ trim("{$evaluador->nombre} {$evaluador->apellido}") }}
        @else
            Profesional evaluador
        @endif
    </p>
    <p class="cargo">
        Médico Ocupacional · Dispensario Médico Laboral del GADPE
        @if ($evaluador?->codigo_medico)
            {{-- Sin prefijo propio: `codigo_medico` es el registro ante el ACESS
                 y ya suele traerlo escrito, así que «Registro ACESS ACESS-08-…»
                 salía tartamudeando. --}}
            <br>Registro profesional {{ $evaluador->codigo_medico }}
        @endif
    </p>
</div>

</body>
</html>
