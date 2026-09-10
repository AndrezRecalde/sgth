<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Receta médica {{ $receta->folio }}</title>
    <style>
        @page { margin: 26px 34px 70px; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.45;
        }

        /* ── Membrete ───────────────────────────────────────────── */
        table.membrete { width: 100%; border-collapse: collapse; }
        table.membrete td { vertical-align: middle; }
        table.membrete .logo { width: 74px; }
        table.membrete .logo img { width: 66px; }
        table.membrete .centro { text-align: center; }
        table.membrete h1 { font-size: 12.5px; margin: 0; letter-spacing: 0.3px; }
        table.membrete h2 { font-size: 11px; font-weight: normal; margin: 2px 0 0; }
        table.membrete .ruc { font-size: 9px; color: #555; margin-top: 2px; }
        table.membrete .folio-cell { width: 150px; text-align: right; }

        .folio-caja {
            border: 1.2px solid #222; padding: 5px 8px; display: inline-block;
            text-align: center; min-width: 122px;
        }
        .folio-caja .etiqueta { font-size: 8px; letter-spacing: 1px; color: #555; }
        .folio-caja .numero {
            font-family: "Courier New", monospace; font-size: 13px;
            font-weight: bold; letter-spacing: 1px;
        }

        .barra {
            border-top: 2px solid #222; margin: 9px 0 0;
        }

        .titulo {
            text-align: center; margin: 12px 0 10px;
            font-size: 15px; font-weight: bold; letter-spacing: 2px;
        }

        /* ── Bloques de datos ───────────────────────────────────── */
        .seccion {
            background: #ececec; padding: 3px 7px; font-size: 9px;
            font-weight: bold; letter-spacing: 0.8px; margin-top: 11px;
            border: 1px solid #bbb; border-bottom: none;
        }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos td {
            border: 1px solid #bbb; padding: 4px 7px; vertical-align: top;
        }
        table.datos .etiqueta {
            display: block; font-size: 8px; color: #666;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        table.datos .valor { font-size: 11px; }

        /* ── Alergias ───────────────────────────────────────────── */
        .alergias {
            border: 1.5px solid #b71c1c; background: #fdf1f1;
            padding: 6px 9px; margin-top: 11px; font-size: 10.5px;
        }
        .alergias .rotulo {
            font-weight: bold; color: #b71c1c; letter-spacing: 0.5px;
        }
        /* Sin alergias que advertir, el recuadro no debe alarmar: el rojo se
           reserva para cuando de verdad hay algo que mirar antes de entregar. */
        .alergias.neutra { border-color: #bbb; background: #f7f7f7; }
        .alergias.neutra .rotulo { color: #444; }
        .alergias .salvedad {
            font-size: 9px; color: #555; margin-top: 2px;
        }

        /* ── Medicamentos ───────────────────────────────────────── */
        table.rp { width: 100%; border-collapse: collapse; margin-top: 0; }
        table.rp th {
            background: #ececec; border: 1px solid #999; padding: 4px 6px;
            font-size: 8.5px; letter-spacing: 0.4px; text-align: left;
        }
        table.rp td {
            border: 1px solid #bbb; padding: 5px 6px; vertical-align: top;
        }
        table.rp .num { text-align: center; width: 22px; font-weight: bold; }
        table.rp .cant { text-align: center; width: 46px; }
        table.rp .farmaco { font-weight: bold; font-size: 11px; }
        table.rp .presentacion { font-size: 9px; color: #555; }
        table.rp .posologia { font-size: 10px; }
        table.rp .observacion { font-size: 9px; color: #555; font-style: italic; }

        .marca-externo { font-weight: bold; }
        .nota-externos {
            font-size: 9px; color: #555; margin-top: 4px; padding-left: 2px;
        }

        .cierre {
            text-align: center; font-size: 9px; color: #555;
            letter-spacing: 1px; margin-top: 5px;
        }

        .indicaciones {
            border: 1px solid #bbb; border-top: none;
            padding: 6px 8px; font-size: 10.5px;
        }
        .indicaciones .rotulo {
            font-size: 8px; color: #666; text-transform: uppercase;
            letter-spacing: 0.4px; display: block;
        }

        /* ── Firma ──────────────────────────────────────────────── */
        table.firmas { width: 100%; margin-top: 34px; border-collapse: collapse; }
        table.firmas td { text-align: center; vertical-align: bottom; }
        .linea-firma {
            border-top: 1px solid #222; width: 205px; margin: 26px auto 3px;
        }
        .firma-nombre { font-weight: bold; font-size: 11px; }
        .firma-detalle { font-size: 9px; color: #555; }

        /* ── Anulada ────────────────────────────────────────────── */
        .marca-anulada {
            position: absolute; top: 330px; left: 84px;
            font-size: 72px; color: #d32f2f; opacity: 0.15;
            transform: rotate(-24deg); font-weight: bold; letter-spacing: 6px;
        }
        .aviso-anulada {
            border: 1.5px solid #d32f2f; color: #b71c1c;
            padding: 6px 9px; margin-top: 11px; font-size: 10px;
        }

        /* ── Pie ────────────────────────────────────────────────── */
        .pie {
            position: fixed; bottom: -46px; left: 0; right: 0;
            font-size: 8px; color: #666;
            border-top: 1px solid #ddd; padding-top: 5px;
        }
    </style>
</head>
<body>

@if ($receta->estaAnulada())
    <div class="marca-anulada">ANULADA</div>
@endif

<table class="membrete">
    <tr>
        <td class="logo">
            @if (file_exists($logo))
                <img src="{{ $logo }}" alt="GADPE">
            @endif
        </td>
        <td class="centro">
            <h1>{{ $institucion['nombre'] }}</h1>
            <h2>{{ $institucion['unidad'] }}</h2>
            <div class="ruc">RUC {{ $institucion['ruc'] }}</div>
        </td>
        <td class="folio-cell">
            <div class="folio-caja">
                <div class="etiqueta">RECETA N°</div>
                <div class="numero">{{ $receta->folio ?? $receta->id }}</div>
            </div>
        </td>
    </tr>
</table>
<div class="barra"></div>

<div class="titulo">RECETA MÉDICA</div>

@if ($receta->estaAnulada())
    {{-- La frase se arma antes de imprimirse: repartida entre @if y saltos de
         línea, el punto final quedaba separado del nombre por el espacio que
         deja el salto («por Cristhian Recalde . Motivo: …»). --}}
    @php
        $anulacion = collect([
            $receta->anulado_en
                ? 'el ' . $receta->anulado_en->format('d/m/Y \a \l\a\s H:i')
                : null,
            $receta->anulador
                ? 'por ' . $receta->anulador->nombre_completo
                : null,
        ])->filter()->implode(' ');

        // El motivo puede venir con su propio punto final —«Otro» deja
        // escribirlo a mano— y entonces se sumaba al de la frase: «tras
        // interconsulta.. No debe despacharse».
        $motivo = rtrim(trim((string) $receta->motivo_anulacion), '.');
    @endphp
    <div class="aviso-anulada">
        <strong>Esta receta fue anulada</strong>{{ $anulacion ? ' ' . $anulacion : '' }}.
        @if ($motivo !== '')
            Motivo: {{ $motivo }}.
        @endif
        No debe despacharse ni total ni parcialmente.
    </div>
@endif

{{-- ── Paciente ─────────────────────────────────────────────── --}}
<div class="seccion">DATOS DEL PACIENTE</div>
<table class="datos">
    <tr>
        <td colspan="2" style="width: 50%">
            <span class="etiqueta">Apellidos y nombres</span>
            <span class="valor">{{ $paciente['nombre'] }}</span>
        </td>
        <td style="width: 22%">
            <span class="etiqueta">Cédula</span>
            <span class="valor">{{ $paciente['cedula'] ?? '—' }}</span>
        </td>
        <td style="width: 14%">
            <span class="etiqueta">Edad</span>
            <span class="valor">
                {{ $paciente['edad'] !== null ? $paciente['edad'] . ' años' : '—' }}
            </span>
        </td>
        <td style="width: 14%">
            <span class="etiqueta">Sexo</span>
            <span class="valor">{{ $paciente['sexo'] ?? '—' }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="5">
            <span class="etiqueta">Fecha de emisión</span>
            <span class="valor">{{ $receta->fecha_emision->format('d/m/Y') }}</span>
        </td>
    </tr>
</table>

{{-- El diagnóstico NO se imprime, ni el código CIE-10 ni su descripción.
     Este papel se lo lleva el paciente y pasa por manos ajenas —el mostrador
     lo atienden compañeros suyos de la institución, y fuera lo ve quien le
     despache—; el motivo por el que está medicado no tiene por qué viajar con
     la medicación. Consta en la historia clínica y en la pantalla de la
     consulta, que es donde hace falta y donde el acceso está controlado.

     Las alergias tampoco se imprimen por defecto, por lo mismo. Se imprimen
     cuando el médico lo pide, que es lo que conviene si el paciente va a
     comprar fuera: allí nadie puede saberlas de otro modo. --}}

{{-- ── Alergias ─────────────────────────────────────────────────────────────
     El bloque sale SIEMPRE, en uno de tres estados. Que apareciera solo cuando
     hay alergias tenía dos problemas: quien recibía una receta sin él no podía
     distinguir «no tiene ninguna registrada» de «este impreso no trae ese
     dato», y su ausencia delataría al paciente cuyas alergias se omiten.

     Nunca se afirma «sin alergias» cuando el médico las ha omitido: eso
     convertiría una medida de privacidad en un peligro clínico.

     Sin icono de advertencia: la fuente del PDF no tiene ese glifo y lo
     imprimía como un «7», que en una línea de alergias se lee como un número
     de alergias. --}}
@if ($omitirAlergias)
    <div class="alergias neutra">
        <span class="rotulo">ALERGIAS:</span>
        Consúltelas en el Dispensario Médico antes de dispensar.
    </div>
@elseif ($alergias->isNotEmpty())
    <div class="alergias">
        <span class="rotulo">ALERGIAS A MEDICAMENTOS:</span>
        @foreach ($alergias as $alergia)
            {{ $alergia->descripcion }}<span style="color:#555;">
                ({{ $alergia->severidad }})</span>{{ $loop->last ? '.' : ';' }}
        @endforeach
        <div class="salvedad">
            Según lo registrado en la historia clínica; confirme con el
            paciente antes de dispensar.
        </div>
    </div>
@else
    <div class="alergias neutra">
        <span class="rotulo">ALERGIAS A MEDICAMENTOS:</span>
        sin alergias registradas.
        <div class="salvedad">
            Que no consten no significa que no existan: confirme con el
            paciente antes de dispensar.
        </div>
    </div>
@endif

{{-- ── Medicamentos ─────────────────────────────────────────── --}}
<div class="seccion">PRESCRIPCIÓN</div>
<table class="rp">
    <thead>
        <tr>
            <th class="num">N°</th>
            <th>Medicamento</th>
            <th class="cant">Cant.</th>
            <th style="width: 42%">Posología e indicaciones</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($items as $item)
        @php
            $ficha = $item->inventario;

            // La prescripción se escribe en nombre genérico, que es como debe
            // recetar un establecimiento público: el principio activo manda, y
            // el nombre del catálogo solo aparece si dice otra cosa —una marca
            // comercial— para que quien despacha sepa qué caja buscar.
            $generico = $ficha
                ? ($ficha->principio_activo ?: $ficha->nombre)
                : $item->medicamento_externo;

            $nombreEnFarmacia = $ficha
                && $ficha->principio_activo
                && mb_strtolower(trim($ficha->nombre)) !== mb_strtolower(trim($ficha->principio_activo))
                    ? $ficha->nombre
                    : null;

            $presentacion = $ficha?->presentacion?->etiqueta();
        @endphp
        <tr>
            <td class="num">{{ $loop->iteration }}</td>
            <td>
                {{-- El asterisco es para el paciente, que si no sale de aquí sin
                     saber cuál de los seis tiene que ir a comprar. Va sin
                     rótulo y remite a una nota al pie, para que fuera nadie lo
                     lea como que el medicamento no está disponible: no lo
                     maneja ESTE dispensario, y eso es un dato de esta casa, no
                     del fármaco. Solo marca lo que está fuera del catálogo; lo
                     que está en él pero hoy agotado, no, porque mañana puede
                     no estarlo. --}}
                <span class="farmaco">{{ $generico }}</span>@if ($item->esExterno())<span class="marca-externo">*</span>@endif
                @if ($ficha)
                    <div class="presentacion">
                        {{ collect([
                            $ficha->concentracion,
                            $presentacion,
                            $nombreEnFarmacia
                                ? 'En farmacia: ' . $nombreEnFarmacia
                                : null,
                        ])->filter()->implode(' · ') }}
                    </div>
                @endif
            </td>
            <td class="cant">{{ $item->cantidad_prescrita }}</td>
            <td>
                <div class="posologia">
                    {{ $item->dosis }} · {{ $item->frecuencia }} · {{ $item->duracion }}
                </div>
                @if ($item->observaciones)
                    <div class="observacion">{{ $item->observaciones }}</div>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- La nota va antes del cierre para que «fin de la prescripción» siga siendo
     lo último de la lista. --}}
@if ($items->contains->esExterno())
    <div class="nota-externos">
        <span class="marca-externo">*</span>
        No se entrega en el Dispensario Médico del GADPE; adquiéralo en una
        farmacia externa.
    </div>
@endif

{{-- El total cierra la lista: sin él, cualquiera puede añadir una línea a
     mano debajo del último medicamento y la receta lo admitiría. --}}
<div class="cierre">
    ——— {{ $items->count() }}
    medicamento{{ $items->count() === 1 ? '' : 's' }} prescrito{{ $items->count() === 1 ? '' : 's' }}
    · fin de la prescripción ———
</div>

@if ($receta->indicaciones_generales)
    <div class="seccion">INDICACIONES GENERALES</div>
    <div class="indicaciones">{{ $receta->indicaciones_generales }}</div>
@endif

{{-- ── Firma ────────────────────────────────────────────────── --}}
{{-- Solo la del médico. Un «recibí conforme» firmado por el paciente en el
     papel que se lleva él no le sirve de constancia a nadie: la institución se
     queda sin ese ejemplar, y de lo entregado ya responde el despacho, que
     guarda quién lo hizo y cuándo. --}}
<table class="firmas">
    <tr>
        <td>
            <div class="linea-firma"></div>
            <div class="firma-nombre">{{ $prescriptor['nombre'] }}</div>
            <div class="firma-detalle">
                Médico prescriptor
                @if ($prescriptor['cedula'])
                    · C.I. {{ $prescriptor['cedula'] }}
                @endif
            </div>
            <div class="firma-detalle">
                Código ACESS: {{ $prescriptor['codigo'] ?? '—' }}
            </div>
        </td>
    </tr>
</table>

<div class="pie">
    Receta {{ $receta->folio ?? $receta->id }} ·
    emitida el {{ $receta->created_at->format('d/m/Y \a \l\a\s H:i') }} ·
    {{ $institucion['establecimiento'] }}.
    Documento generado por el Sistema de Gestión de Talento Humano del GAD
    Provincial de Esmeraldas; su validez puede verificarse en el Dispensario
    Médico citando el número de receta. Válida únicamente para el paciente
    identificado en este documento.
</div>

</body>
</html>
