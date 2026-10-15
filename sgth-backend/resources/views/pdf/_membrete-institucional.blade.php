{{-- La cabecera común de los documentos institucionales (Comunicación Social,
     fase 2.2): el logo de documentos a la izquierda, el nombre oficial con la
     dependencia que emite y, si se cargó, el logo secundario a la derecha.

     Todo llega vigente a la fecha del documento: $logo, $logoSecundario e
     $identidad salen de IdentidadInstitucionalService::paraDocumento(). Los
     estilos (.membrete, .institucion) los pone cada documento, porque unos lo
     fijan en el margen de cada hoja y otros no. --}}
<div class="membrete">
    <table>
        <tr>
            {{-- El logo cede ancho: el nombre institucional en mayúscula
                 ocupa bastante más y no debe partirse en tres líneas. --}}
            <td style="width: 26%;">
                @if(($logo ?? '') !== '' && file_exists($logo))
                    <img src="{{ $logo }}" alt="Logo {{ $identidad['siglas'] ?? '' }}">
                @endif
            </td>
            <td class="institucion">
                <strong>{{ $identidad['nombre_oficial'] ?? '' }}</strong>
                {{ $dependencia }}
            </td>
            @if(($logoSecundario ?? '') !== '' && file_exists($logoSecundario))
                <td style="width: 18%; text-align: right;">
                    <img src="{{ $logoSecundario }}" alt="">
                </td>
            @endif
        </tr>
    </table>
</div>
