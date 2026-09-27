<x-mail::message>
# {{ $emision->tipo->titulo() }}

Estimado/a **{{ $nombre }}**:

Adjunto encontrará el documento que solicitó a la Unidad de Administración del
Talento Humano del GAD Provincial de Esmeraldas.

El certificado es **válido hasta el {{ $venceEl }}**. Quien lo reciba puede
comprobar su autenticidad con el código **{{ $codigo }}** en la siguiente
dirección:

<x-mail::button :url="$urlVerificacion">
Verificar este certificado
</x-mail::button>

Si necesita uno con información actualizada, solicítelo nuevamente a Talento
Humano.

Unidad de Administración del Talento Humano<br>
GAD Provincial de Esmeraldas
</x-mail::message>
