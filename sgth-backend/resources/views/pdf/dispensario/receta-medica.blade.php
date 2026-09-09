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

        .externo-marca {
            display: inline-block; border: 1px solid #8a6100;
            background: #fdf6e3; color: #6d4c00;
            font-size: 7.5px; font-weight: bold; letter-spacing: 0.4px;
            padding: 1px 4px; margin-left: 4px;
        }
        .externo-nota { font-size: 9px; color: #6d4c00; }

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
        table.firmas td { width: 50%; text-align: center; vertical-align: bottom; }
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
    @endphp
    <div class="aviso-anulada">
        <strong>Esta receta fue anulada</strong>{{ $anulacion ? ' ' . $anulacion : '' }}.
        @if ($receta->motivo_anulacion)
            Motivo: {{ $receta->motivo_anulacion }}.
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
        <td colspan="3">
            <span class="etiqueta">Condición</span>
            <span class="valor">{{ $paciente['condicion'] }}</span>
        </td>
        <td colspan="2">
            <span class="etiqueta">Fecha de emisión</span>
            <span class="valor">{{ $receta->fecha_emision->format('d/m/Y') }}</span>
        </td>
    </tr>
</table>

{{-- ── Diagnóstico ──────────────────────────────────────────── --}}
@php
    $consulta   = $receta->consultaMedica;
    $principal  = $consulta?->diagnosticoCie10Principal;
    $secundarios = $consulta?->diagnosticosSecundarios ?? collect();
@endphp
@if ($principal || $secundarios->isNotEmpty())
    <div class="seccion">DIAGNÓSTICO (CIE-10)</div>
    <table class="datos">
        <tr>
            <td>
                @if ($principal)
                    <span class="etiqueta">Principal</span>
                    <span class="valor">
                        <strong>{{ $principal->codigo }}</strong> — {{ $principal->descripcion }}
                    </span>
                @endif
                @foreach ($secundarios as $secundario)
                    @if ($secundario->diagnostico)
                        <span class="etiqueta" style="margin-top: 4px;">
                            {{ $loop->first ? 'Secundarios' : '' }}
                        </span>
                        <span class="valor">
                            <strong>{{ $secundario->diagnostico->codigo }}</strong>
                            — {{ $secundario->diagnostico->descripcion }}
                        </span>
                    @endif
                @endforeach
            </td>
        </tr>
    </table>
@endif

{{-- ── Alergias ─────────────────────────────────────────────── --}}
@if ($alergias->isNotEmpty())
    {{-- Sin icono: la fuente del PDF no tiene el glifo de advertencia y lo
         imprimía como un «7», que en una línea de alergias se lee como un
         número de alergias. El recuadro rojo ya avisa por sí solo. --}}
    <div class="alergias">
        <span class="rotulo">ALERGIAS A MEDICAMENTOS:</span>
        @foreach ($alergias as $alergia)
            {{ $alergia->descripcion }}<span style="color:#555;">
                ({{ $alergia->severidad }})</span>{{ $loop->last ? '.' : ';' }}
        @endforeach
        <div style="font-size: 9px; color: #555; margin-top: 2px;">
            Verifique antes de dispensar.
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
                <span class="farmaco">{{ $generico }}</span>
                @if ($item->esExterno())
                    <span class="externo-marca">NO DISPONIBLE EN FARMACIA</span>
                @endif
                <div class="presentacion">
                    @if ($ficha)
                        {{ collect([
                            $ficha->concentracion,
                            $presentacion,
                            $nombreEnFarmacia
                                ? 'En farmacia: ' . $nombreEnFarmacia
                                : null,
                        ])->filter()->implode(' · ') }}
                    @else
                        <span class="externo-nota">
                            El dispensario no maneja este medicamento;
                            adquiérase en farmacia externa.
                        </span>
                    @endif
                </div>
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

{{-- ── Firmas ───────────────────────────────────────────────── --}}
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
        <td>
            <div class="linea-firma"></div>
            <div class="firma-nombre">Recibí conforme</div>
            <div class="firma-detalle">Nombre, cédula y firma del paciente</div>
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
