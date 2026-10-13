{{-- El membrete y el pie fijos de los documentos del inventario de TIC. --}}
<div class="membrete">
    <table>
        <tr>
            <td style="width: 26%;">
                @if(file_exists($logo))
                    <img src="{{ $logo }}" alt="Logo GADPE">
                @endif
            </td>
            <td class="institucion">
                <strong>Gobierno Autónomo Descentralizado Provincial de Esmeraldas</strong>
                Dirección de Tecnologías de la Información y Comunicación
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    Dirección: 10 de Agosto entre Bolívar y Pedro Vicente Maldonado.<br>
    Telefono: 062721433
</div>
