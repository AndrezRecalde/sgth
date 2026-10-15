{{-- El anverso (docs/diseno-comunicacion-social.md, 6.2): V2 arriba, la foto,
     los nombres y apellidos, el cargo, la unidad y la cédula. Sin firma: la
     credencial la valida el QR (D11).

     Recibe $t (la tarjeta de CredencialService), $x e $y (dónde va, en mm),
     $guia (si lleva la guía de corte) y lo común: $logo, $logoMedidas e
     $identidad. --}}
@php
    $d = $t['datos'];
@endphp
<div class="tarjeta{{ $guia ? ' con-guia' : '' }}" style="left: {{ $x }}mm; top: {{ $y }}mm;">
    @if ($logo)
        <img class="logo" src="{{ $logo }}" alt=""
             style="width: {{ $logoMedidas['ancho'] }}mm; height: {{ $logoMedidas['alto'] }}mm;
                    left: {{ round((54 - $logoMedidas['ancho']) / 2, 2) }}mm;
                    top: {{ round(4 + (11 - $logoMedidas['alto']) / 2, 2) }}mm;">
    @else
        <div class="logo-texto">{{ $identidad['siglas'] ?? '' }}</div>
    @endif

    <div class="foto">
        @if ($t['foto'])
            <img src="{{ $t['foto'] }}" alt="">
        @endif
    </div>

    <div class="servidor">
        <div class="nombres">{{ $d['nombres'] }}</div>
        <div class="apellidos">{{ $d['apellidos'] }}</div>
        @if ($d['cargo'])
            <div class="cargo">{{ $d['cargo'] }}</div>
        @endif
        @if ($d['unidad'])
            <div class="unidad">{{ $d['unidad'] }}</div>
        @endif
        @if ($d['cedula'])
            <div class="cedula">C.I. {{ $d['cedula'] }}</div>
        @endif
    </div>

    <div class="banda">{{ $identidad['nombre_corto'] ?: ($identidad['siglas'] ?? '') }}</div>
</div>
