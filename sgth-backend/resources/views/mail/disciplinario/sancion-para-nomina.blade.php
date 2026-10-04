<x-mail::message>
@if($anulada)
# Acción de personal anulada

Talento Humano **anuló** la acción de personal **N.º {{ $numero }}**, que se le
envió para aplicar un descuento en el rol de pagos de **{{ $servidor }}**
(C.I. {{ $cedula }}).

@if($motivo)
**Motivo de la anulación:** {{ $motivo }}
@endif

Si el descuento ya se aplicó, disponga a quien corresponda su reverso. Se
adjunta el documento con la marca de anulación.
@else
# Sanción disciplinaria para nómina

Talento Humano registró la acción de personal **N.º {{ $numero }}**, que impone
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
| **Descuento referencial** | **{{ $monto }}** |
</x-mail::table>

El descuento es una **referencia** calculada por el sistema de Talento Humano
@if($hasta)
(remuneración ÷ 30 × días de suspensión).
@else
(porcentaje de la multa sobre la remuneración).
@endif
El valor definitivo lo establece Financiero en su sistema de nómina.

Unidad de Administración del Talento Humano<br>
GAD Provincial de Esmeraldas
</x-mail::message>
