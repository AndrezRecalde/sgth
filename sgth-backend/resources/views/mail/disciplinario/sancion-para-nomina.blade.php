<x-mail::message>
{{-- La de un obrero va en su hoja de registro interno (fase 4.3). --}}
@php($documento ??= 'la acción de personal')
@if($anulada)
# Sanción anulada

Talento Humano **anuló** {{ $documento }} **N.º {{ $numero }}**, que se le
envió para aplicar un descuento en el rol de pagos de **{{ $servidor }}**
(C.I. {{ $cedula }}).

@if($motivo)
**Motivo de la anulación:** {{ $motivo }}
@endif

Si el descuento ya se aplicó, disponga a quien corresponda su reverso. Se
adjunta el documento con la marca de anulación.
@else
# Sanción disciplinaria para nómina

Talento Humano registró {{ $documento }} **N.º {{ $numero }}**, que impone
una sanción con efecto en la remuneración de **{{ $servidor }}**
(C.I. {{ $cedula }}). Disponga a quien corresponda su aplicación en el rol de
pagos.
@endif

<x-mail::table>
| | |
|:--|:--|
| Sanción | {{ $sancion }} |
| Detalle | {{ $detalle }} |
@if($hasta)
| Período | Del {{ $desde }} al {{ $hasta }} |
@else
| Rige desde | {{ $desde }} |
@endif
| Remuneración mensual unificada | {{ $base }} |
</x-mail::table>

El valor del descuento lo **calcula Financiero** en su sistema de nómina
@if($hasta)
(la remuneración de los días de suspensión).
@else
(el porcentaje de la multa sobre la remuneración).
@endif

Unidad de Administración del Talento Humano<br>
GAD Provincial de Esmeraldas
</x-mail::message>
