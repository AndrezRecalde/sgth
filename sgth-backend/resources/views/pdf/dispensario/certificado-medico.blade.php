<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado médico {{ $certificado->folio }}</title>
    <style>
        @page { margin: 30px 40px; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #222;
            line-height: 1.55;
        }
        .encabezado { text-align: center; border-bottom: 2px solid #222; padding-bottom: 10px; }
        .encabezado h1 { font-size: 15px; margin: 0; letter-spacing: 0.5px; }
        .encabezado h2 { font-size: 12px; font-weight: normal; margin: 3px 0 0; }
        .titulo { text-align: center; margin: 22px 0 4px; font-size: 16px; font-weight: bold; letter-spacing: 1px; }
        .folio { text-align: center; font-family: "Courier New", monospace; font-size: 11px; color: #555; }

        /* La marca de anulado va detrás del texto y no encima: el documento
           tiene que seguir leyéndose para saber qué fue lo que se anuló. */
        .anulado {
            position: absolute; top: 300px; left: 90px;
            font-size: 78px; color: #d32f2f; opacity: 0.16;
            transform: rotate(-24deg); font-weight: bold; letter-spacing: 6px;
        }
        .aviso-anulado {
            border: 1.5px solid #d32f2f; color: #b71c1c;
            padding: 8px 10px; margin: 14px 0; font-size: 11px;
        }

        table.datos { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.datos th, table.datos td { border: 1px solid #bbb; padding: 6px 8px; vertical-align: top; }
        table.datos th { background: #f2f2f2; text-align: left; width: 32%; font-weight: bold; }

        .reposo { margin-top: 18px; border: 1px solid #bbb; padding: 10px; background: #fafafa; }
        .reposo .dias { font-size: 20px; font-weight: bold; }

        .firma { margin-top: 60px; text-align: center; }
        .firma .linea { border-top: 1px solid #222; width: 260px; margin: 0 auto 4px; }
        .pie { margin-top: 26px; font-size: 9.5px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>

@if ($certificado->estaAnulado())
    <div class="anulado">ANULADO</div>
@endif

<div class="encabezado">
    <h1>GOBIERNO AUTÓNOMO DESCENTRALIZADO PROVINCIAL DE ESMERALDAS</h1>
    <h2>Dispensario Médico</h2>
</div>

<div class="titulo">CERTIFICADO MÉDICO</div>
<div class="folio">{{ $certificado->folio ?? '—' }}</div>

@if ($certificado->estaAnulado())
    <div class="aviso-anulado">
        <strong>Este certificado fue anulado</strong>
        el {{ $certificado->anulado_en->format('d/m/Y \a \l\a\s H:i') }}
        por {{ $certificado->anulador?->nombre_completo ?? '—' }}.
        @if ($certificado->motivo_anulacion)
            Motivo: {{ $certificado->motivo_anulacion }}.
        @endif
        No es válido para justificar ausencia.
    </div>
@endif

<table class="datos">
    <tr>
        <th>Paciente</th>
        <td>{{ $paciente['nombre'] }}</td>
    </tr>
    <tr>
        <th>Cédula</th>
        <td>{{ $paciente['cedula'] ?? '—' }}</td>
    </tr>
    <tr>
        <th>Condición</th>
        <td>{{ $paciente['condicion'] }}</td>
    </tr>
    <tr>
        <th>Fecha de atención</th>
        <td>{{ optional($certificado->consultaMedica?->fecha_consulta)->format('d/m/Y') ?? '—' }}</td>
    </tr>
    @if ($certificado->diagnosticoCie10)
        <tr>
            <th>Diagnóstico (CIE-10)</th>
            <td>
                <strong>{{ $certificado->diagnosticoCie10->codigo }}</strong>
                — {{ $certificado->diagnosticoCie10->descripcion }}
            </td>
        </tr>
    @endif
</table>

<div class="reposo">
    Se certifica que el paciente requiere reposo médico por
    <span class="dias">{{ $certificado->dias_reposo }}</span>
    día{{ $certificado->dias_reposo === 1 ? '' : 's' }},
    desde el <strong>{{ $certificado->fecha_inicio->format('d/m/Y') }}</strong>
    hasta el <strong>{{ $certificado->fecha_fin->format('d/m/Y') }}</strong>, inclusive.

    @if ($certificado->permisoServidor)
        <br><br>
        <span style="font-size: 10.5px; color: #555;">
            Permiso de asistencia asociado:
            <strong>{{ $certificado->permisoServidor->folio }}</strong>
        </span>
    @endif
</div>

@if ($certificado->observaciones)
    <p style="margin-top: 16px;">
        <strong>Observaciones:</strong> {{ $certificado->observaciones }}
    </p>
@endif

<div class="firma">
    <div class="linea"></div>
    <div><strong>{{ $certificado->emisor?->nombre_completo ?? '—' }}</strong></div>
    <div style="font-size: 11px; color: #555;">Médico del Dispensario</div>
</div>

<div class="pie">
    Emitido el {{ $certificado->created_at->format('d/m/Y \a \l\a\s H:i') }}.
    Documento generado por el Sistema de Gestión de Talento Humano del GAD
    Provincial de Esmeraldas. Su validez puede verificarse en el Dispensario
    Médico citando el folio {{ $certificado->folio ?? '—' }}.
</div>

</body>
</html>
