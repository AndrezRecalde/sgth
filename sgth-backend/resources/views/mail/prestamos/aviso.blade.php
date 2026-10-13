<x-mail::message>
# {{ $titulo }}

@if($aviso === 'vencido_tic')
El préstamo **{{ $folio }}** de **{{ $nombre }}** debía volver el **{{ $fin }}** y todavía no se registra la devolución.
@else
Estimado/a **{{ $nombre }}**:

@switch($aviso)
@case('aprobado')
La Unidad de TIC aprobó su préstamo **{{ $folio }}**. Retire los equipos desde el **{{ $inicio }}**; la reserva no se guarda indefinidamente si no los retira a tiempo. Devuélvalos el **{{ $fin }}**.
@break
@case('rechazado')
La Unidad de TIC no pudo aprobar su solicitud **{{ $folio }}**, para el {{ $inicio }}.@if($motivo) Motivo: {{ $motivo }}@endif
@break
@case('cancelado')
La Unidad de TIC canceló su préstamo **{{ $folio }}**, para el {{ $inicio }}.@if($motivo) Motivo: {{ $motivo }}@endif
@break
@case('entregado')
Retiró los equipos del préstamo **{{ $folio }}** el {{ $entregado }}. Devuélvalos a la Unidad de TIC a más tardar el **{{ $fin }}**.
@break
@case('devuelto')
La Unidad de TIC recibió los equipos del préstamo **{{ $folio }}** el {{ $devuelto }}. Gracias.
@break
@case('recordatorio')
Los equipos del préstamo **{{ $folio }}** se devuelven el **{{ $fin }}**. Acérquese a la Unidad de TIC a tiempo.
@break
@case('vencido')
Los equipos del préstamo **{{ $folio }}** debían volver el **{{ $fin }}**. Devuélvalos cuanto antes a la Unidad de TIC: mientras tanto no puede pedir otros.
@break
@case('no_retirado')
No retiró los equipos de su reserva **{{ $folio }}**, que empezaba el {{ $inicio }}. La reserva se liberó; si todavía los necesita, pídalos de nuevo.
@break
@endswitch
@endif

**Equipos:**
@foreach($equipos as $equipo)
- {{ $equipo }}
@endforeach

@if(count($novedades) > 0)
**Novedades a la devolución:**
@foreach($novedades as $novedad)
- {{ $novedad }}
@endforeach
@endif

<x-mail::button :url="$url">
{{ $aviso === 'vencido_tic' ? 'Ver el préstamo' : 'Ver mis préstamos' }}
</x-mail::button>

Unidad de Tecnologías de la Información y Comunicación<br>
GAD Provincial de Esmeraldas
</x-mail::message>
