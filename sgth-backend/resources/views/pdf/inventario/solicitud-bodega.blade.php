<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de materiales informáticos a bodega {{ $codigo }}</title>
    {{-- El formato de la hoja «REPUESTOS PEDIDOS» del Excel de TIC: original y
         copia lado a lado en una hoja A4 horizontal. Sin membrete fijo: el
         formulario es el mismo de siempre. dompdf no soporta flexbox ni grid. --}}
    <style>
        @page { margin: 9mm 8mm; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8.4px; line-height: 1.35; color: #1c2321; margin: 0; }
        table { border-collapse: collapse; }
        .hoja { width: 100%; }
        .hoja > tbody > tr > td.copia { width: 49.2%; vertical-align: top; padding: 0; }
        .hoja > tbody > tr > td.medio { width: 1.6%; }
        .marco { width: 100%; border: 0.8px solid #4a5a53; }
        .marco td { border: 0.6px solid #8a9a92; padding: 2px 4px; vertical-align: top; }
        .titulo td { border: none; padding: 3px 4px; }
        .titulo .nombre { font-size: 10px; font-weight: bold; text-align: center; letter-spacing: 0.3px; }
        .titulo .pedido { text-align: right; white-space: nowrap; font-size: 8px; }
        .titulo .pedido b { font-size: 9px; }
        .ejemplar { font-size: 6.5px; letter-spacing: 1.5px; color: #5b6b63; text-align: center; padding-bottom: 2px; }
        .rotulo { font-weight: bold; white-space: nowrap; width: 1%; }
        .texto { text-align: justify; }
        .items th { background: #eef3f0; border: 0.6px solid #8a9a92; padding: 2px 3px; font-size: 6.5px; text-transform: uppercase; text-align: center; }
        .items td { border: 0.6px solid #8a9a92; padding: 2px 3px; height: 14px; vertical-align: middle; }
        .items .num { text-align: center; white-space: nowrap; }
        .items .letra { text-align: center; font-size: 6.6px; }
        .firmas td { border: 0.6px solid #8a9a92; padding: 3px 4px; vertical-align: top; width: 33.33%; }
        .firmas .cargo { font-weight: bold; font-size: 6.8px; text-transform: uppercase; }
        .firmas .espacio { height: 36px; }
        .firmas .linea { border-top: 0.6px solid #4a5a53; padding-top: 1px; text-align: center; font-size: 6.6px; color: #3d4d46; }
        .anulada { color: #a52a2a; font-weight: bold; text-align: center; font-size: 9px; letter-spacing: 2px; }
    </style>
</head>
<body>

@php
    $s = $solicitud;
    $f = fn (string $clave) => $firmantes[$clave] ?? ['nombre' => null, 'cargo' => null];
    $para = $f('administrativo');
    $fecha = $s->fecha->locale('es')->isoFormat('DD [de] MMMM [del] YYYY');
@endphp

<table class="hoja">
    <tr>
        @foreach (['ORIGINAL', 'COPIA'] as $i => $ejemplar)
            @if ($i === 1)<td class="medio"></td>@endif
            <td class="copia">
                <div class="ejemplar">{{ $ejemplar }}</div>
                <table class="marco">
                    <tr class="titulo">
                        <td colspan="2" class="nombre">SOLICITUD DE MATERIALES INFORMÁTICOS A BODEGA</td>
                        <td class="pedido">Pedido n.º <b>{{ $codigo }}</b></td>
                    </tr>
                    @if ($s->estado === 'anulada')
                        <tr><td colspan="3" class="anulada">ANULADA</td></tr>
                    @endif
                    <tr><td class="rotulo">Fecha:</td><td colspan="2">Esmeraldas, {{ $fecha }}</td></tr>
                    <tr>
                        <td class="rotulo">Para:</td>
                        <td colspan="2">{{ $para['nombre'] ?? '' }}{{ $para['nombre'] && $para['cargo'] ? ', ' : '' }}{{ $para['cargo'] }}</td>
                    </tr>
                    <tr><td class="rotulo">De:</td><td colspan="2"><b>{{ $s->servidor?->nombre_completo }}</b></td></tr>
                    <tr>
                        <td class="rotulo">Proceso:</td><td><b>{{ $proceso ?? '' }}</b></td>
                        <td><span class="rotulo">Subproceso:</span> {{ $subproceso ?? '' }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="texto">
                            Agradeceré disponga al Departamento de Bodega el despacho de los materiales que serán utilizados
                            {{ $s->bien ? 'en este equipo' : 'por el área' }}: <b>{{ $s->motivo }}</b>
                        </td>
                    </tr>
                    <tr>
                        <td class="rotulo">Código nuevo:</td><td>{{ $s->bien?->codigo_institucional ?? '—' }}</td>
                        <td><span class="rotulo">Código anterior:</span> {{ $s->bien?->codigo_anterior ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="padding: 0; border: none;">
                            <table class="items" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th rowspan="2" style="width: 6%;">Ítem</th>
                                        <th rowspan="2" style="width: 12%;">Código</th>
                                        <th rowspan="2">Descripción</th>
                                        <th colspan="2" style="width: 22%;">Cantidad solicitada</th>
                                        <th colspan="2" style="width: 22%;">Cantidad despachada</th>
                                    </tr>
                                    <tr>
                                        <th style="width: 7%;">Número</th><th>Letra</th>
                                        <th style="width: 7%;">Número</th><th>Letra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($renglones as $r)
                                        <tr>
                                            <td class="num">{{ $r['item'] }}</td>
                                            <td class="num">{{ $r['codigo'] }}</td>
                                            <td>{{ $r['descripcion'] }}</td>
                                            <td class="num">{{ $r['solicitada'] }}</td>
                                            <td class="letra">{{ $r['solicitada_letras'] }}</td>
                                            <td class="num">{{ $r['despachada'] }}</td>
                                            <td class="letra">{{ $r['despachada_letras'] }}</td>
                                        </tr>
                                    @endforeach
                                    @for ($v = 0; $v < $vacias; $v++)
                                        <tr><td class="num">{{ count($renglones) + $v + 1 }}</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                                    @endfor
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" style="padding: 0; border: none;">
                            <table class="firmas" style="width: 100%;">
                                <tr>
                                    @foreach ([['area', 'Director del área'], ['tic', 'Director de TIC'], ['administrativo', 'Autorizado por']] as [$clave, $rotulo])
                                        <td>
                                            <div class="cargo">{{ $rotulo }}</div>
                                            <div class="espacio"></div>
                                            <div class="linea">
                                                {{ $f($clave)['nombre'] ?? '' }}<br>
                                                {{ $f($clave)['cargo'] ?? '' }}
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <div class="cargo">Solicitado por</div>
                                        <div class="espacio"></div>
                                        <div class="linea">{{ $f('solicitante')['nombre'] ?? '' }}</div>
                                    </td>
                                    <td>
                                        <div class="cargo">Entregado por</div>
                                        <div class="espacio"></div>
                                        <div class="linea">Bodega</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        @endforeach
    </tr>
</table>

</body>
</html>
