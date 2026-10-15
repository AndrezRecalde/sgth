{{-- El reverso (docs/diseno-comunicacion-social.md, 6.2): el QR, «Válida
     hasta» solo si el vínculo tiene fecha de fin, el tipo de sangre y el
     contacto de emergencia si están registrados (D6), el aviso de devolución
     con la dirección y el teléfono, y V1 como marca de agua.

     Una línea sin dato no se imprime, en lugar de quedar en blanco. --}}
@php
    $d = $t['datos'];
    $contacto = $d['contacto_emergencia'];
    $sitio = $identidad['sitio_web'] ?? null;
@endphp
<div class="tarjeta{{ $guia ? ' con-guia' : '' }}" style="left: {{ $x }}mm; top: {{ $y }}mm;">
    @if ($isotipo)
        <img class="marca-agua" src="{{ $isotipo }}" alt="">
    @endif

    <div class="qr">
        @if ($t['qr'])
            <img src="{{ $t['qr'] }}" alt="">
        @endif
    </div>
    <div class="qr-texto">Escanee el código para verificar esta credencial.</div>

    <div class="reverso-datos">
        @if ($d['valida_hasta'])
            <div class="fila">
                <div class="etiqueta">Válida hasta</div>
                <div class="dato">{{ \Illuminate\Support\Carbon::parse($d['valida_hasta'])->format('d/m/Y') }}</div>
            </div>
        @endif
        @if ($d['tipo_sangre'])
            <div class="fila">
                <div class="etiqueta">Tipo de sangre</div>
                <div class="dato">{{ $d['tipo_sangre'] }}</div>
            </div>
        @endif
        @if ($contacto)
            <div class="fila">
                <div class="etiqueta">En caso de emergencia, avisar a</div>
                <div><strong>{{ $contacto['nombre'] }}</strong>@if ($contacto['parentesco']) ({{ $contacto['parentesco'] }})@endif</div>
                @if ($contacto['telefono'])
                    <div>{{ $contacto['telefono'] }}</div>
                @endif
            </div>
        @endif
    </div>

    <div class="aviso">
        Esta credencial es personal e intransferible. Si la encuentra, devuélvala a
        {{ $identidad['nombre_oficial'] ?? '' }}@if (! empty($identidad['direccion'])), {{ $identidad['direccion'] }}@endif.
        @if (! empty($identidad['telefono']))
            Teléfono {{ $identidad['telefono'] }}.
        @endif
    </div>

    <div class="banda">{{ $sitio ?: ($identidad['siglas'] ?? '') }}</div>
</div>
