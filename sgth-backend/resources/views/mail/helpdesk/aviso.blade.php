<x-mail::message>
# {{ $titulo }}

@switch($aviso)
@case('asignado')
Se le asignó el ticket **{{ $folio }}** de {{ $solicitante }}, con prioridad **{{ $prioridad }}**.@if($vence) Vence el **{{ $vence }}**.@endif

@break
@case('reabierto')
{{ $solicitante }} reabrió el ticket **{{ $folio }}**: dice que no quedó resuelto. Revise su mensaje en la conversación.
@break
@case('pendiente')
Estimado/a **{{ $solicitante }}**: para seguir con su ticket **{{ $folio }}**, la Unidad de TIC necesita su respuesta.

@if($pregunta)
> {{ $pregunta }}
@endif

Mientras no responda, el ticket queda en espera.
@break
@case('resuelto')
Estimado/a **{{ $solicitante }}**: la Unidad de TIC dio por resuelto su ticket **{{ $folio }}**.

@if($solucion)
> {{ $solucion }}
@endif

Confírmelo y califique la atención, o díganos si sigue sin funcionar. Si no responde, el ticket se cerrará solo en unos días hábiles.
@break
@endswitch

**Asunto:** {{ $asunto }}

<x-mail::button :url="$url">
Ver el ticket
</x-mail::button>

Mesa de ayuda de TIC<br>
GAD Provincial de Esmeraldas
</x-mail::message>
