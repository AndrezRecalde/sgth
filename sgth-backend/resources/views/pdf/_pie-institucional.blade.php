{{-- El pie con la dirección y el teléfono de la institución (fase 2.2), de
     Identidad institucional. Los estilos (.footer) los pone cada documento. --}}
<div class="footer">
    Dirección: {{ rtrim($identidad['direccion'] ?? '', '.') }}.<br>
    Teléfono: {{ $identidad['telefono'] ?? '' }}
</div>
