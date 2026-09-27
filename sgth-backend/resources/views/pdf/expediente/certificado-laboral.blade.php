@php
    use Illuminate\Support\Carbon;

    $fecha = fn (?string $f) => $f ? Carbon::parse($f)->format('d/m/Y') : null;
    $dinero = fn ($v) => $v !== null ? '$ '.number_format((float) $v, 2) : '—';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    {{-- Sin selector universal: en dompdf un `*` con margin/padding en cero se
         aplica también a @page y deja la hoja sin márgenes. Cada elemento
         declara los suyos. --}}
    <style>
        @page { margin: 1.9cm 2cm 2.3cm 2cm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            color: #222222;
            line-height: 1.35;
        }

        .encabezado { text-align: center; margin-bottom: 14px; }
        .encabezado img { height: 42px; }
        .institucion { font-size: 12pt; font-weight: bold; margin: 6px 0 0 0; }
        .titulo { font-size: 13pt; font-weight: bold; margin: 14px 0 2px 0; }
        .unidad { font-size: 9.5pt; color: #555555; margin: 0; }

        p { margin: 0 0 8px 0; }
        .persona { text-align: center; font-weight: bold; margin: 12px 0; }
        .persona .cedula { display: block; font-weight: normal; font-size: 10pt; }

        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px 0; }
        th, td { border: 1px solid #cccccc; padding: 3px 5px; font-size: 8.5pt; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        td.num { text-align: right; }

        .resumen { margin: 10px 0; }
        .resumen td { border: none; padding: 1px 0; font-size: 10pt; }
        .resumen td.etiqueta { color: #555555; width: 45%; }

        .seccion { font-size: 10pt; font-weight: bold; margin: 16px 0 4px 0; }

        .cierre { margin-top: 16px; }
        .lugar { margin-top: 18px; margin-bottom: 0; }

        /* Sin `page-break-inside: avoid`: dompdf no lo respeta partiendo el
           bloque, lo empuja entero a la página siguiente, y con un solo
           período mandaba la firma a una segunda hoja casi vacía. */
        .firma { margin-top: 28px; text-align: center; }
        .firma .linea { border-top: 1px solid #222222; width: 62%; margin: 0 auto 4px auto; }
        .firma .nombre { font-weight: bold; margin: 0; }
        .firma .cargo { font-size: 9pt; color: #555555; margin: 0; }

        /* Anclado al pie de la hoja y fuera del flujo. Recortar márgenes para
           que entrara solo movía el umbral: con una unidad de nombre largo
           —«Gestión de Tecnologías de la Información y Comunicación» ocupa
           cuatro líneas en la celda— la firma volvía a partirse. `bottom: 0`,
           nunca negativo: con un valor negativo dompdf saca el bloque de la
           hoja y desaparece. */
        .verificacion {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #dddddd;
            padding-top: 8px;
            font-size: 8pt;
            color: #555555;
        }
        .verificacion .codigo { font-weight: bold; letter-spacing: 0.5px; }
    </style>
</head>
<body>

<div class="encabezado">
    @if (file_exists($logo))
        <img src="{{ $logo }}" alt="">
    @endif
    <p class="institucion">GOBIERNO AUTÓNOMO DESCENTRALIZADO PROVINCIAL DE ESMERALDAS</p>
    <p class="titulo">{{ $tipo->titulo() }}</p>
    <p class="unidad">Unidad de Administración del Talento Humano</p>
</div>

<p>
    La Dirección de Administración de Talento Humano del Gobierno Autónomo
    Descentralizado Provincial de Esmeraldas certifica que el/la servidor/a:
</p>

<p class="persona">
    {{ $datos['nombre_completo'] }}
    <span class="cedula">C.I. {{ $datos['cedula'] }}</span>
</p>

<p>{{ $tipo->formulaDeServicio() }}</p>

<table>
    <thead>
        <tr>
            <th style="width: 4%;">N°</th>
            <th style="width: 20%;">Modalidad</th>
            <th style="width: 13%;">N° contrato</th>
            <th style="width: 21%;">Unidad administrativa</th>
            <th style="width: 18%;">Puesto</th>
            <th style="width: 10%;">Desde</th>
            <th style="width: 10%;">Hasta</th>
            @if ($datos['con_remuneracion'])
                <th style="width: 12%;">R.M.U.</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($datos['periodos'] as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p['nombramiento'] }}</td>
                <td>{{ $p['numero_contrato'] ?? '—' }}</td>
                <td>{{ $p['unidad'] ?? '—' }}</td>
                <td>{{ $p['puesto'] ?? '—' }}</td>
                <td>{{ $fecha($p['desde']) ?? '—' }}</td>
                <td>{{ $fecha($p['hasta']) ?? 'Hasta la actualidad' }}</td>
                @if ($datos['con_remuneracion'])
                    <td class="num">{{ $dinero($p['remuneracion']) }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $datos['con_remuneracion'] ? 8 : 7 }}">
                    Sin períodos registrados.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

@if (count($datos['subrogaciones']) > 0)
    <p class="seccion">Subrogaciones y encargos ejercidos</p>
    <table>
        <thead>
            <tr>
                <th style="width: 16%;">Figura</th>
                <th style="width: 30%;">Puesto</th>
                <th style="width: 30%;">Unidad administrativa</th>
                <th style="width: 12%;">Desde</th>
                <th style="width: 12%;">Hasta</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datos['subrogaciones'] as $s)
                <tr>
                    <td>{{ $s['tipo'] ?? '—' }}</td>
                    <td>{{ $s['puesto'] ?? '—' }}</td>
                    <td>{{ $s['unidad'] ?? '—' }}</td>
                    <td>{{ $fecha($s['desde']) ?? '—' }}</td>
                    <td>{{ $fecha($s['hasta']) ?? 'En curso' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="resumen">
    <tr>
        <td class="etiqueta">Régimen</td>
        <td>{{ $datos['regimen'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="etiqueta">Puesto actual</td>
        <td>{{ $datos['cargo_actual'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="etiqueta">Unidad administrativa actual</td>
        <td>{{ $datos['unidad_actual'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="etiqueta">Fecha de ingreso a la institución</td>
        <td>{{ $fecha($datos['ingreso']) ?? '—' }}</td>
    </tr>
    <tr>
        <td class="etiqueta">{{ $datos['etiqueta_tiempo'] }}</td>
        <td>
            @if ($datos['anios_servicio'] !== null)
                {{ $datos['anios_servicio'] }}
                {{ $datos['anios_servicio'] === 1 ? 'año' : 'años' }}
            @else
                —
            @endif
        </td>
    </tr>
</table>

<p class="cierre">
    Se expide el presente documento a petición del interesado, para los fines
    que estime conveniente.
</p>

<div class="pie">

<p class="lugar">
    Esmeraldas, {{ $emision->emitido_en->locale('es')->isoFormat('DD [de] MMMM [de] YYYY') }}
</p>

<div class="firma">
    <div class="linea"></div>
    <p class="nombre">{{ $emision->firmante_nombre ?? '—' }}</p>
    <p class="cargo">{{ $emision->firmante_cargo }}</p>
    <p class="cargo">GAD Provincial de Esmeraldas</p>
</div>

<div class="verificacion">
    Código de verificación <span class="codigo">{{ $emision->codigo }}</span>.
    Compruebe la autenticidad de este documento en
    {{ config('app.url') }}/verificar/{{ $emision->codigo }}
    <br>
    Válido hasta el {{ $emision->vence_en->format('d/m/Y') }}. Este certificado
    refleja la información del expediente a la fecha de su emisión.
</div>

</div>

</body>
</html>
