<x-mail::message>
# Acción de personal {{ $codigo }}

Estimado/a **{{ $nombre }}**:

La Unidad de Administración del Talento Humano del GAD Provincial de
Esmeraldas le notifica la acción de personal **{{ $codigo }}** —{{ $accion }}—,
que rige a partir del **{{ $rige }}**. Adjunto encontrará el documento.

Ingrese a «Mis documentos laborales», en el portal del servidor, para acusar
recibo:

<x-mail::button :url="$urlPortal">
Ver mis documentos laborales
</x-mail::button>

Si tiene alguna duda sobre esta acción, comuníquese con Talento Humano.

Unidad de Administración del Talento Humano<br>
GAD Provincial de Esmeraldas
</x-mail::message>
